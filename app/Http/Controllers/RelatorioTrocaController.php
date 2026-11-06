<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioTrocaExport;
use App\Models\CreditoLoja;
use App\Models\NotaFiscal;
use App\Models\Venda;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Financeiro > Relatório > Troca.
 *
 * Uma linha por troca = NF-e de ENTRADA de devolução de cliente (origem "troca"), com a venda original
 * (NFC-e ou NF-e), o crédito em loja gerado (vale-troca), quanto dele já foi usado e a saída do item novo.
 * Os itens devolvidos abrem num modal ("Ver itens"). Exportação (PDF/Excel) de TODAS as trocas do filtro:
 * "detalhada" inclui os itens; "resumida" só as trocas.
 */
class RelatorioTrocaController extends Controller
{
    private const TIPOS_BUSCA = ['cliente', 'cnpj', 'entrada', 'original', 'vale'];

    public const DOCUMENTOS = ['todos' => 'NFC-e e NF-e', 'nfce' => 'Só NFC-e (cupom)', 'nfe' => 'Só NF-e'];

    public const SITUACOES = [
        'validas'   => 'Autorizadas e rascunhos',
        'emitida'   => 'Autorizadas',
        'rascunho'  => 'Rascunhos',
        'cancelada' => 'Canceladas',
        'todas'     => 'Todas',
    ];

    private const VISOES = ['detalhada' => 'Com itens (detalhada)', 'resumida' => 'Só as trocas (resumida)'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $trocas = $this->listagem($base, $filtros)->paginate(20)->appends($request->query());
        $this->complementar($trocas->getCollection());

        if ($request->ajax()) {
            return view('financeiro.relatorios.troca._tabela', compact('trocas', 'totais', 'filtros'))->render();
        }

        return view('financeiro.relatorios.troca.index', compact('trocas', 'totais', 'filtros'));
    }

    /** Exporta TODAS as trocas do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $trocas = $this->listagem($this->consultaBase($filtros), $filtros)->get();
        $this->complementar($trocas);

        return Excel::download(
            new RelatorioTrocaExport($trocas, $filtros['visao'] === 'detalhada'),
            'trocas-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $trocas = $this->listagem($base, $filtros)->get();
        $this->complementar($trocas);
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('financeiro.relatorios.troca.pdf', compact('trocas', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('trocas-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        return NotaFiscal::query()
            ->where('origem_tipo', 'troca')
            ->where(fn ($q) => $q->whereNotNull('troca_venda_id')->orWhereNotNull('troca_nota_fiscal_id'))
            ->when($f['documento'] === 'nfce', fn ($q) => $q->whereNotNull('troca_venda_id'))
            ->when($f['documento'] === 'nfe', fn ($q) => $q->whereNotNull('troca_nota_fiscal_id'))
            ->when($f['situacao'] === 'validas', fn ($q) => $q->where('status', '!=', 'cancelada'))
            ->when(in_array($f['situacao'], ['emitida', 'rascunho', 'cancelada'], true), fn ($q) => $q->where('status', $f['situacao']))
            // Data da troca: autorização da NF-e de entrada; rascunho ainda não autorizado, a data de criação
            ->when($f['data_inicio'] !== '', fn ($q) => $q->whereRaw('DATE(COALESCE(emitida_em, created_at)) >= ?', [$f['data_inicio']]))
            ->when($f['data_fim'] !== '', fn ($q) => $q->whereRaw('DATE(COALESCE(emitida_em, created_at)) <= ?', [$f['data_fim']]))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'cnpj'     => $q->whereHas('cliente', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos ?: $busca) . '%')),
            'entrada'  => $q->where('numero', (int) $digitos),
            'original' => $q->where(fn ($w) => $w
                ->whereHas('trocaVenda', fn ($v) => $v->where('numero_nfce', (int) $digitos))
                ->orWhereHas('trocaNotaFiscal', fn ($n) => $n->where('numero', (int) $digitos))),
            'vale'     => $q->whereIn('id', CreditoLoja::where('codigo', 'like', '%' . strtoupper($busca) . '%')->select('nota_fiscal_id')),
            default    => $q->whereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%")),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['cliente', 'cfopSaida', 'trocaVenda', 'trocaNotaFiscal', 'itens.produto']);

        match ($f['ordenar']) {
            'antigas' => $q->orderByRaw('COALESCE(emitida_em, created_at)')->orderBy('id'),
            'valor'   => $q->orderByDesc('valor_total')->orderByDesc('id'),
            default   => $q->orderByRaw('COALESCE(emitida_em, created_at) DESC')->orderByDesc('id'),
        };

        return $q;
    }

    /** Crédito em loja, venda do item novo e usos no caixa de cada troca (sem uma consulta por linha). */
    private function complementar(Collection $trocas): void
    {
        $ids = $trocas->pluck('id');
        $creditos = CreditoLoja::with('movimentos')->whereIn('nota_fiscal_id', $ids)->get()->keyBy('nota_fiscal_id');
        $vendasNovas = NotaFiscal::whereIn('troca_entrada_id', $ids)->where('status', '!=', 'cancelada')->get()->groupBy('troca_entrada_id');
        $usosCaixa = Venda::whereIn('uuid', $creditos->flatMap->movimentos->where('tipo', 'uso')->pluck('venda_uuid')->filter()->unique())
            ->get(['uuid', 'numero_nfce', 'serie_nfce'])->keyBy('uuid');

        foreach ($trocas as $troca) {
            $credito = $creditos[$troca->id] ?? null;
            $usos = $credito?->movimentos ?? collect();
            $usado = round((float) $usos->where('tipo', 'uso')->sum('valor') - (float) $usos->where('tipo', 'estorno')->sum('valor'), 2);

            $saidas = collect($vendasNovas[$troca->id] ?? [])->map(fn ($n) => $n->numero ? "NF-e nº {$n->numero}" : 'NF-e (rascunho)');
            foreach ($usos->where('tipo', 'uso')->whereNotNull('venda_uuid') as $mov) {
                $v = $usosCaixa[$mov->venda_uuid] ?? null;
                $saidas->push($v ? "NFC-e nº {$v->numero_nfce}" : 'Venda no caixa');
            }
            foreach ($usos->where('tipo', 'uso')->whereNotNull('nota_fiscal_id') as $mov) {
                if (!collect($vendasNovas[$troca->id] ?? [])->contains('id', $mov->nota_fiscal_id)) {
                    $saidas->push('NF-e (crédito)');
                }
            }

            $troca->setAttribute('rel_credito', $credito);
            $troca->setAttribute('rel_credito_usado', max(0, $usado));
            $troca->setAttribute('rel_saidas', $saidas->unique()->values());
            $troca->setAttribute('rel_original', $troca->trocaVenda
                ? ['tipo' => 'NFC-e', 'numero' => $troca->trocaVenda->numero_nfce, 'serie' => $troca->trocaVenda->serie_nfce, 'data' => $troca->trocaVenda->created_at, 'chave' => $troca->trocaVenda->chave_nfe]
                : ['tipo' => 'NF-e', 'numero' => $troca->trocaNotaFiscal?->numero, 'serie' => $troca->trocaNotaFiscal?->serie, 'data' => $troca->trocaNotaFiscal?->emitida_em, 'chave' => $troca->trocaNotaFiscal?->chave_acesso]);
        }
    }

