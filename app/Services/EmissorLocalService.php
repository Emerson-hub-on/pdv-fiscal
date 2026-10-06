<?php

namespace App\Services;

use App\Models\VendaLocal;
use Exception;
use Illuminate\Support\Facades\DB;

class EmissorLocalService extends FiscalEmissorService
{
    /**
     * Emite (ou reenvia, se já estiver em contingência) a NFC-e de uma venda do SQLite,
     * sem consultar o MySQL central.
     */
    public function emitirLocal(string $uuid, bool $forcar = false): array
    {
        $venda = VendaLocal::carregar($uuid);

        if ($venda->status === 'emitida') {
            throw new Exception('Esta venda já tem NFC-e emitida.');
        }

        if ($venda->status_sync === 'cancelada') {
            throw new Exception('Esta venda foi cancelada e não será emitida.');
        }

        // Venda que o servidor já recebeu: ele pode tê-la emitido por conta própria, e emitir aqui duplicaria a nota
        if ($venda->status_sync === 'sincronizada' && !$forcar) {
            throw new Exception('Esta venda já foi enviada ao servidor, que pode tê-la emitido. Emitir aqui também pode duplicar a NFC-e. Use --forcar apenas em homologação.');
        }

        return $this->emitir($venda);
    }

    protected function criarNfeService($pdv): NfeService
    {
        return new NfeServiceLocal($pdv);
    }

    /**
     * Numeração a partir do contador local (numeracao_nfce), sempre respeitando o último
     * número que o servidor informou para este PDV (espelho). Pega o maior dos dois.
     */
    protected function reservarNumero($venda, $pdv): array
    {
        if ($venda->numero_nfce) {
            return [(int) $venda->numero_nfce, (string) $venda->serie_nfce];
        }

        $db = DB::connection('sqlite_local');
        $serie = (string) $pdv->serie_nfce;

        return $db->transaction(function () use ($db, $venda, $pdv, $serie) {
            $contador = $db->table('numeracao_nfce')->where('pdv_id', $pdv->id)->first();

            $ultimo = max(
                (int) $pdv->numero_atual_nfce,
                ($contador && $contador->serie === $serie) ? (int) $contador->ultimo_numero : 0
            );

            $numero = $ultimo + 1;

            $db->table('numeracao_nfce')->updateOrInsert(
                ['pdv_id' => $pdv->id],
                ['serie' => $serie, 'ultimo_numero' => $numero, 'created_at' => now(), 'updated_at' => now()]
            );

            $venda->update(['numero_nfce' => $numero, 'serie_nfce' => $serie]);

            return [$numero, $serie];
        });
    }
}