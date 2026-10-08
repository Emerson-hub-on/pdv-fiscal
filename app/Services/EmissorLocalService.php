<?php

namespace App\Services;

use App\Models\VendaLocal;
use App\Support\EmissaoLocal;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Venda;

class EmissorLocalService extends FiscalEmissorService
{
    private const CHAVE_SEFAZ_FORA = 'sefaz_fora_do_ar';
    private const SEGUNDOS_SEFAZ_FORA = 60;

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
        
        if ($venda->status === 'cancelada') {
            throw new Exception('Esta venda foi cancelada e não será emitida.');
        }

        if ($venda->status_sync === 'cancelada') {
            throw new Exception('Esta venda foi cancelada e não será emitida.');
        }

       $pdvId = $venda->caixa->pdv->id ?? null;

        if ($venda->status_sync === 'sincronizada' && !$forcar && !EmissaoLocal::ativa($pdvId ? (int) $pdvId : null)) {
            throw new Exception('Esta venda já foi enviada ao servidor, que pode tê-la emitido. Emitir aqui também pode duplicar a NFC-e. Use --forcar apenas em homologação.');
        }

        return $this->emitir($venda);
    }

    protected function criarNfeService($pdv): NfeService
    {
        return new NfeServiceLocal($pdv);
    }

    // O caixa é o dono da numeração: não há trava aqui
    protected function garantirEmissaoPermitida($pdv): void
    {
    }

    // Depois de uma falha de conexão com a SEFAZ, as próximas vendas já saem em contingência
    protected function sefazEmContingencia(): bool
    {
        return Cache::store('file')->has(self::CHAVE_SEFAZ_FORA);
    }

    protected function registrarSefazFora(): void
    {
        Cache::store('file')->put(self::CHAVE_SEFAZ_FORA, true, self::SEGUNDOS_SEFAZ_FORA);
    }

    public function cancelarLocal(string $uuid, string $justificativa): array
    {
        return $this->cancelar(VendaLocal::carregar($uuid), $justificativa);
    }

    // No caixa o estoque devolvido é o do SQLite; o servidor recebe o cancelamento na sincronização
    protected function registrarCancelamento(Venda $venda, ?string $nProt, string $justificativa): void
    {
        $db = DB::connection('sqlite_local');

        $db->transaction(function () use ($db, $venda, $nProt, $justificativa) {
            foreach ($venda->itens as $item) {
                if ($item->produto_variante_id) {
                    $db->table('produto_variantes_cache')->where('id', $item->produto_variante_id)
                        ->increment('estoque', $item->quantidade);
                } else {
                    $db->table('produtos_cache')->where('id', $item->produto_id)
                        ->increment('estoque', $item->quantidade);
                }
            }

            $venda->update([
                'status' => 'cancelada',
                'motivo_cancelamento' => "Cancelado pelo operador: {$justificativa} (protocolo cancelamento: {$nProt})",
            ]);
        });
    }

    /**
     * Numeração: o maior entre o último número que o servidor conhece (espelho), o contador local
     * e, se o banco local não tem o contador (banco novo ou restaurado), o maior número já gravado em XML.
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

            if ($contador && $contador->serie === $serie) {
                $ultimoLocal = (int) $contador->ultimo_numero;
            } else {
                $empresa = $db->table('empresa_cache')->first(['cnpj', 'ambiente']);
                $ultimoLocal = $empresa
                    ? $this->ultimoNumeroEmitidoEmDisco($serie, (string) $empresa->cnpj, (int) $empresa->ambiente)
                    : 0;
            }

            $numero = max((int) $pdv->numero_atual_nfce, $ultimoLocal) + 1;

            $db->table('numeracao_nfce')->updateOrInsert(
                ['pdv_id' => $pdv->id],
                ['serie' => $serie, 'ultimo_numero' => $numero, 'created_at' => now(), 'updated_at' => now()]
            );

            $venda->update(['numero_nfce' => $numero, 'serie_nfce' => $serie]);

            return [$numero, $serie];
        });
    }

    /**
     * Maior número de NFC-e já gravado em XML nesta máquina para a série, CNPJ e ambiente.
     * Os XMLs ficam fora do SQLite, então sobrevivem à perda do banco.
     */
    private function ultimoNumeroEmitidoEmDisco(string $serie, string $cnpj, int $ambiente): int
    {
        $pasta = storage_path('app/XML_nfce');

        if (!is_dir($pasta)) {
            return 0;
        }

        $serie = str_pad($serie, 3, '0', STR_PAD_LEFT);
        $maior = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($arquivos as $arquivo) {
            // O nome do arquivo começa pela chave de acesso (44 dígitos)
            if (!preg_match('/^(\d{44})/', $arquivo->getFilename(), $m)) {
                continue;
            }

            $chave = $m[1];

            if (substr($chave, 6, 14) !== $cnpj || substr($chave, 20, 2) !== '65' || substr($chave, 22, 3) !== $serie) {
                continue;
            }

            // A chave não traz o ambiente: confere dentro do XML para não misturar homologação e produção
            if (!str_contains(file_get_contents($arquivo->getPathname()), "<tpAmb>{$ambiente}</tpAmb>")) {
                continue;
            }

            $maior = max($maior, (int) substr($chave, 25, 9));
        }

        return $maior;
    }
}