    /** Cards: trocas e valores das trocas filtradas (canceladas não somam valor). */
    private function totais($base): object
    {
        $ids = (clone $base)->pluck('id');
        $validas = NotaFiscal::whereIn('id', $ids)->where('status', '!=', 'cancelada');
        $creditos = CreditoLoja::whereIn('nota_fiscal_id', $ids)->where('status', '!=', 'cancelado');

        return (object) [
            'quantidade'     => $ids->count(),
            'valor'          => (float) (clone $validas)->sum('valor_total'),
            'credito_gerado' => (float) (clone $creditos)->sum('valor'),
            'credito_aberto' => (float) (clone $creditos)->sum('saldo'),
            'credito_usado'  => max(0, (float) (clone $creditos)->sum('valor') - (float) (clone $creditos)->sum('saldo')),
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

        // Sem filtro de data informado: mês atual até hoje (como os relatórios de notas)
        $inicio = $request->has('data_inicio') ? $this->data($request->input('data_inicio')) : now()->startOfMonth()->startOfDay();
        $fim = $request->has('data_fim') ? $this->data($request->input('data_fim')) : now()->startOfDay();

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'cliente'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'documento'   => $escolha('documento', array_keys(self::DOCUMENTOS), 'todos'),
            'situacao'    => $escolha('situacao', array_keys(self::SITUACOES), 'validas'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigas', 'valor'], 'recentes'),
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
            'Venda original: ' . self::DOCUMENTOS[$f['documento']],
            'Situação: ' . self::SITUACOES[$f['situacao']],
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['busca'] !== '') {
            $rotulos = ['cliente' => 'Cliente', 'cnpj' => 'CPF/CNPJ', 'entrada' => 'Nº da NF-e de entrada', 'original' => 'Nº da venda original', 'vale' => 'Vale-troca'];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'sem limite';
            $linhas[] = "Data da troca: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
