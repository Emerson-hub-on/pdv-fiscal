<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioPedidoComandaExport;
use App\Models\Comanda;
use App\Models\ComandaPedido;
use App\Models\IfoodPedido;
use App\Services\VendaRestauranteService;
use App\Support\FormasCaixa;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Comandas > Relatórios > Pedidos.
 *
 * Uma linha por venda do restaurante: a conta de uma mesa (todos os pedidos dela) ou um pedido do iFood.
 * Os itens abrem num modal ("Ver itens"). Exportação (PDF/Excel): "detalhada" inclui os itens; "resumida" só as vendas.
 * As duas origens ficam em tabelas diferentes, então a lista é montada em PHP e paginada em memória.
 */
class RelatorioPedidoComandaController extends Controller
{
    private const TIPOS_BUSCA = ['numero', 'cliente', 'produto'];

    private const ORIGENS = ['todas' => 'Mesas e iFood', 'mesa' => 'Só mesas', 'ifood' => 'Só iFood'];

    // concluido = conta paga / pedido do iFood concluído; andamento = conta aberta / pedido em preparo ou a caminho
    private const STATUS = [
        'todos'     => 'Todos',
        'concluido' => 'Concluídos',
        'andamento' => 'Em andamento',
        'cancelado' => 'Cancelados',
    ];

    private const EMISSOES = ['todas' => 'Todas', 'emitidas' => 'Enviadas ao caixa (NFC-e)', 'nao_emitidas' => 'Sem NFC-e'];

    private const VISOES = ['detalhada' => 'Com itens (detalhada)', 'resumida' => 'Só os pedidos (resumida)'];

    private const ORDENS = ['recentes', 'antigos', 'valor', 'origem'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $linhas = $this->linhas($filtros);
        $totais = $this->totais($linhas);

        $porPagina = 20;
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $pedidos = new LengthAwarePaginator(
            $linhas->forPage($pagina, $porPagina)->values(),
            $linhas->count(),
            $porPagina,
            $pagina,
            ['path' => route('comandas.relatorios.pedidos.index'), 'query' => $request->query()]
        );

        if ($request->ajax()) {
            return view('comandas.relatorios.pedidos._tabela', compact('pedidos', 'totais', 'filtros'))->render();
        }

        $meios = FormasCaixa::MEIOS;

        return view('comandas.relatorios.pedidos.index', compact('pedidos', 'totais', 'filtros', 'meios'));
    }

    /** Exporta TODOS os pedidos do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);

        return Excel::download(
            new RelatorioPedidoComandaExport($this->linhas($filtros), $filtros['visao'] === 'detalhada'),
            'pedidos-restaurante-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $pedidos = $this->linhas($filtros);
        $totais = $this->totais($pedidos);
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('comandas.relatorios.pedidos.pdf', compact('pedidos', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('pedidos-restaurante-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** Linhas das duas origens já filtradas e ordenadas. */
    private function linhas(array $f): Collection
    {
        $linhas = collect();

        if ($f['origem'] !== 'ifood') {
            $linhas = $linhas->concat($this->contasDeMesa($f)->map(fn (Comanda $c) => $this->linhaMesa($c)));
        }

        if ($f['origem'] !== 'mesa') {
            $linhas = $linhas->concat(
                $this->pedidosIfood($f)
                    ->map(fn (ComandaPedido $p) => $this->linhaIfood($p))
                    // As formas do iFood ficam num JSON: o filtro de meio é feito aqui
                    ->when($f['meio'] !== '', fn ($c) => $c->filter(fn ($l) => in_array($f['meio'], $l['meios'], true)))
            );
        }

        $linhas = $this->comNfce($linhas->values());

        if ($f['emissao'] !== 'todas') {
            $linhas = $linhas->filter(fn ($l) => ($l['venda_uuid'] !== null) === ($f['emissao'] === 'emitidas'))->values();
        }

        return match ($f['ordenar']) {
            'antigos' => $linhas->sortBy(fn ($l) => $l['momento']?->getTimestamp() ?? 0)->values(),
            'valor'   => $linhas->sortByDesc('total')->values(),
            'origem'  => $linhas->sortBy([['origem', 'desc'], fn ($a, $b) => ($b['momento']?->getTimestamp() ?? 0) <=> ($a['momento']?->getTimestamp() ?? 0)])->values(),
            default   => $linhas->sortByDesc(fn ($l) => $l['momento']?->getTimestamp() ?? 0)->values(),
        };
    }

