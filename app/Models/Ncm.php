<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Ncm extends Model
{
    protected $table = 'ncms';
    protected $fillable = ['codigo', 'descricao', 'cadastro_avulso'];

    protected $casts = [
        'cadastro_avulso' => 'boolean',
    ];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }
}