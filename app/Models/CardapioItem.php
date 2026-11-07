<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item do cardápio das comandas (Comandas → Cardápio → Menu).
 * Igual ao caixa: produto com variação entra por variante (cada código de barras é um item, com foto própria);
 * produto_variante_id nulo = produto sem variação.
 * Nome, preço e estoque são sempre os do cadastro; aqui só a foto, a descrição do cardápio, a ordem e a situação.
 */
class CardapioItem extends Model
{
    protected $table = 'cardapio_itens';

    protected $fillable = ['produto_id', 'produto_variante_id', 'cardapio_categoria_id', 'imagem', 'descricao', 'ativo', 'ordem'];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariante::class, 'produto_variante_id');
    }

    /** Categoria do cardápio (não é a categoria do cadastro do produto). */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CardapioCategoria::class, 'cardapio_categoria_id');
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem')->orderBy('id');
    }

    /** Nome como no caixa: "Arroz — BRANCO / 1" para a variante. */
    public function nomeExibido(): string
    {
        $nome = (string) $this->produto?->nome;
        $rotulo = $this->variante?->rotulo();

        return $rotulo ? "{$nome} — {$rotulo}" : $nome;
    }

    /** Código como no caixa: o código de barras da variante; sem variante, o do produto (ou o interno). */
    public function codigoExibido(): ?string
    {
        return $this->variante
            ? $this->variante->codigo_barras
            : ($this->produto?->codigo_barras ?: $this->produto?->codigo_interno);
    }

    /** Estoque da variante; sem variante, o do produto. */
    public function estoqueAtual(): float
    {
        return (float) ($this->variante ? $this->variante->estoque : ($this->produto?->estoque ?? 0));
    }

    /** Descrição do cardápio; sem ela, a do cadastro do produto. */
    public function descricaoExibida(): ?string
    {
        return $this->descricao ?: $this->produto?->descricao;
    }

    /**
     * URL da foto (com a data de alteração, para o navegador não mostrar a antiga do cache).
     * $rota: cardapio.imagem (painel) ou garcom.cardapio.imagem (tela do garçom, outro login).
     */
    public function urlImagem(string $rota = 'cardapio.imagem'): ?string
    {
        return $this->imagem
            ? route($rota, ['item' => $this->id, 'v' => $this->updated_at?->timestamp])
            : null;
    }
}
