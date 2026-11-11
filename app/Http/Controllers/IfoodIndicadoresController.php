<?php

namespace App\Http\Controllers;

use App\Exports\IfoodIndicadoresExport;
use App\Services\IfoodIndicadoresService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Comandas → Relatórios → Indicadores iFood: volume, faturamento, ticket médio, cancelamentos,
 * tempos de aceite, preparo e entrega, horários de pico e os mais vendidos, comparando com o período anterior.
 */
class IfoodIndicadoresController extends Controller
{
    public function __construct(private IfoodIndicadoresService $indicadores)
    {
    }

    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $dados = $this->indicadores->calcular($f['data_inicio'], $f['data_fim'], $f['tipo']);
        $tipos = IfoodIndicadoresService::TIPOS;

        return view('comandas.relatorios.ifood-indicadores.index', compact('f', 'dados', 'tipos'));
    }

    public function exportarExcel(Request $request)
    {
        $f = $this->filtros($request);
        $dados = $this->indicadores->calcular($f['data_inicio'], $f['data_fim'], $f['tipo']);

        return Excel::download(new IfoodIndicadoresExport($dados, $f), 'indicadores-ifood-' . now()->format('Ymd-His') . '.xlsx');
    }

    /** Período (padrão: o mês atual; no máximo 1 ano) e tipo do pedido. */
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
        if ($inicio->lt($fim->copy()->subYear())) {
            $inicio = $fim->copy()->subYear();
        }

        $tipo = (string) $request->input('tipo', 'todos');

        return [
            'data_inicio' => $inicio->toDateString(),
            'data_fim'    => $fim->toDateString(),
            'tipo'        => array_key_exists($tipo, IfoodIndicadoresService::TIPOS) ? $tipo : 'todos',
        ];
    }
}
