<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\VendasCanceladasExport;
use App\Http\Controllers\Controller;
use App\Support\OcorrenciaCaixa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Canceladas
 * Uma linha por venda cancelada: NFC-e do caixa (vendas) e NF-e de venda (notas_fiscais de saída, finalidade normal).
 * - Data do cancelamento: NF-e pelo cancelado_em; NFC-e pela ocorrência de cancelamento registrada no caixa
 *   (com quem cancelou e o supervisor que liberou), ou a última atualização da venda quando não houver ocorrência.
 * - Filtros iguais aos outros relatórios de vendas: NFC-e, NF-e ou ambos, período, busca e ordenação.
 */
class VendasCanceladasController extends Controller
{
    private const COLUNA_NOME_USUARIO = 'name';

    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);

        $totais = $this->totais($base);
        $documentos = $this->ordenar(clone $base, $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString();
        $itensPorDocumento = $this->itensDosDocumentos($documentos->getCollection());

        $dados = compact('documentos', 'itensPorDocumento', 'totais', 'filtros');

        if ($request->ajax()) {
            return view('relatorios.vendas.canceladas._tabela', $dados);
        }

        return view('relatorios.vendas.canceladas.index', $dados);
    }

    /** Resumida: uma linha por venda cancelada. Detalhada: uma linha por item, com os dados da venda. */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);
        $documentos = $this->ordenar(clone $base, $filtros['ordenar'])->get();

        return Excel::download(
            new VendasCanceladasExport(
                $documentos,
                $filtros['visao'] === 'detalhada' ? $this->itensDosDocumentos($documentos) : collect(),
                $this->totais($base),
                $filtros['visao']
            ),
            'vendas-canceladas-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);
        $documentos = $this->ordenar(clone $base, $filtros['ordenar'])->get();

        return Pdf::loadView('relatorios.vendas.canceladas.pdf', [
            'documentos' => $documentos,
            'itensPorDocumento' => $filtros['visao'] === 'detalhada' ? $this->itensDosDocumentos($documentos) : collect(),
            'totais' => $this->totais($base),
            'filtros' => $filtros,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ])->setPaper('a4', 'landscape')
            ->download('vendas-canceladas-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        return [
            'tipo_busca' => in_array($request->get('tipo_busca'), ['documento', 'cliente', 'motivo', 'vendedor'], true)
                ? $request->get('tipo_busca') : 'documento',
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true) ? $request->get('tipo') : 'todos',
            // Período pela data do cancelamento ou pela data da venda (emissão)
            'base_data' => $request->get('base_data') === 'emissao' ? 'emissao' : 'cancelamento',
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            'ordenar' => in_array($request->get('ordenar'), ['recentes', 'antigos', 'valor', 'documento'], true)
                ? $request->get('ordenar') : 'recentes',
            'visao' => $request->get('visao') === 'detalhada' ? 'detalhada' : 'resumida',
        ];
    }

    /** Vendas canceladas (NFC-e + NF-e) com as mesmas colunas, já filtradas. */
    private function consulta(array $f): Builder
    {
        $nome = self::COLUNA_NOME_USUARIO;

        // Ocorrência de cancelamento do caixa: quando, quem cancelou e quem liberou
        $ocorrencias = DB::table('caixa_ocorrencias')
            ->where('tipo', OcorrenciaCaixa::CANCELAMENTO_NFCE)
            ->whereNotNull('venda_uuid')
            ->selectRaw('venda_uuid, MIN(ocorrida_em) as cancelado_em, MAX(operador_id) as operador_id,
                         MAX(supervisor_nome) as supervisor_nome, MAX(liberado_operador) as liberado_operador')
            ->groupBy('venda_uuid');

        $nfce = DB::table('vendas as d')
            ->leftJoinSub($ocorrencias, 'oc', 'oc.venda_uuid', '=', 'd.uuid')
            ->leftJoin('clientes as c', 'c.id', '=', 'd.cliente_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'oc.operador_id')
            ->where('d.status', 'cancelada')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.numero_nfce as numero,
                d.serie_nfce COLLATE utf8mb4_unicode_ci as serie,
                COALESCE(d.emitida_em, d.created_at) as emitida_em,
                COALESCE(oc.cancelado_em, d.updated_at) as cancelado_em,
                d.total as valor,
                COALESCE(c.nome COLLATE utf8mb4_unicode_ci, CASE WHEN d.cpf_na_nota IS NOT NULL THEN CONCAT('CPF ', d.cpf_na_nota) END COLLATE utf8mb4_unicode_ci) as cliente,
                d.motivo_cancelamento COLLATE utf8mb4_unicode_ci as motivo,
                NULL as protocolo_cancelamento,
                d.chave_nfe COLLATE utf8mb4_unicode_ci as chave,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor,
                uc.{$nome} COLLATE utf8mb4_unicode_ci as cancelado_por,
                (CASE WHEN oc.liberado_operador = 1 THEN 'Operador liberado' ELSE oc.supervisor_nome END) COLLATE utf8mb4_unicode_ci as autorizado_por,
                (SELECT COUNT(*) FROM venda_itens vi WHERE vi.venda_id = d.id) as qtd_itens
            ");

        $nfe = DB::table('notas_fiscais as d')
            ->leftJoin('clientes as c', 'c.id', '=', 'd.cliente_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
            ->where('d.status', 'cancelada')
            ->where('d.tipo_operacao', '<>', 'entrada')
            ->where('d.finalidade', 1) // só NF-e de venda: devolução, complementar e ajuste ficam de fora
            ->selectRaw("
                'NF-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.numero as numero,
                d.serie COLLATE utf8mb4_unicode_ci as serie,
                d.emitida_em as emitida_em,
                COALESCE(d.cancelado_em, d.updated_at) as cancelado_em,
                d.valor_total as valor,
                c.nome COLLATE utf8mb4_unicode_ci as cliente,
                d.motivo_cancelamento COLLATE utf8mb4_unicode_ci as motivo,
                d.protocolo_cancelamento COLLATE utf8mb4_unicode_ci as protocolo_cancelamento,
                d.chave_acesso COLLATE utf8mb4_unicode_ci as chave,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor,
                NULL as cancelado_por,
                NULL as autorizado_por,
                (SELECT COUNT(*) FROM nota_fiscal_itens ni WHERE ni.nota_fiscal_id = d.id) as qtd_itens
            ");

        $lados = match ($f['tipo']) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        $q = DB::query()->fromSub($lados, 't')->select('t.*');
        $coluna = $f['base_data'] === 'emissao' ? 't.emitida_em' : 't.cancelado_em';

        if ($f['data_inicio'] !== '') {
            $q->where($coluna, '>=', $f['data_inicio'] . ' 00:00:00');
        }
        if ($f['data_fim'] !== '') {
            $q->where($coluna, '<=', $f['data_fim'] . ' 23:59:59');
        }

        if ($f['busca'] !== '') {
            $termo = $f['busca'];
            match ($f['tipo_busca']) {
                'documento' => $q->where('t.numero', preg_replace('/\D/', '', $termo) ?: -1),
                'cliente' => $q->where('t.cliente', 'like', "%{$termo}%"),
                'vendedor' => $q->where('t.vendedor', 'like', "%{$termo}%"),
                default => $q->where('t.motivo', 'like', "%{$termo}%"),
            };
        }

        return $q;
    }

    private function ordenar(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('t.cancelado_em')->orderBy('t.numero'),
            'valor' => $q->orderByDesc('t.valor')->orderByDesc('t.cancelado_em'),
            'documento' => $q->orderBy('t.tipo')->orderBy('t.numero'),
            default => $q->orderByDesc('t.cancelado_em')->orderByDesc('t.numero'),
        };
    }

    private function totais(Builder $base): object
    {
        return DB::query()->fromSub(clone $base, 'r')->selectRaw("
            COUNT(*) as documentos,
            COALESCE(SUM(r.valor), 0) as valor,
            COALESCE(SUM(CASE WHEN r.tipo = 'NFC-e' THEN 1 ELSE 0 END), 0) as qtd_nfce,
            COALESCE(SUM(CASE WHEN r.tipo = 'NFC-e' THEN r.valor ELSE 0 END), 0) as valor_nfce,
            COALESCE(SUM(CASE WHEN r.tipo = 'NF-e' THEN 1 ELSE 0 END), 0) as qtd_nfe,
            COALESCE(SUM(CASE WHEN r.tipo = 'NF-e' THEN r.valor ELSE 0 END), 0) as valor_nfe,
            COALESCE(SUM(r.qtd_itens), 0) as itens
        ")->first();
    }

    /** Itens das vendas listadas, agrupados por "tipo-id" (modal "Ver itens" e exportação detalhada). */
    private function itensDosDocumentos(Collection $documentos): Collection
    {
        if ($documentos->isEmpty()) {
            return collect();
        }

        $ids = $documentos->groupBy('tipo')->map(fn ($g) => $g->pluck('documento_id')->all());
        $itens = collect();

        if (!empty($ids['NFC-e'])) {
            $itens = $itens->concat(DB::table('venda_itens as i')
                ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
                ->whereIn('i.venda_id', $ids['NFC-e'])
                ->orderBy('i.id')
                ->get([
                    DB::raw("'NFC-e' as tipo"), 'i.venda_id as documento_id', 'i.id as item_id',
                    DB::raw('COALESCE(i.codigo_produto, p.codigo_interno) as codigo'),
                    DB::raw('COALESCE(i.descricao, p.nome) as descricao'),
                    'i.quantidade', 'i.preco_unitario as valor_unitario',
                    DB::raw('COALESCE(i.desconto_efetivo, i.desconto, 0) as desconto'),
                    DB::raw('COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) - COALESCE(i.desconto_efetivo, i.desconto, 0) as valor_total'),
                ]));
        }

        if (!empty($ids['NF-e'])) {
            $itens = $itens->concat(DB::table('nota_fiscal_itens as i')
                ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
                ->whereIn('i.nota_fiscal_id', $ids['NF-e'])
                ->orderBy('i.id')
                ->get([
                    DB::raw("'NF-e' as tipo"), 'i.nota_fiscal_id as documento_id', 'i.id as item_id',
                    'p.codigo_interno as codigo',
                    DB::raw('COALESCE(i.descricao, p.nome) as descricao'),
                    'i.quantidade', 'i.valor_unitario',
                    DB::raw('COALESCE(i.desconto_efetivo, i.valor_desconto, 0) as desconto'),
                    'i.valor_total',
                ]));
        }

        return $itens->groupBy(fn ($i) => $i->tipo . '-' . $i->documento_id);
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];
        $linhas[] = ($f['base_data'] === 'emissao' ? 'Venda de ' : 'Cancelada de ')
            . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];
        $linhas[] = 'Exportação: ' . ($f['visao'] === 'detalhada' ? 'detalhada (com itens)' : 'resumida (só as vendas)');

        if ($f['busca'] !== '') {
            $linhas[] = ['documento' => 'Nº do documento', 'cliente' => 'Cliente', 'motivo' => 'Motivo', 'vendedor' => 'Vendedor'][$f['tipo_busca']]
                . ': ' . $f['busca'];
        }

        return $linhas;
    }
}
