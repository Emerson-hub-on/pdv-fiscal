<?php

namespace App\Services;

use App\Models\CfopSaida;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Venda;
use App\Support\ClienteEspelho;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Faturamento → Montagem NF-e → NF-e de Troca.
 *
 * 1) Entrada do item antigo: NF-e de ENTRADA de devolução de cliente (finalidade 4), a partir da venda original
 *    (NFC-e do caixa ou NF-e de venda). Cada item referencia a chave da venda e o nº do item (DFeReferenciado).
 *    CFOP 1202/2202 (mercadoria normal) ou 1411/2411 (vendida com ST); 1 = mesmo estado, 2 = outro estado.
 *    Destinatário: o cliente identificado na venda; sem cliente, a própria empresa (cadastro de cliente espelho).
 * 2) Saída do item novo: venda no caixa (NFC-e) ou NF-e de venda (5102; itens com ST saem com o CFOP da tributação).
 *
 * Documento de origem: ['tipo' => 'nfce'|'nfe', 'modelo' => Venda|NotaFiscal].
 */
class MontagemNfeTrocaService
{
    private const CST_COM_ST = ['10', '30', '60', '70'];
    private const CSOSN_COM_ST = ['201', '202', '203', '500'];
    private const CFOP_VENDA_COM_ST = ['5401', '5403', '5405', '6401', '6403', '6404'];

    // ---------------- Documento de origem ----------------

    public static function carregar(string $tipo, int $id): ?array
    {
        $modelo = $tipo === 'nfe'
            ? NotaFiscal::with('cliente')->find($id)
            : Venda::with('cliente')->find($id);

        return $modelo ? ['tipo' => $tipo === 'nfe' ? 'nfe' : 'nfce', 'modelo' => $modelo] : null;
    }

    public static function chave(array $doc): ?string
    {
        $chave = $doc['tipo'] === 'nfe' ? $doc['modelo']->chave_acesso : $doc['modelo']->chave_nfe;

        return preg_match('/^\d{44}$/', (string) $chave) ? $chave : null;
    }

    public static function rotulo(array $doc): string
    {
        $m = $doc['modelo'];

        return $doc['tipo'] === 'nfe'
            ? "NF-e nº {$m->numero}, série {$m->serie}"
            : "NFC-e nº {$m->numero_nfce}, série {$m->serie_nfce}";
    }

    public static function dataEmissao(array $doc): ?string
    {
        $m = $doc['modelo'];
        $data = $doc['tipo'] === 'nfe' ? $m->emitida_em : ($m->emitida_em ? \Illuminate\Support\Carbon::parse($m->emitida_em) : $m->created_at);

        return $data?->format('d/m/Y');
    }

    /** Motivo que impede a troca a partir deste documento (null = pode). */
    public static function erroDocumento(?array $doc): ?string
    {
        if (!$doc) {
            return 'Documento de venda não encontrado.';
        }

        $m = $doc['modelo'];
        $rotulo = self::rotulo($doc);

        if ($doc['tipo'] === 'nfe') {
            if ($m->status !== 'emitida') {
                return "A {$rotulo} não está autorizada (situação: {$m->status}).";
            }
            if ($m->tipo_operacao !== 'saida' || (int) $m->finalidade !== 1) {
                return "A {$rotulo} não é uma nota de venda (saída normal).";
            }
        } elseif ($m->status !== 'emitida') {
            return $m->status === 'cancelada'
                ? "A {$rotulo} está cancelada: não há o que devolver."
                : "A {$rotulo} ainda não está autorizada pela SEFAZ (situação: {$m->status}).";
        }

        if (!self::chave($doc)) {
            return "A {$rotulo} não tem chave de acesso registrada: a devolução precisa referenciá-la.";
        }

        return null;
    }

    // ---------------- Busca ----------------

    public function buscarNfePorNumero(int $numero): Collection
    {
        return NotaFiscal::with('cliente')->where('numero', $numero)->where('tipo_operacao', 'saida')
            ->where('finalidade', 1)->whereNotNull('chave_acesso')->orderByDesc('id')->get();
    }

