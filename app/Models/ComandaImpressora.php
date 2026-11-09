<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Impressora das comandas (balcão, cozinha, churrascaria...). Recebe os itens das categorias ligadas
 * a ela ou, com "pedido completo", todos os itens. Conexão pela rede (IP:porta, padrão 9100) ou
 * USB no servidor (nome da impressora compartilhada no Windows, ou caminho como /dev/usb/lp0).
 */
class ComandaImpressora extends Model
{
    public const REDE = 'rede';
    public const USB  = 'usb';

    protected $table = 'comanda_impressoras';

    protected $fillable = [
        'nome', 'conexao', 'endereco', 'porta', 'colunas', 'copias',
        'pedido_completo', 'sem_categoria', 'mostrar_precos', 'ativo',
    ];

    protected $casts = [
        'porta'           => 'integer',
        'colunas'         => 'integer',
        'copias'          => 'integer',
        'pedido_completo' => 'boolean',
        'sem_categoria'   => 'boolean',
        'mostrar_precos'  => 'boolean',
        'ativo'           => 'boolean',
    ];

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(CardapioCategoria::class, 'comanda_impressora_categoria', 'impressora_id', 'cardapio_categoria_id');
    }

    /** Recebe este item? (pela categoria atual do item no cardápio) */
    public function recebe(?int $categoriaId): bool
    {
        if ($this->pedido_completo) {
            return true;
        }

        return $categoriaId === null
            ? $this->sem_categoria
            : $this->categorias->contains('id', $categoriaId);
    }

    public function descricaoConexao(): string
    {
        return $this->conexao === self::REDE
            ? "Rede · {$this->endereco}:{$this->porta}"
            : "USB · {$this->endereco}";
    }

    public function paraTela(): array
    {
        return [
            'id'              => $this->id,
            'nome'            => $this->nome,
            'conexao'         => $this->conexao,
            'endereco'        => $this->endereco,
            'porta'           => $this->porta,
            'colunas'         => $this->colunas,
            'copias'          => $this->copias,
            'pedido_completo' => $this->pedido_completo,
            'sem_categoria'   => $this->sem_categoria,
            'mostrar_precos'  => $this->mostrar_precos,
            'ativo'           => $this->ativo,
            'categorias'      => $this->categorias->pluck('id')->values(),
            'descricao'       => $this->descricaoConexao(),
        ];
    }
}
