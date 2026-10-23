<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioOrdemServicoExport;
use App\Models\Cliente;
use App\Models\OrdemServico;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pedidos > Relatório > Ordem de Serviço.
 *
 * Uma linha por OS; o detalhe (equipamento, laudo, serviço executado e peças) abre num modal.
 * Exportação (PDF/Excel): "detalhada" inclui as peças; "resumida" só as OS.
 */
class RelatorioOrdemServicoController extends Controller
{
    /** Status em que a OS ainda está em aberto (não concluída, entregue nem cancelada). */
    private const EM_ANDAMENTO = ['aberta', 'em_analise', 'aguardando_aprovacao', 'aguardando_peca', 'em_andamento'];

    private const TIPOS_BUSCA = ['numero', 'cliente', 'equipamento', 'serie'];

    private const VISOES = ['detalhada' => 'Com peças (detalhada)', 'resumida' => 'Só as OS (resumida)'];

    private const PRIORIDADES = ['todas' => 'Todas', 'normal' => 'Normal', 'urgente' => 'Urgente'];

    private const TIPOS_DATA = [
        'abertura'  => 'Data de abertura',
        'previsao'  => 'Previsão de entrega',
        'conclusao' => 'Data de conclusão',
        'entrega'   => 'Data de entrega',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        // Na tela as peças vêm junto para abrir no modal sem nova requisição
        $ordens = $this->listagem($base, $filtros)
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('pedidos.relatorios.ordem-servico._tabela', compact('ordens', 'totais', 'filtros'))->render();
        }

        $tecnicos = DB::table('users')
            ->whereIn('id', DB::table('ordens_servico')->whereNotNull('tecnico_id')->select('tecnico_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pedidos.relatorios.ordem-servico.index', compact('ordens', 'totais', 'filtros', 'tecnicos'));
    }

    /** Exporta TODAS as OS do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $ordens = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioOrdemServicoExport($ordens, $filtros['visao'] === 'detalhada'),
            'ordens-servico-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $ordens = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('pedidos.relatorios.ordem-servico.pdf', compact('ordens', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('ordens-servico-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        $colunaData = match ($f['tipo_data']) {
            'previsao'  => 'previsao_entrega',
            'conclusao' => 'concluida_em',
            'entrega'   => 'entregue_em',
            default     => 'data_abertura',
        };

        return OrdemServico::query()
            ->when($f['status'] === 'andamento', fn ($q) => $q->whereIn('status', self::EM_ANDAMENTO))
            // mesma regra de OrdemServico::atrasada(): previsão já passou (hoje não conta) e não está concluída/entregue/cancelada
            ->when($f['status'] === 'atrasada', fn ($q) => $q
                ->whereNotIn('status', ['concluida', 'entregue', 'cancelada'])
                ->whereDate('previsao_entrega', '<', today()))
            ->when(array_key_exists($f['status'], OrdemServico::STATUS), fn ($q) => $q->where('status', $f['status']))
            ->when($f['tecnico'] !== '', fn ($q) => $q->where('tecnico_id', (int) $f['tecnico']))
            ->when($f['prioridade'] !== 'todas', fn ($q) => $q->where('prioridade', $f['prioridade']))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($f['data_inicio'] !== '', fn ($q) => $q->whereDate($colunaData, '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($q) => $q->whereDate($colunaData, '<=', $f['data_fim']));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'numero'      => $q->where('numero', $digitos !== '' ? (int) ltrim($digitos, '0') : -1),
            'equipamento' => $q->where(fn ($w) => $w->where('equipamento', 'like', "%{$busca}%")
                ->orWhere('marca', 'like', "%{$busca}%")
                ->orWhere('modelo', 'like', "%{$busca}%")),
            'serie'       => $q->where('numero_serie', 'like', "%{$busca}%"),
            // cliente cadastrado ou avulso (nome digitado na própria OS)
            default       => $q->where(fn ($w) => $w->where('cliente_nome', 'like', "%{$busca}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"))),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['cliente', 'tecnico', 'itens']);

        $tabelaCliente = (new Cliente)->getTable();

        match ($f['ordenar']) {
            'antigos'  => $q->orderBy('numero'),
            'valor'    => $q->orderByDesc('valor_total')->orderByDesc('numero'),
            'previsao' => $q->orderByRaw('previsao_entrega is null')->orderBy('previsao_entrega')->orderByDesc('numero'),
            'cliente'  => $q->orderByRaw("COALESCE((select c.nome from {$tabelaCliente} c where c.id = ordens_servico.cliente_id), cliente_nome)")
                ->orderByDesc('numero'),
            default    => $q->orderByDesc('numero'),
        };

        return $q;
    }

    /** O valor das OS não inclui as canceladas. */
    private function totais($base): object
    {
        $andamento = "'" . implode("','", self::EM_ANDAMENTO) . "'";

        return (clone $base)->toBase()->selectRaw("
            COALESCE(SUM(status IN ({$andamento})), 0) AS em_andamento,
            COALESCE(SUM(status NOT IN ('concluida', 'entregue', 'cancelada') AND previsao_entrega < ?), 0) AS atrasadas,
            COALESCE(SUM(status IN ('concluida', 'entregue')), 0) AS concluidas,
            COALESCE(SUM(status = 'cancelada'), 0) AS canceladas,
            COALESCE(SUM(CASE WHEN status <> 'cancelada' THEN valor_total ELSE 0 END), 0) AS valor_ordens
        ", [today()->toDateString()])->first();
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

        // Sem período por padrão: OS em aberto não podem sumir por causa da data.
        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        $statusValidos = array_merge(['todos', 'andamento', 'atrasada'], array_keys(OrdemServico::STATUS));

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'numero'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', $statusValidos, 'todos'),
            'tecnico'     => $id('tecnico'),
            'prioridade'  => $escolha('prioridade', array_keys(self::PRIORIDADES), 'todas'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'abertura'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'previsao', 'valor', 'cliente'], 'recentes'),
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
        $rotuloStatus = match ($f['status']) {
            'todos'     => 'Todas',
            'andamento' => 'Em andamento (não concluídas)',
            'atrasada'  => 'Atrasadas',
            default     => OrdemServico::STATUS[$f['status']] ?? $f['status'],
        };

        $linhas = [
            'OS: ' . $rotuloStatus,
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['tecnico'] !== '') {
            $linhas[] = 'Técnico: ' . (DB::table('users')->where('id', $f['tecnico'])->value('name') ?? '—');
        }

        if ($f['prioridade'] !== 'todas') {
            $linhas[] = 'Prioridade: ' . self::PRIORIDADES[$f['prioridade']];
        }

        if ($f['busca'] !== '') {
            $rotulos = ['numero' => 'Número', 'cliente' => 'Cliente', 'equipamento' => 'Equipamento', 'serie' => 'Nº de série'];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'sem limite';
            $linhas[] = self::TIPOS_DATA[$f['tipo_data']] . ": de {$de} até {$ate}";
        }

        return $linhas;
    }
}
