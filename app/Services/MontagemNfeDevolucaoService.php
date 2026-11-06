<?php

namespace App\Services;

use App\Models\CfopSaida;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\EntradaNotaItem;
use App\Models\FormaPagamento;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faturamento → Montagem NF-e → NF-e Devolução.
 * Parte de uma nota de entrada (compra) finalizada: o operador escolhe os itens e as quantidades e o sistema monta,
 * em memória, a NF-e de devolução ao fornecedor (finalidade 4) com:
 *  - CFOP de devolução de compra: 5xxx com fornecedor do mesmo estado, 6xxx de outro estado;
 *    mercadoria com ST na compra → 5411/6411; sem ST → 5202/6202 (config fiscal.devolucao_cfops);
 *  - referência por item (DFeReferenciado): chave da nota de compra + nº do item (nItem) em cada item;
 *  - o fornecedor como destinatário (cadastrado como cliente se ainda não for).
 */
class MontagemNfeDevolucaoService
{
    private const CST_COM_ST = ['10', '30', '60', '70'];
    private const CSOSN_COM_ST = ['201', '202', '203', '500'];

    /** Motivo que impede devolver esta entrada (null = pode). */
    public static function erroEntradaParaDevolucao(?EntradaNota $entrada): ?string
    {
        if (!$entrada) {
            return 'Nota de entrada não encontrada.';
        }

        if ($entrada->status !== 'finalizada') {
            return "A nota de entrada nº {$entrada->numero} ainda não foi finalizada: finalize a entrada antes de devolver.";
        }

        if ((string) $entrada->modelo !== '55' || !preg_match('/^\d{44}$/', (string) $entrada->chave_acesso)) {
            return "A nota de entrada nº {$entrada->numero} não tem a chave de acesso de uma NF-e (modelo 55): a devolução precisa referenciar a nota de compra.";
        }

        if (!$entrada->fornecedor) {
            return "A nota de entrada nº {$entrada->numero} não tem fornecedor.";
        }

        return null;
    }

    public function buscarPorNumero(int $numero): Collection
    {
        return EntradaNota::with('fornecedor')->where('numero', $numero)->orderByDesc('data_entrada')->get();
    }

    public function buscarPorData(string $data): Collection
    {
        return EntradaNota::with('fornecedor')->whereDate('data_entrada', $data)->orderByDesc('id')->get();
    }

    /** NF-e de devolução (não canceladas) já geradas a partir da entrada. */
    public static function devolucoesDaEntrada(EntradaNota $entrada): Collection
    {
        return NotaFiscal::where('entrada_nota_id', $entrada->id)
            ->where('status', '!=', 'cancelada')
            ->orderBy('id')
            ->get();
    }

    /**
     * Itens da entrada com o nº do item na nota de compra e o saldo que ainda pode ser devolvido.
     * Cada linha: item, n_item, n_item_deduzido, quantidade, devolvido, saldo, com_st.
     */
    public function itensComSaldo(EntradaNota $entrada, ?int $ignorarNotaId = null): Collection
    {
        $itens = $entrada->itens()->with('produto')->orderBy('id')->get();
        $devolvido = $this->quantidadesDevolvidas($entrada, $ignorarNotaId);

        return $itens->values()->map(function (EntradaNotaItem $item, int $posicao) use ($devolvido) {
            $nItem = $item->n_item_origem ?: $posicao + 1;
            $chave = $this->chaveSaldo($nItem, $item->produto_id, $item->produto_variante_id);
            $jaDevolvido = (float) ($devolvido[$chave] ?? 0);
            // Quantidades na unidade do estoque (item comprado em caixa: quantidade × unidades por caixa)
            $quantidade = $item->quantidade_estoque;

            return [
                'item'            => $item,
                'n_item'          => $nItem,
                'n_item_deduzido' => !$item->n_item_origem,
                'quantidade'      => $quantidade,
                'devolvido'       => $jaDevolvido,
                'saldo'           => max(0, round($quantidade - $jaDevolvido, 3)),
                'com_st'          => self::itemComSt($item),
            ];
        });
    }

