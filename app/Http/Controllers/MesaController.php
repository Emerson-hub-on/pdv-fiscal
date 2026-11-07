<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comandas → Mesas → Lista de Mesas: cadastro das mesas (só no painel).
 * A situação (disponível / aguardando pedido / pedido entregue) é mudada pelo garçom na tela dele.
 */
class MesaController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('busca'));
        $status = $request->query('status', '');
        $situacao = $request->query('situacao', 'ativas');

        $mesas = Mesa::with('alteradoPor:id,name')
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%' . addcslashes($busca, '\\%_') . '%')
                ->when(ctype_digit($busca), fn ($n) => $n->orWhere('numero', (int) $busca))))
            ->when(array_key_exists($status, Mesa::STATUS), fn ($q) => $q->where('status', $status))
            ->when($situacao === 'ativas', fn ($q) => $q->where('ativo', true))
            ->when($situacao === 'inativas', fn ($q) => $q->where('ativo', false))
            ->ordenadas()
            ->get();

        $contagem = Mesa::where('ativo', true)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $totais = [
            'mesas'                 => Mesa::where('ativo', true)->count(),
            Mesa::DISPONIVEL        => (int) ($contagem[Mesa::DISPONIVEL] ?? 0),
            Mesa::AGUARDANDO        => (int) ($contagem[Mesa::AGUARDANDO] ?? 0),
            Mesa::ENTREGUE          => (int) ($contagem[Mesa::ENTREGUE] ?? 0),
        ];

        return view('comandas.mesas.index', compact('mesas', 'totais', 'busca', 'status', 'situacao'));
    }

    public function create()
    {
        $mesa = new Mesa(['numero' => (int) Mesa::max('numero') + 1, 'ativo' => true]);

        return view('comandas.mesas.create', compact('mesa'));
    }

    public function store(Request $request): RedirectResponse
    {
        $mesa = Mesa::create($this->validar($request));

        return redirect()->route('mesas.index')->with('sucesso', $mesa->titulo() . ' cadastrada.');
    }

    public function edit(Mesa $mesa)
    {
        $mesa->load('alteradoPor:id,name');

        return view('comandas.mesas.edit', compact('mesa'));
    }

    public function update(Request $request, Mesa $mesa): RedirectResponse
    {
        $dados = $this->validar($request, $mesa);

        // Mesa ocupada não some da tela do garçom no meio do atendimento
        if (!$dados['ativo'] && $mesa->ativo && $mesa->status !== Mesa::DISPONIVEL) {
            return back()->withInput()->withErrors([
                'ativo' => 'A mesa está "' . $mesa->dadosStatus()['rotulo'] . '": só dá para inativar quando ela estiver disponível.',
            ]);
        }

        $mesa->update($dados);

        return redirect()->route('mesas.index')->with('sucesso', $mesa->titulo() . ' atualizada.');
    }

    public function destroy(Mesa $mesa): RedirectResponse
    {
        if ($mesa->status !== Mesa::DISPONIVEL) {
            return back()->withErrors([
                'mesa' => 'A mesa está "' . $mesa->dadosStatus()['rotulo'] . '": só dá para excluir quando ela estiver disponível.',
            ]);
        }

        // Mesa com pedidos guarda o histórico deles: em vez de excluir, inative
        if ($mesa->pedidos()->exists()) {
            return back()->withErrors([
                'mesa' => $mesa->titulo() . ' já teve pedidos: não pode ser excluída (inative-a se não for mais usar).',
            ]);
        }

        $titulo = $mesa->titulo();
        $mesa->delete();

        return redirect()->route('mesas.index')->with('sucesso', $titulo . ' excluída.');
    }

    private function validar(Request $request, ?Mesa $mesa = null): array
    {
        $dados = $request->validate([
            'numero'  => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('mesas', 'numero')->ignore($mesa?->id)],
            'nome'    => ['nullable', 'string', 'max:60'],
            'lugares' => ['nullable', 'integer', 'min:1', 'max:99'],
            'ativo'   => ['boolean'],
        ], [
            'numero.required' => 'Informe o número da mesa.',
            'numero.unique'   => 'Já existe uma mesa com esse número.',
            'lugares.max'     => 'A mesa pode ter no máximo 99 lugares.',
        ]);

        return [
            'numero'  => (int) $dados['numero'],
            'nome'    => trim((string) ($dados['nome'] ?? '')) ?: null,
            'lugares' => $dados['lugares'] ?? null,
            'ativo'   => $request->boolean('ativo'),
        ];
    }
}
