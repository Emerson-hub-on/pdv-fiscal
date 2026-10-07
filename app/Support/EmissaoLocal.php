<?php

namespace App\Support;

use App\Models\CaixaLocal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmissaoLocal
{
    // PDV do caixa aberto do operador logado (null se não houver caixa aberto)
    public static function pdvId(): ?int
    {
        $caixa = CaixaLocal::aberto(Auth::id());

        return $caixa ? (int) $caixa->pdv_id : null;
    }

    // O PDV emite NFC-e pelo próprio caixa? Sem PDV definido, não emite localmente.
    public static function ativa(?int $pdvId = null): bool
    {
        $pdvId ??= self::pdvId();

        if (!$pdvId) {
            return false;
        }

        return (bool) DB::connection('sqlite_local')->table('pdvs_cache')
            ->where('id', $pdvId)
            ->value('emissao_local');
    }
}