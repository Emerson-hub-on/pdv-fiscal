<?php

namespace App\Http\Controllers;

use App\Models\CaixaLocal;
use App\Models\PdvCache;
use App\Services\SyncService;
use App\Support\CentralStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\Maquina;
use App\Services\UsuarioCache;
use App\Services\RelatorioFechamentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Support\OcorrenciaCaixa;

class CaixaController extends Controller
{
    public function abrirForm()
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        // Caixa aberto no servidor por outro caminho: traz junto os PDVs, senão o PDV dele fica sem cadastro aqui
        if (!$caixa && !CentralStatus::fora()) {
            (new SyncService())->puxarPdvs();
            (new SyncService())->puxarCaixasAbertos();
            $caixa = CaixaLocal::aberto(Auth::id());
        }

        if ($caixa) {
            return redirect()->route('vendas.pdv');
        }

        $pdvs = PdvCache::where('ativo', true)
            ->where(fn ($q) => $q->whereNull('maquina')->orWhere('maquina', Maquina::nome()))
            ->orderBy('id')
            ->get();

        if ($pdvs->isEmpty() && !CentralStatus::fora()) {
            (new SyncService())->puxarPdvs();
            $pdvs = PdvCache::where('ativo', true)
                ->where(fn ($q) => $q->whereNull('maquina')->orWhere('maquina', Maquina::nome()))
                ->orderBy('id')
                ->get();
        }

        if ($pdvs->isEmpty()) {
            abort(503, 'Nenhum PDV disponível para este computador (' . \App\Support\Maquina::nome() . '). Confira o nome do computador no cadastro do PDV e sincronize novamente.');
        }

        $ocupados = CaixaLocal::where('status', 'aberto')->pluck('pdv_id')->all();

        // Caixas deixados abertos por outros operadores nos PDVs deste computador: um supervisor pode fechá-los
        $caixasDeOutros = CaixaLocal::with('pdv')
            ->where('status', 'aberto')
            ->whereIn('pdv_id', $pdvs->pluck('id'))
            ->orderBy('data_abertura')
            ->get()
            ->each(fn ($c) => $c->setAttribute('operador_nome', UsuarioCache::porId($c->operador_id)?->name ?? 'Operador #' . $c->operador_id));

