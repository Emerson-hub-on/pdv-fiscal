<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\FiscalEmissorService;
use Exception;

class FiscalController extends Controller
{
    public function comprovante(Venda $venda)
    {
        $venda->load('itens.produto');
        return view('vendas.comprovante', compact('venda'));
    }

    public function emitir(Venda $venda)
    {
        if ($venda->status === 'emitida') {
            return back()->with('sucesso', 'Esta venda já foi emitida.');
        }

        try {
            $service = new FiscalEmissorService();
            $resultado = $service->emitir($venda);

            return back()->with('sucesso', 'Cupom fiscal emitido com sucesso! Chave: ' . $resultado['chave']);
        } catch (Exception $e) {
            return back()->with('erro_fiscal', $e->getMessage());
        }
    }
}