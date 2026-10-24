<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\MargemLucroExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Margem de Lucro
 *
 *   Venda (valor líquido) − Custo (preco_custo gravado na venda) − Carga tributária = Lucro
 *   Margem % = Lucro ÷ Venda
 *
 * Mesma base do relatório "Vendas Detalhada": NFC-e e NF-e de saída emitidas. O custo e os impostos vêm do que foi
 * gravado em cada item na emissão. Item sem custo ou sem impostos gravados (venda antiga, produto sem preço de
 * custo) aparece com "—" e fica fora dos totais e da margem, para não distorcer o resultado.
 */
class MargemLucroController extends Controller
{
    // Coluna com o nome do usuário na tabela users (ajuste se for outra, ex.: "nome")
    private const COLUNA_NOME_USUARIO = 'name';

    /**
     * Carga tributária = ICMS + PIS + COFINS, que já estão embutidos no preço de venda.
     * O IPI fica de fora: é cobrado do cliente por fora do valor da venda (não entra no valor líquido).
     * IBS/CBS: em 2026 são só o período de teste (compensados com PIS/COFINS), por isso começam desligados.
     * Troque para true se quiser somá-los à carga.
     */
    private const INCLUIR_IBS_CBS_NA_CARGA = false;

    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $itensBase = $this->consultaItens($filtros);

        $totais = $this->totais($itensBase);

        $documentos = $this->ordenarDocumentos($this->consultaDocumentos($itensBase), $filtros['ordenar'])
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $itensPorDocumento = $this->itensDosDocumentos($itensBase, $documentos->getCollection());

        $dados = compact('documentos', 'itensPorDocumento', 'totais', 'filtros');

        if ($request->ajax()) {
            return view('relatorios.vendas.margem-lucro._tabela', $dados);
        }

