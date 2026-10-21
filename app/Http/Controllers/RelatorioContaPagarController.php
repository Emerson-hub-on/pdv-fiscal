<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioContaPagarExport;
use App\Models\ContaPagar;
use App\Models\ContaPagarParcela;
use App\Models\Fornecedor;
use App\Models\TipoContaPagar;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Financeiro > Relatório > Contas a Pagar.
 *
 * Uma linha por conta; as parcelas abrem num modal ("Ver parcelas").
 * Exportação (PDF/Excel): "detalhada" inclui as parcelas; "resumida" só as contas.
 *
 * Período por VENCIMENTO ou PAGAMENTO filtra pelas parcelas (a conta entra se tiver
 * parcela no período) e os cards somam só as parcelas daquele período.
 */
class RelatorioContaPagarController extends Controller
{
    private const TIPOS_BUSCA = ['fornecedor', 'descricao', 'documento', 'cnpj'];

    private const STATUS = [
        'aberta'  => 'Em aberto',
        'vencida' => 'Com parcela vencida',
        'paga'    => 'Pagas',
        'todas'   => 'Todas',
    ];

    private const ORIGENS = ['todas' => 'Todas', 'entrada' => 'Geradas por entrada de nota', 'manual' => 'Lançadas manualmente'];

    private const VISOES = ['detalhada' => 'Com parcelas (detalhada)', 'resumida' => 'Só as contas (resumida)'];

    private const TIPOS_DATA = [
        'vencimento' => 'Vencimento da parcela',
        'pagamento'  => 'Pagamento da parcela',
        'conta'      => 'Data da conta',
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
            return view('financeiro.relatorios.contas-pagar._tabela', compact('contas', 'totais', 'filtros'))->render();
        }

        $tipos = TipoContaPagar::orderBy('descricao')->get(['id', 'descricao']);

        return view('financeiro.relatorios.contas-pagar.index', compact('contas', 'totais', 'filtros', 'tipos'));
    }

    /** Exporta TODAS as contas do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $contas = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioContaPagarExport($contas, $filtros['visao'] === 'detalhada'),
            'contas-pagar-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base, $filtros);
        $contas = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('financeiro.relatorios.contas-pagar.pdf', compact('contas', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('contas-pagar-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** Restrições de data aplicadas às PARCELAS (vencimento ou pagamento). */
    private function restringirParcelas($q, array $f)
    {
        // "Em aberto"/"vencida" + período de vencimento: só interessa parcela ainda não paga
        $soPendentes = in_array($f['status'], ['aberta', 'vencida'], true) && $f['tipo_data'] === 'vencimento';
        $coluna = $f['tipo_data'] === 'pagamento' ? 'data_pagamento' : 'vencimento';

        return $q
            ->when($soPendentes, fn ($x) => $x->whereNull('data_pagamento'))
            ->when($f['tipo_data'] === 'pagamento', fn ($x) => $x->whereNotNull('data_pagamento'))
            ->when($f['data_inicio'] !== '', fn ($x) => $x->whereDate($coluna, '>=', $f['data_inicio']))
            ->when($f['data_fim'] !== '', fn ($x) => $x->whereDate($coluna, '<=', $f['data_fim']));
    }

    private function consultaBase(array $f)
    {
        $temPeriodo = $f['data_inicio'] !== '' || $f['data_fim'] !== '';

        return ContaPagar::query()
            ->when($f['status'] === 'aberta', fn ($q) => $q->where('status', 'aberta'))
            ->when($f['status'] === 'paga', fn ($q) => $q->where('status', 'paga'))
            ->when($f['status'] === 'vencida', fn ($q) => $q->whereHas(
                'parcelas',
                fn ($p) => $p->whereNull('data_pagamento')->whereDate('vencimento', '<', today())
            ))
            ->when($f['origem'] === 'entrada', fn ($q) => $q->whereNotNull('entrada_nota_id'))
            ->when($f['origem'] === 'manual', fn ($q) => $q->whereNull('entrada_nota_id'))
            ->when($f['tipo'] !== '', fn ($q) => $q->where('tipo_conta_pagar_id', (int) $f['tipo']))
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
            'descricao'  => $q->where('descricao', 'like', "%{$busca}%"),
            // aceita o número do documento ou o código interno exibido na listagem (CP-000123)
            'documento'  => $q->where(function ($w) use ($busca) {
                $w->where('numero_documento', 'like', "%{$busca}%");

                if (preg_match('/^CP-?0*(\d+)$/i', $busca, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            }),
            'cnpj'       => $q->whereHas('fornecedor', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%')),
            default      => $q->whereHas('fornecedor', fn ($c) => $c->where('nome', 'like', "%{$busca}%")
                ->orWhere('nome_fantasia', 'like', "%{$busca}%")),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['tipo', 'fornecedor', 'parcelas.formaPagamento']);

        // vencimento mais próximo entre as parcelas ainda não pagas (contas pagas vão para o fim)
        $proximo = '(select min(p.vencimento) from contas_pagar_parcelas p where p.conta_pagar_id = contas_pagar.id and p.data_pagamento is null)';

        match ($f['ordenar']) {
            'recentes'   => $q->orderByDesc('data_conta')->orderByDesc('id'),
            'valor'      => $q->orderByDesc('valor_total')->orderByDesc('id'),
            'fornecedor' => $q->orderBy(
                Fornecedor::select('nome')->whereColumn((new Fornecedor)->getTable() . '.id', 'contas_pagar.fornecedor_id')->limit(1)
            )->orderByDesc('id'),
            default      => $q->orderByRaw("{$proximo} is null")->orderByRaw($proximo)->orderBy('id'),
        };

        return $q;
    }

    /**
     * Cards: somam as PARCELAS das contas filtradas (e, com período de vencimento/pagamento,
     * só as parcelas daquele período).
     */
    private function totais($base, array $f): object
    {
        $hoje = today()->toDateString();
        $temPeriodo = $f['data_inicio'] !== '' || $f['data_fim'] !== '';

        return ContaPagarParcela::query()
            ->whereIn('conta_pagar_id', (clone $base)->select('contas_pagar.id'))
            ->when($temPeriodo && $f['tipo_data'] !== 'conta', fn ($q) => $this->restringirParcelas($q, $f))
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN data_pagamento IS NULL THEN valor ELSE 0 END), 0) AS aberto,
                COALESCE(SUM(CASE WHEN data_pagamento IS NULL AND vencimento < ? THEN valor ELSE 0 END), 0) AS vencido,
                COALESCE(SUM(CASE WHEN data_pagamento IS NULL AND vencimento >= ? THEN valor ELSE 0 END), 0) AS a_vencer,
                COALESCE(SUM(CASE WHEN data_pagamento IS NOT NULL THEN COALESCE(valor_pago, valor) ELSE 0 END), 0) AS pago
            ", [$hoje, $hoje])
            ->first();
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

        // Sem período por padrão: contas a pagar em aberto não podem sumir por causa da data.
        $inicio = $this->data($request->input('data_inicio'));
        $fim = $this->data($request->input('data_fim'));

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'fornecedor'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', array_keys(self::STATUS), 'aberta'),
            'origem'      => $escolha('origem', array_keys(self::ORIGENS), 'todas'),
            'tipo'        => $id('tipo'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'vencimento'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['vencimento', 'recentes', 'valor', 'fornecedor'], 'vencimento'),
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
            $linhas[] = 'Tipo: ' . (TipoContaPagar::find($f['tipo'])->descricao ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'fornecedor' => 'Fornecedor', 'descricao' => 'Descrição',
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
