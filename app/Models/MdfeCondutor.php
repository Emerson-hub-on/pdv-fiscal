<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MdfeCondutor extends Model
{
    protected $table = 'mdfe_condutores';

    public $timestamps = false;

    protected $fillable = ['mdfe_id', 'mdfe_veiculo_id', 'condutor_id', 'nome', 'cpf'];

    public function mdfeVeiculo(): BelongsTo
    {
        return $this->belongsTo(MdfeVeiculo::class);
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(Condutor::class);
    }
}
