<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CstEntradaConversao extends Model
{
    use HasFactory;

    protected $table = 'cst_entrada_conversoes';

    protected $fillable = [
        'regime', 
        'tipo_origem', 
        'codigo_origem', 
        'codigo_entrada', 
        'gera_credito'
    ];

    protected $casts = [
        'gera_credito' => 'boolean',
    ];


    public const ORIGENS = [
        'venda' => [
            '5101', '5102', '5103', '5104', '5105', '5106', '5109', '5110',
            '5116', '5117', '5118', '5119', '5120', '5122', '5123', '5551',
            '6101', '6102', '6103', '6104', '6105', '6106', '6107', '6108', '6109', '6110',
            '6116', '6117', '6118', '6119', '6120', '6122', '6123', '6551',
        ],
        'st'          => ['5401', '5402', '5403', '5404', '5405', '6401', '6402', '6403', '6404', '6405'],
        'bonificacao' => ['5910', '6910'],
        'amostra'     => ['5911', '6911'],
        'outras'      => ['5949', '6949'],
    ];

    /** Gera as regras origem → entrada de uma operação (5xxx → 1xxx, 6xxx → 2xxx). */
    public static function gerar(OperacaoEntrada $operacao, string $sufixo, string $sufixoSt, array $grupos): void
    {
        $cfops = CfopEntrada::pluck('id', 'codigo');

        foreach ($grupos as $grupo) {
            foreach (self::ORIGENS[$grupo] as $cfopOrigem) {
                $area = $cfopOrigem[0] === '5' ? '1' : '2';
                $destino = $area . ($grupo === 'st' ? $sufixoSt : $sufixo);

                if (! isset($cfops[$destino])) {
                    continue;
                }

                self::firstOrCreate(
                    ['operacao_entrada_id' => $operacao->id, 'cfop_origem' => $cfopOrigem],
                    ['cfop_entrada_id' => $cfops[$destino]]
                );
            }
        }
    }
}