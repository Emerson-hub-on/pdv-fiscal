<?php

namespace App\Http\Controllers;

use App\Models\CardapioItem;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Comandas → Cardápio → Menu: escolhe os produtos que o garçom vê ao fazer o pedido e a foto de cada um.
 * Igual ao caixa, produto com variação entra por variante (cada código de barras é um item).
 * Nome, valor e estoque são sempre os do cadastro do produto. A foto é reduzida para no máximo
 * 800 px no lado maior (fica leve para o celular do garçom) e guardada em storage/app/private/cardapio.
 */
class CardapioController extends Controller
{
    private const PASTA = 'cardapio';
    private const LADO_MAXIMO = 800;

    public function index(Request $request)
    {
        $busca = trim((string) $request->query('busca'));
        $categoriaId = $request->integer('categoria') ?: null;
        $situacao = $request->query('situacao', 'todos');

        $itens = CardapioItem::with(['produto.categoria', 'variante'])
            ->when($busca !== '', function ($q) use ($busca) {
                $like = '%' . addcslashes($busca, '\\%_') . '%';
                $q->where(fn ($w) => $w
                    ->whereHas('produto', fn ($p) => $p->where('nome', 'like', $like)
                        ->orWhere('codigo_interno', $busca)
                        ->orWhere('codigo_barras', $busca))
                    ->orWhereHas('variante', fn ($v) => $v->where('codigo_barras', $busca)
                        ->orWhere('cor', 'like', $like)
                        ->orWhere('tamanho', 'like', $like)));
            })
            ->when($categoriaId, fn ($q) => $q->whereHas('produto', fn ($p) => $p->where('categoria_id', $categoriaId)))
            ->when($situacao === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($situacao === 'inativos', fn ($q) => $q->where('ativo', false))
            ->ordenados()
            ->get();

        $categorias = Categoria::whereIn('id', Produto::whereIn('id', CardapioItem::select('produto_id'))->select('categoria_id'))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $totais = [
            'itens'      => CardapioItem::count(),
            'ativos'     => CardapioItem::where('ativo', true)->count(),
            'sem_foto'   => CardapioItem::whereNull('imagem')->count(),
            'sem_estoque'=> CardapioItem::with(['produto', 'variante'])->get()->filter(fn ($i) => $i->estoqueAtual() <= 0)->count(),
        ];

        return view('comandas.cardapio.index', compact('itens', 'categorias', 'totais', 'busca', 'categoriaId', 'situacao'));
    }

    public function create()
    {
        $item = new CardapioItem([
            'ativo' => true,
            'ordem' => (int) CardapioItem::max('ordem') + 10,
        ]);

        return view('comandas.cardapio.create', compact('item'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        $item = new CardapioItem($dados);
        $item->produto_id = $request->integer('produto_id');
        $item->produto_variante_id = $request->integer('produto_variante_id') ?: null;
        if ($request->hasFile('imagem')) {
            $item->imagem = $this->salvarImagem($request->file('imagem'));
        }
        $item->save();

        return redirect()->route('cardapio.index')
            ->with('sucesso', '"' . $item->nomeExibido() . '" incluído no cardápio.');
    }

    public function edit(CardapioItem $item)
    {
        $item->load(['produto.categoria', 'variante']);

        return view('comandas.cardapio.edit', compact('item'));
    }

    public function update(Request $request, CardapioItem $item): RedirectResponse
    {
        $dados = $this->validar($request, $item);

        if ($request->hasFile('imagem')) {
            $this->apagarImagem($item);
            $dados['imagem'] = $this->salvarImagem($request->file('imagem'));
        } elseif ($request->boolean('remover_imagem')) {
            $this->apagarImagem($item);
            $dados['imagem'] = null;
        }

        $item->update($dados);

        return redirect()->route('cardapio.index')->with('sucesso', 'Item do cardápio atualizado.');
    }

    /** Tira o produto do cardápio (o cadastro do produto não é alterado). */
    public function destroy(CardapioItem $item): RedirectResponse
    {
        $nome = $item->nomeExibido();
        $this->apagarImagem($item);
        $item->delete();

        return redirect()->route('cardapio.index')->with('sucesso', '"' . $nome . '" retirado do cardápio.');
    }

    /** Foto do item (também será usada pela tela do garçom). */
    public function imagem(CardapioItem $item): BinaryFileResponse
    {
        abort_unless($item->imagem && Storage::disk('local')->exists($item->imagem), 404);

        return response()->file(Storage::disk('local')->path($item->imagem), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Busca da tela "Adicionar produto", igual à do caixa: produto com variação aparece uma linha por variante
     * (código de barras da variante); só entra o que está ativo e ainda não está no cardápio.
     */
    public function buscarProduto(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('termo'));
        if ($termo === '') {
            return response()->json([]);
        }

        $like = '%' . addcslashes($termo, '\\%_') . '%';
        $linha = fn (Produto $p, ?ProdutoVariante $v = null) => [
            'id'                  => $p->id,
            'produto_variante_id' => $v?->id,
            'nome'                => $v && $v->rotulo() ? "{$p->nome} — {$v->rotulo()}" : $p->nome,
            'codigo_interno'      => $v ? null : $p->codigo_interno,
            'codigo_barras'       => $v ? $v->codigo_barras : $p->codigo_barras,
            'categoria'           => $p->categoria?->nome,
            'descricao'           => $p->descricao,
            'preco_venda'         => (float) $p->preco_venda,
            'estoque'             => (float) ($v ? $v->estoque : $p->estoque),
            'unidade'             => $p->unidade_comercial ?: 'UN',
        ];
        $variantesNoCardapio = CardapioItem::whereNotNull('produto_variante_id')->select('produto_variante_id');

        // Código de barras de variante (leitor): devolve só ela
        if (ctype_digit($termo)) {
            $variante = ProdutoVariante::with('produto.categoria')
                ->where('codigo_barras', $termo)
                ->whereHas('produto', fn ($p) => $p->ativos()->where('tem_variacao', true))
                ->whereNotIn('id', $variantesNoCardapio)
                ->first();

            if ($variante) {
                return response()->json([$linha($variante->produto, $variante)]);
            }
        }

        $simples = Produto::ativos()
            ->with('categoria:id,nome')
            ->where('tem_variacao', false)
            ->whereNotIn('id', CardapioItem::whereNull('produto_variante_id')->select('produto_id'))
            ->where(fn ($q) => $q->where('nome', 'like', $like)
                ->orWhere('codigo_interno', $termo)
                ->orWhere('codigo_barras', $termo)
                ->orWhere('referencia', 'like', $like))
            ->orderBy('nome')
            ->limit(30)
            ->get()
            ->map(fn (Produto $p) => $linha($p));

        $variantes = ProdutoVariante::with('produto.categoria')
            ->whereHas('produto', fn ($p) => $p->ativos()->where('tem_variacao', true))
            ->whereNotIn('id', $variantesNoCardapio)
            ->where(fn ($q) => $q->where('codigo_barras', $termo)
                ->orWhere('cor', 'like', $like)
                ->orWhere('tamanho', 'like', $like)
                ->orWhereHas('produto', fn ($p) => $p->where('nome', 'like', $like)
                    ->orWhere('codigo_interno', $termo)
                    ->orWhere('referencia', 'like', $like)))
            ->limit(30)
            ->get()
            ->map(fn (ProdutoVariante $v) => $linha($v->produto, $v));

        return response()->json(
            $simples->concat($variantes)->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)->values()->take(40)->values()
        );
    }

    private function validar(Request $request, ?CardapioItem $item = null): array
    {
        $regras = [
            'imagem'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'ordem'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'ativo'     => ['boolean'],
        ];

        if (!$item) {
            $regras['produto_id'] = ['required', Rule::exists('produtos', 'id')->where('ativo', true)];
            $regras['produto_variante_id'] = [
                'nullable',
                'integer',
                Rule::exists('produto_variantes', 'id')->where('produto_id', $request->integer('produto_id')),
            ];
        }

        $validador = Validator::make($request->all(), $regras, [
            'produto_id.required'        => 'Escolha o produto.',
            'produto_id.exists'          => 'Produto não encontrado ou inativo.',
            'produto_variante_id.exists' => 'A variação escolhida não pertence ao produto.',
            'imagem.image'               => 'O arquivo precisa ser uma imagem.',
            'imagem.mimes'               => 'Use uma imagem JPG, PNG ou WEBP.',
            'imagem.max'                 => 'A imagem pode ter no máximo 8 MB.',
            'imagem.uploaded'            => 'Não foi possível enviar a imagem (tamanho acima do permitido pelo servidor?).',
        ], ['descricao' => 'descrição']);

        // Igual ao caixa: produto com variação entra pela variante; e cada produto/variante uma vez só
        // (a chave única do banco não pega o NULL repetido de produto sem variação)
        $validador->after(function ($v) use ($request, $item) {
            if ($item || $v->errors()->hasAny(['produto_id', 'produto_variante_id'])) {
                return;
            }

            $produto = Produto::find($request->integer('produto_id'));
            $varianteId = $request->integer('produto_variante_id') ?: null;

            if ($produto->tem_variacao && !$varianteId) {
                $v->errors()->add('produto_id', 'Este produto tem variação: escolha a variação (código de barras) no modal.');
            } elseif (!$produto->tem_variacao && $varianteId) {
                $v->errors()->add('produto_id', 'Este produto não tem variação.');
            } elseif (CardapioItem::where('produto_id', $produto->id)->where('produto_variante_id', $varianteId)->exists()) {
                $v->errors()->add('produto_id', $varianteId ? 'Esta variação já está no cardápio.' : 'Este produto já está no cardápio.');
            }
        });

        $validador->validate();

        return [
            'descricao' => trim((string) $request->input('descricao')) ?: null,
            'ordem'     => (int) $request->input('ordem', 0),
            'ativo'     => $request->boolean('ativo'),
        ];
    }

    /** Reduz para no máximo 800 px no lado maior e grava em WEBP (ou JPG sem suporte a WEBP). */
    private function salvarImagem(UploadedFile $arquivo): string
    {
        $origem = @imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));
        if (!$origem) {
            // GD não conseguiu abrir: guarda o arquivo como veio
            return $arquivo->store(self::PASTA, 'local');
        }

        $origem = $this->corrigirOrientacao($origem, $arquivo);
        $larg = imagesx($origem);
        $alt = imagesy($origem);
        $escala = min(1, self::LADO_MAXIMO / max($larg, $alt));
        $novaLarg = max(1, (int) round($larg * $escala));
        $novaAlt = max(1, (int) round($alt * $escala));

        $destino = imagecreatetruecolor($novaLarg, $novaAlt);
        $webp = function_exists('imagewebp');
        if ($webp) {
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
        } else {
            imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        }
        imagecopyresampled($destino, $origem, 0, 0, 0, 0, $novaLarg, $novaAlt, $larg, $alt);

        ob_start();
        $webp ? imagewebp($destino, null, 82) : imagejpeg($destino, null, 85);
        $conteudo = ob_get_clean();
        imagedestroy($origem);
        imagedestroy($destino);

        $caminho = self::PASTA . '/' . Str::uuid() . ($webp ? '.webp' : '.jpg');
        Storage::disk('local')->put($caminho, $conteudo);

        return $caminho;
    }

    /** Foto de celular vem "deitada" com a orientação no EXIF: gira antes de reduzir. */
    private function corrigirOrientacao(\GdImage $img, UploadedFile $arquivo): \GdImage
    {
        if (!function_exists('exif_read_data') || !in_array($arquivo->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $img;
        }

        $orientacao = (int) (@exif_read_data($arquivo->getRealPath())['Orientation'] ?? 1);
        $graus = [3 => 180, 6 => -90, 8 => 90][$orientacao] ?? 0;

        return $graus ? (imagerotate($img, $graus, 0) ?: $img) : $img;
    }

    private function apagarImagem(CardapioItem $item): void
    {
        if ($item->imagem) {
            Storage::disk('local')->delete($item->imagem);
        }
    }
}