    /** Quanto já foi devolvido de cada item (por nItem + produto + variante), em NF-e não canceladas. */
    private function quantidadesDevolvidas(EntradaNota $entrada, ?int $ignorarNotaId): array
    {
        return NotaFiscalItem::query()
            ->join('notas_fiscais', 'notas_fiscais.id', '=', 'nota_fiscal_itens.nota_fiscal_id')
            ->where('notas_fiscais.status', '!=', 'cancelada')
            ->where('notas_fiscais.finalidade', 4)
            ->where('nota_fiscal_itens.ref_chave_acesso', $entrada->chave_acesso)
            ->when($ignorarNotaId, fn ($q) => $q->where('notas_fiscais.id', '!=', $ignorarNotaId))
            ->get(['nota_fiscal_itens.ref_nitem', 'nota_fiscal_itens.produto_id', 'nota_fiscal_itens.produto_variante_id', 'nota_fiscal_itens.quantidade'])
            ->groupBy(fn ($i) => $this->chaveSaldo((int) $i->ref_nitem, $i->produto_id, $i->produto_variante_id))
            ->map(fn ($grupo) => (float) $grupo->sum('quantidade'))
            ->all();
    }

    private function chaveSaldo(int $nItem, $produtoId, $varianteId): string
    {
        return $nItem . '|' . (int) $produtoId . '|' . (int) $varianteId;
    }

    public static function itemComSt(EntradaNotaItem $item): bool
    {
        return in_array((string) $item->cst_origem, self::CST_COM_ST, true)
            || in_array((string) $item->csosn_origem, self::CSOSN_COM_ST, true);
    }

    /**
     * Confere as quantidades de uma NF-e de devolução contra o saldo da entrada (ao salvar o rascunho).
     * $itens: itens do formulário (produto_id, produto_variante_id, quantidade, ref_chave_acesso, ref_nitem).
     */
    public function erroQuantidades(EntradaNota $entrada, array $itens, ?int $ignorarNotaId = null): ?string
    {
        $saldos = $this->itensComSaldo($entrada, $ignorarNotaId)
            ->groupBy(fn ($l) => $this->chaveSaldo($l['n_item'], $l['item']->produto_id, $l['item']->produto_variante_id))
            ->map(fn ($g) => ['saldo' => $g->sum('saldo'), 'descricao' => $g->first()['item']->descricao, 'n_item' => $g->first()['n_item']]);

        $pedidas = collect($itens)
            ->filter(fn ($i) => ($i['ref_chave_acesso'] ?? null) === $entrada->chave_acesso)
            ->groupBy(fn ($i) => $this->chaveSaldo((int) ($i['ref_nitem'] ?? 0), $i['produto_id'] ?? 0, $i['produto_variante_id'] ?? null))
            ->map(fn ($g) => (float) collect($g)->sum(fn ($i) => (float) ($i['quantidade'] ?? 0)));

        foreach ($pedidas as $chave => $quantidade) {
            $saldo = $saldos[$chave] ?? null;

            if (!$saldo) {
                return 'Há item referenciando a nota de compra que não corresponde a nenhum item dela (confira produto e nº do item).';
            }

            if ($quantidade - $saldo['saldo'] > 0.0005) {
                return sprintf(
                    'O item %d (%s) só tem %s para devolver; a nota está devolvendo %s.',
                    $saldo['n_item'], $saldo['descricao'],
                    number_format($saldo['saldo'], 3, ',', '.'), number_format($quantidade, 3, ',', '.')
                );
            }
        }

        return null;
    }

