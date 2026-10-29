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

class CaixaController extends Controller
{
    public function abrirForm()
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        if (!$caixa && !CentralStatus::fora()) {
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

        return view('caixa.abrir', compact('pdvs', 'ocupados'));
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
            return back()->withErrors(['pdv_id' => 'Este PDV já tem um caixa aberto.'])->withInput();
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

        (new SyncService())->enviarCaixas();

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

        (new SyncService())->enviarCaixas();

        $mensagem = $caixa->fresh()->sync_pendente
            ? 'Caixa fechado. O fechamento será enviado ao servidor quando a conexão voltar.'
            : 'Caixa fechado com sucesso.';

        // Tela "Caixa fechado", que pergunta se imprime o relatório de fechamento
        return redirect()->route('caixa.fechado', $caixa->uuid)->with('sucesso', $mensagem);
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

    // Só o próprio operador vê o fechamento do seu caixa
    private function caixaFechadoDoOperador(string $uuid): ?CaixaLocal
    {
        return CaixaLocal::with('pdv')
            ->where('uuid', $uuid)
            ->where('operador_id', Auth::id())
            ->where('status', '!=', 'aberto')
            ->first();
    }


}