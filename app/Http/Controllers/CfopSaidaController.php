<?php

namespace App\Http\Controllers;

use App\Models\CfopSaida;
use Illuminate\Http\Request;

class CfopSaidaController extends Controller
{
    public function listar(Request $request)
    {
        $termo = $request->get('termo');

        $cfops = CfopSaida::ativos()
            ->when($termo, fn ($q) => $q->where('codigo', 'like', "{$termo}%")
                ->orWhere('descricao', 'like', "%{$termo}%"))
            ->orderByRaw('ordem IS NULL, ordem ASC, codigo ASC')
            ->get();

        return response()->json($cfops);
    }

    public function criar(Request $request)
    {
        $dados = $request->validate([
            'codigo'                    => ['required', 'string', 'size:4', 'unique:cfop_saida,codigo'],
            'descricao'                 => ['required', 'string', 'max:255'],
            'movimenta_estoque'         => ['sometimes', 'boolean'],
            'natureza_operacao_padrao'  => ['nullable', 'string', 'max:255'],
            'finalidade_padrao'         => ['nullable', 'in:1,2,3,4'],
        ]);

        $proximaOrdem = (CfopSaida::max('ordem') ?? 0) + 1;

        $cfop = CfopSaida::create([
            'codigo'                   => $dados['codigo'],
            'descricao'                => $dados['descricao'],
            'movimenta_estoque'        => $dados['movimenta_estoque'] ?? true,
            'natureza_operacao_padrao' => $dados['natureza_operacao_padrao'] ?? null,
            'finalidade_padrao'        => $dados['finalidade_padrao'] ?? 1,
            'ordem'                    => $proximaOrdem,
        ]);

        return response()->json($cfop);
    }

    public function editar(Request $request)
    {
        $dados = $request->validate([
            'id'                        => ['required', 'exists:cfop_saida,id'],
            'codigo'                    => ['required', 'string', 'size:4', 'unique:cfop_saida,codigo,' . $request->id],
            'descricao'                 => ['required', 'string', 'max:255'],
            'movimenta_estoque'         => ['sometimes', 'boolean'],
            'natureza_operacao_padrao'  => ['nullable', 'string', 'max:255'],
            'finalidade_padrao'         => ['nullable', 'in:1,2,3,4'],
        ]);

        $cfop = CfopSaida::findOrFail($dados['id']);
        $cfop->update([
            'codigo'                   => $dados['codigo'],
            'descricao'                => $dados['descricao'],
            'movimenta_estoque'        => $dados['movimenta_estoque'] ?? false,
            'natureza_operacao_padrao' => $dados['natureza_operacao_padrao'] ?? null,
            'finalidade_padrao'        => $dados['finalidade_padrao'] ?? 1,
        ]);

        return response()->json($cfop);
    }
}