    /**
     * Monta a NF-e de devolução em memória (não salva). $quantidades: [entrada_nota_item_id => quantidade].
     * Pode cadastrar o fornecedor como cliente (destinatário) e os CFOPs de devolução que faltarem.
     * Retorna [NotaFiscal $nota, string[] $avisos].
     */
    public function montar(EntradaNota $entrada, array $quantidades): array
    {
        $entrada->loadMissing('fornecedor');
        $avisos = [];

        $linhas = $this->itensComSaldo($entrada)
            ->map(function ($l) use ($quantidades) {
                $l['devolver'] = round(min((float) ($quantidades[$l['item']->id] ?? 0), $l['saldo']), 3);
                return $l;
            })
            ->filter(fn ($l) => $l['devolver'] > 0)
            ->values();

        $cliente = $this->destinatario($entrada, $avisos);
        $cfop = $this->cfopDevolucao($entrada, $linhas, $avisos);
        $forma = FormaPagamento::ativos()->paraSaida()->where('meio_pagamento', '90')->first()
            ?? FormaPagamento::ativos()->paraSaida()->orderBy('ordem')->first();

        if ($linhas->contains(fn ($l) => $l['n_item_deduzido'])) {
            $avisos[] = 'Esta entrada foi lançada antes do sistema guardar o nº do item do XML: o nº do item de cada referência foi deduzido pela ordem dos itens. Confira com a nota de compra.';
        }

        $nota = new NotaFiscal([
            'cliente_id'                 => $cliente?->id,
            'natureza_operacao'          => $cfop?->natureza_operacao_padrao ?: 'Devolução de compra',
            'finalidade'                 => 4,
            'cfop_saida_id'              => $cfop?->id,
            'forma_pagamento_id'         => $forma?->id,
            'informacoes_complementares' => sprintf(
                'Devolução referente à NF-e nº %s, série %s, de %s, chave %s.',
                $entrada->numero, $entrada->serie, $entrada->data_emissao?->format('d/m/Y'), $entrada->chave_acesso
            ),
            'notas_referenciadas'        => [], // devolução referencia por item (DFeReferenciado), não no cabeçalho
            'origem_tipo'                => 'devolucao',
            'entrada_nota_id'            => $entrada->id,
            'frete_por_item'             => false,
            'mod_frete'                  => 9,
        ]);

        $destacar = (bool) $cfop?->destacar_bases;

        $nota->setRelation('cliente', $cliente);
        $nota->setRelation('cfopSaida', $cfop);
        $nota->setRelation('formaPagamento', $forma);
        $nota->setRelation('entradaNota', $entrada);
        $nota->setRelation('venda', null);
        $nota->setRelation('pedidoVenda', null);
        $nota->setRelation('transportador', null);
        $nota->setRelation('veiculo', null);
        $nota->setRelation('itens', $linhas->map(fn ($l) => $this->itemDevolucao($entrada, $l, $destacar)));

        return [$nota, $avisos];
    }

    private function itemDevolucao(EntradaNota $entrada, array $l, bool $destacar): NotaFiscalItem
    {
        /** @var EntradaNotaItem $origem */
        $origem = $l['item'];
        $produto = $origem->produto;
        $qtd = $l['devolver'];
        $fator = $l['quantidade'] > 0 ? $qtd / $l['quantidade'] : 0; // proporção devolvida do item de compra
        // Unidade do estoque: o valor unitário da compra é dividido pelas unidades por caixa (o total não muda)
        $valorUnitario = round((float) $origem->valor_unitario / max((int) $origem->qtd_por_caixa, 1), 4);
        $desconto = round((float) $origem->valor_desconto * $fator, 2);

        $item = new NotaFiscalItem([
            'produto_id'            => $origem->produto_id,
            'produto_variante_id'   => $origem->produto_variante_id,
            'descricao'             => ($origem->descricao && $origem->descricao !== $produto?->nome) ? $origem->descricao : null,
            'ncm_id'                => $produto?->ncm_id,
            'cest_id'               => $produto?->cest_id,
            'class_trib_ibs_cbs_id' => $produto?->class_trib_ibs_cbs_id,
            'tributacao_id'         => $produto?->tributacao_id,
            'pis_cofins_id'         => $produto?->pis_cofins_id,
            'ipi_id'                => $produto?->ipi_id,
            'quantidade'            => $qtd,
            'valor_unitario'        => $valorUnitario,
            'valor_desconto'        => $desconto,
            'valor_outras_despesas' => round((float) $origem->valor_outras_despesas * $fator, 2),
            'valor_frete'           => 0,
            'valor_total'           => round($qtd * $valorUnitario - $desconto, 2),
            'ref_chave_acesso'      => $entrada->chave_acesso,
            'ref_nitem'             => $l['n_item'],
            // CFOP com "destacar bases": devolve o ICMS/IPI destacados na compra, proporcionais à quantidade
            'impostos_manuais'      => $destacar,
            'bc_icms'               => $destacar ? round((float) $origem->bc_icms * $fator, 2) : 0,
            'valor_icms'            => $destacar ? round((float) $origem->valor_icms * $fator, 2) : 0,
            'aliquota_icms'         => $destacar ? (float) $origem->aliquota_icms : 0,
            'valor_ipi'             => $destacar ? round((float) $origem->valor_ipi * $fator, 2) : 0,
            'aliquota_ipi'          => $destacar ? (float) $origem->aliquota_ipi : 0,
        ]);
        $item->setRelation('produto', $produto);

        return $item;
    }

