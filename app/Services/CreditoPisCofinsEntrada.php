<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\SpedConfiguracao;
use Illuminate\Support\Facades\DB;

/**
 * PIS/COFINS das entradas para a EFD-Contribuições.
 *
 * O PIS/COFINS que vem no XML é o do FORNECEDOR (débito dele). O que importa aqui é o
 * crédito da EMPRESA, decidido por: regime (Configuração do SPED), CFOP de entrada,
 * classificação de PIS/COFINS do produto (CST de saída) e tipo do item.
 *
 * Só se aplica a empresas do regime normal (CRT 3). No Simples tudo fica vazio.
 */
class CreditoPisCofinsEntrada
{
    public const ALIQUOTA_PIS_NAO_CUMULATIVO    = 1.65;
    public const ALIQUOTA_COFINS_NAO_CUMULATIVO = 7.60;

    public const CSTS = [
        '50' => 'Crédito vinculado exclusivamente a receita tributada no mercado interno',
        '51' => 'Crédito vinculado exclusivamente a receita não tributada no mercado interno',
        '52' => 'Crédito vinculado exclusivamente a receita de exportação',
        '53' => 'Crédito vinculado a receitas tributadas e não tributadas no mercado interno',
        '54' => 'Crédito vinculado a receitas tributadas no mercado interno e de exportação',
        '55' => 'Crédito vinculado a receitas não tributadas no mercado interno e de exportação',
        '56' => 'Crédito vinculado a receitas tributadas e não tributadas no mercado interno e de exportação',
        '70' => 'Aquisição sem direito a crédito',
        '71' => 'Aquisição com isenção',
        '72' => 'Aquisição com suspensão',
        '73' => 'Aquisição a alíquota zero',
        '74' => 'Aquisição sem incidência da contribuição',
        '75' => 'Aquisição por substituição tributária',
        '98' => 'Outras operações de entrada',
        '99' => 'Outras operações',
    ];

    /** Natureza da base de cálculo do crédito (tabela 4.3.7) — as que uma entrada de mercadoria usa. */
    public const NATUREZAS = [
        '01' => 'Aquisição de bens para revenda',
        '02' => 'Aquisição de bens utilizados como insumo',
        '03' => 'Aquisição de serviços utilizados como insumo',
        '12' => 'Devolução de vendas sujeitas à incidência não cumulativa',
        '13' => 'Outras operações com direito a crédito',
    ];

    /** CSTs que dão crédito (exigem NAT_BC_CRED). */
    public const CSTS_COM_CREDITO = ['50', '51', '52', '53', '54', '55', '56'];

    /** Sufixos de CFOP (3 últimos dígitos) de compra para revenda/industrialização. */
    private const CFOP_COMPRA = ['101', '102', '111', '113', '116', '117', '118', '120', '121', '122', '401', '403', '651', '652'];

    /** Sufixos de CFOP de devolução de venda. */
    private const CFOP_DEVOLUCAO = ['201', '202', '203', '204', '410', '411', '660', '661', '662'];

    /** Sufixos de CFOP de uso e consumo / ativo imobilizado (aquisição sem crédito no C170). */
    private const CFOP_USO_ATIVO = ['406', '407', '551', '556'];

    /** CST de saída do produto => CST de entrada quando a aquisição não dá crédito. */
    private const CST_SEM_CREDITO = [
        '02' => '70', '03' => '70', '04' => '70',
        '05' => '75', '06' => '73', '07' => '71', '08' => '74', '09' => '72',
    ];

    /** Tipos de item (registro 0200) que são insumo: natureza 02 em vez de 01. */
    private const TIPOS_INSUMO = ['01', '02', '06', '10'];

    public static function regime(): ?string
    {
        if ((int) Empresa::atual()->crt !== 3) {
            return null;
        }

        return SpedConfiguracao::atual()->regime_pis_cofins ?: null;
    }

    /**
     * Calcula e grava o PIS/COFINS de todos os itens da entrada.
     * Itens com pis_cofins_manual só têm os VALORES recalculados (base × alíquota).
     */
    public function aplicar(EntradaNota $entrada): void
    {
        $regime = self::regime();

        if ($regime === null) {
            return; // Simples, ou regime ainda não configurado (a verificação do SPED avisa)
        }

        $entrada->load(['itens.produto.pisCofins', 'itens.cfopEntrada']);
        $itens = $entrada->itens->values();
        $bases = $this->basesDosItens($entrada, $itens);

        foreach ($itens as $n => $item) {
            if ($item->pis_cofins_manual) {
                $dados = $this->valores(
                    (string) $item->cst_pis_cofins,
                    $item->nat_bc_cred,
                    (float) $item->bc_pis_cofins,
                    (float) $item->aliquota_pis,
                    (float) $item->aliquota_cofins
                );
            } else {
                $dados = $this->automatico($item, $regime, $bases[$n]);
            }

            DB::table('entrada_nota_itens')->where('id', $item->id)->update($dados + ['updated_at' => now()]);
        }
    }

