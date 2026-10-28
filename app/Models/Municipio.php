<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = ['codigo_ibge', 'nome', 'nome_busca', 'uf'];

    protected static function booted(): void
    {
        // Mantém nome_busca em dia nas gravações normais (o import em lote já calcula por conta própria)
        static::saving(fn (self $m) => $m->nome_busca = self::normalizar($m->nome));
    }

    /** "São João d'Aliança" => "sao joao d'alianca" */
    public static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }
}
