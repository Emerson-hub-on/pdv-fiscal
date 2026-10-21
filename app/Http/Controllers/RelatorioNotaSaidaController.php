<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioNotaSaidaExport;
use App\Models\CfopSaida;
use App\Models\Cliente;
use App\Models\NotaFiscal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Faturamento > Relatório > Saída.
 *
 * Lista as notas fiscais do módulo "Nota Fiscal - Saída" já autorizadas
 * (emitidas) e/ou canceladas. Rascunhos não entram: não são documento fiscal.
 *
 * Visão "detalhada": cada nota traz os itens logo abaixo.
 * Visão "resumida": só as notas.
 */
class RelatorioNotaSaidaController extends Controller
{
    private const TIPOS_BUSCA = ['numero', 'cliente', 'documento', 'chave', 'produto'];

    private const STATUS = [
        'emitida'   => 'Emitidas',
        'cancelada' => 'Canceladas',
        'todas'     => 'Emitidas e canceladas',
    ];

    private const OPERACOES = ['todas' => 'Todas', 'saida' => 'Saída', 'entrada' => 'Entrada'];

    private const VISOES = ['detalhada' => 'Detalhada (com itens)', 'resumida' => 'Resumida (só as notas)'];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $notas = $this->listagem($base, $filtros)
            ->paginate($filtros['visao'] === 'detalhada' ? 15 : 30)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('faturamento.relatorios.saida._tabela', compact('notas', 'totais', 'filtros'))->render();
        }

        $cfops = CfopSaida::orderBy('codigo')->get(['id', 'codigo']);
        $operadores = DB::table('users')
            ->whereIn('id', DB::table('notas_fiscais')->whereNotNull('operador_id')->select('operador_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('faturamento.relatorios.saida.index', compact('notas', 'totais', 'filtros', 'cfops', 'operadores'));
    }

    /** Exporta TODAS as notas do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $notas = $this->listagem($this->consultaBase($filtros), $filtros)->get();

        return Excel::download(
            new RelatorioNotaSaidaExport($notas, $filtros['visao'] === 'detalhada'),
            'notas-saida-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $notas = $this->listagem($base, $filtros)->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('faturamento.relatorios.saida.pdf', compact('notas', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('notas-saida-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        $colunaData = $f['tipo_data'] === 'cancelamento' ? 'cancelado_em' : 'emitida_em';

        return NotaFiscal::query()
            ->when(
                $f['status'] === 'todas',
                fn ($q) => $q->whereIn('status', ['emitida', 'cancelada']),
                fn ($q) => $q->where('status', $f['status'])
            )
            ->when($f['operacao'] === 'entrada', fn ($q) => $q->where('tipo_operacao', 'entrada'))
            ->when($f['operacao'] === 'saida', fn ($q) => $q->where(
                fn ($w) => $w->whereNull('tipo_operacao')->orWhere('tipo_operacao', '!=', 'entrada')
            ))
            ->when($f['cfop'] !== '', fn ($q) => $q->where('cfop_saida_id', (int) $f['cfop']))
            ->when($f['operador'] !== '', fn ($q) => $q->where('operador_id', (int) $f['operador']))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($f['data_inicio'] !== '', fn ($q) => $q->where($colunaData, '>=', $f['data_inicio'] . ' 00:00:00'))
            ->when($f['data_fim'] !== '', fn ($q) => $q->where($colunaData, '<=', $f['data_fim'] . ' 23:59:59'));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'numero'    => $q->where('numero', $digitos !== '' ? (int) $digitos : -1),
            'cliente'   => $q->whereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%")),
            'documento' => $q->whereHas('cliente', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%')),
            'chave'     => $q->where('chave_acesso', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%'),
            default     => $q->whereHas('itens', fn ($i) => $i->where(
                fn ($w) => $w->where('descricao', 'like', "%{$busca}%")
                    ->orWhereHas('produto', fn ($p) => $p->where('codigo_interno', $busca)->orWhere('codigo_barras', $busca))
            )),
        };
    }

    private function listagem($base, array $f)
    {
        $q = (clone $base)->with(['cliente', 'cfopSaida', 'operador']);

        if ($f['visao'] === 'detalhada') {
            $q->with(['itens.produto', 'itens.variante', 'itens.ncm']);
        }

        match ($f['ordenar']) {
            'antigos' => $q->orderBy('emitida_em')->orderBy('id'),
            'numero'  => $q->orderBy('serie')->orderBy('numero'),
            'valor'   => $q->orderByDesc('valor_total')->orderByDesc('id'),
            'cliente' => $q->orderBy(
                Cliente::select('nome')->whereColumn((new Cliente)->getTable() . '.id', 'notas_fiscais.cliente_id')->limit(1)
            )->orderByDesc('id'),
            default   => $q->orderByDesc('emitida_em')->orderByDesc('id'),
        };

        return $q;
    }

    /** Emitidas e canceladas sempre separadas: cancelada não entra no valor faturado. */
    private function totais($base): object
    {
        return (clone $base)->toBase()->selectRaw("
            COALESCE(SUM(status = 'emitida'), 0) AS emitidas,
            COALESCE(SUM(CASE WHEN status = 'emitida' THEN valor_total ELSE 0 END), 0) AS valor_emitido,
            COALESCE(SUM(status = 'cancelada'), 0) AS canceladas,
            COALESCE(SUM(CASE WHEN status = 'cancelada' THEN valor_total ELSE 0 END), 0) AS valor_cancelado
        ")->first();
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

        // Sem o parâmetro na URL (primeiro acesso): mês atual.
        // Com o parâmetro vazio (operador limpou o campo): sem limite.
        $inicio = $request->has('data_inicio')
            ? $this->data($request->input('data_inicio'))
            : now()->startOfMonth()->startOfDay();
        $fim = $request->has('data_fim')
            ? $this->data($request->input('data_fim'))
            : now()->startOfDay();

        if ($inicio && $fim && $inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $id = fn (string $campo): string => ($n = (int) $request->input($campo)) > 0 ? (string) $n : '';

        return [
            'tipo_busca'  => $escolha('tipo_busca', self::TIPOS_BUSCA, 'numero'),
            'busca'       => trim(is_string($request->input('busca')) ? $request->input('busca') : ''),
            'status'      => $escolha('status', array_keys(self::STATUS), 'emitida'),
            'operacao'    => $escolha('operacao', array_keys(self::OPERACOES), 'todas'),
            'cfop'        => $id('cfop'),
            'operador'    => $id('operador'),
            'tipo_data'   => $escolha('tipo_data', ['emissao', 'cancelamento'], 'emissao'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'numero', 'valor', 'cliente'], 'recentes'),
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
            'Notas: ' . self::STATUS[$f['status']],
            'Exibição: ' . self::VISOES[$f['visao']],
        ];

        if ($f['operacao'] !== 'todas') {
            $linhas[] = 'Operação: ' . self::OPERACOES[$f['operacao']];
        }

        if ($f['cfop'] !== '') {
            $linhas[] = 'CFOP: ' . (CfopSaida::find($f['cfop'])->codigo ?? '—');
        }

        if ($f['operador'] !== '') {
            $linhas[] = 'Operador: ' . (DB::table('users')->where('id', $f['operador'])->value('name') ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'numero' => 'Número', 'cliente' => 'Cliente', 'documento' => 'CPF/CNPJ',
                'chave' => 'Chave de acesso', 'produto' => 'Produto',
            ];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $rotulo = $f['tipo_data'] === 'cancelamento' ? 'Cancelamento' : 'Emissão';
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "{$rotulo}: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