    public function buscarNfePorData(string $data): Collection
    {
        return NotaFiscal::with('cliente')->whereDate('emitida_em', $data)->where('tipo_operacao', 'saida')
            ->where('finalidade', 1)->where('status', '!=', 'rascunho')->orderByDesc('numero')->get();
    }

    /** NF-e de entrada (troca) não canceladas já feitas a partir do documento. */
    public static function trocasDoDocumento(array $doc): Collection
    {
        $coluna = $doc['tipo'] === 'nfe' ? 'troca_nota_fiscal_id' : 'troca_venda_id';

        return NotaFiscal::where($coluna, $doc['modelo']->id)->where('status', '!=', 'cancelada')->orderBy('id')->get();
    }

    // ---------------- Itens e saldo ----------------

    /**
     * Itens vendidos com o nº do item no documento e o saldo que ainda pode ser devolvido.
     * Cada linha: id, n_item, produto_id, produto_variante_id, produto, descricao, quantidade, devolvido, saldo,
     * valor_unitario, desconto, com_st, bc_icms, aliquota_icms, valor_icms.
     */
    public function itensComSaldo(array $doc, ?int $ignorarNotaId = null): Collection
    {
        $linhas = $doc['tipo'] === 'nfe' ? $this->linhasNfe($doc['modelo']) : $this->linhasNfce($doc['modelo']);
        $devolvido = $this->quantidadesDevolvidas((string) self::chave($doc), $ignorarNotaId);

        return $linhas->map(function ($l) use ($devolvido) {
            $l['devolvido'] = (float) ($devolvido[$this->chaveSaldo($l['n_item'], $l['produto_id'], $l['produto_variante_id'])] ?? 0);
            $l['saldo'] = max(0, round($l['quantidade'] - $l['devolvido'], 3));

            return $l;
        });
    }

    private function linhasNfce(Venda $venda): Collection
    {
        // Mesma ordem e mesmo filtro da emissão da NFC-e (o nItem é a posição entre os itens com produto)
        return $venda->itens()->with('produto.tributacao')->orderBy('id')->get()
            ->filter(fn ($i) => $i->produto_id)
            ->values()
            ->map(function ($i, $posicao) {
                $trib = $i->produto?->tributacao;
                $cst = (string) ($i->cst_csosn ?: ($trib?->csosn ?: $trib?->cst_icms));

                return [
                    'id'                  => $i->id,
                    'n_item'              => $posicao + 1,
                    'produto_id'          => $i->produto_id,
                    'produto_variante_id' => $i->produto_variante_id,
                    'produto'             => $i->produto,
                    'descricao'           => $i->descricao ?: $i->produto?->nome,
                    'quantidade'          => (float) $i->quantidade,
                    'valor_unitario'      => (float) $i->preco_unitario,
                    // desconto efetivo = desconto do item + rateio do desconto da venda (gravado na emissão)
                    'desconto'            => (float) ($i->impostos_registrados_em ? $i->desconto_efetivo : $i->desconto),
                    'com_st'              => self::cstComSt($cst) || in_array((string) $i->cfop, self::CFOP_VENDA_COM_ST, true),
                    'bc_icms'             => (float) $i->bc_icms,
                    'aliquota_icms'       => (float) $i->aliquota_icms,
                    'valor_icms'          => (float) $i->valor_icms,
                ];
            });
    }

