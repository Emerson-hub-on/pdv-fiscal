<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\FiscalEmissorService;
use Illuminate\Http\Request;

class CancelamentoController extends Controller
{
    public function listar()
    {
        $vendas = Venda::where('status', 'emitida')
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
        $validado = $request->validate([
            'justificativa' => 'required|string|min:15',
        ]);

        try {
            $resultado = (new FiscalEmissorService())->cancelar($venda, $validado['justificativa']);
            return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }
}