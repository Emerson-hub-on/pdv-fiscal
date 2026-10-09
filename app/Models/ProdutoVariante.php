<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoVariante extends Model
{
    protected $table = 'produto_variantes';

    protected $fillable = [
        'produto_id', 'cor', 'tamanho', 'codigo_barras', 'codigo_barras_valido', 'estoque', 'estoque_minimo',
    ];

    protected $casts = [
        'codigo_barras_valido' => 'boolean',
    ];

    /**
     * Prefixo do código interno gerado para variação sem EAN.
     * Fica fora da faixa dos códigos internos de produto (que são zeros à
     * esquerda + código interno) e fora da faixa 2x, usada por etiquetas de balança.
     */
    public const PREFIXO_CODIGO_INTERNO = '99';

    /**
     * Código interno da variação: prefixo + id (12 dígitos) + dígito verificador
     * EAN-13. Determinístico (mesmo id = mesmo código) e único por variação.
     * Uso interno — não vai no XML da NFC-e (codigo_barras_valido = false).
     */
    public static function codigoInternoPara(int $id): string
    {
        $base = self::PREFIXO_CODIGO_INTERNO . str_pad((string) $id, 10, '0', STR_PAD_LEFT);

        $soma = 0;
        foreach (array_reverse(str_split($base)) as $posicao => $digito) {
            $soma += ((int) $digito) * ($posicao % 2 === 0 ? 3 : 1);
        }

        return $base . ((10 - ($soma % 10)) % 10);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}