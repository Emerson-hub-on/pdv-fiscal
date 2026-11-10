<?php

namespace App\Services;

use App\Models\CaixaLocal;
use App\Models\Venda;
use App\Support\CentralStatus;
use App\Support\EmissaoLocal;
use Illuminate\Support\Facades\DB;

/**
 * Emite a NFC-e de uma venda já gravada no SQLite do caixa (vendas_pendentes).
 * Usado pela venda de balcão (VendaController::finalizar) e pelas vendas do restaurante.
 *
 * - PDV emitindo no caixa: emite aqui, sem depender do servidor (a venda sobe depois pelo agendador).
 * - PDV em emergência (emissão pelo servidor): envia a venda e o servidor emite; servidor fora = venda fica no caixa.
 *
 * @return array{sucesso: bool, contingencia: bool, erro: ?string, chave?: string}
 */
class EmissaoCaixaService
{
    public const SERVIDOR_FORA = 'Servidor indisponível: a venda foi salva no caixa e será enviada quando a conexão voltar.';

    public function emitir(string $uuid, CaixaLocal $caixa): array
    {
        $emissao = ['sucesso' => false, 'contingencia' => false, 'erro' => null];

        if (EmissaoLocal::ativa((int) $caixa->pdv_id)) {
            return $this->emitirNoCaixa($uuid);
        }

        if (CentralStatus::fora()) {
            $emissao['erro'] = self::SERVIDOR_FORA;

            return $emissao;
        }

        try {
            (new SyncService())->enviarVendasPendentes();

            if (CentralStatus::fora()) {
                $emissao['erro'] = self::SERVIDOR_FORA;

                return $emissao;
            }

            $vendaCentral = Venda::where('uuid', $uuid)->first();

            if ($vendaCentral) {
                try {
                    $resultado = (new FiscalEmissorService())->emitir($vendaCentral);
                    $emissao = ['sucesso' => true, 'contingencia' => false, 'erro' => null, 'chave' => $resultado['chave']];
                } catch (\Exception $e) {
                    $vendaCentral->refresh();
                    $emissao = [
                        'sucesso' => false,
                        'contingencia' => $vendaCentral->status === 'contingencia',
                        'erro' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            if (CentralStatus::erroDeConexao($e)) {
                CentralStatus::marcarFora();
                $emissao['erro'] = self::SERVIDOR_FORA;
            }
        }

        return $emissao;
    }

    // Emite pelo emissor local. A contingência (tpEmis 9) também termina em exceção, mas o documento já é válido.
    private function emitirNoCaixa(string $uuid): array
    {
        try {
            $resultado = (new EmissorLocalService())->emitirLocal($uuid);

            return ['sucesso' => true, 'contingencia' => false, 'erro' => null, 'chave' => $resultado['chave']];
        } catch (\Throwable $e) {
            $situacao = DB::connection('sqlite_local')->table('vendas_pendentes')
                ->where('uuid', $uuid)->value('status_fiscal');

            return [
                'sucesso' => false,
                'contingencia' => $situacao === 'contingencia',
                'erro' => $e->getMessage(),
            ];
        }
    }
}
