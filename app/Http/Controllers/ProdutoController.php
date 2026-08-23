<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('status', 'ativos'); // ativos | inativos | todos

        $produtos = Produto::query()
            ->when($filtro === 'ativos', fn($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn($q) => $q->where('ativo', false))
            ->orderBy('nome')
            ->paginate(20);

        return view('produtos.index', compact('produtos', 'filtro'));
    }

    public function create()
    {
        $proximoCodigo = Produto::proximoCodigoInterno();
        return view('produtos.create', compact('proximoCodigo'));
    }

    public function store(Request $request)
    {
        $validado = $this->validarProduto($request);

        $produto = Produto::create($validado);

        $this->sincronizarVariantes($request, $produto);

        return redirect()->route('produtos.index')
            ->with('sucesso', 'Produto cadastrado com sucesso.');
    }

    public function edit(Produto $produto)
    {
        $produto->load('variantes');
        return view('produtos.edit', compact('produto'));
    }

    public function update(Request $request, Produto $produto)
    {
        $validado = $this->validarProduto($request, $produto->id);

        $produto->update($validado);

        $this->sincronizarVariantes($request, $produto);

        return redirect()->route('produtos.index')
            ->with('sucesso', 'Produto atualizado com sucesso.');
    }

    private function sincronizarVariantes(Request $request, Produto $produto): void
{
    if (!$produto->tem_variacao) {
        $produto->variantes()->delete();
        return;
    }

    $idsEnviados = [];

    foreach ($request->input('variantes', []) as $linha) {
        if (empty($linha['cor']) && empty($linha['tamanho'])) {
            continue; // ignora linha vazia
        }

        $variante = $produto->variantes()->updateOrCreate(
            ['id' => $linha['id'] ?: null],
            [
                'cor' => $linha['cor'] ?? null,
                'tamanho' => $linha['tamanho'] ?? null,
                'sku' => $linha['sku'] ?? null,
                'estoque' => $linha['estoque'] ?? 0,
                'estoque_minimo' => $linha['estoque_minimo'] ?? 0,
            ]
        );

        $idsEnviados[] = $variante->id;
    }

    // Remove variantes que existiam antes mas foram excluídas na tela
    $produto->variantes()->whereNotIn('id', $idsEnviados)->delete();
}
    /**
     * Sem destroy() de propósito — produtos não podem ser excluídos.
     */
    public function toggleAtivo(Produto $produto)
    {
        $produto->ativo ? $produto->inativar() : $produto->reativar();

        return redirect()->route('produtos.index')
            ->with('sucesso', $produto->ativo ? 'Produto reativado.' : 'Produto inativado.');
    }

    private function validarProduto(Request $request, $idAtual = null): array
    {
        return $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias,id',
            'marca_id' => 'nullable|exists:marcas,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'codigo_interno' => 'required|string|max:50|unique:produtos,codigo_interno,' . $idAtual,
            'codigo_barras' => 'nullable|string|max:50',
            'ncm_id' => 'required|exists:ncms,id',
            'cest' => 'nullable|string|size:7',
            'tributacao_id' => 'required|exists:tributacoes,id',
            'unidade_comercial' => 'required|string|max:6',
            'unidade_tributavel' => 'required|string|max:6',
            'origem_mercadoria' => 'required|integer|between:0,8',
            'class_trib_ibs_cbs' => 'nullable|string|max:6',
            'preco_venda' => 'required|numeric|min:0',
            'preco_custo' => 'nullable|numeric|min:0',
            'tem_variacao' => 'boolean',
            'produto_balanca' => 'boolean',
            'estoque' => 'required_if:tem_variacao,false|integer|min:0',
            'estoque_minimo' => 'nullable|integer|min:0',
        ]);
    }
}