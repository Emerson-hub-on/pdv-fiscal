<?php

namespace App\Services;

use App\Models\VendaLocal;
use App\Support\EmissaoLocal;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Venda;
use App\Models\Pdv;
use App\Support\DadosFiscaisLocais;
use Illuminate\Support\Str;

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

    public function inutilizarLocal(int $pdvId, int $numeroInicial, int $numeroFinal, string $justificativa): array
    {
        $pdv = DadosFiscaisLocais::pdv($pdvId);

        if (!$pdv) {
            throw new Exception('PDV não encontrado no caixa. Sincronize com o servidor.');
        }

        // Número já autorizado (ou cancelado depois de autorizado) não pode ser inutilizado
        $usados = DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('serie_nfce', (string) $pdv->serie_nfce)
            ->whereBetween('numero_nfce', [$numeroInicial, $numeroFinal])
            ->whereIn('status_fiscal', ['emitida', 'cancelada'])
            ->orderBy('numero_nfce')
            ->pluck('numero_nfce');

        if ($usados->isNotEmpty()) {
            throw new Exception('A faixa inclui número(s) já autorizado(s) pela SEFAZ (' . $usados->implode(', ') . '). Só é possível inutilizar números que nunca foram autorizados.');
        }

        return $this->inutilizar($pdv, $numeroInicial, $numeroFinal, $justificativa);
    }

    // No caixa o registro fica no SQLite; o servidor recebe na sincronização
    protected function registrarInutilizacao(Pdv $pdv, int $numeroInicial, int $numeroFinal, string $justificativa, bool $sucesso, ?string $nProt, ?string $xMotivo): void
    {
        $db = DB::connection('sqlite_local');
        $serie = (string) $pdv->serie_nfce;

        $db->table('inutilizacoes_local')->insert([
            'uuid' => (string) Str::uuid(),
            'pdv_id' => $pdv->id,
            'serie' => $serie,
            'numero_inicial' => $numeroInicial,
            'numero_final' => $numeroFinal,
            'justificativa' => $justificativa,
            'status' => $sucesso ? 'sucesso' : 'erro',
            'protocolo' => $nProt,
            'motivo' => $xMotivo,
            'operador_id' => auth()->id(),
            'sync_pendente' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Os números inutilizados não podem ser reaproveitados: o contador passa do fim da faixa
        if ($sucesso) {
            $db->table('numeracao_nfce')
                ->where('pdv_id', $pdv->id)
                ->where('serie', $serie)
                ->where('ultimo_numero', '<', $numeroFinal)
                ->update(['ultimo_numero' => $numeroFinal, 'updated_at' => now()]);
        }
    }

    // Vendas do caixa presas na faixa: cancela e devolve o estoque do caixa (o servidor recebe pela sincronização de vendas)
    protected function cancelarVendasDaFaixa(Pdv $pdv, int $numeroInicial, int $numeroFinal, ?string $nProt): void
    {
        $db = DB::connection('sqlite_local');

        $vendas = $db->table('vendas_pendentes')
            ->whereIn('status_fiscal', ['contingencia', 'pendente'])
            ->where('status', '!=', 'cancelada')
            ->where('serie_nfce', (string) $pdv->serie_nfce)
            ->whereBetween('numero_nfce', [$numeroInicial, $numeroFinal])
            ->get();

        foreach ($vendas as $v) {
            $db->transaction(function () use ($db, $v, $nProt) {
                foreach (json_decode($v->itens, true) ?? [] as $item) {
                    if (!empty($item['produto_variante_id'])) {
                        $db->table('produto_variantes_cache')->where('id', $item['produto_variante_id'])
                            ->increment('estoque', $item['quantidade']);
                    } else {
                        $db->table('produtos_cache')->where('id', $item['produto_id'])
                            ->increment('estoque', $item['quantidade']);
                    }
                }

                $db->table('vendas_pendentes')->where('uuid', $v->uuid)->update([
                    'status_fiscal' => 'cancelada',
                    'motivo_cancelamento' => "Número inutilizado (protocolo {$nProt}). Venda não será emitida.",
                    'fiscal_sync_pendente' => true,
                    'updated_at' => now(),
                ]);
            });
        }
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