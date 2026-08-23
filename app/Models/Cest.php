<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cest extends Model
{
    protected $fillable = [
        'codigo',
        'descricao',
        'segmento_codigo',
        'segmento_descricao',
    ];

    /**
     * Retorna o código formatado no padrão oficial SS.III.DD
     */
    public function getCodigoFormatadoAttribute(): string
    {
        $c = $this->codigo;
        return substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 2);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    /**
     * Códigos NCM relacionados a este CEST (tabela cest_ncm guarda o código
     * NCM cru, sem depender de FK pra tabela de NCM — ajuste se preferir
     * ligar por ncm_id caso já tenha uma tabela "ncms" com id próprio).
     */
    public function ncmsRelacionados(): HasMany
    {
        return $this->hasMany(\App\Models\CestNcm::class);
    }
}