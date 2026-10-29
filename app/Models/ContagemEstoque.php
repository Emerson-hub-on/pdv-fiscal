<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContagemEstoque extends Model
{
    protected $table = 'contagens_estoque';

    public const RASCUNHO = 'rascunho';
    public const APLICADA = 'aplicada';

    protected $fillable = ['data', 'status', 'arquivo_nome', 'observacao', 'user_id', 'aplicada_em', 'aplicada_por'];

    protected $casts = [
        'data'        => 'date',
        'aplicada_em' => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ContagemEstoqueItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aplicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicada_por');
    }

    public function isRascunho(): bool
    {
        return $this->status === self::RASCUNHO;
    }

    /** Rótulo usado no histórico de movimentações. */
    public function rotulo(): string
    {
        return "Contagem física #{$this->id} de {$this->data->format('d/m/Y')}";
    }
}