    private function contasDeMesa(array $f): Collection
    {
        $status = ['concluido' => [Comanda::PAGA], 'andamento' => [Comanda::ABERTA], 'cancelado' => [Comanda::CANCELADA]][$f['status']] ?? null;
        $momento = 'DATE(COALESCE(comandas.fechada_em, comandas.aberta_em))';
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        return Comanda::with(['mesa', 'pagamentos', 'fechadaPor', 'pedidos.itens', 'pedidos.garcom'])
            ->when($status, fn ($q) => $q->whereIn('status', $status))
            ->when($f['data_inicio'] !== '', fn ($q) => $q->whereRaw("{$momento} >= ?", [$f['data_inicio']]))
            ->when($f['data_fim'] !== '', fn ($q) => $q->whereRaw("{$momento} <= ?", [$f['data_fim']]))
            ->when($f['meio'] !== '', fn ($q) => $q->whereHas('pagamentos', fn ($p) => $p->where('meio_pagamento', $f['meio'])))
            ->when($busca !== '', fn ($q) => match ($f['tipo_busca']) {
                'numero'  => $q->whereHas('mesa', fn ($m) => $m->where('numero', $digitos !== '' ? (int) $digitos : -1)),
                'cliente' => $q->whereHas('pedidos', fn ($p) => $p->where('cliente_nome', 'like', "%{$busca}%")),
                default   => $q->whereHas('pedidos.itens', fn ($i) => $i->where('nome', 'like', "%{$busca}%")),
            })
            ->get();
    }

