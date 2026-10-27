<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MdfeMunicipio extends Model
{
    protected $table = 'mdfe_municipios';

    public const CARREGAMENTO    = 'carregamento';
    public const DESCARREGAMENTO = 'descarregamento';

    public $timestamps = false;

    protected $fillable = ['mdfe_id', 'tipo', 'codigo_ibge', 'nome', 'uf', 'ordem'];

    /** Documentos descarregados neste município (só faz sentido para tipo = descarregamento). */
    public function documentos(): HasMany
    {
        return $this->hasMany(MdfeDocumento::class);
    }
}
