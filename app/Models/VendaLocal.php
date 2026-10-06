<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\DB;
use App\Support\DadosFiscaisLocais;

/**
 * Venda do SQLite vestida de Venda: tem os mesmos atributos e relações que o
 * FiscalEmissorService lê, e o update() grava de volta no vendas_pendentes.
 * "status" aqui é a situação FISCAL (status_fiscal); a de sincronização fica em "status_sync".
 */
class VendaLocal extends Venda
{
    protected $connection = 'sqlite_local';
    protected $table = 'vendas_pendentes';

    public static function carregar(string $uuid): self
    {
        $db = DB::connection('sqlite_local');
        $linha = $db->table('vendas_pendentes')->where('uuid', $uuid)->first();

        if (!$linha) {
            throw new Exception('Venda não encontrada no caixa.');
        }

        $venda = new self();
        $venda->exists = true;
        $venda->forceFill(self::atributos($linha));

        $caixa = $db->table('caixas_local')
            ->when(
                $linha->caixa_uuid,
                fn ($q) => $q->where('uuid', $linha->caixa_uuid),
                fn ($q) => $q->where('id_central', $linha->caixa_id_central)
            )
            ->first();

        if (!$caixa) {
            throw new Exception('O caixa desta venda não foi encontrado no caixa local.');
        }

        $venda->setRelation('caixa', (object) ['pdv' => DadosFiscaisLocais::pdv((int) $caixa->pdv_id)]);
        $venda->setRelation('itens', self::montarItens($db, $linha));
        $venda->setRelation('pagamentos', collect(json_decode($linha->pagamentos ?? '[]', true) ?? [])->map(fn ($p) => (object) $p));
        $venda->setRelation('cliente', self::montarCliente($db, $linha->cliente_id));

        return $venda;
    }

    // Os dados já estão carregados: não há o que buscar
    public function load($relations)
    {
        return $this;
    }

    public function refresh()
    {
        $linha = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $this->uuid)->first();

        if ($linha) {
            $this->forceFill(self::atributos($linha));
        }

        return $this;
    }

    // Grava no SQLite. O "status" do emissor é a coluna status_fiscal.
    // Grava no SQLite. O "status" do emissor é a coluna status_fiscal.
    public function update(array $attributes = [], array $options = [])
    {
        $colunas = [];

        foreach ($attributes as $chave => $valor) {
            $colunas[$chave === 'status' ? 'status_fiscal' : $chave] = $valor;
        }

        $camposFiscais = [
            'status_fiscal', 'numero_nfce', 'serie_nfce', 'chave_nfe', 'protocolo_nfe', 'tp_emis',
            'dh_cont', 'x_just', 'xml_contingencia', 'ultimo_arquivo_xml', 'motivo_rejeicao', 'emitida_em',
        ];

        if (array_intersect(array_keys($colunas), $camposFiscais)) {
            $colunas['fiscal_sync_pendente'] = true;
        }

        DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('uuid', $this->uuid)
            ->update($colunas + ['updated_at' => now()]);

        $this->forceFill($attributes);

        return true;
    }

    private static function atributos(object $linha): array
    {
        return [
            'id' => $linha->id,
            'uuid' => $linha->uuid,
            'total' => $linha->total,
            'troco' => $linha->troco,
            'desconto' => $linha->desconto,
            'cpf_na_nota' => $linha->cpf_na_nota,
            'cliente_id' => $linha->cliente_id,
            'status' => $linha->status_fiscal,
            'status_sync' => $linha->status,
            'numero_nfce' => $linha->numero_nfce,
            'serie_nfce' => $linha->serie_nfce,
            'chave_nfe' => $linha->chave_nfe,
            'protocolo_nfe' => $linha->protocolo_nfe,
            'tp_emis' => $linha->tp_emis,
            'dh_cont' => $linha->dh_cont,
            'x_just' => $linha->x_just,
            'xml_contingencia' => $linha->xml_contingencia,
            'ultimo_arquivo_xml' => $linha->ultimo_arquivo_xml,
            'motivo_rejeicao' => $linha->motivo_rejeicao,
            'emitida_em' => $linha->emitida_em,
        ];
    }

    private static function montarItens($db, object $linha)
    {
        return collect(json_decode($linha->itens, true) ?? [])->map(function ($item) use ($db) {
            $p = $db->table('produtos_cache')->where('id', $item['produto_id'])->first();

            if (!$p) {
                throw new Exception("O produto {$item['produto_id']} desta venda não está mais no caixa. Sincronize com o servidor.");
            }

            $variante = !empty($item['produto_variante_id'])
                ? $db->table('produto_variantes_cache')->where('id', $item['produto_variante_id'])->first()
                : null;

            $produto = (object) [
                'id' => $p->id,
                'nome' => $p->nome,
                'codigo_interno' => $p->codigo_interno,
                'codigo_barras' => $p->codigo_barras,
                'codigo_barras_valido' => (bool) $p->codigo_barras_valido,
                'unidade_comercial' => $p->unidade_comercial,
                'unidade_tributavel' => $p->unidade_tributavel,
                'origem_mercadoria' => $p->origem_mercadoria,
                'ncm' => (object) ['codigo' => $p->ncm],
                'cest' => $p->cest ? (object) ['codigo' => $p->cest] : null,
                'tributacao' => (object) [
                    'cfop' => $p->cfop_padrao,
                    'csosn' => $p->csosn,
                    'cst_icms' => $p->cst_icms,
                    'aliquota_icms' => $p->aliquota_icms,
                ],
                'pisCofins' => $p->pis_cofins_cst !== null ? (object) [
                    'codigo' => $p->pis_cofins_cst,
                    'aliquota_pis' => $p->aliquota_pis,
                    'aliquota_cofins' => $p->aliquota_cofins,
                ] : null,
                'classificacaoTributaria' => $p->class_trib_ibs_cbs ? (object) [
                    'codigo' => $p->class_trib_ibs_cbs,
                    'cst_codigo' => $p->class_trib_cst,
                    'percentual_reducao_ibs' => $p->percentual_reducao_ibs,
                    'percentual_reducao_cbs' => $p->percentual_reducao_cbs,
                ] : null,
            ];

            return (object) [
                'produto_id' => $item['produto_id'],
                'produto_variante_id' => $item['produto_variante_id'] ?? null,
                'produto' => $produto,
                'variante' => $variante,
                'quantidade' => (int) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'desconto' => (float) ($item['desconto'] ?? 0),
            ];
        });
    }

    private static function montarCliente($db, $clienteId): ?object
    {
        return $clienteId
            ? $db->table('clientes_cache')->where('id', $clienteId)->first()
            : null;
    }
}