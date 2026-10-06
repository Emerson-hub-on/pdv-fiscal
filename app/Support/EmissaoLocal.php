<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class EmissaoLocal
{
    // O PDV desta máquina emite NFC-e pelo próprio caixa?
    public static function ativa(): bool
    {
        return (bool) DB::connection('sqlite_local')->table('pdvs_cache')
            ->where('id', config('app.pdv_id'))
            ->value('emissao_local');
    }
}