    private function linhasNfe(NotaFiscal $nota): Collection
    {
        return $nota->itens()->with('produto', 'tributacao')->orderBy('id')->get()
            ->values()
            ->map(function ($i, $posicao) use ($nota) {
                $trib = $i->tributacao;

                return [
                    'id'                  => $i->id,
                    'n_item'              => $posicao + 1,
                    'produto_id'          => $i->produto_id,
                    'produto_variante_id' => $i->produto_variante_id,
                    'produto'             => $i->produto,
                    'descricao'           => $i->descricao ?: $i->produto?->nome,
                    'quantidade'          => (float) $i->quantidade,
                    'valor_unitario'      => (float) $i->valor_unitario,
                    'desconto'            => (float) $i->valor_desconto,
                    'com_st'              => self::cstComSt((string) ($trib?->csosn ?: $trib?->cst_icms))
                                             || in_array((string) $i->cfopEfetivo($nota), self::CFOP_VENDA_COM_ST, true),
                    'bc_icms'             => (float) $i->bc_icms,
                    'aliquota_icms'       => (float) $i->aliquota_icms,
                    'valor_icms'          => (float) $i->valor_icms,
                ];
            });
    }

    private static function cstComSt(string $cst): bool
    {
        return in_array($cst, self::CST_COM_ST, true) || in_array($cst, self::CSOSN_COM_ST, true);
    }

    private function quantidadesDevolvidas(string $chave, ?int $ignorarNotaId): array
    {
        return NotaFiscalItem::query()
            ->join('notas_fiscais', 'notas_fiscais.id', '=', 'nota_fiscal_itens.nota_fiscal_id')
            ->where('notas_fiscais.status', '!=', 'cancelada')
            ->where('notas_fiscais.finalidade', 4)
            ->where('nota_fiscal_itens.ref_chave_acesso', $chave)
            ->when($ignorarNotaId, fn ($q) => $q->where('notas_fiscais.id', '!=', $ignorarNotaId))
            ->get(['nota_fiscal_itens.ref_nitem', 'nota_fiscal_itens.produto_id', 'nota_fiscal_itens.produto_variante_id', 'nota_fiscal_itens.quantidade'])
            ->groupBy(fn ($i) => $this->chaveSaldo((int) $i->ref_nitem, $i->produto_id, $i->produto_variante_id))
            ->map(fn ($g) => (float) $g->sum('quantidade'))
            ->all();
    }

    private function chaveSaldo(int $nItem, $produtoId, $varianteId): string
    {
        return $nItem . '|' . (int) $produtoId . '|' . (int) $varianteId;
    }

    /** Ao salvar: as quantidades da NF-e de entrada não podem passar do saldo do documento de venda. */
    public function erroQuantidades(array $doc, array $itens, ?int $ignorarNotaId = null): ?string
    {
        $chave = self::chave($doc);
        $saldos = $this->itensComSaldo($doc, $ignorarNotaId)
            ->keyBy(fn ($l) => $this->chaveSaldo($l['n_item'], $l['produto_id'], $l['produto_variante_id']));

        $pedidas = collect($itens)
            ->filter(fn ($i) => ($i['ref_chave_acesso'] ?? null) === $chave)
            ->groupBy(fn ($i) => $this->chaveSaldo((int) ($i['ref_nitem'] ?? 0), $i['produto_id'] ?? 0, $i['produto_variante_id'] ?? null))
            ->map(fn ($g) => (float) collect($g)->sum(fn ($i) => (float) ($i['quantidade'] ?? 0)));

        foreach ($pedidas as $k => $quantidade) {
            $l = $saldos[$k] ?? null;

            if (!$l) {
                return 'Há item referenciando a venda original que não corresponde a nenhum item dela (confira produto e nº do item).';
            }

            if ($quantidade - $l['saldo'] > 0.0005) {
                return sprintf('O item %d (%s) só tem %s para devolver; a nota está devolvendo %s.',
                    $l['n_item'], $l['descricao'], number_format($l['saldo'], 3, ',', '.'), number_format($quantidade, 3, ',', '.'));
            }
        }

        return null;
    }

    // ---------------- 1) NF-e de entrada (devolução do cliente) ----------------

