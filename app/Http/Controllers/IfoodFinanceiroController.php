<?php

namespace App\Http\Controllers;

use App\Exports\IfoodConciliacaoExport;
use App\Models\IfoodConfiguracao;
use App\Services\IfoodFinanceiroService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Comandas → Relatórios → Conciliação iFood: o que o iFood lançou por pedido (venda, comissão, taxas...), o líquido
 * do repasse, os repasses pagos/previstos (com a conta a receber) e as divergências com os pedidos do sistema.
 */
class IfoodFinanceiroController extends Controller
{
    private const SITUACOES = [
        'todas'       => 'Todas',
        'divergente'  => 'Divergentes',
        'sem_evento'  => 'Sem lançamento do iFood',
        'sem_sistema' => 'Fora do sistema',
        'aguardando'  => 'Aguardando o iFood',
        'ok'          => 'Conferidos',
    ];

    public function __construct(private IfoodFinanceiroService $financeiro)
    {
    }

    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $aba = $request->input('aba') === 'repasses' ? 'repasses' : 'pedidos';

        $todos = $this->financeiro->conciliacao($f['data_inicio'], $f['data_fim']);
        $linhas = $this->filtrar($todos, $f);
        $totais = $this->totais($todos);

        $porPagina = 30;
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $pedidos = new LengthAwarePaginator($linhas->forPage($pagina, $porPagina)->values(), $linhas->count(), $porPagina, $pagina, [
            'path' => route('ifood.financeiro.index'), 'query' => $request->query(),
        ]);

        $repasses = $this->financeiro->repasses($f['data_inicio'], now()->addDays(31)->toDateString())
            ->filter(fn ($r) => $r->data_pagamento >= $f['data_inicio'])->values();
        $semPedido = $this->financeiro->eventosSemPedido($f['data_inicio'], $f['data_fim']);

        $cfg = IfoodConfiguracao::query()->first();
        $sincronizadoEm = IfoodFinanceiroService::sincronizadoEm();
        $situacoes = self::SITUACOES;

        return view('comandas.relatorios.ifood-financeiro.index', compact(
            'pedidos', 'totais', 'f', 'aba', 'repasses', 'semPedido', 'cfg', 'sincronizadoEm', 'situacoes'
        ));
    }

    public function sincronizar(Request $request): JsonResponse
    {
        try {
            $r = $this->financeiro->sincronizar();
            Log::info('iFood: conciliação sincronizada', ['usuario' => $request->user()->id] + $r);

            $mensagem = "Sincronizado com o iFood: {$r['eventos']} lançamento(s) e {$r['repasses']} repasse(s)"
                . ($r['contas'] ? ", {$r['contas']} em Contas a Receber." : '.');
            // A tela recarrega e mostra o aviso flutuante padrão do sistema (session 'sucesso')
            session()->flash('sucesso', $mensagem);

            return response()->json(['ok' => true] + $r + ['message' => $mensagem]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function exportarExcel(Request $request)
    {
        $f = $this->filtros($request);
        $linhas = $this->filtrar($this->financeiro->conciliacao($f['data_inicio'], $f['data_fim']), $f);

        return Excel::download(new IfoodConciliacaoExport($linhas), 'conciliacao-ifood-' . now()->format('Ymd-His') . '.xlsx');
    }

    private function filtrar(Collection $linhas, array $f): Collection
    {
        return $linhas
            ->when($f['situacao'] !== 'todas', fn ($c) => $c->where('situacao', $f['situacao']))
            ->when($f['busca'] !== '', fn ($c) => $c->filter(fn ($l) => str_contains($l['pedido'], ltrim($f['busca'], '#'))
                || str_contains(mb_strtolower($l['cliente']), mb_strtolower($f['busca']))
                || str_contains($l['order_id'], $f['busca'])))
            ->values();
    }

    /** Resumo do período (todos os pedidos, sem o filtro de situação). */
    private function totais(Collection $linhas): object
    {
        $soma = fn (string $cat) => round($linhas->sum(fn ($l) => $l['categorias'][$cat]), 2);

        return (object) [
            'pedidos'      => $linhas->where('situacao', '!=', 'sem_sistema')->count(),
            'vendas'       => $soma('venda'),
            'comissoes'    => $soma('comissao'),
            'taxas'        => round($soma('taxa') + $soma('entrega'), 2),
            'promocoes'    => $soma('promocao'),
            'cancelamentos' => $soma('cancelamento'),
            'outros'       => $soma('outros'),
            'liquido'      => round($linhas->sum('liquido'), 2),
            'direto'       => round($linhas->sum('direto'), 2),
            'divergentes'  => $linhas->whereIn('situacao', ['divergente', 'sem_evento', 'sem_sistema'])->count(),
        ];
    }

    private function filtros(Request $request): array
    {
        $data = function (string $campo, Carbon $padrao) use ($request): Carbon {
            try {
                $v = $request->input($campo);

                return is_string($v) && $v !== '' ? Carbon::createFromFormat('Y-m-d', $v)->startOfDay() : $padrao;
            } catch (Throwable) {
                return $padrao;
            }
        };

        $inicio = $data('data_inicio', now()->startOfMonth()->startOfDay());
        $fim = $data('data_fim', now()->startOfDay());
        if ($inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        // Período de no máximo 1 ano na tela
        if ($inicio->lt($fim->copy()->subYear())) {
            $inicio = $fim->copy()->subYear();
        }

        $situacao = $request->input('situacao');

        return [
            'data_inicio' => $inicio->toDateString(),
            'data_fim'    => $fim->toDateString(),
            'situacao'    => is_string($situacao) && isset(self::SITUACOES[$situacao]) ? $situacao : 'todas',
            'busca'       => mb_substr(trim((string) $request->input('busca', '')), 0, 60),
        ];
    }
}
