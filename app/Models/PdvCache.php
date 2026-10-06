<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PdvCache extends Model
{
    protected $connection = 'sqlite_local';
    protected $table = 'pdvs_cache';
    public $incrementing = false; // o id vem do central
    protected $guarded = [];      // gravado só pelo SyncService

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Próximo número da NFC-e deste PDV: o maior entre o último número que o servidor
     * conhece (espelho) e o último reservado aqui no caixa (contador local, mesma série).
     */
    public function proximoNumeroNfce(): int
    {
        $ultimoLocal = DB::connection('sqlite_local')->table('numeracao_nfce')
            ->where('pdv_id', $this->id)
            ->where('serie', (string) $this->serie_nfce)
            ->value('ultimo_numero');

        return max((int) $this->numero_atual_nfce, (int) $ultimoLocal) + 1;
    }
}