<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['nome'];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }
}