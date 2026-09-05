<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('status', 'ativos'); // ativos | inativos | todos
        $ordenarPor = $request->get('ordenar', 'nome'); // nome | codigo
        $busca = $request->get('busca');
        $tipoBusca = $request->get('tipo_busca', 'nome'); // nome | codigo_interno | codigo_barras

        $produtos = $this->consultarProdutos($request, $filtro, $ordenarPor, $busca, $tipoBusca)
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return view('produtos._tabela', compact('produtos'))->render();
        }

        return view('produtos.index', compact('produtos', 'filtro', 'ordenarPor', 'busca', 'tipoBusca'));
    }

    private function consultarProdutos(Request $request, string $filtro, string $ordenarPor, ?string $busca, string $tipoBusca)
    {
        return Produto::query()
            ->when($filtro === 'ativos', fn($q) => $q->where('ativo', true))
            ->when($filtro === 'inativos', fn($q) => $q->where('ativo', false))
            ->when($busca, function ($q) use ($busca, $tipoBusca) {
                $coluna = match ($tipoBusca) {
                    'codigo_interno' => 'codigo_interno',
                    'codigo_barras' => 'codigo_barras',
                    default => 'nome',
                };
                $q->where($coluna, 'like', "%{$busca}%");
            })
            ->when($ordenarPor === 'codigo', fn($q) => $q->orderByRaw('CAST(codigo_interno AS UNSIGNED) ASC, codigo_interno ASC'))
            ->when($ordenarPor !== 'codigo', fn($q) => $q->orderBy('nome'));
    }

    public function create()
    {
        $proximoCodigo = Produto::proximoCodigoInterno();
        return view('produtos.create', compact('proximoCodigo'));
    }


    public function verificarCodigoBarras(Request $request): JsonResponse
    {
        $codigo = trim((string) $request->query('codigo', ''));
        $excluirId = $request->query('excluir');
    
        if ($codigo === '') {
            return response()->json(['duplicado' => false]);
        }
    
        $ehCodigoDeControle = ctype_digit($codigo) && strlen($codigo) < 8;
        if ($ehCodigoDeControle) {
            $codigo = str_pad($codigo, 13, '0', STR_PAD_LEFT);
        }
    
        $existe = Produto::where('codigo_barras', $codigo)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->exists();
    
        return response()->json(['duplicado' => $existe, 'codigo_normalizado' => $codigo]);
    }
    

    /**
     * Normaliza o código de barras antes de validar/salvar.
     *
     * Regras:
     * - Campo vazio: usa o código interno como base para o "código de controle".
     * - Campo preenchido, mas só com dígitos e com menos de 8 caracteres
     *   (menor que o menor padrão de EAN real, o EAN-8): não é um código de
     *   barras de verdade — trata como se fosse um código interno digitado
     *   ali por engano/teste, e também aplica o padding.
     * - Nos dois casos acima, preenche com "0" à esquerda até 13 dígitos e
     *   marca codigo_barras_valido = false (uso interno, não vai no XML).
     * - Qualquer outro valor preenchido é tratado como código de barras real.
     */
    private function resolverCodigoBarras(Request $request): void
    {
        $valorDigitado = trim((string) $request->input('codigo_barras'));
 
        if ($valorDigitado === '') {
            $codigoInterno = preg_replace('/\D/', '', (string) $request->input('codigo_interno'));
 
            $request->merge([
                'codigo_barras' => str_pad($codigoInterno, 13, '0', STR_PAD_LEFT),
                'codigo_barras_valido' => false,
            ]);
        } else {
            $request->merge(['codigo_barras_valido' => true]);
        }
    }

    public function store(Request $request)
    {   
        $this->resolverCodigoBarras($request);
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
        $this->resolverCodigoBarras($request);
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
            'codigo_barras' => [
                'required', 'string', 'max:50',
                Rule::unique('produtos', 'codigo_barras')->ignore($idAtual),
                function ($attribute, $value, $fail) {
                    // O fallback automático (campo deixado em branco) já vem com 13
                    // dígitos, todos numéricos, então nunca cai aqui. Isso só pega
                    // quem digitou manualmente um número curto tentando usar de atalho.
                    if (ctype_digit($value) && strlen($value) < 8) {
                        $fail('Código de barras inválido. Use o EAN oficial (mínimo 8 dígitos) ou deixe o campo em branco para o sistema gerar automaticamente a partir do código interno.');
                    }
                },
            ],
            'codigo_barras_valido' => 'boolean',
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