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
            'codigo'                   => ['required', 'regex:/^\d{4}$/', 'unique:cfop_saida,codigo'],
            'descricao'                => ['required', 'string', 'max:255'],
            'tipo_operacao'            => ['required', 'in:entrada,saida'],
            'movimenta_estoque'        => ['sometimes', 'boolean'],
            'natureza_operacao_padrao' => ['nullable', 'string', 'max:255'],
            'finalidade_padrao'        => ['nullable', 'in:1,2,3,4,5,6'],
        ]);

        $this->validarCoerenciaTipo($dados['codigo'], $dados['tipo_operacao']);

        $cfop = CfopSaida::create([
            'codigo'                   => $dados['codigo'],
            'descricao'                => $dados['descricao'],
            'tipo_operacao'            => $dados['tipo_operacao'],
            'movimenta_estoque'        => $dados['movimenta_estoque'] ?? true,
            'natureza_operacao_padrao' => $dados['natureza_operacao_padrao'] ?? null,
            'finalidade_padrao'        => $dados['finalidade_padrao'] ?? 1,
            'ordem'                    => (CfopSaida::max('ordem') ?? 0) + 1,
        ]);

        return response()->json($cfop);
    }

    public function editar(Request $request)
    {
        $dados = $request->validate([
            'id'                       => ['required', 'exists:cfop_saida,id'],
            'codigo'                   => ['required', 'regex:/^\d{4}$/', 'unique:cfop_saida,codigo,' . $request->id],
            'descricao'                => ['required', 'string', 'max:255'],
            'tipo_operacao'            => ['required', 'in:entrada,saida'],
            'movimenta_estoque'        => ['sometimes', 'boolean'],
            'natureza_operacao_padrao' => ['nullable', 'string', 'max:255'],
            'finalidade_padrao'        => ['nullable', 'in:1,2,3,4,5,6'],
        ]);

        $this->validarCoerenciaTipo($dados['codigo'], $dados['tipo_operacao']);

        $cfop = CfopSaida::findOrFail($dados['id']);
        $cfop->update([
            'codigo'                   => $dados['codigo'],
            'descricao'                => $dados['descricao'],
            'tipo_operacao'            => $dados['tipo_operacao'],
            'movimenta_estoque'        => $dados['movimenta_estoque'] ?? false,
            'natureza_operacao_padrao' => $dados['natureza_operacao_padrao'] ?? null,
            'finalidade_padrao'        => $dados['finalidade_padrao'] ?? 1,
        ]);

        return response()->json($cfop);
    }

    /**
     * O primeiro dígito do CFOP define o sentido da operação. Um CFOP de entrada
     * (1/2/3) em nota de saída, ou o contrário, é rejeitado pela SEFAZ.
     */
    private function validarCoerenciaTipo(string $codigo, string $tipo): void
    {
        $primeiro = $codigo[0];

        $esperado = match (true) {
            in_array($primeiro, ['1', '2', '3'], true) => 'entrada',
            in_array($primeiro, ['5', '6', '7'], true) => 'saida',
            default => null,
        };

        if ($esperado === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'codigo' => 'CFOP inválido: deve começar com 1, 2, 3, 5, 6 ou 7.',
            ]);
        }

        if ($esperado !== $tipo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tipo_operacao' => "CFOP {$codigo} começa com {$primeiro}, então deve ser de " . ($esperado === 'entrada' ? 'entrada' : 'saída') . '.',
            ]);
        }
    }
}