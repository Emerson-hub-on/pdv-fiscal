<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaixaController extends Controller
{
    /**
     * Tela de abertura de caixa (se o operador ainda não tem um aberto)
     */
    public function abrirForm()
    {
        $caixaAberto = Caixa::aberto(Auth::id());

        if ($caixaAberto) {
            return redirect()->route('vendas.pdv');
        }

        return view('caixa.abrir');
    }

    public function abrir(Request $request)
    {
        $validado = $request->validate([
            'valor_abertura' => 'required|numeric|min:0',
        ]);

        Caixa::create([
            'operador_id' => Auth::id(),
            'data_abertura' => now(),
            'valor_abertura' => $validado['valor_abertura'],
            'status' => 'aberto',
        ]);

        return redirect()->route('vendas.pdv')->with('sucesso', 'Caixa aberto com sucesso.');
    }

    /**
     * Tela de fechamento de caixa
     */
    public function fecharForm()
    {
        $caixa = Caixa::aberto(Auth::id());

        if (!$caixa) {
            return redirect()->route('caixa.abrir-form');
        }

        $valorEsperado = $caixa->valor_abertura + $caixa->totalVendido();

        return view('caixa.fechar', compact('caixa', 'valorEsperado'));
    }

    public function fechar(Request $request)
    {
        $caixa = Caixa::aberto(Auth::id());

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
        ]);

        return redirect()->route('auth.escolha')->with('sucesso', 'Caixa fechado com sucesso.');
    }
}