    private function pedidosIfood(array $f): Collection
    {
        $status = [
            'concluido' => fn ($q) => $q->where('status', ComandaPedido::CONCLUIDO),
            'cancelado' => fn ($q) => $q->where('status', ComandaPedido::CANCELADO),
            'andamento' => fn ($q) => $q->whereIn('status', ComandaPedido::STATUS_ABERTOS),
        ][$f['status']] ?? null;
        $busca = $f['busca'];

        return ComandaPedido::with(['itens', 'ifood'])
            ->where('origem', 'ifood')
            ->when($status, $status)
            ->when($f['data_inicio'] !== '', fn ($q) => $q->whereDate('enviado_em', '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($q) => $q->whereDate('enviado_em', '<=', $f['data_fim']))
            ->when($busca !== '', fn ($q) => match ($f['tipo_busca']) {
                'numero'  => $q->where(fn ($w) => $w->whereHas('ifood', fn ($i) => $i->where('display_id', 'like', '%' . ltrim($busca, '#') . '%'))
                    ->orWhere('id', ctype_digit($busca) ? (int) $busca : -1)),
                'cliente' => $q->where(fn ($w) => $w->where('cliente_nome', 'like', "%{$busca}%")
                    ->orWhereHas('ifood', fn ($i) => $i->where('cliente_nome', 'like', "%{$busca}%"))),
                default   => $q->whereHas('itens', fn ($i) => $i->where('nome', 'like', "%{$busca}%")),
            })
            ->get();
    }

    private function linhaMesa(Comanda $conta): array
    {
        // Conta cancelada: todos os pedidos foram cancelados, então mostra o que foi pedido
        $pedidos = $conta->status === Comanda::CANCELADA
            ? $conta->pedidos
            : $conta->pedidos->where('status', '!=', ComandaPedido::CANCELADO);

        return [
            'chave'        => 'mesa-' . $conta->id,
            'origem'       => VendaRestauranteService::MESA,
            'pedido'       => $conta->mesa ? 'Mesa ' . $conta->mesa->numero : 'Mesa',
            'detalhe'      => $conta->mesa?->nome ?: ($pedidos->count() . ' ' . ($pedidos->count() === 1 ? 'pedido' : 'pedidos')),
            'momento'      => $conta->fechada_em ?? $conta->aberta_em,
            'aberto_em'    => $conta->aberta_em,
            'cliente'      => $pedidos->pluck('cliente_nome')->filter()->unique()->implode(', '),
            'atendimento'  => $pedidos->map(fn ($p) => $p->garcom?->name)->filter()->unique()->implode(', ') ?: ($conta->fechadaPor?->name ?? ''),
            'pagamento'    => $conta->pagamentos->pluck('descricao')->filter()->unique()->implode(', '),
            'pagamentos'   => $conta->pagamentos->map(fn ($p) => ['descricao' => $p->descricao, 'valor' => (float) $p->valor])->values()->all(),
            'meios'        => $conta->pagamentos->pluck('meio_pagamento')->filter()->unique()->values()->all(),
            'status'       => match ($conta->status) {
                Comanda::PAGA      => 'concluido',
                Comanda::CANCELADA => 'cancelado',
                default            => 'andamento',
            },
            'status_rotulo' => match ($conta->status) {
                Comanda::PAGA      => 'Paga',
                Comanda::CANCELADA => 'Cancelada',
                default            => 'Em aberto',
            },
            'subtotal'     => (float) $conta->total,
            'taxa_entrega' => 0.0,
            'descontos'    => 0.0,
            'total'        => (float) $conta->total,
            'troco'        => (float) $conta->troco,
            'observacao'   => '',
            'venda_uuid'   => $conta->venda_uuid,
            'itens'        => $this->itens($pedidos->flatMap->itens),
        ];
    }

    private function linhaIfood(ComandaPedido $pedido): array
    {
        $ifood = $pedido->ifood;
        $situacao = match (true) {
            $pedido->status === ComandaPedido::CONCLUIDO => ['concluido', 'Concluído'],
            $pedido->status === ComandaPedido::CANCELADO => ['cancelado', 'Cancelado'],
            default => ['andamento', ComandaPedido::STATUS[$pedido->status]['rotulo'] ?? 'Em andamento'],
        };
        $pagamentos = $ifood ? collect($ifood->paraTela()['pagamentos']) : collect();

        return [
            'chave'        => 'ifood-' . $pedido->id,
            'origem'       => VendaRestauranteService::IFOOD,
            'pedido'       => 'iFood #' . ($ifood?->display_id ?? $pedido->id),
            'detalhe'      => $ifood ? (IfoodPedido::TIPOS[$ifood->tipo] ?? $ifood->tipo) : '',
            'momento'      => $pedido->enviado_em,
            'aberto_em'    => $pedido->enviado_em,
            'cliente'      => (string) ($ifood?->cliente_nome ?: $pedido->cliente_nome),
            'atendimento'  => $ifood ? (IfoodPedido::TIPOS[$ifood->tipo] ?? (string) $ifood->tipo) : '',
            'pagamento'    => $pagamentos->pluck('descricao')->implode(', '),
            'pagamentos'   => $pagamentos->map(fn ($p) => ['descricao' => $p['descricao'], 'valor' => (float) $p['valor']])->values()->all(),
            'meios'        => collect($ifood?->pagamentos ?? [])
                ->flatMap(fn ($p) => VendaRestauranteService::MEIOS_IFOOD[$p['metodo'] ?? 'OTHER'] ?? ['99'])
                ->unique()->values()->all(),
            'status'       => $situacao[0],
            'status_rotulo' => $situacao[1],
            'subtotal'     => (float) $pedido->total,
            'taxa_entrega' => (float) ($ifood?->taxa_entrega ?? 0),
            'descontos'    => (float) ($ifood?->descontos ?? 0),
            // Total = itens (o mesmo valor da NFC-e); a taxa de entrega e os descontos do iFood aparecem à parte
            'total'        => (float) $pedido->total,
            'troco'        => 0.0,
            'observacao'   => (string) ($ifood?->observacoes ?? ''),
            'venda_uuid'   => $pedido->venda_uuid,
            'itens'        => $this->itens($pedido->itens),
        ];
    }

    private function itens(Collection $itens): array
    {
        return $itens->map(fn ($i) => [
            'nome'           => (string) $i->nome,
            'quantidade'     => (int) $i->quantidade,
            'preco_unitario' => (float) $i->preco_unitario,
            'subtotal'       => (float) $i->subtotal,
            'observacao'     => (string) ($i->observacao ?? ''),
        ])->values()->all();
    }

    /** Situação da NFC-e de cada linha pela venda reservada no caixa (venda_uuid). */
    private function comNfce(Collection $linhas): Collection
    {
        $uuids = $linhas->pluck('venda_uuid')->filter()->unique()->values();
        $vendas = $uuids->isEmpty()
            ? collect()
            : $uuids->chunk(500)->flatMap(fn ($lote) => DB::table('vendas')->whereIn('uuid', $lote->all())->get(['uuid', 'status', 'numero_nfce']))->keyBy('uuid');

        return $linhas->map(function (array $l) use ($vendas) {
            $venda = $l['venda_uuid'] ? $vendas->get($l['venda_uuid']) : null;
            $numero = $venda?->numero_nfce ? 'nº ' . str_pad((string) $venda->numero_nfce, 6, '0', STR_PAD_LEFT) : '';

            [$l['nfce'], $l['nfce_situacao']] = match (true) {
                !$l['venda_uuid']                     => ['Sem NFC-e', 'sem'],
                !$venda                               => ['Aguardando o caixa sincronizar', 'aguardando'],
                $venda->status === 'emitida'          => [trim("Emitida {$numero}"), 'emitida'],
                $venda->status === 'contingencia'     => [trim("Contingência {$numero}"), 'contingencia'],
                $venda->status === 'cancelada'        => [trim("Cancelada {$numero}"), 'cancelada'],
                default                               => [trim("Pendente {$numero}"), 'pendente'],
            };

            return $l;
        });
    }

    /** Cancelados sempre separados: não entram no valor vendido. */
    private function totais(Collection $linhas): object
    {
        $concluidos = $linhas->where('status', 'concluido');
        $cancelados = $linhas->where('status', 'cancelado');

        return (object) [
            'concluidos'      => $concluidos->count(),
            'valor_vendido'   => round($concluidos->sum('total'), 2),
            'andamento'       => $linhas->where('status', 'andamento')->count(),
            'mesas'           => $concluidos->where('origem', 'mesa')->count(),
            'valor_mesas'     => round($concluidos->where('origem', 'mesa')->sum('total'), 2),
            'ifood'           => $concluidos->where('origem', 'ifood')->count(),
            'valor_ifood'     => round($concluidos->where('origem', 'ifood')->sum('total'), 2),
            'cancelados'      => $cancelados->count(),
            'valor_cancelado' => round($cancelados->sum('total'), 2),
        ];
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    /** Lê e saneia os filtros; valor inválido volta ao padrão em vez de gerar erro. */
    private function filtros(Request $request): array
    {
        $escolha = function (string $campo, array $validos, string $padrao) use ($request): string {
            $valor = $request->input($campo);

            return is_string($valor) && in_array($valor, $validos, true) ? $valor : $padrao;
        };

        // Sem o parâmetro na URL (primeiro acesso): mês atual. Com o parâmetro vazio: sem limite.
        $inicio = $request->has('data_inicio')
            ? $this->data($request->input('data_inicio'))
            : now()->startOfMonth()->startOfDay();
        $fim = $request->has('data_fim')
            ? $this->data($request->input('data_fim'))
            : now()->startOfDay();

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'numero'),
            'busca'       => mb_substr(trim(is_string($request->input('busca')) ? $request->input('busca') : ''), 0, 100),
            'origem'      => $escolha('origem', array_keys(self::ORIGENS), 'todas'),
            'status'      => $escolha('status', array_keys(self::STATUS), 'todos'),
            'meio'        => $escolha('meio', array_map('strval', array_keys(FormasCaixa::MEIOS)), ''),
            'emissao'     => $escolha('emissao', array_keys(self::EMISSOES), 'todas'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'resumida'),
            'ordenar'     => $escolha('ordenar', self::ORDENS, 'recentes'),
        ];
    }

    private function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Linhas de texto com os filtros aplicados (cabeçalho do PDF). */
    private function resumoFiltros(array $f): array
    {
        $linhas = [
            'Origem: ' . self::ORIGENS[$f['origem']],
            'Pedidos: ' . self::STATUS[$f['status']],
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['meio'] !== '') {
            $linhas[] = 'Meio de pagamento: ' . (FormasCaixa::MEIOS[$f['meio']] ?? $f['meio']);
        }

        if ($f['emissao'] !== 'todas') {
            $linhas[] = 'NFC-e: ' . self::EMISSOES[$f['emissao']];
        }

        if ($f['busca'] !== '') {
            $rotulos = ['numero' => 'Número', 'cliente' => 'Cliente', 'produto' => 'Produto'];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "Período: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
