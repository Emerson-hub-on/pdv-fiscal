<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\VendasFormaPagamentoExport;
use App\Http\Controllers\Controller;
use App\Models\FormaPagamento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Forma de Pagamento
 *
 * Faturamento por modalidade de pagamento (dinheiro, cartão de crédito, cartão de débito, PIX, boleto e outros),
 * separando o que foi recebido à vista do que ficou a prazo, para NFC-e e NF-e de saída emitidas.
 *
 *  - NFC-e: cada linha de venda_pagamentos (a venda pode ser paga com mais de uma forma). O troco é descontado do
 *    dinheiro, porque o valor do pagamento em dinheiro é o que o cliente entregou, não o que ficou no caixa.
 *  - NF-e: a forma de pagamento escolhida na nota (formas de pagamento cadastradas, com o meio de pagamento da SEFAZ
 *    e o indicador à vista/a prazo) pelo valor total da nota.
 *
 * Importante: o valor é o que foi pago/cobrado (total da venda ou da nota, com IPI e frete na NF-e). Por isso o total
 * aqui pode ser maior que o "valor líquido" dos relatórios de vendas por produto.
 */
class VendasFormaPagamentoController extends Controller
{
    private const POR_PAGINA = 50;

    /** Modalidade => [rótulo, códigos de meio de pagamento (tPag) que pertencem a ela]. "outros" recebe o que sobrar. */
    private const MODALIDADES = [
        'dinheiro' => ['Dinheiro', ['01']],
        'credito' => ['Cartão de crédito', ['03']],
        'debito' => ['Cartão de débito', ['04']],
        'pix' => ['PIX', ['17', '20']],
        'boleto' => ['Boleto', ['15']],
        'outros' => ['Outros', []],
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $pagamentos = $this->pagamentos($filtros);

        $resumo = $this->resumo($pagamentos);

        // Resumida (padrão): só o resumo por modalidade. Detalhada: a lista de pagamentos, em vez do resumo.
        $lista = $filtros['visao'] === 'detalhada'
            ? $this->ordenar($this->lista($pagamentos, $filtros), $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString()
            : null;

        $modalidades = $this->rotulos();
        $dados = compact('lista', 'resumo', 'filtros', 'modalidades');

        if ($request->ajax()) {
            return view('relatorios.vendas.forma-pagamento._tabela', $dados);
        }

        return view('relatorios.vendas.forma-pagamento.index', $dados);
    }

    // Mesma visão da tela. Resumida: uma linha por modalidade. Detalhada: uma linha por pagamento (venda/nota).
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $pagamentos = $this->pagamentos($filtros);

        $linhas = $filtros['visao'] === 'detalhada'
            ? $this->ordenar($this->lista($pagamentos, $filtros), $filtros['ordenar'])->get()
            : collect($this->resumo($pagamentos)->modalidades);

        return Excel::download(
            new VendasFormaPagamentoExport($linhas, $this->resumo($pagamentos), $filtros['visao']),
            'forma-pagamento-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $pagamentos = $this->pagamentos($filtros);

        $dados = [
            'lista' => $filtros['visao'] === 'detalhada'
                ? $this->ordenar($this->lista($pagamentos, $filtros), $filtros['ordenar'])->get()
                : collect(),
            'resumo' => $this->resumo($pagamentos),
            'filtros' => $filtros,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.forma-pagamento.pdf', $dados)
            ->setPaper('a4', $filtros['visao'] === 'detalhada' ? 'landscape' : 'portrait')
            ->download('forma-pagamento-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function rotulos(): array
    {
        return array_map(fn ($m) => $m[0], self::MODALIDADES);
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        return [
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true)
                ? $request->get('tipo') : 'todos',
            'modalidade' => array_key_exists((string) $request->get('modalidade'), self::MODALIDADES)
                ? $request->get('modalidade') : 'todas',
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            'ordenar' => in_array($request->get('ordenar'), ['recentes', 'antigos', 'maior_valor', 'documento'], true)
                ? $request->get('ordenar') : 'recentes',
            // Define o que aparece na tela e na exportação (PDF/Excel): por modalidade ou cada pagamento
            'visao' => $request->get('visao') === 'detalhada' ? 'detalhada' : 'resumida',
        ];
    }

    /** CASE que traduz o código do meio de pagamento (tPag) na modalidade do relatório. */
    private function casoModalidade(string $coluna): string
    {
        $quando = '';

        foreach (self::MODALIDADES as $chave => [, $codigos]) {
            if ($codigos === []) {
                continue;
            }

            $lista = implode(',', array_map(fn ($c) => "'{$c}'", $codigos));
            $quando .= " WHEN {$coluna} IN ({$lista}) THEN '{$chave}'";
        }

        return "CASE{$quando} ELSE 'outros' END";
    }

    /**
     * Um registro por pagamento (NFC-e + NF-e emitidas) com modalidade, à vista/a prazo e valor.
     * As tabelas têm collations diferentes: os textos levam COLLATE para o UNION não dar "Illegal mix of collations".
     */
    private function pagamentos(array $f): Builder
    {
        $formas = (new FormaPagamento())->getTable();

        $nfce = DB::table('venda_pagamentos as vp')
            ->join('vendas as d', 'd.id', '=', 'vp.venda_id')
            ->where('d.status', 'emitida')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                d.numero_nfce as numero,
                d.serie_nfce COLLATE utf8mb4_unicode_ci as serie,
                vp.id as pagamento_id,
                (CASE vp.forma_pagamento
                    WHEN 'dinheiro' THEN '01' WHEN 'credito' THEN '03' WHEN 'debito' THEN '04' WHEN 'pix' THEN '17'
                    ELSE '99' END) COLLATE utf8mb4_unicode_ci as codigo,
                (CASE vp.forma_pagamento
                    WHEN 'dinheiro' THEN 'Dinheiro' WHEN 'credito' THEN 'Cartão de crédito'
                    WHEN 'debito' THEN 'Cartão de débito' WHEN 'pix' THEN 'PIX'
                    ELSE 'Outros' END) COLLATE utf8mb4_unicode_ci as forma,
                0 as a_prazo,
                (vp.valor - CASE
                    WHEN vp.forma_pagamento = 'dinheiro'
                         AND vp.id = (SELECT MIN(x.id) FROM venda_pagamentos x WHERE x.venda_id = vp.venda_id AND x.forma_pagamento = 'dinheiro')
                    THEN COALESCE(d.troco, 0) ELSE 0 END) as valor
            ");

        $nfe = DB::table('notas_fiscais as d')
            ->leftJoin("{$formas} as fp", 'fp.id', '=', 'd.forma_pagamento_id')
            ->where('d.status', 'emitida')
            ->where('d.tipo_operacao', '<>', 'entrada')
            ->where('d.finalidade', 1) // só NF-e normal: devolução, complementar e ajuste não são venda
            ->selectRaw("
                'NF-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                d.numero as numero,
                d.serie COLLATE utf8mb4_unicode_ci as serie,
                d.id as pagamento_id,
                COALESCE(fp.meio_pagamento COLLATE utf8mb4_unicode_ci, '90') as codigo,
                COALESCE(fp.descricao COLLATE utf8mb4_unicode_ci, 'Sem forma de pagamento') as forma,
                COALESCE(fp.ind_pag, 0) as a_prazo,
                d.valor_total as valor
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

        return DB::query()->fromSub($lados, 't')->selectRaw('t.*, ' . $this->casoModalidade('t.codigo') . ' as modalidade');
    }

    /** Lista da visão detalhada: filtro de modalidade e busca por nº do documento (o resumo por modalidade não usa). */
    private function lista(Builder $pagamentos, array $f): Builder
    {
        $q = DB::query()->fromSub(clone $pagamentos, 'p')->select('p.*');

        if ($f['modalidade'] !== 'todas') {
            $q->where('p.modalidade', $f['modalidade']);
        }

        if ($f['busca'] !== '') {
            $q->where('p.numero', $f['busca']);
        }

        return $q;
    }

    private function ordenar(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('p.data')->orderBy('p.numero')->orderBy('p.pagamento_id'),
            'maior_valor' => $q->orderByDesc('p.valor')->orderByDesc('p.data'),
            'documento' => $q->orderBy('p.tipo')->orderBy('p.numero')->orderBy('p.pagamento_id'),
            default => $q->orderByDesc('p.data')->orderByDesc('p.numero')->orderBy('p.pagamento_id'),
        };
    }

    /** Totais por modalidade (NFC-e x NF-e), à vista x a prazo e total geral, sobre todos os pagamentos do filtro. */
    private function resumo(Builder $pagamentos): object
    {
        $linhas = DB::query()->fromSub(clone $pagamentos, 'p')->selectRaw("
            p.modalidade,
            COUNT(*) as pagamentos,
            COUNT(DISTINCT p.tipo, p.documento_id) as documentos,
            COALESCE(SUM(p.valor), 0) as total,
            COALESCE(SUM(CASE WHEN p.tipo = 'NFC-e' THEN p.valor END), 0) as nfce,
            COALESCE(SUM(CASE WHEN p.tipo = 'NF-e' THEN p.valor END), 0) as nfe,
            COALESCE(SUM(CASE WHEN p.a_prazo = 0 THEN p.valor END), 0) as a_vista,
            COALESCE(SUM(CASE WHEN p.a_prazo = 1 THEN p.valor END), 0) as a_prazo
        ")->groupBy('p.modalidade')->get()->keyBy('modalidade');

        $geral = (float) $linhas->sum('total');

        $modalidades = [];
        foreach (self::MODALIDADES as $chave => [$rotulo]) {
            $l = $linhas->get($chave);
            $total = (float) ($l->total ?? 0);
            $qtd = (int) ($l->pagamentos ?? 0);

            $modalidades[$chave] = (object) [
                'chave' => $chave,
                'rotulo' => $rotulo,
                'pagamentos' => $qtd,
                'total' => $total,
                'nfce' => (float) ($l->nfce ?? 0),
                'nfe' => (float) ($l->nfe ?? 0),
                'a_vista' => (float) ($l->a_vista ?? 0),
                'a_prazo' => (float) ($l->a_prazo ?? 0),
                'percentual' => $geral > 0 ? $total / $geral * 100 : 0,
                'ticket_medio' => $qtd > 0 ? $total / $qtd : 0,
            ];
        }

        $documentos = DB::query()->fromSub(clone $pagamentos, 'p')
            ->selectRaw('COUNT(DISTINCT p.tipo, p.documento_id) as documentos')->value('documentos');

        return (object) [
            'modalidades' => $modalidades,
            'total' => $geral,
            'pagamentos' => (int) $linhas->sum('pagamentos'),
            'documentos' => (int) $documentos,
            'nfce' => (float) $linhas->sum('nfce'),
            'nfe' => (float) $linhas->sum('nfe'),
            'a_vista' => (float) $linhas->sum('a_vista'),
            'a_prazo' => (float) $linhas->sum('a_prazo'),
            'percentual_a_prazo' => $geral > 0 ? (float) $linhas->sum('a_prazo') / $geral * 100 : 0,
        ];
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Exportação: ' . ($f['visao'] === 'detalhada' ? 'detalhada (cada pagamento)' : 'resumida (por modalidade)');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];

        if ($f['modalidade'] !== 'todas') {
            $linhas[] = 'Modalidade: ' . self::MODALIDADES[$f['modalidade']][0];
        }
        if ($f['busca'] !== '') {
            $linhas[] = 'Nº do documento: ' . $f['busca'];
        }

        return $linhas;
    }
}