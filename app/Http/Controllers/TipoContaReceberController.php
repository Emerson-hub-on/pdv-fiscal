<?php

namespace App\Http\Controllers;

use App\Models\TipoContaReceber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoContaReceberController extends Controller
{
    /** Cadastro rápido de tipo de conta, chamado por fetch a partir do formulário da conta. */
    public function rapido(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:60', 'unique:tipos_conta_receber,descricao'],
        ], [
            'descricao.required' => 'Informe a descrição do tipo.',
            'descricao.unique'   => 'Já existe um tipo com esse nome.',
        ]);

        $tipo = TipoContaReceber::create($dados);

        return response()->json(['id' => $tipo->id, 'descricao' => $tipo->descricao]);
    }
}