    /** O destinatário da NF-e é um cliente: usa o de mesmo CPF/CNPJ do fornecedor ou cadastra a partir dele. */
    private function destinatario(EntradaNota $entrada, array &$avisos): ?Cliente
    {
        $fornecedor = $entrada->fornecedor;
        $documento = preg_replace('/\D/', '', (string) $fornecedor->cpf_cnpj);

        $cliente = $documento !== '' ? Cliente::where('cpf_cnpj', $documento)->first() : null;

        if ($cliente) {
            if (!$cliente->ativo) {
                $avisos[] = "O cadastro de cliente de {$cliente->nome} (destinatário da devolução) está inativo: confira antes de emitir.";
            }
            return $cliente;
        }

        if ($documento === '') {
            $avisos[] = 'O fornecedor não tem CPF/CNPJ: selecione o destinatário da devolução.';
            return null;
        }

        $cliente = Cliente::create(collect($fornecedor->only([
            'tipo_pessoa', 'nome', 'nome_fantasia', 'indicador_ie', 'ie', 'email', 'telefone',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'cod_municipio', 'uf',
        ]))->all() + ['cpf_cnpj' => $documento, 'ativo' => true]);

        $avisos[] = "O fornecedor {$fornecedor->nome} foi cadastrado também como cliente, para ser o destinatário da NF-e de devolução.";

        return $cliente;
    }

    /** CFOP de devolução de compra: 5 (mesmo estado) ou 6 (outro estado); com ST na compra usa o de ST. */
    private function cfopDevolucao(EntradaNota $entrada, Collection $linhas, array &$avisos): ?CfopSaida
    {
        $ufFornecedor = strtoupper((string) $entrada->fornecedor->uf);
        $ufEmpresa = strtoupper((string) Empresa::first()?->uf);
        $digito = ($ufFornecedor !== '' && $ufFornecedor !== $ufEmpresa) ? '6' : '5';

        $comSt = $linhas->filter(fn ($l) => $l['com_st']);
        $semSt = $linhas->reject(fn ($l) => $l['com_st']);
        // Itens com e sem ST: vale o grupo de maior valor (a NF-e tem um CFOP só)
        $valor = fn ($grupo) => $grupo->sum(fn ($l) => $l['devolver'] / max($l['quantidade'], 0.001) * (float) $l['item']->valor_total);
        $tipo = $comSt->isNotEmpty() && $valor($comSt) >= $valor($semSt) ? 'st' : 'normal';

        $codigo = $digito . substr((string) config("fiscal.devolucao_cfops.{$tipo}"), 1);

        if ($comSt->isNotEmpty() && $semSt->isNotEmpty()) {
            $avisos[] = "Há itens com e sem substituição tributária na compra. A NF-e usa um CFOP só ({$codigo}); se precisar, faça duas devoluções separando os itens.";
        }

        if ($ufFornecedor === '') {
            $avisos[] = 'O fornecedor não tem UF no cadastro: o CFOP foi escolhido como operação dentro do estado. Confira.';
        }

        $cfop = CfopSaida::where('codigo', $codigo)->first();

        if (!$cfop) {
            $cfop = CfopSaida::create([
                'codigo'                   => $codigo,
                'descricao'                => $tipo === 'st'
                    ? 'Devolução de compra para comercialização em operação com mercadoria sujeita ao regime de substituição tributária'
                    : 'Devolução de compra para comercialização',
                'tipo_operacao'            => 'saida',
                'movimenta_estoque'        => true,
                'ativo'                    => true,
                'natureza_operacao_padrao' => 'Devolução de compra',
                'finalidade_padrao'        => 4,
                'destacar_bases'           => true,
            ]);
            $avisos[] = "O CFOP {$codigo} não estava cadastrado e foi incluído no cadastro de tipos de nota.";
        } elseif (!$cfop->ativo) {
            $avisos[] = "O CFOP {$codigo} está inativo no cadastro de tipos de nota: ative-o ou escolha outro.";
            return null;
        }

        return $cfop;
    }
}
