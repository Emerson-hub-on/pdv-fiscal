<?php

namespace App\Http\Controllers\Relatorios;

use App\Exports\VendasPorVendedorExport;
use App\Http\Controllers\Controller;
use App\Models\TipoOperador;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Relatórios > Vendas > Vendas por Vendedor
 *
 * Vendedor = operador_id da venda (NFC-e, operador do caixa) ou da nota (NF-e, operador fiscal/administrador).
 * Base: NFC-e e NF-e de saída emitidas, como nos outros relatórios de vendas.
 *
 * O histórico precisa continuar completo: usuário inativo ou com acesso desativado hoje continua aparecendo nas
 * vendas que fez (por isso não se filtra por users.ativo nem por acessos.ativo). O "perfil" mostrado é o dos acessos
 * ATIVOS de hoje (tipos de operador), e o filtro por tipo de operador considera qualquer acesso que a pessoa teve.
 *
 * Visões (a tela mostra só uma tabela por vez):
 *   resumida (padrão) = uma linha por vendedor; detalhada = uma linha por venda (documento).
 */
class VendasPorVendedorController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $totais = $this->totais($itens);

        $registros = $filtros['visao'] === 'detalhada'
            ? $this->ordenarDocumentos($this->documentos($itens), $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString()
            : $this->ordenarVendedores($this->vendedores($itens), $filtros['ordenar'])->paginate(self::POR_PAGINA)->withQueryString();

        $dados = [
            'registros' => $registros,
            'totais' => $totais,
            'filtros' => $filtros,
            'tipos' => $this->tiposOperador(),
        ];

        if ($request->ajax()) {
            return view('relatorios.vendas.vendedor._tabela', $dados);
        }

        return view('relatorios.vendas.vendedor.index', $dados);
    }

    // Mesma visão da tela. Resumida: uma linha por vendedor. Detalhada: uma linha por venda.
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $linhas = $filtros['visao'] === 'detalhada'
            ? $this->ordenarDocumentos($this->documentos($itens), $filtros['ordenar'])->get()
            : $this->ordenarVendedores($this->vendedores($itens), $filtros['ordenar'])->get();

        return Excel::download(
            new VendasPorVendedorExport($linhas, $this->totais($itens), $filtros['visao']),
            'vendas-por-vendedor-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $itens = $this->itens($filtros);

        $dados = [
            'registros' => $filtros['visao'] === 'detalhada'
                ? $this->ordenarDocumentos($this->documentos($itens), $filtros['ordenar'])->get()
                : $this->ordenarVendedores($this->vendedores($itens), $filtros['ordenar'])->get(),
            'totais' => $this->totais($itens),
            'filtros' => $filtros,
            'tipos' => $this->tiposOperador(),
            'resumoFiltros' => $this->resumoFiltros($filtros),
        ];

        return Pdf::loadView('relatorios.vendas.vendedor.pdf', $dados)
            ->setPaper('a4', $filtros['visao'] === 'detalhada' ? 'landscape' : 'portrait')
            ->download('vendas-por-vendedor-' . $filtros['visao'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    /** slug => nome dos tipos de operador cadastrados (caixa, supervisor, fiscal, técnico...) + o administrador. */
    private function tiposOperador(): array
    {
        $tipos = TipoOperador::query()->orderBy('nome')->pluck('nome', 'slug')->all();

        return $tipos + ['administrador' => 'Administrador'];
    }

    private function filtros(Request $request): array
    {
        $primeiraVisita = $request->query() === [];

        return [
            'busca' => trim((string) $request->get('busca', '')),
            'tipo' => in_array($request->get('tipo'), ['todos', 'nfce', 'nfe'], true)
                ? $request->get('tipo') : 'todos',
            'tipo_operador' => array_key_exists((string) $request->get('tipo_operador'), $this->tiposOperador())
                ? $request->get('tipo_operador') : 'todos',
            // Sem nenhum filtro na URL, abre no mês atual para não varrer o histórico inteiro
            'data_inicio' => $primeiraVisita ? now()->startOfMonth()->toDateString() : (string) $request->get('data_inicio', ''),
            'data_fim' => $primeiraVisita ? now()->toDateString() : (string) $request->get('data_fim', ''),
            // "padrao" = maior venda na resumida e mais recentes na detalhada
            'ordenar' => in_array($request->get('ordenar'), ['padrao', 'maior_venda', 'mais_vendas', 'maior_ticket', 'vendedor', 'recentes', 'antigos'], true)
                ? $request->get('ordenar') : 'padrao',
            'visao' => $request->get('visao') === 'detalhada' ? 'detalhada' : 'resumida',
        ];
    }

    /**
     * Itens de NFC-e e NF-e emitidas com o vendedor (operador_id) e os valores.
     * valor_bruto = produtos + acréscimos (frete e outras despesas); valor_liquido = bruto − descontos.
     * As tabelas têm collations diferentes: os textos levam COLLATE para o UNION não dar "Illegal mix of collations".
     */
    private function itens(array $f): Builder
    {
        $nfce = DB::table('venda_itens as i')
            ->join('vendas as d', 'd.id', '=', 'i.venda_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.operador_id')
            ->where('d.status', 'emitida')
            ->selectRaw("
                'NFC-e' COLLATE utf8mb4_unicode_ci as tipo,
                d.id as documento_id,
                d.emitida_em as data,
                d.numero_nfce as numero,
                d.serie_nfce COLLATE utf8mb4_unicode_ci as serie,
                d.operador_id as operador_id,
                u.name COLLATE utf8mb4_unicode_ci as vendedor,
                u.codigo as codigo_vendedor,
                u.ativo as vendedor_ativo,
                u.is_admin as is_admin,
                i.quantidade as quantidade,
                COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.desconto, 0) as desconto,
                0 as acrescimo,
                (COALESCE(i.valor_bruto, i.preco_unitario * i.quantidade) - COALESCE(i.desconto_efetivo, i.desconto, 0)) as valor_liquido
            ");

        $nfe = DB::table('nota_fiscal_itens as i')
            ->join('notas_fiscais as d', 'd.id', '=', 'i.nota_fiscal_id')
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
                d.operador_id as operador_id,
                u.name COLLATE utf8mb4_unicode_ci as vendedor,
                u.codigo as codigo_vendedor,
                u.ativo as vendedor_ativo,
                u.is_admin as is_admin,
                i.quantidade as quantidade,
                (i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) as valor_bruto,
                COALESCE(i.desconto_efetivo, i.valor_desconto, 0) as desconto,
                (i.valor_frete + i.valor_outras_despesas) as acrescimo,
                ((i.valor_unitario * i.quantidade + i.valor_frete + i.valor_outras_despesas) - COALESCE(i.desconto_efetivo, i.valor_desconto, 0)) as valor_liquido
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

        $consulta = DB::query()->fromSub($lados, 't')->select('t.*');

        // Tipo de operador: qualquer acesso que a pessoa tenha (ou tenha tido) desse tipo, ativo ou não.
        // "Administrador" não é um tipo cadastrado: vem da marca is_admin do usuário.
        if ($f['tipo_operador'] === 'administrador') {
            $consulta->where('t.is_admin', 1);
        } elseif ($f['tipo_operador'] !== 'todos') {
            $consulta->whereRaw(
                'EXISTS (SELECT 1 FROM acessos a JOIN tipos_operador tp ON tp.id = a.tipo_operador_id
                         WHERE a.user_id = t.operador_id AND tp.slug = ?)',
                [$f['tipo_operador']]
            );
        }

        // Busca por nome ou código do vendedor
        if ($f['busca'] !== '') {
            $termo = $f['busca'];
            $consulta->where(function ($q) use ($termo) {
                $q->where('t.vendedor', 'like', "%{$termo}%");

                if (ctype_digit($termo)) {
                    $q->orWhere('t.codigo_vendedor', (int) $termo);
                }
            });
        }

        return $consulta;
    }

    /** Uma linha por vendedor (inclui "Sem vendedor", quando o documento não tem operador). */
    private function vendedores(Builder $itens): Builder
    {
        return DB::query()->fromSub(clone $itens, 'r')->selectRaw("
            r.operador_id,
            COALESCE(MIN(r.vendedor), 'Sem vendedor') as vendedor,
            MIN(r.codigo_vendedor) as codigo_vendedor,
            MIN(r.vendedor_ativo) as vendedor_ativo,
            MAX(r.is_admin) as is_admin,
            (SELECT GROUP_CONCAT(DISTINCT tp.nome ORDER BY tp.nome SEPARATOR ', ')
               FROM acessos a JOIN tipos_operador tp ON tp.id = a.tipo_operador_id
              WHERE a.user_id = r.operador_id AND a.ativo = 1 AND tp.ativo = 1) as perfil,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COUNT(*) as itens,
            SUM(r.quantidade) as quantidade,
            SUM(r.valor_bruto) as valor_bruto,
            SUM(r.desconto) as desconto,
            SUM(r.acrescimo) as acrescimo,
            SUM(r.valor_liquido) as valor_liquido,
            CASE WHEN COUNT(DISTINCT r.tipo, r.documento_id) > 0
                 THEN SUM(r.valor_liquido) / COUNT(DISTINCT r.tipo, r.documento_id) END as ticket_medio
        ")->groupBy('r.operador_id');
    }

    /** Uma linha por venda (documento), com o vendedor. */
    private function documentos(Builder $itens): Builder
    {
        return DB::query()->fromSub(clone $itens, 'r')->selectRaw("
            r.tipo,
            r.documento_id,
            MIN(r.data) as data,
            MIN(r.numero) as numero,
            MIN(r.serie) as serie,
            r.operador_id,
            COALESCE(MIN(r.vendedor), 'Sem vendedor') as vendedor,
            MIN(r.codigo_vendedor) as codigo_vendedor,
            COUNT(*) as itens,
            SUM(r.quantidade) as quantidade,
            SUM(r.valor_bruto) as valor_bruto,
            SUM(r.desconto) as desconto,
            SUM(r.acrescimo) as acrescimo,
            SUM(r.valor_liquido) as valor_liquido
        ")->groupBy('r.tipo', 'r.documento_id', 'r.operador_id');
    }

    private function ordenarVendedores(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'mais_vendas' => $q->orderByDesc('documentos')->orderByDesc('valor_liquido'),
            'maior_ticket' => $q->orderByDesc('ticket_medio'),
            'vendedor' => $q->orderBy('vendedor'),
            default => $q->orderByDesc('valor_liquido'),
        };
    }

    private function ordenarDocumentos(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'antigos' => $q->orderBy('data')->orderBy('numero'),
            'maior_venda' => $q->orderByDesc('valor_liquido'),
            'vendedor' => $q->orderBy('vendedor')->orderByDesc('data'),
            default => $q->orderByDesc('data')->orderByDesc('numero'),
        };
    }

    /** Totais do filtro e o melhor vendedor (maior valor líquido). */
    private function totais(Builder $itens): object
    {
        $t = DB::query()->fromSub(clone $itens, 'r')->selectRaw('
            COUNT(*) as itens,
            COUNT(DISTINCT r.tipo, r.documento_id) as documentos,
            COUNT(DISTINCT r.operador_id) as vendedores,
            COALESCE(SUM(r.quantidade), 0) as quantidade,
            COALESCE(SUM(r.valor_bruto), 0) as valor_bruto,
            COALESCE(SUM(r.desconto), 0) as desconto,
            COALESCE(SUM(r.acrescimo), 0) as acrescimo,
            COALESCE(SUM(r.valor_liquido), 0) as valor_liquido
        ')->first();

        $t->ticket_medio = $t->documentos > 0 ? $t->valor_liquido / $t->documentos : 0;
        $t->desconto_percentual = $t->valor_bruto > 0 ? $t->desconto / $t->valor_bruto * 100 : 0;

        $melhor = $this->ordenarVendedores($this->vendedores($itens), 'maior_venda')->first();
        $t->melhor_vendedor = $melhor->vendedor ?? null;
        $t->melhor_valor = (float) ($melhor->valor_liquido ?? 0);

        return $t;
    }

    private function resumoFiltros(array $f): array
    {
        $linhas = [];

        $linhas[] = 'Período: ' . ($f['data_inicio'] !== '' ? date('d/m/Y', strtotime($f['data_inicio'])) : 'início')
            . ' a ' . ($f['data_fim'] !== '' ? date('d/m/Y', strtotime($f['data_fim'])) : 'hoje');
        $linhas[] = 'Visão: ' . ($f['visao'] === 'detalhada' ? 'detalhada (cada venda)' : 'resumida (por vendedor)');
        $linhas[] = 'Documentos: ' . ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e', 'nfe' => 'Só NF-e'][$f['tipo']];

        if ($f['tipo_operador'] !== 'todos') {
            $linhas[] = 'Tipo de operador: ' . ($this->tiposOperador()[$f['tipo_operador']] ?? $f['tipo_operador']);
        }
        if ($f['busca'] !== '') {
            $linhas[] = 'Vendedor: ' . $f['busca'];
        }

        return $linhas;
    }
}
