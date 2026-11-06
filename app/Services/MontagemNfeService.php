<?php

namespace App\Services;

use App\Models\CfopSaida;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Inutilizacao;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Pdv;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faturamento → Montagem NF-e → A partir de NFC-e.
 * Localiza a NFC-e (venda do caixa já sincronizada no servidor) e monta, em memória,
 * uma NF-e com itens, cliente, pagamento e referência à NFC-e. Nada é gravado aqui:
 * o operador confere na tela da NF-e e salva como rascunho.
 */
class MontagemNfeService
{
    // Forma de pagamento do caixa -> meio (tPag) e nome: pelo cadastro (FormasCaixa)

    /** Motivo que impede montar NF-e desta venda (null = pode montar). */
    public static function erroVendaParaMontagem(?Venda $venda): ?string
    {
        if (!$venda) {
            return 'NFC-e não encontrada.';
        }

        if ($venda->status === 'cancelada') {
            return "A NFC-e nº {$venda->numero_nfce} está cancelada: não é possível gerar NF-e a partir dela.";
        }

        if (!in_array($venda->status, ['emitida', 'contingencia'], true)) {
            return "A NFC-e nº {$venda->numero_nfce} ainda não foi autorizada (situação: {$venda->status}).";
        }

        $nfeExistente = self::nfeJaGerada($venda);
        if ($nfeExistente) {
            $numero = $nfeExistente->numero ? "nº {$nfeExistente->numero}" : "(rascunho #{$nfeExistente->id})";

            return "Já existe uma NF-e {$numero} gerada a partir desta NFC-e (situação: {$nfeExistente->status}).";
        }

        return null;
    }

    public static function nfeJaGerada(Venda $venda): ?NotaFiscal
    {
        return NotaFiscal::where('venda_id', $venda->id)
            ->where('status', '!=', 'cancelada')
            ->latest('id')
            ->first();
    }

    /**
     * Busca pela série e número do cupom.
     * Retorna ['venda' => ?Venda, 'situacao' => encontrada|nao_sincronizada|inutilizada|nao_encontrada, 'mensagem' => ?string].
     */
    public function buscarPorCupom(int $serie, int $numero): array
    {
        $venda = Venda::with('cliente')
            ->where('serie_nfce', $serie)
            ->where('numero_nfce', $numero)
            ->orderByRaw("status = 'cancelada'") // havendo mais de uma, a válida primeiro
            ->first();

        if ($venda) {
            return ['venda' => $venda, 'situacao' => 'encontrada', 'mensagem' => null];
        }

        // Ainda no banco do caixa deste computador
        $local = $this->vendasLocaisPendentes()
            ->first(fn ($v) => (int) $v->serie_nfce === $serie && (int) $v->numero_nfce === $numero);

        if ($local) {
            return [
                'venda' => null,
                'situacao' => 'nao_sincronizada',
                'mensagem' => "Venda não sincronizada: a NFC-e nº {$numero}, série {$serie}, "
                    . 'de ' . Carbon::parse($local->vendida_em)->format('d/m/Y H:i')
                    . ' ainda está só no caixa. Use "Atualizar Caixa" para enviá-la ao servidor e tente de novo.'
                    . ($local->erro_sync_mensagem ? " Último erro ao enviar: {$local->erro_sync_mensagem}" : ''),
            ];
        }

        $inutilizada = Inutilizacao::where('serie', $serie)
            ->where('status', 'sucesso')
            ->where('numero_inicial', '<=', $numero)
            ->where('numero_final', '>=', $numero)
            ->exists();

        if ($inutilizada) {
            return [
                'venda' => null,
                'situacao' => 'inutilizada',
                'mensagem' => "O número {$numero} da série {$serie} foi inutilizado: não existe NFC-e com esse número.",
            ];
        }

        // O caixa já informou ao servidor que usou esse número, mas a venda não chegou
        $pdv = Pdv::where('serie_nfce', $serie)->where('numero_atual_nfce', '>=', $numero)->first();

        if ($pdv) {
            return [
                'venda' => null,
                'situacao' => 'nao_sincronizada',
                'mensagem' => "Venda não sincronizada: o número {$numero} da série {$serie} já foi usado pelo caixa "
                    . "\"{$pdv->nome}\", mas a venda ainda não chegou ao servidor. "
                    . 'No computador desse caixa, use "Atualizar Caixa" e tente de novo.',
            ];
        }

        return [
            'venda' => null,
            'situacao' => 'nao_encontrada',
            'mensagem' => "Nenhuma NFC-e encontrada com a série {$serie} e o número {$numero}.",
        ];
    }

    /** NFC-e do dia no servidor. */
    public function buscarPorData(string $data): Collection
    {
        return Venda::with('cliente')
            ->whereDate('created_at', $data)
            ->whereNotNull('numero_nfce')
            ->orderByDesc('created_at')
            ->get();
    }

