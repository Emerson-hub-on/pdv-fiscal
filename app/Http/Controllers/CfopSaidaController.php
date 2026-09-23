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
            ->orderBy('codigo')
            ->get();

        return response()->json($cfops);
    }

    public function criar(Request $request)
    {
        $dados = $request->validate([
            'codigo'            => ['required', 'string', 'size:4', 'unique:cfop_saida,codigo'],
            'descricao'         => ['required', 'string', 'max:255'],
            'movimenta_estoque' => ['sometimes', 'boolean'],
        ]);

        $cfop = CfopSaida::create([
            'codigo'            => $dados['codigo'],
            'descricao'         => $dados['descricao'],
            'movimenta_estoque' => $dados['movimenta_estoque'] ?? true,
        ]);

        return response()->json($cfop);
    }

    public function editar(Request $request)
    {
        $dados = $request->validate([
            'id'                => ['required', 'exists:cfop_saida,id'],
            'codigo'            => ['required', 'string', 'size:4', 'unique:cfop_saida,codigo,' . $request->id],
            'descricao'         => ['required', 'string', 'max:255'],
            'movimenta_estoque' => ['sometimes', 'boolean'],
        ]);

        $cfop = CfopSaida::findOrFail($dados['id']);
        $cfop->update([
            'codigo'            => $dados['codigo'],
            'descricao'         => $dados['descricao'],
            'movimenta_estoque' => $dados['movimenta_estoque'] ?? false,
        ]);

        return response()->json($cfop);
    }
}