        return view('caixa.abrir', compact('pdvs', 'ocupados', 'caixasDeOutros'));
    }

    public function abrir(Request $request)
    {
        $validado = $request->validate([
            'valor_abertura' => 'required|numeric|min:0',
            'pdv_id' => ['required', Rule::exists('sqlite_local.pdvs_cache', 'id')->where('ativo', 1)],
        ]);

        $pdv = PdvCache::where('ativo', true)->find($validado['pdv_id']);
        if ($pdv?->maquina && $pdv->maquina !== Maquina::nome()) {
            return back()->withErrors(['pdv_id' => 'Este PDV é restrito a outro computador.'])->withInput();
        }

        if (CaixaLocal::aberto(Auth::id())) {
            return redirect()->route('vendas.pdv');
        }

        if (CaixaLocal::where('pdv_id', $validado['pdv_id'])->where('status', 'aberto')->exists()) {
            return back()->withErrors(['pdv_id' => 'Este PDV já tem um caixa aberto por outro operador. Um supervisor pode fechá-lo em "Caixas abertos neste computador".'])->withInput();
        }

        CaixaLocal::create([
            'uuid' => (string) Str::uuid(),
            'operador_id' => Auth::id(),
            'pdv_id' => $validado['pdv_id'],
            'data_abertura' => now(),
            'valor_abertura' => $validado['valor_abertura'],
            'status' => 'aberto',
            'sync_pendente' => true,
        ]);

        // Sem envio aqui: o agendador (a cada minuto) e o Atualizar Caixa levam a abertura ao servidor,
        // para a tela nunca esperar pela rede

        // O PDV imprime o relatório de abertura assim que carrega
        return redirect()->route('vendas.pdv')
            ->with('sucesso', 'Caixa aberto com sucesso.')
            ->with('imprimir_abertura', true);
    }

    /**
     * Relatório de abertura do caixa aberto do operador, em bobina 80 mm.
     * ?auto=1 abre a impressão sozinho; ?reimpressao=1 marca a via como reimpressão.
     */
    public function relatorioAbertura(Request $request)
    {
        $caixa = CaixaLocal::aberto(Auth::id())?->load('pdv');

        if (!$caixa) {
            abort(404, 'Nenhum caixa aberto para este operador.');
        }

        $empresa = DB::connection('sqlite_local')->table('empresa_cache')->first();
        $operador = UsuarioCache::porId($caixa->operador_id) ?? Auth::user();

        return view('caixa.relatorio-abertura', [
            'caixa' => $caixa,
            'empresa' => $empresa,
            'operador' => $operador,
            'maquina' => Maquina::nome(),
            'reimpressao' => $request->boolean('reimpressao'),
            'auto' => $request->boolean('auto'),
        ]);
    }

    /**
     * Tela de fechamento de caixa
     */
    public function fecharForm()
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        $valorEsperado = $caixa->valor_abertura + $caixa->totalVendido();

        return view('caixa.fechar', compact('caixa', 'valorEsperado'));
    }

    public function fechar(Request $request)
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        $validado = $request->validate([
            'valor_fechamento_informado' => 'required|numeric|min:0',
            'observacao' => 'nullable|string',
        ]);

        $valorEsperado = $caixa->valor_abertura + $caixa->totalVendido();

        $caixa->update([
            'data_fechamento' => now(),
            'valor_fechamento_informado' => $validado['valor_fechamento_informado'],
            'valor_fechamento_esperado' => $valorEsperado,
            'observacao' => $validado['observacao'] ?? null,
            'status' => 'fechado',
            'sync_pendente' => true,
        ]);

        // O fechamento sobe pelo agendador ou pelo Atualizar Caixa, como a abertura

        // Tela "Caixa fechado", que pergunta se imprime o relatório de fechamento
        return redirect()->route('caixa.fechado', $caixa->uuid)->with('sucesso', 'Caixa fechado com sucesso.');
    }

    /**
     * Fechamento do caixa de outro operador (ele saiu sem fechar e o PDV ficou preso).
     * Só com o código e a senha de um supervisor ou administrador; fica registrado quem fechou.
     */
    public function fecharOutroForm(string $uuid)
    {
        $caixa = $this->caixaDeOutroOperador($uuid);

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form')
                ->withErrors(['pdv_id' => 'Este caixa não está mais aberto ou não pertence a um PDV deste computador.']);
        }

        $totalVendido = $caixa->totalVendido();

        return view('caixa.fechar-outro', [
            'caixa' => $caixa,
            'operador' => UsuarioCache::porId($caixa->operador_id),
            'totalVendido' => $totalVendido,
            'valorEsperado' => $caixa->valor_abertura + $totalVendido,
        ]);
    }

    public function fecharOutro(Request $request, string $uuid)
    {
        $caixa = $this->caixaDeOutroOperador($uuid);

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form')
                ->withErrors(['pdv_id' => 'Este caixa não está mais aberto ou não pertence a um PDV deste computador.']);
        }

        $validado = $request->validate([
            'supervisor_codigo' => 'required|integer|min:1',
            'supervisor_senha' => 'required|string',
            'valor_fechamento_informado' => 'required|numeric|min:0',
            'observacao' => 'required|string|max:500',
        ], [
            'observacao.required' => 'Informe o motivo do fechamento.',
        ], [
            'supervisor_codigo' => 'código do supervisor',
            'supervisor_senha' => 'senha do supervisor',
            'valor_fechamento_informado' => 'valor contado',
            'observacao' => 'motivo',
        ]);

        // O caixa só consulta o SQLite local (funciona com o servidor fora do ar)
        $supervisor = UsuarioCache::porCodigo((int) $validado['supervisor_codigo']);

        if (!$supervisor || !$supervisor->podeAutorizar() || !Hash::check($validado['supervisor_senha'], $supervisor->password)) {
            Log::warning('Fechamento de caixa de outro operador recusado', [
                'caixa_uuid' => $caixa->uuid,
                'codigo_informado' => $validado['supervisor_codigo'],
                'logado' => Auth::id(),
            ]);

            return back()
                ->withErrors(['supervisor_codigo' => 'Código ou senha inválidos, ou o usuário não é supervisor nem administrador.'])
                ->withInput($request->except('supervisor_senha'));
        }

        if ((int) $supervisor->id === (int) $caixa->operador_id) {
            return back()
                ->withErrors(['supervisor_codigo' => 'Este caixa é deste usuário: ele deve entrar com o próprio código e fechar pelo caixa.'])
                ->withInput($request->except('supervisor_senha'));
        }

        $perfil = $supervisor->isAdmin() ? 'administrador' : 'supervisor';

        $caixa->update([
            'data_fechamento' => now(),
            'valor_fechamento_informado' => $validado['valor_fechamento_informado'],
            'valor_fechamento_esperado' => $caixa->valor_abertura + $caixa->totalVendido(),
            'observacao' => $validado['observacao'],
            'status' => 'fechado',
            'fechado_por_id' => $supervisor->id,
            'fechado_por_nome' => $supervisor->name,
            'fechado_por_perfil' => $perfil,
            'sync_pendente' => true,
        ]);

        // Também sai em "Outras liberações" do relatório de fechamento
        OcorrenciaCaixa::registrar([
            'tipo' => OcorrenciaCaixa::AUTORIZACAO,
            'acao' => 'fechar_caixa_outro',
            'caixa_uuid' => $caixa->uuid,
            'supervisor_id' => $supervisor->id,
            'descricao' => 'Caixa fechado pelo ' . $perfil . ': ' . $validado['observacao'],
        ]);

        Log::info('Caixa de outro operador fechado', [
            'caixa_uuid' => $caixa->uuid,
            'operador_id' => $caixa->operador_id,
            'fechado_por_id' => $supervisor->id,
            'perfil' => $perfil,
            'logado' => Auth::id(),
        ]);

        // Quem está logado (não é o dono do caixa) pode ver e imprimir este fechamento nesta sessão
        session()->push('caixas_fechados_por_supervisor', $caixa->uuid);

        return redirect()->route('caixa.fechado', $caixa->uuid)
            ->with('sucesso', 'Caixa fechado pelo ' . $perfil . ' ' . $supervisor->name . '.');
    }

    // Caixa aberto de outro operador, num PDV que este computador pode usar
    private function caixaDeOutroOperador(string $uuid): ?CaixaLocal
    {
        $caixa = CaixaLocal::with('pdv')
            ->where('uuid', $uuid)
            ->where('status', 'aberto')
            ->where('operador_id', '!=', Auth::id())
            ->first();

        $maquina = $caixa?->pdv?->maquina;

        return $caixa && $caixa->pdv && (!$maquina || $maquina === Maquina::nome()) ? $caixa : null;
    }

    /** Depois do fechamento: resumo e a pergunta "Deseja imprimir o relatório de fechamento?" */
    public function fechado(string $uuid, RelatorioFechamentoService $servico)
    {
        $caixa = $this->caixaFechadoDoOperador($uuid);

        if (!$caixa) {
            return redirect()->route('auth.escolha');
        }

        return view('caixa.fechado', [
            'caixa' => $caixa,
            'r' => $servico->dados($caixa),
            // Fechou o caixa de outro operador: volta para a abertura, para usar o PDV liberado
            'urlConcluir' => (int) $caixa->operador_id === (int) Auth::id()
                ? route('auth.escolha')
                : route('caixa.abrir-form'),
        ]);
    }

    /** Relatório de fechamento em bobina 80 mm. ?auto=1 abre a impressão; ?reimpressao=1 marca a via. */
    public function relatorioFechamento(Request $request, string $uuid, RelatorioFechamentoService $servico)
    {
        $caixa = $this->caixaFechadoDoOperador($uuid);

        if (!$caixa) {
            abort(404, 'Caixa fechado não encontrado para este operador.');
        }

        return view('caixa.relatorio-fechamento', [
            'caixa' => $caixa,
            'r' => $servico->dados($caixa),
            'empresa' => DB::connection('sqlite_local')->table('empresa_cache')->first(),
            'operador' => UsuarioCache::porId($caixa->operador_id) ?? Auth::user(),
            'maquina' => Maquina::nome(),
            'reimpressao' => $request->boolean('reimpressao'),
            'auto' => $request->boolean('auto'),
        ]);
    }

    // Só o próprio operador vê o fechamento do seu caixa (ou quem acabou de fechá-lo com o supervisor, nesta sessão)
    private function caixaFechadoDoOperador(string $uuid): ?CaixaLocal
    {
        $fechadosPorSupervisor = (array) session('caixas_fechados_por_supervisor', []);

        return CaixaLocal::with('pdv')
            ->where('uuid', $uuid)
            ->where(fn ($q) => $q->where('operador_id', Auth::id())->orWhereIn('uuid', $fechadosPorSupervisor))
            ->where('status', '!=', 'aberto')
            ->first();
    }


}