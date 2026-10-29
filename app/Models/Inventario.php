<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventario extends Model
{
    protected $table = 'inventarios';

    public const RASCUNHO = 'rascunho';
    public const FECHADO  = 'fechado';

    /**
     * MOT_INV do registro H005. Por enquanto só o 01: os motivos 02 a 05 exigem o
     * registro H020 (ICMS do estoque), que ainda não é gerado.
     */
    public const MOTIVOS = [
        '01' => 'No final do período',
    ];

    protected $fillable = ['data', 'motivo', 'status', 'incluir_zerados', 'valor_total', 'observacao', 'user_id', 'fechado_em'];

    protected $casts = [
        'data'            => 'date',
        'incluir_zerados' => 'boolean',
        'valor_total'     => 'decimal:2',
        'fechado_em'      => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFechados($query)
    {
        return $query->where('status', self::FECHADO);
    }

    public function isRascunho(): bool
    {
        return $this->status === self::RASCUNHO;
    }

    public function recalcularTotal(): void
    {
        $this->valor_total = round((float) $this->itens()->sum('valor_total'), 2);
        $this->save();
    }
}