    /** $quantidades: [id do item vendido => quantidade]. Retorna [NotaFiscal, avisos]. */
    public function montarEntrada(array $doc, array $quantidades): array
    {
        $avisos = [];

        $linhas = $this->itensComSaldo($doc)
            ->map(function ($l) use ($quantidades) {
                $l['devolver'] = round(min((float) ($quantidades[$l['id']] ?? 0), $l['saldo']), 3);
                return $l;
            })
            ->filter(fn ($l) => $l['devolver'] > 0 && $l['produto'])
            ->values();

        [$cliente, $ehEmpresa] = $this->destinatario($doc, $avisos);
        $cfop = $this->cfopEntrada($cliente, $ehEmpresa, $linhas, $avisos);
        $forma = FormaPagamento::ativos()->paraSaida()->where('meio_pagamento', '90')->first()
            ?? FormaPagamento::ativos()->paraSaida()->orderBy('ordem')->first();
        $chave = self::chave($doc);

        $nota = new NotaFiscal([
            'cliente_id'                 => $cliente?->id,
            'natureza_operacao'          => $cfop?->natureza_operacao_padrao ?: 'Devolução de venda',
            'finalidade'                 => 4,
            'cfop_saida_id'              => $cfop?->id,
            'forma_pagamento_id'         => $forma?->id,
            'informacoes_complementares' => sprintf(
                'Troca: devolução de mercadoria referente à %s, emitida em %s, chave %s.%s',
                self::rotulo($doc), self::dataEmissao($doc), $chave,
                $ehEmpresa ? ' Consumidor não identificado na venda.' : ''
            ),
            'notas_referenciadas'        => [], // devolução referencia por item (DFeReferenciado)
            'origem_tipo'                => 'troca',
            'troca_venda_id'             => $doc['tipo'] === 'nfce' ? $doc['modelo']->id : null,
            'troca_nota_fiscal_id'       => $doc['tipo'] === 'nfe' ? $doc['modelo']->id : null,
            'frete_por_item'             => false,
            'mod_frete'                  => 9,
        ]);

        $destacar = (bool) $cfop?->destacar_bases;

        $this->relacoesVazias($nota, $cliente, $cfop, $forma);
        $nota->setRelation($doc['tipo'] === 'nfe' ? 'trocaNotaFiscal' : 'trocaVenda', $doc['modelo']);
        $nota->setRelation('itens', $linhas->map(function ($l) use ($chave, $destacar) {
            $fator = $l['quantidade'] > 0 ? $l['devolver'] / $l['quantidade'] : 0;
            $desconto = round($l['desconto'] * $fator, 2);
            $produto = $l['produto'];

            $item = new NotaFiscalItem([
                'produto_id'            => $l['produto_id'],
                'produto_variante_id'   => $l['produto_variante_id'],
                'descricao'             => ($l['descricao'] && $l['descricao'] !== $produto->nome) ? $l['descricao'] : null,
                'ncm_id'                => $produto->ncm_id,
                'cest_id'               => $produto->cest_id,
                'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
                'tributacao_id'         => $produto->tributacao_id,
                'pis_cofins_id'         => $produto->pis_cofins_id,
                'ipi_id'                => $produto->ipi_id,
                'quantidade'            => $l['devolver'],
                'valor_unitario'        => $l['valor_unitario'],
                'valor_desconto'        => $desconto,
                'valor_outras_despesas' => 0,
                'valor_frete'           => 0,
                'valor_total'           => round($l['devolver'] * $l['valor_unitario'] - $desconto, 2),
                'ref_chave_acesso'      => $chave,
                'ref_nitem'             => $l['n_item'],
                // CFOP com "destacar bases": o ICMS destacado na venda volta proporcional à quantidade
                'impostos_manuais'      => $destacar,
                'bc_icms'               => $destacar ? round($l['bc_icms'] * $fator, 2) : 0,
                'valor_icms'            => $destacar ? round($l['valor_icms'] * $fator, 2) : 0,
                'aliquota_icms'         => $destacar ? $l['aliquota_icms'] : 0,
                'valor_ipi'             => 0,
                'aliquota_ipi'          => 0,
            ]);
            $item->setRelation('produto', $produto);

            return $item;
        }));

        return [$nota, $avisos];
    }