        return view('relatorios.vendas.margem-lucro.index', $dados);
    }

    // Detalhada: uma linha por item. Resumida: uma linha por venda.
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itensBase = $this->consultaItens($filtros);

        $linhas = $filtros['visao'] === 'detalhada'
            ? $this->ordenarItens(clone $itensBase, $filtros['ordenar'])->get()
            : $this->ordenarDocumentos($this->consultaDocumentos($itensBase), $filtros['ordenar'])->get();

        return Excel::download(
            new MargemLucroExport($linhas, $this->totais($itensBase), $filtros['visao']),
            'margem-lucro-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    // Detalhada: cada venda seguida dos seus itens. Resumida: só as vendas.
    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $itensBase = $this->consultaItens($filtros);

        $dados = [
            'documentos' => $this->ordenarDocumentos($this->consultaDocumentos($itensBase), $filtros['ordenar'])->get(),
            'itensPorDocumento' => $filtros['visao'] === 'detalhada'
                ? $this->ordenarItens(clone $itensBase, 'antigos')->get()->groupBy(fn ($i) => $i->tipo . '-' . $i->documento_id)
                : collect(),
            'totais' => $this->totais($itensBase),
            'filtros' => $filtros,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.margem-lucro.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('margem-lucro-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        return [
            'tipo_busca' => in_array($request->get('tipo_busca'), ['documento', 'produto', 'vendedor'], true)
                ? $request->get('tipo_busca') : 'documento',
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true)
                ? $request->get('tipo') : 'todos',
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            'ordenar' => in_array($request->get('ordenar'), ['recentes', 'antigos', 'documento', 'menor_margem', 'maior_margem'], true)
                ? $request->get('ordenar') : 'recentes',
            // Só vale para a exportação (PDF/Excel): com os itens de cada venda ou só as vendas
            'visao' => $request->get('visao') === 'detalhada' ? 'detalhada' : 'resumida',
        ];
    }

    /**
     * Itens de NFC-e e NF-e emitidas com as mesmas colunas (base de tudo: lista, totais e exportação).
     * valor_venda = produtos + acréscimos − descontos; carga = ICMS + PIS + COFINS (ver INCLUIR_IBS_CBS_NA_CARGA).
     * As tabelas têm collations diferentes: os textos levam COLLATE para o UNION não dar "Illegal mix of collations".
     */
    private function consultaItens(array $f): Builder
    {
        $nome = self::COLUNA_NOME_USUARIO;
        $ibsCbs = self::INCLUIR_IBS_CBS_NA_CARGA ? '+ i.valor_ibs + i.valor_cbs' : '';

        $nfce = DB::table('venda_itens as i')
            ->join('vendas as d', 'd.id', '=', 'i.venda_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
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
                i.custo_unitario as custo_unitario,
                i.valor_custo as custo,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
                (i.valor_icms + i.valor_pis + i.valor_cofins {$ibsCbs}) as carga,
                (i.valor_custo IS NOT NULL) as tem_custo,
                (i.impostos_registrados_em IS NOT NULL) as tem_impostos,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor
            ");

        $nfe = DB::table('nota_fiscal_itens as i')
            ->join('notas_fiscais as d', 'd.id', '=', 'i.nota_fiscal_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
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
                i.custo_unitario as custo_unitario,
                i.valor_custo as custo,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
                (i.valor_icms + i.valor_pis + i.valor_cofins {$ibsCbs}) as carga,
                (i.valor_custo IS NOT NULL) as tem_custo,
                (i.impostos_registrados_em IS NOT NULL) as tem_impostos,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor
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

        // "completo" = tem custo e impostos gravados: só esses itens entram na margem
        $consulta = DB::query()->fromSub($lados, 't')->selectRaw('
            t.*,
            (t.tem_custo = 1 AND t.tem_impostos = 1) as completo,
            CASE WHEN t.tem_custo = 1 AND t.tem_impostos = 1 THEN t.valor_venda - t.custo - t.carga END as lucro,
            CASE WHEN t.tem_custo = 1 AND t.tem_impostos = 1 AND t.valor_venda > 0
                 THEN (t.valor_venda - t.custo - t.carga) / t.valor_venda * 100 END as margem
        ');

        if ($f['busca'] !== '') {
            $termo = $f['busca'];

            match ($f['tipo_busca']) {
                'documento' => $consulta->where('t.numero', $termo),
                'vendedor' => $consulta->where('t.vendedor', 'like', "%{$termo}%"),
                default => $consulta->where(fn ($q) => $q
                    ->where('t.codigo', 'like', "%{$termo}%")
                    ->orWhere('t.descricao', 'like', "%{$termo}%")),
            };
        }

        return $consulta;
    }

    /** Uma linha por venda. Venda com algum item sem custo/impostos mostra custo, lucro e margem em branco. */
    private function consultaDocumentos(Builder $itensBase): Builder
    {
        return DB::query()->fromSub(clone $itensBase, 'r')->selectRaw('
            r.tipo,
            r.documento_id,
            MIN(r.data) as data,
            MIN(r.numero) as numero,
            MIN(r.serie) as serie,
            MIN(r.vendedor) as vendedor,
            COUNT(*) as qtd_itens,
            SUM(1 - r.completo) as itens_sem_dados,
            SUM(r.quantidade) as quantidade,
            SUM(r.valor_venda) as valor_venda,
            SUM(r.custo) as custo,
            SUM(r.carga) as carga,
            SUM(r.icms) as icms,
            SUM(r.pis) as pis,
            SUM(r.cofins) as cofins,
            SUM(r.ibs) as ibs,
            SUM(r.cbs) as cbs,
            MIN(r.completo) as completo,
            CASE WHEN MIN(r.completo) = 1 THEN SUM(r.lucro) END as lucro,
            CASE WHEN MIN(r.completo) = 1 AND SUM(r.valor_venda) > 0 THEN SUM(r.lucro) / SUM(r.valor_venda) * 100 END as margem
        ')->groupBy('r.tipo', 'r.documento_id');
    }

    private function ordenarDocumentos(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('data')->orderBy('numero'),
            'documento' => $q->orderBy('r.tipo')->orderBy('numero'),
            // Venda sem dados completos (margem vazia) vai sempre para o fim
            'menor_margem' => $q->orderByRaw('margem IS NULL')->orderBy('margem')->orderByDesc('data'),
            'maior_margem' => $q->orderByRaw('margem IS NULL')->orderByDesc('margem')->orderByDesc('data'),
            default => $q->orderByDesc('data')->orderByDesc('numero'),
        };
    }

    private function ordenarItens(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('t.data')->orderBy('t.numero')->orderBy('t.item_id'),
            'documento' => $q->orderBy('t.tipo')->orderBy('t.numero')->orderBy('t.item_id'),
            'menor_margem' => $q->orderByRaw('margem IS NULL')->orderBy('margem')->orderBy('t.item_id'),
            'maior_margem' => $q->orderByRaw('margem IS NULL')->orderByDesc('margem')->orderBy('t.item_id'),
            default => $q->orderByDesc('t.data')->orderByDesc('t.numero')->orderBy('t.item_id'),
        };
    }

    /** Itens das vendas que estão na página, agrupados por "tipo-id" (para o modal "Ver itens"). */
    private function itensDosDocumentos(Builder $itensBase, Collection $documentos): Collection
    {
        if ($documentos->isEmpty()) {
            return collect();
        }

        $idsPorTipo = $documentos->groupBy('tipo')->map(fn ($g) => $g->pluck('documento_id')->all());

        $q = clone $itensBase;
        $q->where(function ($w) use ($idsPorTipo) {
            foreach ($idsPorTipo as $tipo => $ids) {
                $w->orWhere(fn ($x) => $x->where('t.tipo', $tipo)->whereIn('t.documento_id', $ids));
            }
        });

        return $q->orderBy('t.item_id')->get()->groupBy(fn ($i) => $i->tipo . '-' . $i->documento_id);
    }

    /** Totais do filtro: venda, custo, carga, lucro e margem só dos itens com custo e impostos gravados. */
    private function totais(Builder $base): object
    {
        $t = DB::query()->fromSub(clone $base, 'r')->selectRaw('
            COUNT(*) as itens,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COALESCE(SUM(r.valor_venda), 0) as venda_geral,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.valor_venda END), 0) as valor_venda,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.custo END), 0) as custo,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.carga END), 0) as carga,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.icms END), 0) as icms,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.pis END), 0) as pis,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.cofins END), 0) as cofins,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.ibs END), 0) as ibs,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.cbs END), 0) as cbs,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.lucro END), 0) as lucro,
            COALESCE(SUM(CASE WHEN r.completo = 1 THEN r.quantidade END), 0) as quantidade,
            COALESCE(SUM(1 - r.completo), 0) as itens_sem_dados,
            COALESCE(SUM(1 - r.tem_custo), 0) as itens_sem_custo,
            COALESCE(SUM(1 - r.tem_impostos), 0) as itens_sem_impostos
        ')->first();

        $t->margem = $t->valor_venda > 0 ? $t->lucro / $t->valor_venda * 100 : null;
        $t->carga_percentual = $t->valor_venda > 0 ? $t->carga / $t->valor_venda * 100 : null;
        $t->incluiu_ibs_cbs = self::INCLUIR_IBS_CBS_NA_CARGA;

        return $t;
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Exportação: ' . ($f['visao'] === 'detalhada' ? 'detalhada (com itens)' : 'resumida (só as vendas)');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];

        if ($f['busca'] !== '') {
            $linhas[] = ['documento' => 'Nº do documento', 'produto' => 'Produto', 'vendedor' => 'Vendedor'][$f['tipo_busca']] . ': ' . $f['busca'];
        }

        return $linhas;
    }
}
