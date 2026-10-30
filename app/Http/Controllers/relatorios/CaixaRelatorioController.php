<?php

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\Empresa;
use App\Models\Pdv;
use App\Models\User;
use App\Models\Venda;
use App\Services\RelatorioFechamentoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios > Caixa (painel administrativo): aberturas e fechamentos de caixa com os dados do servidor.
 * A 2ª via de cada caixa usa as mesmas páginas de bobina 80 mm do PDV.
 */
class CaixaRelatorioController extends Controller
{
    // ---------------- Abertura ----------------

    public function abertura(Request $request)
    {
        $filtros = $this->filtros($request);

        $consulta = $this->consultaBase($filtros, 'data_abertura')
            ->when($filtros['status'] === 'aberto', fn ($q) => $q->where('status', 'aberto'))
            ->when($filtros['status'] === 'fechado', fn ($q) => $q->where('status', '!=', 'aberto'));

        $totais = [
            'quantidade' => (clone $consulta)->count(),
            'fundo' => (float) (clone $consulta)->sum('valor_abertura'),
            'abertos' => (clone $consulta)->where('status', 'aberto')->count(),
        ];

        $caixas = $consulta->with(['pdv', 'operador'])
            ->orderByDesc('data_abertura')
            ->paginate(30)
            ->withQueryString();

        return view('relatorios.caixa.abertura', $this->opcoes() + compact('caixas', 'filtros', 'totais'));
    }

    public function imprimirAbertura(Request $request, Caixa $caixa)
    {
        $caixa->load(['pdv', 'operador']);

        return view('caixa.relatorio-abertura', $this->dadosBobina($caixa) + [
            'reimpressao' => true,
            'auto' => $request->boolean('auto'),
        ]);
    }

    // ---------------- Fechamento ----------------

    public function fechamento(Request $request)
    {
        $filtros = $this->filtros($request);

        $consulta = $this->consultaBase($filtros, 'data_fechamento')
            ->where('status', '!=', 'aberto')
            ->select('caixas.*')
            ->addSelect($this->subtotais());

        $todos = (clone $consulta)->get()->map(fn ($c) => $this->comConferencia($c));

        $totais = [
            'quantidade' => $todos->count(),
            'total_vendido' => $todos->sum('total_vendido'),
            'descontos' => $todos->sum('total_descontos'),
            'cancelado' => $todos->sum('total_cancelado'),
            'informado' => $todos->sum(fn ($c) => (float) $c->valor_fechamento_informado),
            'esperado' => $todos->sum('dinheiro_esperado'),
            'diferenca' => $todos->sum('diferenca'),
            'com_diferenca' => $todos->filter(fn ($c) => abs($c->diferenca) >= 0.01)->count(),
        ];

        $caixas = $consulta->with(['pdv', 'operador'])
            ->orderByDesc('data_fechamento')
            ->paginate(30)
            ->withQueryString();

        $caixas->getCollection()->transform(fn ($c) => $this->comConferencia($c));

        return view('relatorios.caixa.fechamento', $this->opcoes() + compact('caixas', 'filtros', 'totais'));
    }

    public function imprimirFechamento(Request $request, Caixa $caixa, RelatorioFechamentoService $servico)
    {
        abort_if($caixa->status === 'aberto', 422, 'Este caixa ainda está aberto.');

        $caixa->load(['pdv', 'operador']);

        return view('caixa.relatorio-fechamento', $this->dadosBobina($caixa) + [
            'r' => $servico->dadosServidor($caixa),
            'reimpressao' => true,
            'auto' => $request->boolean('auto'),
        ]);
    }

    // ---------------- Apoio ----------------

    private function filtros(Request $request): array
    {
        $validado = $request->validate([
            'de' => 'nullable|date',
            'ate' => 'nullable|date',
            'pdv_id' => 'nullable|integer',
            'operador_id' => 'nullable|integer',
            'status' => 'nullable|in:aberto,fechado',
        ]);

        $de = isset($validado['de']) ? Carbon::parse($validado['de']) : now()->subDays(6);
        $ate = isset($validado['ate']) ? Carbon::parse($validado['ate']) : now();

        if ($de->gt($ate)) {
            [$de, $ate] = [$ate, $de];
        }

        return [
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'pdv_id' => $validado['pdv_id'] ?? null,
            'operador_id' => $validado['operador_id'] ?? null,
            'status' => $validado['status'] ?? null,
        ];
    }

    private function consultaBase(array $filtros, string $campoData): Builder
    {
        return Caixa::query()
            ->whereBetween($campoData, [$filtros['de'] . ' 00:00:00', $filtros['ate'] . ' 23:59:59'])
            ->when($filtros['pdv_id'], fn ($q, $id) => $q->where('pdv_id', $id))
            ->when($filtros['operador_id'], fn ($q, $id) => $q->where('operador_id', $id));
    }

    // Somas por caixa direto no banco (vendas canceladas ficam de fora; o cancelado sai à parte)
    private function subtotais(): array
    {
        $vendas = fn () => Venda::query()->whereColumn('vendas.caixa_id', 'caixas.id');

        return [
            'total_vendido' => $vendas()->where('status', '!=', 'cancelada')->selectRaw('coalesce(sum(total), 0)'),
            'total_descontos' => $vendas()->where('status', '!=', 'cancelada')->selectRaw('coalesce(sum(desconto), 0)'),
            'total_troco' => $vendas()->where('status', '!=', 'cancelada')->selectRaw('coalesce(sum(troco), 0)'),
            'total_cancelado' => $vendas()->where('status', 'cancelada')->selectRaw('coalesce(sum(total), 0)'),
            'qtd_vendas' => $vendas()->where('status', '!=', 'cancelada')->selectRaw('count(*)'),
            'total_dinheiro' => DB::table('venda_pagamentos')
                ->join('vendas', 'vendas.id', '=', 'venda_pagamentos.venda_id')
                ->whereColumn('vendas.caixa_id', 'caixas.id')
                ->where('vendas.status', '!=', 'cancelada')
                ->where('venda_pagamentos.forma_pagamento', 'dinheiro')
                ->selectRaw('coalesce(sum(venda_pagamentos.valor), 0)'),
        ];
    }

    // Mesma conferência da gaveta do relatório de fechamento: fundo + dinheiro recebido - troco
    private function comConferencia(Caixa $c): Caixa
    {
        $c->dinheiro_esperado = round((float) $c->valor_abertura + (float) $c->total_dinheiro - (float) $c->total_troco, 2);
        $c->diferenca = $c->valor_fechamento_informado !== null
            ? round((float) $c->valor_fechamento_informado - $c->dinheiro_esperado, 2)
            : 0.0;

        return $c;
    }

    private function opcoes(): array
    {
        return [
            'pdvs' => Pdv::orderBy('nome')->get(['id', 'nome']),
            'operadores' => User::whereIn('id', Caixa::select('operador_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name', 'codigo']),
        ];
    }

    // Variáveis que as páginas de bobina (caixa.relatorio-abertura/fechamento) esperam
    private function dadosBobina(Caixa $caixa): array
    {
        // No servidor o número do caixa é o próprio id (no PDV ele vem em id_central)
        $caixa->setAttribute('id_central', $caixa->id);
        $caixa->setAttribute('sync_pendente', false);

        return [
            'caixa' => $caixa,
            'empresa' => Empresa::first(),
            'operador' => $caixa->operador ?? (object) ['name' => 'Operador #' . $caixa->operador_id, 'codigo' => null],
            'maquina' => $caixa->pdv?->maquina ?: '-',
        ];
    }
}
