<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscal extends Model
{
    protected $table = 'notas_fiscais';

    protected $fillable = [
        'cliente_id',
        'operador_id',
        'natureza_operacao',
        'finalidade',
        'tipo_operacao',
        'origem_tipo',
        'venda_id',
        'valor_desconto',
        'valor_frete',
        'cfop_saida_id',
        'forma_pagamento_id',
        'informacoes_complementares',
        'notas_referenciadas',
        'motivo_ajuste',
    ];

    protected $casts = [
        'valor_produtos' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_frete' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'emitida_em' => 'datetime',
        'notas_referenciadas' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function serieNfe(): BelongsTo
    {
        return $this->belongsTo(SerieNfe::class, 'serie_nfe_id');
    }

    public function cfopSaida(): BelongsTo
    {
        return $this->belongsTo(CfopSaida::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function scopeRascunhos($query)
    {
        return $query->where('status', 'rascunho');
    }

    /**
     * Recalcula os totais da nota com base nos itens atuais.
     */
    public function recalcularTotais(): void
    {
        $produtos = $this->itens()->sum('valor_total');
        $outras   = $this->itens()->sum('valor_outras_despesas');

        $this->valor_produtos = $produtos;
        $this->valor_total = $produtos - $this->valor_desconto + $this->valor_frete + $outras;
        $this->save();
    }

    /**
     * Texto automático das notas referenciadas para as Informações Complementares.
     * Segue o mesmo critério do XML: a referência global (NFref) só vale para as finalidades
     * que não referenciam por item, e a referência por item (DFeReferenciado) vale sempre
     * que o item tiver chave. Chaves que o operador já digitou na observação não são repetidas.
     */
    public function textoNotasReferenciadas(): ?string
    {
        $textoOperador = (string) $this->informacoes_complementares;
        $partes = [];

        // 1. Referência global (cabeçalho da nota)
        if (!in_array((int) $this->finalidade, config('fiscal.finalidades_referencia_por_item', []), true)) {
            $descricoes = collect($this->notas_referenciadas ?? [])
                ->filter()
                ->unique()
                ->reject(fn ($chave) => str_contains($textoOperador, $chave))
                ->map(fn ($chave) => self::descreverChave($chave));

            if ($descricoes->isNotEmpty()) {
                $partes[] = 'NF-e referenciada(s): ' . $descricoes->implode('; ');
            }
        }

        // 2. Referência por item — agrupa por nota de origem para o texto não ficar enorme
        $itensPorChave = [];
        foreach ($this->itens->values() as $indice => $item) {
            if (!$item->ref_chave_acesso || str_contains($textoOperador, $item->ref_chave_acesso)) {
                continue;
            }

            $itensPorChave[$item->ref_chave_acesso][] = 'item ' . ($indice + 1)
                . ($item->ref_nitem ? ' (item ' . $item->ref_nitem . ' da NF-e de origem)' : '');
        }

        foreach ($itensPorChave as $chave => $itens) {
            $partes[] = 'Referência por item — ' . self::descreverChave($chave) . ': ' . implode(', ', $itens);
        }

        return $partes ? implode(' | ', $partes) : null;
    }

    /** "NF-e nº 25 série 1 (chave 2526...)": número e série vêm da própria chave de 44 dígitos. */
    protected static function descreverChave(string $chave): string
    {
        $chave = preg_replace('/\D/', '', $chave);

        if (strlen($chave) !== 44) {
            return "chave {$chave}";
        }

        $serie  = (int) substr($chave, 22, 3);
        $numero = (int) substr($chave, 25, 9);

        return "NF-e nº {$numero} série {$serie} (chave {$chave})";
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }
}