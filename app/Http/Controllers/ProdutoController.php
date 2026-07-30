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
        return view('produtos.create');
    }

    public function store(Request $request)
    {
        $validado = $this->validarProduto($request);

        Produto::create($validado);

        return redirect()->route('produtos.index')
            ->with('sucesso', 'Produto cadastrado com sucesso.');
    }

    public function edit(Produto $produto)
    {
        return view('produtos.edit', compact('produto'));
    }

    public function update(Request $request, Produto $produto)
    {
        $validado = $this->validarProduto($request, $produto->id);

        $produto->update($validado);

        return redirect()->route('produtos.index')
            ->with('sucesso', 'Produto atualizado com sucesso.');
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
            'categoria' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:100',
            'grupo' => 'nullable|string|max:100',
            'codigo_interno' => 'required|string|max:50|unique:produtos,codigo_interno,' . $idAtual,
            'codigo_barras' => 'nullable|string|max:50',
            'ncm' => 'required|string|size:8',
            'cest' => 'nullable|string|size:7',
            'cfop_padrao' => 'required|string|size:4',
            'unidade_comercial' => 'required|string|max:6',
            'unidade_tributavel' => 'required|string|max:6',
            'origem_mercadoria' => 'required|integer|between:0,8',
            'csosn' => 'required|string|size:3',
            'class_trib_ibs_cbs' => 'nullable|string|max:6',
            'preco_venda' => 'required|numeric|min:0',
            'preco_custo' => 'nullable|numeric|min:0',
            'tem_variacao' => 'boolean',
            'estoque' => 'required_if:tem_variacao,false|integer|min:0',
            'estoque_minimo' => 'nullable|integer|min:0',
        ]);
    }
}