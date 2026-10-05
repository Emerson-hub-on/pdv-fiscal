<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\FiscalEmissorService;
use Illuminate\Http\Request;

class CancelamentoController extends Controller
{
    public function listar()
    {
        $prazoMinutos = config('app.prazo_cancelamento_minutos', 30);
        $limite = now()->subMinutes($prazoMinutos);

        $vendas = Venda::where('status', 'emitida')
            ->where('emitida_em', '>=', $limite)
            ->with('itens.produto')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($venda) {
                return [
                    'id' => $venda->id,
                    'numero_nfce' => $venda->numero_nfce,
                    'chave_nfe' => $venda->chave_nfe,
                    'total' => $venda->total,
                    'criada_em' => $venda->created_at->format('d/m/Y H:i'),
                    'itens' => $venda->itens->map(fn($i) => $i->produto->nome . ' x' . $i->quantidade)->values(),
                ];
            });

        return response()->json($vendas);
    }

    public function cancelar(Request $request, Venda $venda)
    {
        $usuario = Auth::user();
        $liberado = $usuario->caixaLiberado('cancelar_nfce');

        // Operador liberado dispensa o supervisor; os demais precisam da autorização de sessão
        if (!$liberado && !AutorizacaoSupervisor::valida('cancelar_nfce')) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Autorização do supervisor ausente, expirada ou já utilizada. Feche esta janela e solicite novamente.',
            ], 403);
        }

        $validado = $request->validate([
            'justificativa' => 'required|string|min:15',
        ]);

        try {
            $supervisorId = $liberado ? null : AutorizacaoSupervisor::supervisorId('cancelar_nfce');

            $resultado = (new FiscalEmissorService())->cancelar($venda, $validado['justificativa']);

            // Uso único: só gasta a autorização quando o cancelamento deu certo
            AutorizacaoSupervisor::consumir('cancelar_nfce');
            \Illuminate\Support\Facades\DB::connection('sqlite_local')->table('vendas_pendentes')
                ->where('uuid', $venda->uuid)
                ->update(['status' => 'cancelada', 'updated_at' => now()]);

            Log::info('NFC-e cancelada', [
                'venda_id'      => $venda->id,
                'operador_id'   => $usuario->id,
                'supervisor_id' => $supervisorId,
                'liberado'      => $liberado,
            ]);

            return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }
}