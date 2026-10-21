<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioNotaEntradaExport;
use App\Models\EntradaNota;
use App\Models\Fornecedor;
use App\Models\OperacaoEntrada;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Faturamento > Relatório > Entrada.
 *
 * Lista as entradas de nota (módulo "Nota Fiscal - Entrada").
 * Na tela: só as notas; os itens abrem num modal ("Ver itens").
 * Exportação (PDF/Excel): "detalhada" inclui os itens; "resumida" só as notas.
 */
class RelatorioNotaEntradaController extends Controller
{
    private const TIPOS_BUSCA = ['numero', 'fornecedor', 'documento', 'chave', 'produto'];

    private const STATUS = [
        'finalizada' => 'Finalizadas',
        'rascunho'   => 'Rascunhos',
        'todas'      => 'Todas',
    ];

    private const ORIGENS = ['todas' => 'Todas', 'xml' => 'Importadas do XML', 'manual' => 'Digitadas'];

    private const VISOES = ['detalhada' => 'Com itens (detalhada)', 'resumida' => 'Só as notas (resumida)'];

    private const TIPOS_DATA = [
        'entrada'    => 'Data de entrada',
        'emissao'    => 'Data de emissão',
        'finalizacao' => 'Data de finalização',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        // Na tela os itens vêm junto para abrir no modal sem nova requisição
        $entradas = $this->listagem($base, $filtros, true)
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('faturamento.relatorios.entrada._tabela', compact('entradas', 'totais', 'filtros'))->render();
        }

        $operacoes = OperacaoEntrada::orderBy('ordem')->get(['id', 'descricao']);
        $operadores = DB::table('users')
            ->whereIn('id', DB::table('entradas_nota')->whereNotNull('user_id')->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('faturamento.relatorios.entrada.index', compact('entradas', 'totais', 'filtros', 'operacoes', 'operadores'));
    }

