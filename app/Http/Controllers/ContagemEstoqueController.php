<?php

namespace App\Http\Controllers;

use App\Exports\ContagemEstoqueExport;
use App\Imports\ContagemEstoqueImport;
use App\Models\ContagemEstoque;
use App\Services\ContagemEstoqueService;
use App\Services\EstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ContagemEstoqueController extends Controller
{
    /** Planilha para contar: itens com o saldo do sistema na data (ou sem o saldo, na contagem "cega"). */
    public function planilha(Request $request, ContagemEstoqueService $service)
    {
        $dados = $request->validate([
            'data'      => ['required', 'date', 'before_or_equal:today'],
            'com_saldo' => ['nullable', 'boolean'],
        ], [
            'data.before_or_equal' => 'A contagem não pode ter data futura.',
        ]);

        $data = Carbon::parse($dados['data']);
        $comSaldo = $request->boolean('com_saldo');

        return Excel::download(
            new ContagemEstoqueExport($service->planilha($data), 'Saldo no sistema em ' . $data->format('d/m/Y'), $comSaldo),
            'contagem_estoque_' . $data->format('Y_m_d') . '.xlsx'
        );
    }

    public function importar(Request $request, ContagemEstoqueService $service)
    {
        $dados = $request->validate([
            'data'       => ['required', 'date', 'before_or_equal:today'],
            'planilha'   => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], [
            'planilha.required' => 'Selecione a planilha da contagem.',
            'planilha.mimes'    => 'A planilha deve ser .xlsx, .xls ou .csv.',
            'data.before_or_equal' => 'A contagem não pode ter data futura.',
        ]);

        $arquivo = $request->file('planilha');

        try {
            $linhas = Excel::toArray(new ContagemEstoqueImport(), $arquivo)[0] ?? [];
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['planilha' => 'Não foi possível ler a planilha: ' . $e->getMessage()]);
        }

        try {
            $contagem = $service->importar($linhas, Carbon::parse($dados['data']), $arquivo->getClientOriginalName(), $dados['observacao'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('contagens.show', $contagem)
            ->with('sucesso', 'Planilha importada. Confira as diferenças: o estoque só muda quando você aplicar a contagem.');
    }

    public function show(Request $request, ContagemEstoque $contagem)
    {
        $contagem->load(['itens' => fn ($q) => $q->with('produto:id,codigo_interno')->orderBy('descricao'), 'usuario', 'aplicador']);

        $itens = $contagem->itens;

        return view('sped.inventarios.contagens.show', [
            'contagem' => $contagem,
            'totais'   => [
                'contados'     => $itens->count(),
                'diferencas'   => $itens->where('diferenca', '!=', 0)->count(),
                'sobras'       => $itens->where('diferenca', '>', 0)->sum('valor_diferenca'),
                'faltas'       => $itens->where('diferenca', '<', 0)->sum('valor_diferenca'),
                'itensSobra'   => $itens->where('diferenca', '>', 0)->count(),
                'itensFalta'   => $itens->where('diferenca', '<', 0)->count(),
            ],
        ]);
    }

    public function aplicar(ContagemEstoque $contagem, ContagemEstoqueService $service, EstoqueService $estoque)
    {
        abort_unless($contagem->isRascunho(), 403, 'Esta contagem já foi aplicada.');

        try {
            $resultado = $service->aplicar($contagem, $estoque);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('contagens.show', $contagem)
            ->with('sucesso', "Contagem aplicada: {$resultado['ajustes']} ajuste(s) de estoque registrados em {$contagem->data->format('d/m/Y')}. "
                . 'Agora calcule o inventário dessa data.');
    }

    public function destroy(ContagemEstoque $contagem)
    {
        abort_unless($contagem->isRascunho(), 403, 'Contagem aplicada não pode ser excluída: os ajustes já estão no histórico do estoque.');

        $contagem->delete();

        return redirect()->route('inventarios.index')->with('sucesso', 'Contagem em rascunho excluída.');
    }
}
