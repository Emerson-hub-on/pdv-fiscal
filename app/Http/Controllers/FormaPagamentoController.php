<?php

namespace App\Http\Controllers;

use App\Models\FormaPagamento;
use Illuminate\Http\Request;

class FormaPagamentoController extends Controller
{
    public function listar(Request $request)
    {
        $termo = $request->get('termo');

        $formas = FormaPagamento::ativos()
            ->when($termo, fn ($q) => $q->where('descricao', 'like', "%{$termo}%"))
            ->orderByRaw('ordem IS NULL, ordem ASC, descricao ASC')
            ->get();

        return response()->json($formas);
    }

    public function editar(Request $request)
    {
        $dados = $request->validate([
            'id'        => ['required', 'exists:formas_pagamento,id'],
            'descricao' => ['required', 'string', 'max:255', 'unique:formas_pagamento,descricao,' . $request->id],
        ]);

        $forma = FormaPagamento::findOrFail($dados['id']);
        $forma->update(['descricao' => $dados['descricao']]);

        return response()->json($forma);
    }

    public function criar(Request $request)
    {
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:255', 'unique:formas_pagamento,descricao'],
        ]);

        $proximaOrdem = (FormaPagamento::max('ordem') ?? 0) + 1;

        $forma = FormaPagamento::create([
            'descricao' => $dados['descricao'],
            'ordem' => $proximaOrdem,
        ]);

        return response()->json($forma);
    }
}