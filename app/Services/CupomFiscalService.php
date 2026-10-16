<?php

namespace App\Services;

use App\Support\CentralStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;

/**
 * Monta os dados do DANFE NFC-e (cupom fiscal) a partir do XML assinado da venda,
 * lido do SQLite local do caixa. Funciona offline e em contingência.
 */
class CupomFiscalService
{
    private const FORMAS_PAGAMENTO = [
        '01' => 'Dinheiro',
        '02' => 'Cheque',
        '03' => 'Cartão de Crédito',
        '04' => 'Cartão de Débito',
        '05' => 'Crédito Loja',
        '10' => 'Vale Alimentação',
        '11' => 'Vale Refeição',
        '12' => 'Vale Presente',
        '13' => 'Vale Combustível',
        '15' => 'Boleto Bancário',
        '16' => 'Depósito Bancário',
        '17' => 'PIX',
        '18' => 'Transferência/Carteira Digital',
        '19' => 'Fidelidade/Cashback',
        '90' => 'Sem Pagamento',
        '99' => 'Outros',
    ];

    public function dados(string $uuid): array
    {
        $venda = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $uuid)->first();

        if (!$venda) {
            throw new RuntimeException('Venda não encontrada neste caixa.');
        }

        $xml = $this->lerXml($venda);

        if (!$xml) {
            throw new RuntimeException('O XML desta NFC-e não está disponível neste caixa para impressão.');
        }

        $x = $this->interpretar($xml);

        $inf = $x->xpath('//infNFe')[0] ?? null;

        if (!$inf) {
            throw new RuntimeException('XML da NFC-e inválido: grupo infNFe não encontrado.');
        }

        $ide = $inf->ide;
        $emit = $inf->emit;
        $end = $emit->enderEmit;
        $tot = $inf->total->ICMSTot;
        $supl = $x->xpath('//infNFeSupl')[0] ?? null;
        $prot = $x->xpath('//protNFe/infProt')[0] ?? null;

        $chave = preg_replace('/\D/', '', (string) ($inf['Id'] ?? ''));
        $chave = $chave !== '' ? $chave : (string) ($venda->chave_nfe ?? '');

        $protocolo = (string) ($prot->nProt ?? '') ?: (string) ($venda->protocolo_nfe ?? '');
        $dataAutorizacao = ($prot && (string) $prot->dhRecbto) ? Carbon::parse((string) $prot->dhRecbto) : null;

        $tpEmis = (int) ($ide->tpEmis ?? 1);
        $contingencia = $tpEmis !== 1 || $venda->status_fiscal === 'contingencia';

        $itens = [];
        foreach ($inf->det as $det) {
            $p = $det->prod;
            $itens[] = [
                'seq' => (int) $det['nItem'],
                'codigo' => (string) $p->cProd,
                'descricao' => (string) $p->xProd,
                'quantidade' => (float) $p->qCom,
                'unidade' => (string) $p->uCom,
                'valor_unitario' => (float) $p->vUnCom,
                'valor_total' => (float) $p->vProd,
                'desconto' => (float) ($p->vDesc ?? 0),
            ];
        }

        $pagamentos = [];
        foreach ($inf->pag->detPag as $d) {
            $codigo = (string) $d->tPag;
            $pagamentos[] = [
                'forma' => self::FORMAS_PAGAMENTO[$codigo] ?? ((string) ($d->xPag ?? '') ?: 'Outros'),
                'valor' => (float) $d->vPag,
            ];
        }

        $destDoc = (string) ($inf->dest->CPF ?? '') ?: (string) ($inf->dest->CNPJ ?? '');

        return [
            'emitente' => [
                'nome' => (string) $emit->xNome,
                'fantasia' => (string) ($emit->xFant ?? ''),
                'cnpj' => $this->formatarDocumento((string) $emit->CNPJ),
                'ie' => (string) ($emit->IE ?? ''),
                'endereco' => trim((string) $end->xLgr . ', ' . (string) $end->nro
                    . ((string) ($end->xCpl ?? '') !== '' ? ' ' . (string) $end->xCpl : ''), ', '),
                'bairro_cidade' => collect([(string) $end->xBairro, (string) $end->xMun . '/' . (string) $end->UF])
                    ->filter()->implode(' - '),
            ],
            'numero' => (int) $ide->nNF,
            'serie' => (int) $ide->serie,
            'emissao' => Carbon::parse((string) $ide->dhEmi),
            'homologacao' => (int) $ide->tpAmb === 2,
            'contingencia' => $contingencia,
            'cancelada' => $venda->status_fiscal === 'cancelada',
            'chave' => $chave,
            'chave_formatada' => trim(chunk_split($chave, 4, ' ')),
            'protocolo' => $protocolo,
            'data_autorizacao' => $dataAutorizacao,
            'consumidor' => [
                'documento' => $destDoc !== '' ? $this->formatarDocumento($destDoc) : null,
                'nome' => (string) ($inf->dest->xNome ?? ''),
            ],
            'itens' => $itens,
            'qtd_itens' => count($itens),
            'total_produtos' => (float) $tot->vProd,
            'desconto' => (float) ($tot->vDesc ?? 0),
            'total' => (float) $tot->vNF,
            'tributos' => isset($tot->vTotTrib) ? (float) $tot->vTotTrib : null,
            'pagamentos' => $pagamentos,
            'troco' => (float) ($inf->pag->vTroco ?? 0),
            'info_complementar' => (string) ($inf->infAdic->infCpl ?? ''),
            'qrcode' => trim((string) ($supl->qrCode ?? '')),
            'url_consulta' => trim((string) ($supl->urlChave ?? '')),
            // Contingência off-line: o DANFE sai em 2 vias (consumidor e estabelecimento)
            'vias' => $contingencia ? ['VIA CONSUMIDOR', 'VIA ESTABELECIMENTO'] : ['VIA CONSUMIDOR'],
        ];
    }

    private function lerXml(object $venda): ?string
    {
        if ($venda->ultimo_arquivo_xml && is_file($venda->ultimo_arquivo_xml)) {
            return file_get_contents($venda->ultimo_arquivo_xml) ?: null;
        }

        if ($venda->xml_contingencia) {
            return $venda->xml_contingencia;
        }

        // PDV em emissão pelo servidor (modo de emergência): o XML fica no MySQL central
        if (!CentralStatus::fora()) {
            try {
                $central = DB::table('vendas')->where('uuid', $venda->uuid)->first();

                if ($central) {
                    if (!empty($central->xml_nfce)) {
                        return $central->xml_nfce;
                    }
                    if (!empty($central->ultimo_arquivo_xml) && is_file($central->ultimo_arquivo_xml)) {
                        return file_get_contents($central->ultimo_arquivo_xml) ?: null;
                    }

                    return $central->xml_contingencia ?: null;
                }
            } catch (\Throwable $e) {
                if (CentralStatus::erroDeConexao($e)) {
                    CentralStatus::marcarFora();
                }
            }
        }

        return null;
    }

    private function interpretar(string $xml): SimpleXMLElement
    {
        // Remove os namespaces (NFe e assinatura) para consultar sem prefixos
        $limpo = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);

        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($limpo);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$x) {
            throw new RuntimeException('Não foi possível ler o XML da NFC-e.');
        }

        return $x;
    }

    private function formatarDocumento(string $doc): string
    {
        $n = preg_replace('/\D/', '', $doc);

        return match (strlen($n)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $n),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $n),
            default => $doc,
        };
    }
}