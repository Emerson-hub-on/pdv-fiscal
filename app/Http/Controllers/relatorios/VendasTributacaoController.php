<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\VendasTributacaoExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Regime/Alíquota de imposto e tributação
 *
 * Mostra como as vendas foram tributadas, com os impostos destacados na nota, agrupando por:
 *   regime (Simples Nacional ou Regime normal) + CST/CSOSN + CFOP + alíquota de ICMS.
 *
 * O regime vem do próprio código de tributação gravado no item: CSOSN tem 3 dígitos (Simples Nacional) e CST tem
 * 2 dígitos (regime normal). Os valores são os que foram para o XML na emissão (NFC-e e NF-e de saída emitidas).
 * Item de nota emitida antes do registro dos impostos não tem tributação gravada: fica fora e é contado no aviso.
 *
 * Visões (a tela mostra só uma tabela por vez, como nos outros relatórios):
 *   resumida (padrão) = uma linha por grupo de tributação; detalhada = uma linha por item vendido.
 */
class VendasTributacaoController extends Controller
{
    private const POR_PAGINA = 50;

    private const REGIMES = [
        'simples' => 'Simples Nacional',
        'normal' => 'Regime normal',
    ];

    // CSOSN (Simples Nacional, 3 dígitos) e CST do ICMS (regime normal, 2 dígitos)
    private const DESCRICOES = [
        '101' => 'Tributada pelo Simples com permissão de crédito',
        '102' => 'Tributada pelo Simples sem permissão de crédito',
        '103' => 'Isenção do ICMS no Simples para faixa de receita bruta',
        '201' => 'Tributada pelo Simples com permissão de crédito e com ST',
        '202' => 'Tributada pelo Simples sem permissão de crédito e com ST',
        '203' => 'Isenção do ICMS no Simples para faixa de receita bruta e com ST',
        '300' => 'Imune',
        '400' => 'Não tributada pelo Simples',
        '500' => 'ICMS cobrado anteriormente por ST ou antecipação',
        '900' => 'Outros',
        '00' => 'Tributada integralmente',
        '10' => 'Tributada e com cobrança do ICMS por ST',
        '20' => 'Com redução de base de cálculo',
        '30' => 'Isenta ou não tributada e com cobrança do ICMS por ST',
        '40' => 'Isenta',
        '41' => 'Não tributada',
        '50' => 'Suspensão',
        '51' => 'Diferimento',
        '60' => 'ICMS cobrado anteriormente por ST',
        '70' => 'Com redução de base de cálculo e cobrança do ICMS por ST',
        '90' => 'Outras',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $totais = $this->totais($itens);

        // Resumida (padrão): uma linha por tributação. Detalhada: uma linha por item, no lugar do resumo.
        $registros = $filtros['visao'] === 'detalhada'
            ? $this->ordenarItens($this->comImpostos($itens), $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString()
            : $this->ordenarGrupos($this->agrupar($itens), $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString();

        $dados = [
            'registros' => $registros,
            'totais' => $totais,
            'filtros' => $filtros,
            'regimes' => self::REGIMES,
            'descricoes' => self::DESCRICOES,
        ];

        if ($request->ajax()) {
            return view('relatorios.vendas.tributacao._tabela', $dados);
        }

        return view('relatorios.vendas.tributacao.index', $dados);
    }

    // Mesma visão da tela. Resumida: uma linha por tributação. Detalhada: uma linha por item.
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $linhas = $filtros['visao'] === 'detalhada'
            ? $this->ordenarItens($this->comImpostos($itens), $filtros['ordenar'])->get()
            : $this->ordenarGrupos($this->agrupar($itens), $filtros['ordenar'])->get();

        return Excel::download(
            new VendasTributacaoExport($linhas, $this->totais($itens), $filtros['visao'], self::DESCRICOES),
            'tributacao-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $dados = [
            'registros' => $filtros['visao'] === 'detalhada'
                ? $this->ordenarItens($this->comImpostos($itens), $filtros['ordenar'])->get()
                : $this->ordenarGrupos($this->agrupar($itens), $filtros['ordenar'])->get(),
            'totais' => $this->totais($itens),
            'filtros' => $filtros,
            'regimes' => self::REGIMES,
            'descricoes' => self::DESCRICOES,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.tributacao.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('tributacao-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        return [
            'tipo_busca' => in_array($request->get('tipo_busca'), ['produto', 'documento'], true)
                ? $request->get('tipo_busca') : 'produto',
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true)
                ? $request->get('tipo') : 'todos',
            'regime' => array_key_exists((string) $request->get('regime'), self::REGIMES) ? $request->get('regime') : 'todos',
            'cfop' => preg_replace('/\D/', '', (string) $request->get('cfop', '')),
            'cst' => preg_replace('/\D/', '', (string) $request->get('cst', '')),
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            // "padrao" = maior venda na resumida e mais recentes na detalhada
            'ordenar' => in_array($request->get('ordenar'), ['padrao', 'maior_venda', 'maior_imposto', 'recentes', 'antigos', 'documento'], true)
                ? $request->get('ordenar') : 'padrao',
            'visao' => $request->get('visao') === 'detalhada' ? 'detalhada' : 'resumida',
        ];
    }

    /**
     * Itens de NFC-e e NF-e emitidas com a tributação gravada na emissão (mesmas colunas nos dois lados).
     * valor_venda = produtos + acréscimos − descontos. As tabelas têm collations diferentes: os textos levam COLLATE
     * para o UNION não dar "Illegal mix of collations".
     */
    private function itens(array $f): Builder
    {
        $nfce = DB::table('venda_itens as i')
            ->join('vendas as d', 'd.id', '=', 'i.venda_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->where('d.status', 'emitida')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                d.numero_nfce as numero,
                d.serie_nfce COLLATE utf8mb4_unicode_ci as serie,
                i.id as item_id,
                COALESCE(i.codigo_produto COLLATE utf8mb4_unicode_ci, p.codigo_interno COLLATE utf8mb4_unicode_ci) as codigo,
                COALESCE(i.descricao COLLATE utf8mb4_unicode_ci, p.nome COLLATE utf8mb4_unicode_ci) as descricao,
                i.quantidade as quantidade,
                (COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) - COALESCE(i.desconto_efetivo, i.desconto, 0)) as valor_venda,
                i.cfop COLLATE utf8mb4_unicode_ci as cfop,
                i.cst_csosn COLLATE utf8mb4_unicode_ci as cst,
                i.aliquota_icms as aliquota_icms,
                i.bc_icms as bc_icms,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                0 as ipi,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
                (i.impostos_registrados_em IS NOT NULL) as tem_impostos
            ");

        $nfe = DB::table('nota_fiscal_itens as i')
            ->join('notas_fiscais as d', 'd.id', '=', 'i.nota_fiscal_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->where('d.status', 'emitida')
            ->where('d.tipo_operacao', '<>', 'entrada')
            ->where('d.finalidade', 1) // só NF-e normal: devolução, complementar e ajuste não são venda
            ->selectRaw("
                'NF-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                d.numero as numero,
                d.serie COLLATE utf8mb4_unicode_ci as serie,
                i.id as item_id,
                p.codigo_interno COLLATE utf8mb4_unicode_ci as codigo,
                COALESCE(i.descricao COLLATE utf8mb4_unicode_ci, p.nome COLLATE utf8mb4_unicode_ci) as descricao,
                i.quantidade as quantidade,
                ((i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) - COALESCE(i.desconto_efetivo, i.valor_desconto, 0)) as valor_venda,
                i.cfop COLLATE utf8mb4_unicode_ci as cfop,
                i.cst_csosn COLLATE utf8mb4_unicode_ci as cst,
                i.aliquota_icms as aliquota_icms,
                i.bc_icms as bc_icms,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                i.valor_ipi as ipi,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
                (i.impostos_registrados_em IS NOT NULL) as tem_impostos
            ");

        foreach ([$nfce, $nfe] as $q) {
            if ($f['data_inicio'] !== '') {
                $q->where('d.emitida_em', '>=', $f['data_inicio'] . ' 00:00:00');
            }
            if ($f['data_fim'] !== '') {
                $q->where('d.emitida_em', '<=', $f['data_fim'] . ' 23:59:59');
            }
        }

        $lados = match ($f['tipo']) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        // Regime pelo tamanho do código: CSOSN (3 dígitos) = Simples Nacional; CST (2 dígitos) = regime normal
        $consulta = DB::query()->fromSub($lados, 't')->selectRaw("
            t.*,
            CASE WHEN t.cst IS NULL THEN NULL WHEN CHAR_LENGTH(t.cst) = 3 THEN 'simples' ELSE 'normal' END as regime,
            (t.icms + t.pis + t.cofins + t.ipi + t.ibs + t.cbs) as total_impostos
        ");

        // O alias "regime" não vale dentro do WHERE: repete a regra do tamanho do código
        if ($f['regime'] === 'simples') {
            $consulta->whereRaw('t.cst IS NOT NULL AND CHAR_LENGTH(t.cst) = 3');
        } elseif ($f['regime'] === 'normal') {
            $consulta->whereRaw('t.cst IS NOT NULL AND CHAR_LENGTH(t.cst) <> 3');
        }
        if ($f['cfop'] !== '') {
            $consulta->where('t.cfop', $f['cfop']);
        }
        if ($f['cst'] !== '') {
            $consulta->where('t.cst', $f['cst']);
        }
        if ($f['busca'] !== '') {
            $termo = $f['busca'];

            if ($f['tipo_busca'] === 'documento') {
                $consulta->where('t.numero', $termo);
            } else {
                $consulta->where(fn ($q) => $q
                    ->where('t.codigo', 'like', "%{$termo}%")
                    ->orWhere('t.descricao', 'like', "%{$termo}%"));
            }
        }

        return $consulta;
    }

    /** Só itens com impostos gravados (os demais não têm tributação para mostrar). */
    private function comImpostos(Builder $itens): Builder
    {
        return DB::query()->fromSub(clone $itens, 'x')->select('x.*')->where('x.tem_impostos', 1);
    }

    /** Uma linha por regime + CST/CSOSN + CFOP + alíquota de ICMS. */
    private function agrupar(Builder $itens): Builder
    {
        return DB::query()->fromSub($this->comImpostos($itens), 'g')->selectRaw('
            g.regime,
            g.cst,
            g.cfop,
            g.aliquota_icms,
            COUNT(*) as itens,
            COUNT(DISTINCT g.tipo, g.documento_id) as documentos,
            SUM(g.valor_venda) as valor_venda,
            SUM(g.bc_icms) as bc_icms,
            SUM(g.icms) as icms,
            SUM(g.pis) as pis,
            SUM(g.cofins) as cofins,
            SUM(g.ipi) as ipi,
            SUM(g.ibs) as ibs,
            SUM(g.cbs) as cbs,
            SUM(g.total_impostos) as total_impostos,
            CASE WHEN SUM(g.valor_venda) > 0 THEN SUM(g.total_impostos) / SUM(g.valor_venda) * 100 END as carga_percentual
        ')->groupBy('g.regime', 'g.cst', 'g.cfop', 'g.aliquota_icms');
    }

    private function ordenarGrupos(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'maior_imposto' => $q->orderByDesc('total_impostos')->orderByDesc('valor_venda'),
            default => $q->orderByDesc('valor_venda')->orderBy('g.regime')->orderBy('g.cst'),
        };
    }

    private function ordenarItens(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('x.data')->orderBy('x.numero')->orderBy('x.item_id'),
            'documento' => $q->orderBy('x.tipo')->orderBy('x.numero')->orderBy('x.item_id'),
            'maior_venda' => $q->orderByDesc('x.valor_venda'),
            'maior_imposto' => $q->orderByDesc('x.total_impostos'),
            default => $q->orderByDesc('x.data')->orderByDesc('x.numero')->orderBy('x.item_id'),
        };
    }

    /** Totais do filtro (só itens com impostos gravados) e quantos itens ficaram de fora por falta deles. */
    private function totais(Builder $itens): object
    {
        $t = DB::query()->fromSub(clone $itens, 'r')->selectRaw('
            COUNT(*) as itens_total,
            COALESCE(SUM(1 - r.tem_impostos), 0) as itens_sem_impostos,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN 1 END), 0) as itens,
            COUNT(DISTINCT CASE WHEN r.tem_impostos = 1 THEN CONCAT(r.tipo, r.documento_id) END) as documentos,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.valor_venda END), 0) as valor_venda,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.bc_icms END), 0) as bc_icms,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.icms END), 0) as icms,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.pis END), 0) as pis,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.cofins END), 0) as cofins,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.ipi END), 0) as ipi,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.ibs END), 0) as ibs,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.cbs END), 0) as cbs,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 THEN r.total_impostos END), 0) as total_impostos,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 AND r.regime = \'simples\' THEN r.valor_venda END), 0) as venda_simples,
            COALESCE(SUM(CASE WHEN r.tem_impostos = 1 AND r.regime = \'normal\' THEN r.valor_venda END), 0) as venda_normal
        ')->first();

        $t->carga_percentual = $t->valor_venda > 0 ? $t->total_impostos / $t->valor_venda * 100 : null;
        $t->percentual_simples = $t->valor_venda > 0 ? $t->venda_simples / $t->valor_venda * 100 : 0;
        $t->percentual_normal = $t->valor_venda > 0 ? $t->venda_normal / $t->valor_venda * 100 : 0;

        return $t;
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Visão: ' . ($f['visao'] === 'detalhada' ? 'detalhada (cada item)' : 'resumida (por tributação)');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];

        if ($f['regime'] !== 'todos') {
            $linhas[] = 'Regime: ' . self::REGIMES[$f['regime']];
        }
        if ($f['cfop'] !== '') {
            $linhas[] = 'CFOP: ' . $f['cfop'];
        }
        if ($f['cst'] !== '') {
            $linhas[] = 'CST/CSOSN: ' . $f['cst'];
        }
        if ($f['busca'] !== '') {
            $linhas[] = ($f['tipo_busca'] === 'documento' ? 'Nº do documento: ' : 'Produto: ') . $f['busca'];
        }

        return $linhas;
    }
}
