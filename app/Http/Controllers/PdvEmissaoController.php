<?php

namespace App\Http\Controllers;

use App\Models\Pdv;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PdvEmissaoController extends Controller
{
    public function mostrar(Pdv $pdv)
    {
        return view('pdvs.emissao', [
            'pdv' => $pdv,
            'naoEmitidas' => $this->vendasNaoEmitidas($pdv),
            'alteradoPor' => $pdv->emissor_alterado_por ? User::find($pdv->emissor_alterado_por)?->name : null,
        ]);
    }

    // Emergência: o servidor assume a emissão e a numeração do PDV
    public function passarParaServidor(Request $request, Pdv $pdv)
    {
        if (!$pdv->emissao_local) {
            return back()->withErrors(['motivo' => 'Este PDV já está emitindo pelo servidor.']);
        }

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:200'],
            'ultimo_numero' => ['nullable', 'integer', 'min:0'],
            'confirmacao' => ['required', Rule::in([$pdv->nome])],
        ], [
            'motivo.required' => 'Descreva o motivo da troca.',
            'motivo.min' => 'Descreva o motivo da troca (mínimo de 10 caracteres).',
            'confirmacao.required' => 'Digite o nome do PDV para confirmar.',
            'confirmacao.in' => 'O nome digitado não confere com o do PDV.',
        ]);

        // Nunca volta o contador: o número do servidor só avança
        $ultimo = max((int) $pdv->numero_atual_nfce, (int) ($dados['ultimo_numero'] ?? 0));

        $pdv->forceFill([
            'numero_atual_nfce' => $ultimo,
            'emissao_local' => false,
            'emissor_alterado_em' => now(),
            'emissor_alterado_por' => Auth::id(),
            'emissor_motivo' => $dados['motivo'],
        ])->save();

        Log::warning('Emissão do PDV passada para o servidor', [
            'pdv_id' => $pdv->id,
            'usuario_id' => Auth::id(),
            'motivo' => $dados['motivo'],
            'ultimo_numero' => $ultimo,
        ]);

        return redirect()->route('pdvs.emissao', $pdv)
            ->with('sucesso', "O servidor assumiu a emissão do PDV {$pdv->nome}. A próxima NFC-e será a de nº " . ($ultimo + 1) . '.');
    }

    // Fim da emergência: a emissão volta para o caixa
    public function devolverAoCaixa(Pdv $pdv)
    {
        if ($pdv->emissao_local) {
            return back()->withErrors(['caixa' => 'Este PDV já emite pelo caixa.']);
        }

        $pendentes = $this->vendasNaoEmitidas($pdv);

        if ($pendentes > 0) {
            return back()->withErrors([
                'caixa' => "Há {$pendentes} venda(s) deste PDV ainda sem NFC-e no servidor. Emita-as (F1) antes de devolver a emissão ao caixa.",
            ]);
        }

        $pdv->forceFill([
            'emissao_local' => true,
            'emissor_alterado_em' => now(),
            'emissor_alterado_por' => Auth::id(),
            'emissor_motivo' => null,
        ])->save();

        Log::warning('Emissão do PDV devolvida ao caixa', [
            'pdv_id' => $pdv->id,
            'usuario_id' => Auth::id(),
            'numero_atual_nfce' => $pdv->numero_atual_nfce,
        ]);

        return redirect()->route('pdvs.emissao', $pdv)
            ->with('sucesso', 'A emissão voltou para o caixa, que continua do número ' . ($pdv->numero_atual_nfce + 1) . ' assim que sincronizar.');
    }

    private function vendasNaoEmitidas(Pdv $pdv): int
    {
        return Venda::whereIn('status', ['pendente', 'contingencia'])
            ->whereHas('caixa', fn ($q) => $q->where('pdv_id', $pdv->id))
            ->count();
    }
}