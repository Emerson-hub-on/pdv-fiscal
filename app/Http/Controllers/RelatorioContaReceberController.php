<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioContaReceberExport;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\ContaReceberParcela;
use App\Models\TipoContaReceber;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Financeiro > Relatório > Contas a Receber.
 *
 * Uma linha por conta; as parcelas abrem num modal ("Ver parcelas").
 * Exportação (PDF/Excel): "detalhada" inclui as parcelas; "resumida" só as contas.
 *
 * Período por VENCIMENTO ou RECEBIMENTO filtra pelas parcelas (a conta entra se tiver
 * parcela no período) e os cards somam só as parcelas daquele período.
 */
class RelatorioContaReceberController extends Controller
{
    private const TIPOS_BUSCA = ['cliente', 'descricao', 'documento', 'cnpj'];

    private const STATUS = [
        'aberta'   => 'Em aberto',
        'vencida'  => 'Com parcela vencida',
        'recebida' => 'Recebidas',
        'todas'    => 'Todas',
    ];

    private const ORIGENS = ['todas' => 'Todas', 'venda' => 'Geradas por venda', 'manual' => 'Lançadas manualmente'];

    private const VISOES = ['detalhada' => 'Com parcelas (detalhada)', 'resumida' => 'Só as contas (resumida)'];

