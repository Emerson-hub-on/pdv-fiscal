<?php

namespace App\Http\Controllers;

use App\Models\ContagemEstoque;
use App\Models\Inventario;
use App\Models\InventarioItem;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventarioController extends Controller
{
    public function index()
    {
        return view('sped.inventarios.index', [
            'inventarios'      => Inventario::with('usuario')->withCount('itens')->orderByDesc('data')->orderByDesc('id')->paginate(15),
            'inicioHistorico'  => InventarioService::inicioDoHistorico(),
            // Sugestão: o último dia do ano anterior (inventário que vai no arquivo de fevereiro)
            // Depois de aplicar uma contagem, o link "calcular inventário" já traz a data dela
            'dataSugerida'     => $this->dataValida(request('data')) ?? now()->subYear()->endOfYear()->format('Y-m-d'),
            'contagens'        => ContagemEstoque::with('usuario')->withCount('itens')->latest()->limit(10)->get(),
            'hoje'             => now()->format('Y-m-d'),
        ]);
    }

    private function dataValida(?string $data): ?string
    {
        return $data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : null;
    }

    public function store(Request $request, InventarioService $service)
    {
        $dados = $request->validate([
            'data'            => ['required', 'date', 'before_or_equal:today'],
            'motivo'          => ['required', Rule::in(array_keys(Inventario::MOTIVOS))],
            'incluir_zerados' => ['nullable', 'boolean'],
            'observacao'      => ['nullable', 'string', 'max:255'],
        ], [
            'data.before_or_equal' => 'O inventário não pode ter data futura.',
        ]);

        $inventario = Inventario::create([
            'data'            => $dados['data'],
            'motivo'          => $dados['motivo'],
            'incluir_zerados' => $request->boolean('incluir_zerados'),
            'observacao'      => $dados['observacao'] ?? null,
            'user_id'         => auth()->id(),
        ]);

        $service->calcular($inventario);

        return redirect()->route('inventarios.show', $inventario)
            ->with('sucesso', 'Inventário calculado. Confira as quantidades e os custos e depois feche o inventário.');
    }

    public function show(Inventario $inventario)
    {
        $inventario->load(['itens' => fn ($q) => $q->with('produto:id,codigo_interno,nome')->orderBy('produto_id'), 'usuario']);

        $inicio = InventarioService::inicioDoHistorico();

        return view('sped.inventarios.show', [
            'inventario'      => $inventario,
            // Data anterior ao histórico: o cálculo não enxerga o que aconteceu entre a data e o início do histórico
            'antesDoHistorico' => $inicio && $inventario->data->copy()->endOfDay()->lt($inicio),
            'inicioHistorico' => $inicio,
            'negativos'       => $inventario->itens->where('quantidade', '<', 0)->count(),
        ]);
    }

    /** Edição de uma linha (quantidade contada ou custo), salva direto pela tabela. */
    public function atualizarItem(Request $request, Inventario $inventario, InventarioItem $item)
    {
        abort_unless((int) $item->inventario_id === (int) $inventario->id, 404);
        abort_unless($inventario->isRascunho(), 403, 'Inventário fechado não pode ser alterado. Reabra-o primeiro.');

        $dados = $request->validate([
            'quantidade'     => ['required', 'numeric', 'min:0', 'max:999999999'],
            'custo_unitario' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'quantidade.min' => 'A quantidade não pode ser negativa.',
        ]);

        $item->update([
            'quantidade'     => round((float) $dados['quantidade'], 3),
            'custo_unitario' => round((float) $dados['custo_unitario'], 6),
            'valor_total'    => round((float) $dados['quantidade'] * (float) $dados['custo_unitario'], 2),
        ]);

        $inventario->recalcularTotal();

        return response()->json([
            'valor_total'      => (float) $item->valor_total,
            'total_inventario' => (float) $inventario->valor_total,
        ]);
    }

    public function recalcular(Inventario $inventario, InventarioService $service)
    {
        abort_unless($inventario->isRascunho(), 403, 'Reabra o inventário antes de recalcular.');

        $service->calcular($inventario);

        return back()->with('sucesso', 'Inventário recalculado a partir do histórico. Edições feitas nas linhas foram descartadas.');
    }

    public function fechar(Inventario $inventario)
    {
        abort_unless($inventario->isRascunho(), 403);

        if ($inventario->itens()->where('quantidade', '<', 0)->exists()) {
            return back()->withErrors(['inventario' => 'Há produtos com quantidade negativa. Corrija a contagem (ou zere a linha) antes de fechar.']);
        }

        if (!$inventario->itens()->where('quantidade', '>', 0)->exists()) {
            return back()->withErrors(['inventario' => 'O inventário não tem nenhum produto com quantidade.']);
        }

        $inventario->update(['status' => Inventario::FECHADO, 'fechado_em' => now()]);

        return back()->with('sucesso', 'Inventário fechado. Ele já pode ser escolhido na geração do SPED.');
    }

    public function reabrir(Inventario $inventario)
    {
        abort_if($inventario->isRascunho(), 403);

        $inventario->update(['status' => Inventario::RASCUNHO, 'fechado_em' => null]);

        return back()->with('sucesso', 'Inventário reaberto. Se ele já foi enviado em algum SPED, gere o arquivo substituto depois de alterar.');
    }

    public function destroy(Inventario $inventario)
    {
        abort_unless($inventario->isRascunho(), 403, 'Só é possível excluir inventário em rascunho.');

        $inventario->delete();

        return redirect()->route('inventarios.index')->with('sucesso', 'Inventário excluído.');
    }
}