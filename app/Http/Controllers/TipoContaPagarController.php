<?php

namespace App\Http\Controllers;

use App\Models\TipoContaPagar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoContaPagarController extends Controller
{
    /** Cadastro rápido de tipo de conta, chamado por fetch a partir do formulário da conta. */
    public function rapido(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:60', 'unique:tipos_conta_pagar,descricao'],
        ], [
            'descricao.required' => 'Informe a descrição do tipo.',
            'descricao.unique'   => 'Já existe um tipo com esse nome.',
        ]);

        $tipo = TipoContaPagar::create($dados);

        return response()->json(['id' => $tipo->id, 'descricao' => $tipo->descricao]);
    }
}
