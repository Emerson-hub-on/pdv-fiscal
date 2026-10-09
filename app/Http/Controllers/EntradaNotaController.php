<?php

namespace App\Http\Controllers;

use App\Models\EntradaNota;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\ProdutoLote;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EntradaNotaController extends Controller
{
    public function index(Request $request)
    {
        $entradas = EntradaNota::with('fornecedor')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim($request->busca);

                $q->where(function ($w) use ($busca) {
                    $w->where('numero', 'like', "%{$busca}%")
                      ->orWhere('chave_acesso', 'like', "%{$busca}%")
                        ->orWhereHas('fornecedor', function ($f) use ($busca) {
                            $f->where('nome', 'like', "%{$busca}%")
                            ->orWhere('nome_fantasia', 'like', "%{$busca}%");
                            $digitos = preg_replace('/\D/', '', $busca);
                            if ($digitos !== '') {
                                $f->orWhere('cpf_cnpj', 'like', "%{$digitos}%");
                            }
                        });
                });
            })
            ->orderByDesc('data_entrada')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('notasfiscais.entrada.index', compact('entradas'));
    }

    public function create()
    {
        $entrada = new EntradaNota([
            'modelo'          => '55',
            'data_entrada'    => today()->toDateString(),
            'atualizar_custo' => true,
        ]);

        return view('notasfiscais.entrada.create', $this->dadosFormulario($entrada));
    }

    public function store(Request $request)
    {
        $entrada = $this->salvar($request, null);

        if ($request->input('acao') === 'finalizar') {
            $this->finalizar($entrada);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', 'Entrada finalizada e estoque atualizado.');
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Rascunho da entrada salvo.');
    }

    public function edit(EntradaNota $entrada)
    {
        return view('notasfiscais.entrada.edit', $this->dadosFormulario($entrada));
    }

    public function update(Request $request, EntradaNota $entrada)
    {
        abort_unless($entrada->isRascunho(), 403, 'Esta entrada já foi finalizada e não pode ser alterada.');

        $entrada = $this->salvar($request, $entrada);

        if ($request->input('acao') === 'finalizar') {
            $this->finalizar($entrada);

            return redirect()->route('entradas-nota.index')
                ->with('sucesso', 'Entrada finalizada e estoque atualizado.');
        }

        return redirect()->route('entradas-nota.edit', $entrada)
            ->with('sucesso', 'Rascunho da entrada salvo.');
    }

    public function destroy(EntradaNota $entrada)
    {
        abort_unless($entrada->isRascunho(), 403, 'Só é possível excluir entradas em rascunho.');

        $entrada->delete(); // itens caem por cascade

        return redirect()->route('entradas-nota.index')
            ->with('sucesso', 'Rascunho excluído.');
    }

    /**
     * Busca de produtos para o formulário (JSON).
     * Nesta etapa só produtos SEM variação; variações entram quando enviarmos o ProdutoVariante.
     */
    public function buscarProdutos(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $produtos = Produto::ativos()
            ->where('tem_variacao', false)
            ->where(function ($w) use ($q) {
                $w->where('nome', 'like', "%{$q}%")
                  ->orWhere('referencia', 'like', "%{$q}%")
                  ->orWhere('codigo_barras', $q)
                  ->orWhere('codigo_interno', $q);
            })
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'codigo_interno', 'codigo_barras', 'unidade_comercial', 'preco_custo', 'estoque']);

        return response()->json($produtos);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function dadosFormulario(EntradaNota $entrada): array
    {
        $itens = old('itens') ?? $entrada->itens
            ->map(fn ($i) => [
                'produto_id'        => $i->produto_id,
                'descricao'         => $i->descricao,
                'codigo_fornecedor' => $i->codigo_fornecedor,
                'quantidade'        => (float) $i->quantidade,
                'valor_unitario'    => (float) $i->valor_unitario,
                'valor_desconto'    => (float) $i->valor_desconto,
                'lote'              => $i->lote,
                'validade'          => $i->validade?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return [
            'entrada'         => $entrada,
            'fornecedores'    => Fornecedor::ativos()->orderBy('nome')->get(),
            'itens'           => $itens,
            'somenteLeitura'  => $entrada->exists && ! $entrada->isRascunho(),
        ];
    }

    private function validar(Request $request, ?EntradaNota $entrada): array
    {
        $dados = $request->validate([
            'fornecedor_id'     => ['required', 'exists:fornecedores,id'],
            'modelo'            => ['required', Rule::in(['55', '01'])],
            'serie'             => ['nullable', 'string', 'max:3'],
            'numero'            => ['required', 'string', 'max:9'],
            'chave_acesso'      => [
                'nullable', 'digits:44',
                Rule::unique('entradas_nota', 'chave_acesso')->ignore($entrada?->id),
            ],
            'data_emissao'      => ['required', 'date'],
            'data_entrada'      => ['required', 'date', 'after_or_equal:data_emissao'],
            'natureza_operacao' => ['nullable', 'string', 'max:60'],
            'valor_frete'       => ['nullable', 'numeric', 'min:0'],
            'valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'valor_outras'      => ['nullable', 'numeric', 'min:0'],
            'observacao'        => ['nullable', 'string', 'max:1000'],

            'itens'                     => ['required', 'array', 'min:1'],
            'itens.*.produto_id'        => ['required', 'exists:produtos,id'],
            'itens.*.descricao'         => ['nullable', 'string', 'max:200'],
            'itens.*.codigo_fornecedor' => ['nullable', 'string', 'max:60'],
            'itens.*.quantidade'        => ['required', 'numeric', 'gt:0'],
            'itens.*.valor_unitario'    => ['required', 'numeric', 'min:0'],
            'itens.*.valor_desconto'    => ['nullable', 'numeric', 'min:0'],
            'itens.*.lote'              => ['nullable', 'string', 'max:30'],
            'itens.*.validade'          => ['nullable', 'date'],
        ], [
            'itens.required'       => 'Adicione ao menos um item à entrada.',
            'itens.min'            => 'Adicione ao menos um item à entrada.',
            'chave_acesso.digits'  => 'A chave de acesso deve ter 44 dígitos.',
            'chave_acesso.unique'  => 'Já existe uma entrada com esta chave de acesso.',
        ]);

        // Nota duplicada (mesmo fornecedor + modelo + série + número)
        $duplicada = EntradaNota::where('fornecedor_id', $dados['fornecedor_id'])
            ->where('modelo', $dados['modelo'])
            ->where('serie', $dados['serie'] ?? null)
            ->where('numero', $dados['numero'])
            ->where('status', '!=', 'cancelada')
            ->when($entrada, fn ($q) => $q->where('id', '!=', $entrada->id))
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'numero' => 'Esta nota já foi lançada para este fornecedor.',
            ]);
        }

        return $dados;
    }

    private function salvar(Request $request, ?EntradaNota $entrada): EntradaNota
    {
        $dados = $this->validar($request, $entrada);

        $produtos = Produto::whereIn('id', collect($dados['itens'])->pluck('produto_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($dados['itens'] as $i => $item) {
            if ($produtos[$item['produto_id']]->tem_variacao) {
                throw ValidationException::withMessages([
                    "itens.{$i}.produto_id" => 'Produtos com variação ainda não são suportados na entrada de nota.',
                ]);
            }
        }

        return DB::transaction(function () use ($request, $dados, $entrada, $produtos) {
            $entrada ??= new EntradaNota([
                'status'       => 'rascunho',
                'tipo_entrada' => 'manual',
                'user_id'      => auth()->id(),
            ]);

            $entrada->fill(Arr::except($dados, ['itens']));
            $entrada->valor_frete     = $dados['valor_frete'] ?? 0;
            $entrada->valor_desconto  = $dados['valor_desconto'] ?? 0;
            $entrada->valor_outras    = $dados['valor_outras'] ?? 0;
            $entrada->atualizar_custo = $request->boolean('atualizar_custo');
            $entrada->save();

            // Rascunho: recria os itens a cada gravação
            $entrada->itens()->delete();

            foreach ($dados['itens'] as $item) {
                $produto  = $produtos[$item['produto_id']];
                $qtd      = (float) $item['quantidade'];
                $unit     = (float) $item['valor_unitario'];
                $desconto = (float) ($item['valor_desconto'] ?? 0);

                $entrada->itens()->create([
                    'produto_id'        => $produto->id,
                    'codigo_fornecedor' => $item['codigo_fornecedor'] ?? null,
                    'descricao'         => $produto->nome,
                    'unidade'           => $produto->unidade_comercial,
                    'quantidade'        => $qtd,
                    'valor_unitario'    => $unit,
                    'valor_desconto'    => $desconto,
                    'valor_total'       => round(($qtd * $unit) - $desconto, 2),
                    'lote'              => $item['lote'] ?? null,
                    'validade'          => $item['validade'] ?? null,
                ]);
            }

            $entrada->recalcularTotais();

            return $entrada;
        });
    }

    /**
     * Aplica a entrada: soma estoque, atualiza custo (opcional) e gera lotes.
     */
    private function finalizar(EntradaNota $entrada): void
    {
        DB::transaction(function () use ($entrada) {
            $entrada = EntradaNota::whereKey($entrada->id)->lockForUpdate()->firstOrFail();

            if (! $entrada->isRascunho()) {
                return; // proteção contra duplo clique / reenvio
            }

            foreach ($entrada->itens as $item) {
                $produto = Produto::whereKey($item->produto_id)->lockForUpdate()->firstOrFail();

                $produto->increment('estoque', $item->quantidade);

                if ($entrada->atualizar_custo && (float) $item->quantidade > 0) {
                    // custo líquido unitário (já descontado o desconto do item)
                    $produto->update([
                        'preco_custo' => round((float) $item->valor_total / (float) $item->quantidade, 2),
                    ]);
                }

                if ($item->lote || $item->validade) {
                    ProdutoLote::create([
                        'produto_id'           => $item->produto_id,
                        'entrada_nota_item_id' => $item->id,
                        'lote'                 => $item->lote,
                        'validade'             => $item->validade,
                        'quantidade_inicial'   => $item->quantidade,
                        'quantidade_atual'     => $item->quantidade,
                    ]);
                }
            }

            $entrada->update([
                'status'        => 'finalizada',
                'finalizada_em' => now(),
            ]);
        });
    }
}