    /** Cliente identificado na venda; sem cliente, a própria empresa. Retorna [Cliente|null, ehEmpresa]. */
    private function destinatario(array $doc, array &$avisos): array
    {
        $m = $doc['modelo'];
        $cliente = $m->cliente;

        if (!$cliente && $doc['tipo'] === 'nfce' && ($documento = preg_replace('/\D/', '', (string) $m->cpf_na_nota)) !== '') {
            $cliente = Cliente::where('cpf_cnpj', $documento)->first();

            if (!$cliente) {
                $avisos[] = "A NFC-e tem o CPF/CNPJ {$documento} na nota, mas ele não está no cadastro de clientes: a NF-e foi montada contra a própria empresa. Se preferir em nome do cliente, cadastre-o e troque o destinatário.";
            }
        }

        if ($cliente) {
            return [$cliente, false];
        }

        $empresa = Empresa::first();
        $cliente = $empresa ? ClienteEspelho::daEmpresa($empresa) : null;

        if ($cliente) {
            $avisos[] = 'A venda não tem cliente identificado: a NF-e de entrada foi montada contra a própria empresa.';
        } else {
            $avisos[] = 'A venda não tem cliente identificado e não foi possível usar a própria empresa como destinatária: selecione o destinatário.';
        }

        return [$cliente, (bool) $cliente];
    }

    /** CFOP de entrada por devolução de venda: 1 (mesmo estado) ou 2 (outro estado); com ST usa o de ST. */
    private function cfopEntrada(?Cliente $cliente, bool $ehEmpresa, Collection $linhas, array &$avisos): ?CfopSaida
    {
        $ufEmpresa = strtoupper((string) Empresa::first()?->uf);
        $ufCliente = strtoupper((string) $cliente?->uf);
        $digito = (!$ehEmpresa && $ufCliente !== '' && $ufCliente !== $ufEmpresa) ? '2' : '1';

        $comSt = $linhas->filter(fn ($l) => $l['com_st']);
        $semSt = $linhas->reject(fn ($l) => $l['com_st']);
        $valor = fn ($g) => $g->sum(fn ($l) => $l['devolver'] * $l['valor_unitario']);
        $tipo = $comSt->isNotEmpty() && $valor($comSt) >= $valor($semSt) ? 'st' : 'normal';

        $codigo = $digito . substr((string) config("fiscal.troca_cfops.{$tipo}"), 1);

        if ($comSt->isNotEmpty() && $semSt->isNotEmpty()) {
            $avisos[] = "Há itens vendidos com e sem substituição tributária. A NF-e usa um CFOP só ({$codigo}); se precisar, faça duas devoluções separando os itens.";
        }

        $cfop = CfopSaida::where('codigo', $codigo)->first();

        if (!$cfop) {
            $cfop = CfopSaida::create([
                'codigo'                   => $codigo,
                'descricao'                => $tipo === 'st'
                    ? 'Devolução de venda de mercadoria adquirida ou recebida de terceiros em operação com mercadoria sujeita ao regime de substituição tributária'
                    : 'Devolução de venda de mercadoria adquirida ou recebida de terceiros',
                'tipo_operacao'            => 'entrada',
                'movimenta_estoque'        => true,
                'ativo'                    => true,
                'natureza_operacao_padrao' => 'Devolução de venda',
                'finalidade_padrao'        => 4,
                'destacar_bases'           => true,
            ]);
            $avisos[] = "O CFOP {$codigo} não estava cadastrado e foi incluído no cadastro de tipos de nota.";
        } elseif (!$cfop->ativo) {
            $avisos[] = "O CFOP {$codigo} está inativo no cadastro de tipos de nota: ative-o ou escolha outro.";
            return null;
        } elseif ($cfop->tipo_operacao !== 'entrada') {
            $avisos[] = "O CFOP {$codigo} está cadastrado como saída: corrija o tipo de operação no cadastro (devolução de venda é entrada).";
        }

        return $cfop;
    }

