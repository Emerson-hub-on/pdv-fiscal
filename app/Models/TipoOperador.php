<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'nome', 'contexto', 'permite_login', 'ativo'])]
class TipoOperador extends Model
{
    protected $table = 'tipos_operador'; // sem isso o Laravel procuraria "tipo_operadors"

    protected function casts(): array
    {
        return [
            'permite_login' => 'boolean',
            'ativo'         => 'boolean',
        ];
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }
}