<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    /**
     * Cadastro rápido (JSON), usado na tela de entrada de nota.
     * O CRUD completo de fornecedores (Cadastros > Fornecedores) fica para uma etapa própria.
     */
    public function rapido(Request $request)
    {
        $request->merge([
            'cnpj_cpf' => preg_replace('/\D/', '', (string) $request->input('cnpj_cpf')),
        ]);

        $dados = $request->validate([
            'cnpj_cpf'      => ['required', 'regex:/^(\d{11}|\d{14})$/', 'unique:fornecedores,cnpj_cpf'],
            'razao_social'  => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'ie'            => ['nullable', 'string', 'max:20'],
        ], [
            'cnpj_cpf.regex'  => 'Informe um CNPJ (14 dígitos) ou CPF (11 dígitos).',
            'cnpj_cpf.unique' => 'Já existe um fornecedor com este CNPJ/CPF.',
        ]);

        $fornecedor = Fornecedor::create($dados);

        return response()->json([
            'id'   => $fornecedor->id,
            'nome' => $fornecedor->nome_exibicao . ' — ' . $fornecedor->documento_formatado,
        ]);
    }
}
