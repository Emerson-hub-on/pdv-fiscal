<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\VendasDetalhadaExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Detalhada
 * Uma linha por item vendido, juntando NFC-e (venda_itens) e NF-e de saída (nota_fiscal_itens),
 * só de documentos emitidos. Os impostos vêm do que foi gravado na emissão (mesmos valores do XML).
 */
class VendasDetalhadaController extends Controller
{
    // As tabelas têm collations diferentes (unicode_ci x 0900_ai_ci): os textos são unificados com COLLATE
    // para o UNION e o COALESCE não darem "Illegal mix of collations".

    // Coluna com o nome do usuário na tabela users (ajuste se for outra, ex.: "nome")
    private const COLUNA_NOME_USUARIO = 'name';

    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);

        $totais = $this->totais($base);
        $itens = $this->ordenar(clone $base, $filtros['ordenar'])
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $dados = compact('itens', 'totais', 'filtros');

        if ($request->ajax()) {
            return view('relatorios.vendas.detalhada._tabela', $dados);
        }

        return view('relatorios.vendas.detalhada.index', $dados);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);
        $linhas = $this->ordenar(clone $base, $filtros['ordenar'])->get();

        return Excel::download(
            new VendasDetalhadaExport($linhas, $this->totais($base)),
            'vendas-detalhada-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consulta($filtros);

        $dados = [
            'itens' => $this->ordenar(clone $base, $filtros['ordenar'])->get(),
            'totais' => $this->totais($base),
            'filtros' => $filtros,
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.detalhada.pdf', $dados)
            ->setPaper('a4', 'landscape')
            ->download('vendas-detalhada-' . now()->format('Ymd-His') . '.pdf');
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
            'ordenar' => in_array($request->get('ordenar'), ['recentes', 'antigos', 'documento', 'produto'], true)
                ? $request->get('ordenar') : 'recentes',
        ];
    }

    /** Itens de NFC-e e NF-e emitidas, com as mesmas colunas, filtrados. */
    private function consulta(array $f): Builder
    {
        $nome = self::COLUNA_NOME_USUARIO;

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
                COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.desconto, 0) as desconto,
                0 as acrescimo,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                0 as ipi,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
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
                (i.valor_unitario * i.quantidade) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.valor_desconto, 0) as desconto,
                (i.valor_frete + i.valor_outras_despesas) as acrescimo,
                i.valor_icms as icms,
                i.valor_pis as pis,
                i.valor_cofins as cofins,
                i.valor_ipi as ipi,
                i.valor_ibs as ibs,
                i.valor_cbs as cbs,
                (i.impostos_registrados_em IS NOT NULL) as tem_impostos,
                u.{$nome} COLLATE utf8mb4_unicode_ci as vendedor
            ");

        // O período e o tipo entram direto em cada lado: o banco não varre o que será descartado
        foreach ([[$nfce, 'd.emitida_em'], [$nfe, 'd.emitida_em']] as [$q, $coluna]) {
            if ($f['data_inicio'] !== '') {
                $q->where($coluna, '>=', $f['data_inicio'] . ' 00:00:00');
            }
            if ($f['data_fim'] !== '') {
                $q->where($coluna, '<=', $f['data_fim'] . ' 23:59:59');
            }
        }

        $lados = match ($f['tipo']) {
            'nfce' => $nfce,
            'nfe' => $nfe,
            default => $nfce->unionAll($nfe),
        };

        $consulta = DB::query()->fromSub($lados, 't')
            ->selectRaw('t.*, (t.valor_bruto - t.desconto + t.acrescimo) as valor_liquido');

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

    private function ordenar(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('t.data')->orderBy('t.numero')->orderBy('t.item_id'),
            'documento' => $q->orderBy('t.tipo')->orderBy('t.numero')->orderBy('t.item_id'),
            'produto' => $q->orderBy('t.descricao')->orderByDesc('t.data'),
            default => $q->orderByDesc('t.data')->orderByDesc('t.numero')->orderBy('t.item_id'),
        };
    }

    private function totais(Builder $base): object
    {
        $linha = DB::query()->fromSub(clone $base, 'r')->selectRaw('
            COUNT(*) as itens,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COALESCE(SUM(r.quantidade), 0) as quantidade,
            COALESCE(SUM(r.valor_bruto), 0) as valor_bruto,
            COALESCE(SUM(r.desconto), 0) as desconto,
            COALESCE(SUM(r.acrescimo), 0) as acrescimo,
            COALESCE(SUM(r.valor_liquido), 0) as valor_liquido,
            COALESCE(SUM(r.icms), 0) as icms,
            COALESCE(SUM(r.pis), 0) as pis,
            COALESCE(SUM(r.cofins), 0) as cofins,
            COALESCE(SUM(r.ipi), 0) as ipi,
            COALESCE(SUM(r.ibs), 0) as ibs,
            COALESCE(SUM(r.cbs), 0) as cbs,
            COALESCE(SUM(1 - r.tem_impostos), 0) as sem_impostos
        ')->first();

        $linha->impostos = $linha->icms + $linha->pis + $linha->cofins + $linha->ipi + $linha->ibs + $linha->cbs;

        return $linha;
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];

        if ($f['busca'] !== '') {
            $linhas[] = ['documento' => 'Nº do documento', 'produto' => 'Produto', 'vendedor' => 'Vendedor'][$f['tipo_busca']] . ': ' . $f['busca'];
        }

        return $linhas;
    }
}