    private const TIPOS_DATA = [
        'vencimento'   => 'Vencimento da parcela',
        'recebimento'  => 'Recebimento da parcela',
        'conta'        => 'Data da conta',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base, $filtros);
        // Na tela as parcelas vêm junto para abrir no modal sem nova requisição
        $contas = $this->listagem($base, $filtros)
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('financeiro.relatorios.contas-receber._tabela', compact('contas', 'totais', 'filtros'))->render();
        }

        $tipos = TipoContaReceber::orderBy('descricao')->get(['id', 'descricao']);

        return view('financeiro.relatorios.contas-receber.index', compact('contas', 'totais', 'filtros', 'tipos'));
    }

    /** Exporta TODAS as contas do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $contas = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioContaReceberExport($contas, $filtros['visao'] === 'detalhada'),
            'contas-receber-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base, $filtros);
        $contas = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('financeiro.relatorios.contas-receber.pdf', compact('contas', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('contas-receber-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** Restrições de data aplicadas às PARCELAS (vencimento ou recebimento). */
    private function restringirParcelas($q, array $f)
    {
        // "Em aberto"/"vencida" + período de vencimento: só interessa parcela ainda não recebida
        $soPendentes = in_array($f['status'], ['aberta', 'vencida'], true) && $f['tipo_data'] === 'vencimento';
        $coluna = $f['tipo_data'] === 'recebimento' ? 'data_recebimento' : 'vencimento';

        return $q
            ->when($soPendentes, fn ($x) => $x->whereNull('data_recebimento'))
            ->when($f['tipo_data'] === 'recebimento', fn ($x) => $x->whereNotNull('data_recebimento'))
            ->when($f['data_inicio'] !== '', fn ($x) => $x->whereDate($coluna, '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($x) => $x->whereDate($coluna, '<=', $f['data_fim']));
    }

    private function consultaBase(array $f)
    {
        $temPeriodo = $f['data_inicio'] !== '' || $f['data_fim'] !== '';

        return ContaReceber::query()
            ->when($f['status'] === 'aberta', fn ($q) => $q->where('status', 'aberta'))
            ->when($f['status'] === 'recebida', fn ($q) => $q->where('status', 'recebida'))
            ->when($f['status'] === 'vencida', fn ($q) => $q->whereHas(
                'parcelas',
                fn ($p) => $p->whereNull('data_recebimento')->whereDate('vencimento', '<', today())
            ))
            ->when($f['origem'] === 'venda', fn ($q) => $q->whereNotNull('venda_id'))
            ->when($f['origem'] === 'manual', fn ($q) => $q->whereNull('venda_id'))
            ->when($f['tipo'] !== '', fn ($q) => $q->where('tipo_conta_receber_id', (int) $f['tipo']))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($temPeriodo && $f['tipo_data'] === 'conta', fn ($q) => $q
                ->when($f['data_inicio'] !== '', fn ($x) => $x->whereDate('data_conta', '>=', $f['data_inicio']))
                ->when($f['data_fim'] !== '', fn ($x) => $x->whereDate('data_conta', '<=', $f['data_fim'])))
            ->when($temPeriodo && $f['tipo_data'] !== 'conta', fn ($q) => $q->whereHas(
                'parcelas',
                fn ($p) => $this->restringirParcelas($p, $f)
            ));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'descricao' => $q->where('descricao', 'like', "%{$busca}%"),
            // aceita o número do documento ou o código interno exibido na listagem (CR-000123)
            'documento' => $q->where(function ($w) use ($busca) {
                $w->where('numero_documento', 'like', "%{$busca}%");

                if (preg_match('/^CR-?0*(\d+)$/i', $busca, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            }),
            'cnpj'      => $this->temColunaDocumento()
                ? $q->whereHas('cliente', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%'))
                : $q->whereRaw('1 = 0'),
            default     => $q->whereHas('cliente', fn ($c) => $c->where($this->colunaNomeCliente(), 'like', "%{$busca}%")),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['tipo', 'cliente', 'parcelas.formaPagamento']);

        // vencimento mais próximo entre as parcelas ainda não recebidas (contas recebidas vão para o fim)
        $proximo = '(select min(p.vencimento) from contas_receber_parcelas p where p.conta_receber_id = contas_receber.id and p.data_recebimento is null)';

        match ($f['ordenar']) {
            'recentes' => $q->orderByDesc('data_conta')->orderByDesc('id'),
            'valor'    => $q->orderByDesc('valor_total')->orderByDesc('id'),
            'cliente'  => $q->orderBy(
                Cliente::select($this->colunaNomeCliente())->whereColumn((new Cliente)->getTable() . '.id', 'contas_receber.cliente_id')->limit(1)
            )->orderByDesc('id'),
            default    => $q->orderByRaw("{$proximo} is null")->orderByRaw($proximo)->orderBy('id'),
        };

        return $q;
    }

    /**
     * Cards: somam as PARCELAS das contas filtradas (e, com período de vencimento/recebimento,
     * só as parcelas daquele período).
     */
    private function totais($base, array $f): object
    {
        $hoje = today()->toDateString();
        $temPeriodo = $f['data_inicio'] !== '' || $f['data_fim'] !== '';

        return ContaReceberParcela::query()
            ->whereIn('conta_receber_id', (clone $base)->select('contas_receber.id'))
            ->when($temPeriodo && $f['tipo_data'] !== 'conta', fn ($q) => $this->restringirParcelas($q, $f))
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN data_recebimento IS NULL THEN valor ELSE 0 END), 0) AS aberto,
                COALESCE(SUM(CASE WHEN data_recebimento IS NULL AND vencimento < ? THEN valor ELSE 0 END), 0) AS vencido,
                COALESCE(SUM(CASE WHEN data_recebimento IS NULL AND vencimento >= ? THEN valor ELSE 0 END), 0) AS a_vencer,
                COALESCE(SUM(CASE WHEN data_recebimento IS NOT NULL THEN COALESCE(valor_recebido, valor) ELSE 0 END), 0) AS recebido
            ", [$hoje, $hoje])
            ->first();
    }

    /** Coluna de nome da tabela de clientes (nome, razao_social ou nome_fantasia, a primeira que existir). */
    private function colunaNomeCliente(): string
    {
        static $coluna = null;

        return $coluna ??= collect(['nome', 'razao_social', 'nome_fantasia'])
            ->first(fn ($c) => Schema::hasColumn((new Cliente)->getTable(), $c), 'id');
    }

    private function temColunaDocumento(): bool
    {
        static $tem = null;

        return $tem ??= Schema::hasColumn((new Cliente)->getTable(), 'cpf_cnpj');
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

        // Sem período por padrão: contas em aberto não podem sumir por causa da data.
        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'cliente'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', array_keys(self::STATUS), 'aberta'),
            'origem'      => $escolha('origem', array_keys(self::ORIGENS), 'todas'),
            'tipo'        => $id('tipo'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'vencimento'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['vencimento', 'recentes', 'valor', 'cliente'], 'vencimento'),
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
            'Contas: ' . self::STATUS[$f['status']],
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['origem'] !== 'todas') {
            $linhas[] = 'Origem: ' . self::ORIGENS[$f['origem']];
        }

        if ($f['tipo'] !== '') {
            $linhas[] = 'Tipo: ' . (TipoContaReceber::find($f['tipo'])->descricao ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'cliente' => 'Cliente', 'descricao' => 'Descrição',
                'documento' => 'Documento', 'cnpj' => 'CPF/CNPJ',
            ];
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
