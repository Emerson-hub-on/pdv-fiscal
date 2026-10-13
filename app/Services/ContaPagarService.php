<?php

namespace App\Services;

use App\Models\ContaPagar;
use App\Models\EntradaNota;
use App\Models\TipoContaPagar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContaPagarService
{
    public const TIPO_COMPRA = 'Fornecedor / Compra de mercadoria';

    /**
     * Valida e ordena as parcelas enviadas pelo modal da entrada.
     * Chame ANTES de finalizar a nota, para não mexer em estoque se houver erro.
     *
     * @return array<int, array{vencimento: string, valor: float}>
     */
    public function normalizarParcelas(array $parcelas): array
    {
        $validator = Validator::make(['parcelas' => $parcelas], [
            'parcelas'              => ['required', 'array', 'min:1', 'max:120'],
            'parcelas.*.vencimento' => ['required', 'date_format:Y-m-d'],
            'parcelas.*.valor'      => ['required', 'numeric', 'min:0.01'],
        ], [
            'parcelas.required'              => 'Informe ao menos uma parcela para lançar no Contas a Pagar.',
            'parcelas.*.vencimento.required' => 'Informe o vencimento de todas as parcelas do Contas a Pagar.',
            'parcelas.*.vencimento.date_format' => 'Há um vencimento inválido nas parcelas do Contas a Pagar.',
            'parcelas.*.valor.required'      => 'Informe o valor de todas as parcelas do Contas a Pagar.',
            'parcelas.*.valor.min'           => 'O valor de cada parcela do Contas a Pagar deve ser maior que zero.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['cp_parcelas' => $validator->errors()->all()]);
        }

        $lista = collect($parcelas)->map(fn ($p) => [
            'vencimento' => $p['vencimento'],
            'valor'      => round((float) $p['valor'], 2),
        ])->sortBy('vencimento')->values()->all();

        return $lista;
    }

    /**
     * Cria a conta a pagar vinculada à entrada. O valor total da conta é a soma das parcelas
     * (é o que de fato será pago, e pode diferir do total da nota). Idempotente por entrada.
     */
    public function criarDaEntrada(EntradaNota $entrada, array $parcelas): ContaPagar
    {
        $parcelas = $this->normalizarParcelas($parcelas);

        return DB::transaction(function () use ($entrada, $parcelas) {
            $existente = ContaPagar::where('entrada_nota_id', $entrada->id)->first();
            if ($existente) {
                return $existente;
            }

            $entrada->loadMissing('fornecedor');

            $descricao = 'NF-e ' . $entrada->numero . ($entrada->serie ? '/' . $entrada->serie : '');
            if ($entrada->fornecedor) {
                $descricao .= ' — ' . $entrada->fornecedor->nome_exibicao;
            }

            $totalCent = array_sum(array_map(fn ($p) => (int) round($p['valor'] * 100), $parcelas));

            $conta = ContaPagar::create([
                'tipo_conta_pagar_id' => TipoContaPagar::firstOrCreate(['descricao' => self::TIPO_COMPRA])->id,
                'fornecedor_id'       => $entrada->fornecedor_id,
                'entrada_nota_id'     => $entrada->id,
                'descricao'           => Str::limit($descricao, 150, ''),
                'numero_documento'    => $entrada->numero,
                'data_conta'          => $entrada->data_entrada,
                'valor_total'         => $totalCent / 100,
                'status'              => 'aberta',
            ]);

            foreach ($parcelas as $i => $p) {
                $conta->parcelas()->create([
                    'numero'     => $i + 1,
                    'vencimento' => $p['vencimento'],
                    'valor'      => $p['valor'],
                ]);
            }

            return $conta;
        });
    }

    /**
     * Remove a conta vinculada à entrada (ao reabrir ou excluir a nota).
     * Se alguma parcela já foi paga, não remove e avisa o operador.
     *
     * @throws \RuntimeException
     */
    public function removerDaEntrada(EntradaNota $entrada): void
    {
        $conta = ContaPagar::where('entrada_nota_id', $entrada->id)->first();

        if (! $conta) {
            return;
        }

        if ($conta->parcelas()->whereNotNull('data_pagamento')->exists()) {
            throw new \RuntimeException(
                'Esta nota tem parcelas já pagas no Contas a Pagar. Estorne os pagamentos em Financeiro › Contas a Pagar antes de continuar.'
            );
        }

        $conta->delete(); // parcelas saem junto (cascade)
    }
}