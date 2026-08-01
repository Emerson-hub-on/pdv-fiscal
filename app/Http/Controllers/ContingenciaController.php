<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\FiscalEmissorService;

class ContingenciaController extends Controller
{
    public function listar()
    {
        $vendas = Venda::where('status', 'contingencia')
            ->with('itens.produto')
            ->orderBy('created_at')
            ->get()
            ->map(function ($venda) {
                return [
                    'id' => $venda->id, // ainda precisamos do id interno pra fazer o reenvio
                    'numero_nfce' => $venda->numero_nfce,
                    'serie_nfce' => $venda->serie_nfce,
                    'chave_nfe' => $venda->chave_nfe,
                    'total' => $venda->total,
                    'criada_em' => $venda->created_at->format('d/m/Y H:i'),
                    'motivo' => $venda->motivo_rejeicao,
                    'itens' => $venda->itens->map(fn($i) => $i->produto->nome . ' x' . $i->quantidade)->values(),
                ];
            });

        return response()->json($vendas);
    }

    public function reenviar(Venda $venda)
    {
        if ($venda->status !== 'contingencia') {
            return response()->json(['sucesso' => false, 'erro' => 'Venda não está mais em contingência.'], 422);
        }

        try {
            (new FiscalEmissorService())->emitir($venda);
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }
}