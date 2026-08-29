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
        $validado = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias,id',
            'marca_id' => 'nullable|exists:marcas,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'codigo_interno' => 'required|string|max:50|unique:produtos,codigo_interno,' . $idAtual,
            'codigo_barras' => 'nullable|string|max:50',
            'ncm_id' => 'required|exists:ncms,id',
            'cest_id' => 'nullable|exists:cests,id',
            'class_trib_ibs_cbs_id' => 'nullable|exists:classificacoes_tributarias,id',
            'pis_cofins_id' => 'nullable|exists:classificacoes_pis_cofins,id',
            'ipi_id' => 'nullable|exists:classificacoes_ipi,id',
            'tributacao_id' => 'required|exists:tributacoes,id',
            'unidade_comercial' => 'required|string|max:6',
            'unidade_tributavel' => 'required|string|max:6',
            'origem_mercadoria' => 'required|integer|between:0,8',
            'preco_venda' => 'required|numeric|min:0',
            'preco_custo' => 'nullable|numeric|min:0',
            'tem_variacao' => 'boolean',
            'produto_balanca' => 'boolean',
            'estoque' => 'required_if:tem_variacao,false|integer|min:0',
            'estoque_minimo' => 'nullable|integer|min:0',
 
            // Atacado
            'preco_atacado' => 'nullable|numeric|min:0',
            'quantidade_minima_atacado' => 'nullable|numeric|min:0',
            'atacado_tem_prazo' => 'boolean',
            'atacado_data_inicio' => 'required_if:atacado_tem_prazo,1|nullable|date',
            'atacado_data_fim' => 'required_if:atacado_tem_prazo,1|nullable|date|after_or_equal:atacado_data_inicio',
        ]);
 
        // Se o operador desligou o toggle "tem preço de atacado", zera tudo -
        // fica limpo em vez de guardar valores antigos escondidos
        if (! $request->boolean('tem_preco_atacado')) {
            $validado['preco_atacado'] = null;
            $validado['quantidade_minima_atacado'] = null;
            $validado['atacado_tem_prazo'] = false;
            $validado['atacado_data_inicio'] = null;
            $validado['atacado_data_fim'] = null;
        } elseif (! $request->boolean('atacado_tem_prazo')) {
            // Atacado ativo mas sem prazo - permanente, sem datas
            $validado['atacado_tem_prazo'] = false;
            $validado['atacado_data_inicio'] = null;
            $validado['atacado_data_fim'] = null;
        }
 
        return $validado;
    }
}