    // ---------------- 2) NF-e de venda do item novo ----------------

    /** NF-e de venda (sem itens: o operador lança o que o cliente está levando), ligada à NF-e de entrada da troca. */
    public function montarVendaNova(NotaFiscal $entrada): array
    {
        $avisos = [];
        $entrada->loadMissing('cliente');

        $empresa = Empresa::first();
        $documentoEmpresa = preg_replace('/\D/', '', (string) $empresa?->cnpj);
        $cliente = $entrada->cliente;

        if ($cliente && $cliente->cpf_cnpj === $documentoEmpresa) {
            $cliente = null;
            $avisos[] = 'A devolução foi feita contra a própria empresa (consumidor não identificado): selecione o cliente da venda. Para consumidor não identificado, faça a venda do item novo no caixa (NFC-e).';
        }

        $interestadual = $cliente?->uf && strtoupper($cliente->uf) !== strtoupper((string) $empresa?->uf);
        $codigo = ($interestadual ? '6' : '5') . substr((string) config('fiscal.troca_cfops.venda', '5102'), 1);
        $cfop = CfopSaida::ativos()->where('codigo', $codigo)->first();

        if (!$cfop) {
            $avisos[] = "O CFOP {$codigo} não está ativo no cadastro: selecione o tipo de nota.";
        }

        // Crédito em loja gerado pela entrada da troca (existe depois que a entrada é autorizada)
        $credito = app(CreditoLojaService::class)->saldoDisponivel($cliente?->id, $entrada->id);
        if ($credito > 0) {
            $avisos[] = sprintf('O crédito em loja da troca (R$ %s) já vem como pagamento. Se o item novo for mais caro, escolha a forma de pagamento da diferença.',
                number_format($credito, 2, ',', '.'));
        } elseif ($entrada->status !== 'emitida') {
            $avisos[] = 'A NF-e de entrada da troca ainda não foi autorizada: o crédito em loja só é gerado depois da autorização. Emita a entrada antes, para pagar esta venda com o crédito.';
        }

        $nota = new NotaFiscal([
            'cliente_id'                 => $cliente?->id,
            'valor_credito_loja'         => $credito,
            'natureza_operacao'          => $cfop?->natureza_operacao_padrao ?: 'Venda',
            'finalidade'                 => $cfop ? ($cfop->finalidade_padrao ?? 1) : 1,
            'cfop_saida_id'              => $cfop?->id,
            'forma_pagamento_id'         => null,
            'informacoes_complementares' => $entrada->numero
                ? "Troca: mercadoria devolvida pela NF-e de entrada nº {$entrada->numero}, série {$entrada->serie}."
                : 'Troca: mercadoria devolvida pela NF-e de entrada desta mesma troca.',
            'notas_referenciadas'        => [],
            'origem_tipo'                => 'troca',
            'troca_entrada_id'           => $entrada->id,
            'frete_por_item'             => false,
            'mod_frete'                  => 9,
        ]);

        $this->relacoesVazias($nota, $cliente, $cfop, null);
        $nota->setRelation('trocaEntrada', $entrada);
        $nota->setRelation('itens', collect());
        $avisos[] = $credito > 0
            ? 'Adicione os itens que o cliente está levando.'
            : 'Adicione os itens que o cliente está levando e a forma de pagamento.';

        return [$nota, $avisos];
    }

    private function relacoesVazias(NotaFiscal $nota, ?Cliente $cliente, ?CfopSaida $cfop, ?FormaPagamento $forma): void
    {
        $nota->setRelation('cliente', $cliente);
        $nota->setRelation('cfopSaida', $cfop);
        $nota->setRelation('formaPagamento', $forma);
        foreach (['venda', 'pedidoVenda', 'entradaNota', 'trocaVenda', 'trocaNotaFiscal', 'trocaEntrada', 'transportador', 'veiculo'] as $rel) {
            $nota->setRelation($rel, null);
        }
    }
}