    /** Vendas do dia que ainda estão só no caixa deste computador. */
    public function pendentesPorData(string $data): Collection
    {
        return $this->vendasLocaisPendentes()
            ->filter(fn ($v) => $v->vendida_em && Carbon::parse($v->vendida_em)->toDateString() === $data)
            ->values();
    }

    /**
     * Vendas do SQLite do caixa que ainda não subiram (ou cuja situação fiscal não subiu).
     * No servidor não existe banco do caixa: devolve vazio.
     */
    private function vendasLocaisPendentes(): Collection
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            if (!is_file(config('database.connections.sqlite_local.database'))) {
                return $cache = collect();
            }

            $db = DB::connection('sqlite_local');

            if (!$db->getSchemaBuilder()->hasTable('vendas_pendentes')) {
                return $cache = collect();
            }

            return $cache = $db->table('vendas_pendentes')
                ->where(fn ($q) => $q->where('status', '!=', 'sincronizada')->orWhere('fiscal_sync_pendente', true))
                ->whereNotNull('numero_nfce')
                ->get();
        } catch (\Throwable $e) {
            return $cache = collect();
        }
    }

    /**
     * Monta a NF-e em memória (não salva).
     * Retorna [NotaFiscal $nota, string[] $avisos].
     */
    public function montar(Venda $venda): array
    {
        $venda->loadMissing('itens.produto', 'itens.variante', 'pagamentos', 'cliente');
        $avisos = [];

        $cliente = $this->clienteDaVenda($venda, $avisos);
        $cfop = $this->cfopPadrao($cliente, $avisos);
        $forma = $this->formaPagamentoDaVenda($venda, $avisos);

        if ($venda->status === 'contingencia') {
            $avisos[] = 'A NFC-e foi emitida em contingência e ainda não tem autorização da SEFAZ.';
        }

        $chave = preg_match('/^\d{44}$/', (string) $venda->chave_nfe) ? $venda->chave_nfe : null;
        if (!$chave) {
            $avisos[] = 'A NFC-e não tem chave de acesso registrada: ela não foi incluída nas notas referenciadas.';
        }

        $nota = new NotaFiscal([
            'cliente_id'                 => $cliente?->id,
            'natureza_operacao'          => $cfop?->natureza_operacao_padrao,
            'finalidade'                 => $cfop ? ($cfop->finalidade_padrao ?? 1) : null,
            'cfop_saida_id'              => $cfop?->id,
            'forma_pagamento_id'         => $forma?->id,
            'informacoes_complementares' => sprintf(
                'Referente à NFC-e nº %s, série %s, emitida em %s.',
                $venda->numero_nfce,
                $venda->serie_nfce,
                ($venda->emitida_em ? Carbon::parse($venda->emitida_em) : $venda->created_at)->format('d/m/Y')
            ),
            'notas_referenciadas'        => $chave ? [$chave] : [],
            'origem_tipo'                => 'venda',
            'venda_id'                   => $venda->id,
            'frete_por_item'             => false,
            'mod_frete'                  => 9,
        ]);

        $nota->setRelation('cliente', $cliente);
        $nota->setRelation('cfopSaida', $cfop);
        $nota->setRelation('formaPagamento', $forma);
        $nota->setRelation('venda', $venda);
        $nota->setRelation('transportador', null);
        $nota->setRelation('veiculo', null);
        $nota->setRelation('itens', $this->itensDaVenda($venda, $avisos));

        return [$nota, $avisos];
    }

    private function clienteDaVenda(Venda $venda, array &$avisos): ?Cliente
    {
        if ($venda->cliente) {
            return $venda->cliente;
        }

        $documento = preg_replace('/\D/', '', (string) $venda->cpf_na_nota);

        if ($documento !== '') {
            $cliente = Cliente::ativos()->where('cpf_cnpj', $documento)->first();

            if ($cliente) {
                return $cliente;
            }

            $avisos[] = "O CPF/CNPJ informado na NFC-e ({$documento}) não está no cadastro de clientes: cadastre-o ou selecione o cliente.";

            return null;
        }

        $avisos[] = 'A NFC-e não tem cliente identificado: selecione o cliente da NF-e.';

        return null;
    }

    /** Primeiro CFOP ativo da lista do config (cliente de outra UF: tenta antes o 6xxx). */
    protected function cfopPadrao(?Cliente $cliente, array &$avisos, string $chaveConfig = 'fiscal.montagem_nfce_cfops'): ?CfopSaida
    {
        $interestadual = $cliente?->uf && $cliente->uf !== Empresa::first()?->uf;

        foreach (config($chaveConfig, []) as $codigo) {
            $codigo = (string) $codigo;
            $candidatos = $interestadual ? ['6' . substr($codigo, 1), $codigo] : [$codigo];

            foreach ($candidatos as $c) {
                $cfop = CfopSaida::ativos()->where('codigo', $c)->first();
                if ($cfop) {
                    return $cfop;
                }
            }
        }

        $avisos[] = 'Nenhum tipo de nota (CFOP) padrão encontrado no cadastro: selecione o tipo de nota.';

        return null;
    }

    private function formaPagamentoDaVenda(Venda $venda, array &$avisos): ?FormaPagamento
    {
        $pagamentos = $venda->pagamentos
            ->groupBy('forma_pagamento')
            ->map(fn ($grupo) => (float) $grupo->sum('valor'))
            ->sortDesc();

        if ($pagamentos->isEmpty() && $venda->forma_pagamento) {
            $pagamentos = collect([$venda->forma_pagamento => (float) $venda->total]);
        }

        if ($pagamentos->isEmpty()) {
            $avisos[] = 'A NFC-e não tem forma de pagamento registrada: selecione o tipo de pagamento.';
            return null;
        }

        if ($pagamentos->count() > 1) {
            $descricao = $pagamentos
                ->map(fn ($valor, $forma) => \App\Support\FormasCaixa::rotulo($forma) . ' R$ ' . number_format($valor, 2, ',', '.'))
                ->implode(', ');
            $avisos[] = "A NFC-e foi paga com mais de uma forma ({$descricao}). A NF-e aceita uma: foi pré-selecionada a de maior valor.";
        }

        $formaCaixa = (string) $pagamentos->keys()->first();
        // Meio pelo cadastro de formas do caixa; PIX aceita dinâmico ou estático
        $meio = \App\Support\FormasCaixa::meio($formaCaixa);
        $meios = in_array($meio, ['17', '20'], true) ? ['17', '20'] : [$meio];

        $forma = FormaPagamento::ativos()->paraSaida()
            ->whereIn('meio_pagamento', $meios)
            ->orderBy('ordem')
            ->orderBy('id')
            ->first();

        if (!$forma) {
            $nome = \App\Support\FormasCaixa::rotulo($formaCaixa);
            $avisos[] = "Nenhuma forma de pagamento de saída ativa para \"{$nome}\": selecione o tipo de pagamento.";
        }

        return $forma;
    }

    /** Itens da NFC-e com o desconto global rateado (mesma regra da emissão da NFC-e). */
    private function itensDaVenda(Venda $venda, array &$avisos): Collection
    {
        $itens = $venda->itens->filter(fn ($i) => $i->produto_id)->values();

        $semProduto = $itens->filter(fn ($i) => !$i->produto);
        foreach ($semProduto as $i) {
            $avisos[] = "O produto {$i->produto_id} da NFC-e não existe mais no cadastro e ficou de fora.";
        }
        $itens = $itens->filter(fn ($i) => $i->produto)->values();

        $descontoGlobal = round((float) $venda->desconto - (float) $itens->sum('desconto'), 2);
        $brutoTotal = (float) $itens->sum(fn ($i) => (float) $i->preco_unitario * (float) $i->quantidade);
        $somaRateios = 0.0;
        $ultimo = $itens->count() - 1;

        return $itens->map(function ($i, $indice) use ($descontoGlobal, $brutoTotal, &$somaRateios, $ultimo) {
            $produto = $i->produto;
            $quantidade = (float) $i->quantidade;
            $valorUnitario = (float) $i->preco_unitario;
            $bruto = $quantidade * $valorUnitario;

            $rateio = 0.0;
            if ($descontoGlobal > 0) {
                if ($indice === $ultimo) {
                    $rateio = round($descontoGlobal - $somaRateios, 2);
                } else {
                    $rateio = $brutoTotal > 0 ? round($descontoGlobal * $bruto / $brutoTotal, 2) : 0;
                    $somaRateios += $rateio;
                }
            }

            $desconto = round(min((float) ($i->desconto ?? 0) + $rateio, $bruto), 2);

            // Descrição gravada na NFC-e (ex.: com a variante) só quando difere do nome do produto
            $descricao = trim((string) $i->descricao);
            $descricao = ($descricao !== '' && $descricao !== $produto->nome) ? $descricao : null;

            $item = new NotaFiscalItem([
                'produto_id'            => $produto->id,
                'produto_variante_id'   => $i->produto_variante_id,
                'descricao'             => $descricao,
                'ncm_id'                => $produto->ncm_id,
                'cest_id'               => $produto->cest_id,
                'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
                'tributacao_id'         => $produto->tributacao_id,
                'pis_cofins_id'         => $produto->pis_cofins_id,
                'ipi_id'                => $produto->ipi_id,
                'quantidade'            => $quantidade,
                'valor_unitario'        => $valorUnitario,
                'valor_desconto'        => $desconto,
                'valor_outras_despesas' => 0,
                'valor_frete'           => 0,
                'valor_total'           => round($bruto - $desconto, 2),
                'impostos_manuais'      => false,
            ]);
            $item->setRelation('produto', $produto);

            return $item;
        });
    }
}