    /** Exporta TODAS as entradas do filtro atual (não só a página da tela). */
    public function exportarExcel(Request $request)
    {
        $filtros = $this->filtros($request);
        $entradas = $this->listagem($this->consultaBase($filtros), $filtros, $filtros['visao'] === 'detalhada')->get();

        return Excel::download(
            new RelatorioNotaEntradaExport($entradas, $filtros['visao'] === 'detalhada'),
            'notas-entrada-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportarPdf(Request $request)
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totais = $this->totais($base);
        $entradas = $this->listagem($base, $filtros, $filtros['visao'] === 'detalhada')->get();
        $resumoFiltros = $this->resumoFiltros($filtros);

        return Pdf::loadView('faturamento.relatorios.entrada.pdf', compact('entradas', 'totais', 'filtros', 'resumoFiltros'))
            ->setPaper('a4', 'landscape')
            ->download('notas-entrada-' . now()->format('Ymd-His') . '.pdf');
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    private function consultaBase(array $f)
    {
        $colunaData = match ($f['tipo_data']) {
            'emissao'     => 'data_emissao',
            'finalizacao' => 'finalizada_em',
            default       => 'data_entrada',
        };

        // data_entrada/data_emissao são DATE; finalizada_em é datetime
        $inicio = $f['data_inicio'] !== '' ? $f['data_inicio'] . ($colunaData === 'finalizada_em' ? ' 00:00:00' : '') : '';
        $fim = $f['data_fim'] !== '' ? $f['data_fim'] . ($colunaData === 'finalizada_em' ? ' 23:59:59' : '') : '';

        return EntradaNota::query()
            ->when($f['status'] !== 'todas', fn ($q) => $q->where('status', $f['status']))
            ->when($f['origem'] !== 'todas', fn ($q) => $q->where('tipo_entrada', $f['origem']))
            ->when($f['operacao'] !== '', fn ($q) => $q->where('operacao_entrada_id', (int) $f['operacao']))
            ->when($f['operador'] !== '', fn ($q) => $q->where('user_id', (int) $f['operador']))
            ->when($f['busca'] !== '', fn ($q) => $this->aplicarBusca($q, $f))
            ->when($inicio !== '', fn ($q) => $q->where($colunaData, '>=', $inicio))
            ->when($fim !== '', fn ($q) => $q->where($colunaData, '<=', $fim));
    }

    private function aplicarBusca($q, array $f): void
    {
        $busca = $f['busca'];
        $digitos = preg_replace('/\D/', '', $busca);

        match ($f['tipo_busca']) {
            'numero'     => $q->where('numero', $digitos !== '' ? (int) $digitos : -1),
            'fornecedor' => $q->whereHas('fornecedor', fn ($c) => $c->where('nome', 'like', "%{$busca}%")
                ->orWhere('nome_fantasia', 'like', "%{$busca}%")),
            'documento'  => $q->whereHas('fornecedor', fn ($c) => $c->where('cpf_cnpj', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%')),
            'chave'      => $q->where('chave_acesso', 'like', '%' . ($digitos !== '' ? $digitos : $busca) . '%'),
            default      => $q->whereHas('itens', fn ($i) => $i->where(
                fn ($w) => $w->where('descricao', 'like', "%{$busca}%")
                    ->orWhere('codigo_fornecedor', $busca)
                    ->orWhereHas('produto', fn ($p) => $p->where('codigo_interno', $busca)->orWhere('codigo_barras', $busca))
            )),
        };
    }

    private function listagem($base, array $f, bool $comItens)
    {
        $q = (clone $base)->with(['fornecedor', 'operacao', 'usuario']);

        if ($comItens) {
            $q->with(['itens.produto', 'itens.variante']);
        }

        match ($f['ordenar']) {
            'antigos'    => $q->orderBy('data_entrada')->orderBy('id'),
            'numero'     => $q->orderBy('serie')->orderBy('numero'),
            'valor'      => $q->orderByDesc('valor_total')->orderByDesc('id'),
            'fornecedor' => $q->orderBy(
                Fornecedor::select('nome')->whereColumn((new Fornecedor)->getTable() . '.id', 'entradas_nota.fornecedor_id')->limit(1)
            )->orderByDesc('id'),
            default      => $q->orderByDesc('data_entrada')->orderByDesc('id'),
        };

        return $q;
    }

    /** Finalizadas e rascunhos sempre separados: rascunho ainda não é entrada efetiva. */
    private function totais($base): object
    {
        return (clone $base)->toBase()->selectRaw("
            COALESCE(SUM(status = 'finalizada'), 0) AS finalizadas,
            COALESCE(SUM(CASE WHEN status = 'finalizada' THEN valor_total ELSE 0 END), 0) AS valor_finalizado,
            COALESCE(SUM(status = 'rascunho'), 0) AS rascunhos,
            COALESCE(SUM(CASE WHEN status = 'rascunho' THEN valor_total ELSE 0 END), 0) AS valor_rascunho
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
            'status'      => $escolha('status', array_keys(self::STATUS), 'finalizada'),
            'origem'      => $escolha('origem', array_keys(self::ORIGENS), 'todas'),
            'operacao'    => $id('operacao'),
            'operador'    => $id('operador'),
            'tipo_data'   => $escolha('tipo_data', array_keys(self::TIPOS_DATA), 'entrada'),
            'data_inicio' => $inicio?->toDateString() ?? '',
            'data_fim'    => $fim?->toDateString() ?? '',
            'visao'       => $escolha('visao', array_keys(self::VISOES), 'detalhada'),
            'ordenar'     => $escolha('ordenar', ['recentes', 'antigos', 'numero', 'valor', 'fornecedor'], 'recentes'),
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
            'Exportação: ' . self::VISOES[$f['visao']],
        ];

        if ($f['origem'] !== 'todas') {
            $linhas[] = 'Origem: ' . self::ORIGENS[$f['origem']];
        }

        if ($f['operacao'] !== '') {
            $linhas[] = 'Operação: ' . (OperacaoEntrada::find($f['operacao'])->descricao ?? '—');
        }

        if ($f['operador'] !== '') {
            $linhas[] = 'Usuário: ' . (DB::table('users')->where('id', $f['operador'])->value('name') ?? '—');
        }

        if ($f['busca'] !== '') {
            $rotulos = [
                'numero' => 'Número', 'fornecedor' => 'Fornecedor', 'documento' => 'CPF/CNPJ',
                'chave' => 'Chave de acesso', 'produto' => 'Produto',
            ];
            $linhas[] = "Busca por {$rotulos[$f['tipo_busca']]}: {$f['busca']}";
        }

        if ($f['data_inicio'] !== '' || $f['data_fim'] !== '') {
            $rotulo = match ($f['tipo_data']) {
                'emissao' => 'Emissão', 'finalizacao' => 'Finalização', default => 'Entrada',
            };
            $de = $f['data_inicio'] !== '' ? Carbon::parse($f['data_inicio'])->format('d/m/Y') : 'início';
            $ate = $f['data_fim'] !== '' ? Carbon::parse($f['data_fim'])->format('d/m/Y') : 'hoje';
            $linhas[] = "{$rotulo}: de {$de} até {$ate}";
        }

        return $linhas;
    }
}
