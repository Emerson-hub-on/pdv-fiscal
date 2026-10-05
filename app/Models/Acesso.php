<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'tipo_operador_id', 'permissoes', 'ativo'])]
class Acesso extends Model
{
    protected $table = 'acessos';

    protected function casts(): array
    {
        return [
            'permissoes' => 'array',
            'ativo'      => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoOperador::class, 'tipo_operador_id');
    }
}