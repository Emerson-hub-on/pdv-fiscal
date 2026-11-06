<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Forma de pagamento disponível no caixa (Cadastros → Caixa → Formas de pagamento). */
class FormaPagamentoCaixa extends Model
{
    protected $table = 'formas_pagamento_caixa';

    protected $fillable = ['codigo', 'descricao', 'meio_pagamento', 'tipo', 'permite_troco', 'ativo', 'ordem', 'sistema'];

    protected $casts = [
        'permite_troco' => 'boolean',
        'ativo' => 'boolean',
        'sistema' => 'boolean',
    ];

    public function scopeAtivas($q)
    {
        return $q->where('ativo', true);
    }

    public function scopeOrdenadas($q)
    {
        return $q->orderBy('ordem')->orderBy('id');
    }

    /** Já usada em alguma venda do caixa (aí não pode ser excluída, só inativada). */
    public function emUso(): bool
    {
        return \Illuminate\Support\Facades\DB::table('venda_pagamentos')->where('forma_pagamento', $this->codigo)->exists();
    }
}