    /** Pendências para mostrar ao operador depois de salvar/importar. */
    public static function avisos(EntradaNota $entrada): array
    {
        if ((int) Empresa::atual()->crt !== 3) {
            return [];
        }

        if (self::regime() === null) {
            return ['PIS/COFINS das entradas não calculado: defina o regime (cumulativo ou não cumulativo) em Fiscal → SPED → Configuração do SPED.'];
        }

        $sem = DB::table('entrada_nota_itens')
            ->where('entrada_nota_id', $entrada->id)
            ->whereNull('cst_pis_cofins')
            ->count();

        return $sem
            ? ["{$sem} item(ns) sem CST de PIS/COFINS de entrada: o produto não tem classificação de PIS/COFINS. Cadastre-a no produto ou informe na aba Impostos do item."]
            : [];
    }

    private function automatico($item, string $regime, float $base): array
    {
        $cstSaida = $item->produto?->pisCofins?->codigo;
        $cstSaida = $cstSaida !== null ? str_pad((string) $cstSaida, 2, '0', STR_PAD_LEFT) : null;

        $sufixo = substr((string) $item->cfopEntrada?->codigo, 1);
        $devolucao = in_array($sufixo, self::CFOP_DEVOLUCAO, true);

        // Lucro Presumido: não há crédito. A devolução de venda reduz a receita (tratada na apuração).
        if ($regime === 'cumulativo') {
            return $this->valores($devolucao ? '98' : '70', null, 0, 0, 0);
        }

        // sem classificação no produto ou CFOP de entrada ainda pendente: o operador decide
        if ($cstSaida === null || $sufixo === '') {
            return $this->valores(null, null, 0, 0, 0);
        }

        $compra = in_array($sufixo, self::CFOP_COMPRA, true);

        if (in_array($sufixo, self::CFOP_USO_ATIVO, true)) {
            // uso e consumo não dá crédito; o do ativo imobilizado vem pela depreciação (F120/F130), fora do C170
            return $this->valores('70', null, 0, 0, 0);
        }

        if (! $compra && ! $devolucao) {
            return $this->valores('98', null, 0, 0, 0); // bonificação, remessa, transferência...
        }

        if ($cstSaida !== '01') {
            return $this->valores(self::CST_SEM_CREDITO[$cstSaida] ?? '70', null, 0, 0, 0);
        }

        $tipo = (string) ($item->produto?->tipo_item ?? '00');

        if ($tipo === '07' || $tipo === '08') {
            return $this->valores('70', null, 0, 0, 0);
        }

        $natureza = $devolucao ? '12' : (in_array($tipo, self::TIPOS_INSUMO, true) ? '02' : '01');

        return $this->valores('50', $natureza, $base, self::ALIQUOTA_PIS_NAO_CUMULATIVO, self::ALIQUOTA_COFINS_NAO_CUMULATIVO);
    }

    private function valores(?string $cst, ?string $natureza, float $base, float $pPis, float $pCofins): array
    {
        $comCredito = $cst !== null && in_array($cst, self::CSTS_COM_CREDITO, true);

        if (! $comCredito) {
            $base = $pPis = $pCofins = 0;
            $natureza = null;
        }

        $base = round(max($base, 0), 2);

        return [
            'cst_pis_cofins'  => $cst,
            'nat_bc_cred'     => $natureza,
            'bc_pis_cofins'   => $base,
            'aliquota_pis'    => round($pPis, 4),
            'aliquota_cofins' => round($pCofins, 4),
            'valor_pis'       => round($base * $pPis / 100, 2),
            'valor_cofins'    => round($base * $pCofins / 100, 2),
        ];
    }

    /**
     * Base do crédito de cada item: valor do item + frete + outras despesas + IPI − ICMS destacado
     * (o ICMS sai da base do crédito desde a Lei 14.592/2023), mais a parte que cabe ao item do
     * frete/outras/desconto lançados no cabeçalho da entrada (rateados pelo valor do item).
     */
    private function basesDosItens(EntradaNota $entrada, $itens): array
    {
        $cabecalho = (float) $entrada->valor_frete + (float) $entrada->valor_outras - (float) $entrada->valor_desconto;
        $totalItens = (float) $itens->sum(fn ($i) => (float) $i->valor_total);

        $bases = [];
        $rateado = 0.0;
        $ultimo = $itens->count() - 1;

        foreach ($itens as $n => $item) {
            $parte = 0.0;
            if ($cabecalho != 0 && $totalItens > 0) {
                $parte = $n === $ultimo
                    ? round($cabecalho - $rateado, 2)
                    : round($cabecalho * (float) $item->valor_total / $totalItens, 2);
                $rateado += $parte;
            }

            $bases[$n] = round(
                (float) $item->valor_total
                + (float) $item->valor_frete
                + (float) $item->valor_outras_despesas
                + (float) $item->valor_ipi
                - (float) $item->valor_icms
                + $parte,
                2
            );
        }

        return $bases;
    }
}
