<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CfopEntradaConversao extends Model
{
    use HasFactory;

    protected $table = 'cfop_entrada_conversoes';

    protected $fillable = [
        'operacao_entrada_id', 
        'cfop_origem', 
        'cfop_entrada_id'
    ];

    /** Grupos de CFOPs de origem aceitos para conversão. */
    public const ORIGENS = [
        'venda'       => ['5101', '5102', '5108', '6101', '6102', '6108'],
        'st'          => ['5401', '5403', '6401', '6403'],
        'bonificacao' => ['5910', '6910'],
        'amostra'     => ['5911', '6911'],
        'outras'      => ['5949', '6949'],
    ];

    /**
     * Relacionamento: Operação de entrada associada.
     */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(OperacaoEntrada::class, 'operacao_entrada_id');
    }

    /**
     * Relacionamento: CFOP de entrada associado.
     */
    public function cfopEntrada(): BelongsTo
    {
        return $this->belongsTo(CfopEntrada::class, 'cfop_entrada_id');
    }

    /**
     * Gera ou atualiza as regras de conversão de CFOP para uma operação.
     */
    public static function gerar(OperacaoEntrada $operacao, string $sufixo, string $sufixoSt, array $grupos): void
    {
        foreach ($grupos as $grupo) {
            if (!isset(self::ORIGENS[$grupo])) {
                continue;
            }

            foreach (self::ORIGENS[$grupo] as $cfopOrigem) {
                $isSt = str_starts_with($cfopOrigem, '54') || str_starts_with($cfopOrigem, '64') || $grupo === 'st';
                $suf = $isSt ? $sufixoSt : $sufixo;

                $estado = str_starts_with($cfopOrigem, '5') ? '1' : '2';
                $codigoEntrada = $estado . $suf;

                $cfopEntrada = CfopEntrada::where('codigo', $codigoEntrada)->first();

                if ($cfopEntrada) {
                    self::updateOrCreate(
                        [
                            'operacao_entrada_id' => $operacao->id,
                            'cfop_origem'         => $cfopOrigem,
                        ],
                        [
                            'cfop_entrada_id'     => $cfopEntrada->id,
                        ]
                    );
                }
            }
        }
    }
}