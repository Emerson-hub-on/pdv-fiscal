<?php

namespace App\Services;

use App\Models\Venda;
use NFePHP\NFe\Make;
use NFePHP\Common\Exception\SefazException;
use Exception;

class FiscalEmissorService
{
    protected NfeService $nfeService;

    public function __construct()
    {
        $this->nfeService = new NfeService();
    }

    public function emitir(Venda $venda): array
    {
        $venda->load('itens.produto', 'itens.variante');
        $empresa = $this->nfeService->empresa();

        // Reserva o proximo numero da NFC-e
        $numero = $empresa->numero_atual_nfce + 1;

        $nfe = new Make();

        $this->montarInfNFe($nfe);
        $this->montarIde($nfe, $empresa, $numero);
        $this->montarEmit($nfe, $empresa);
        $this->montarItens($nfe, $venda);
        $this->montarTotais($nfe, $venda);
        $this->montarTransporte($nfe);
        $this->montarPagamento($nfe, $venda);
        $this->montarResponsavelTecnico($nfe);

        $xml = $nfe->getXML();

        if (!$xml) {
            throw new Exception('Erro ao montar XML: ' . implode(' | ', $nfe->getErrors()));
        }

        $tools = $this->nfeService->tools();
        $xmlAssinado = $tools->signNFe($xml);

        // Envia para a SEFAZ
        $idLote = str_pad($numero, 15, '0', STR_PAD_LEFT);
        $resposta = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);

        // TEMPORÁRIO: ver resposta real antes de finalizar o parser
        throw new Exception('RESPOSTA SEFAZ: ' . $resposta);
        // Atualiza numeracao da empresa e dados da venda
        $empresa->update(['numero_atual_nfce' => $numero]);

        $venda->update([
            'status' => 'emitida',
            'chave_nfe' => $protocolo['chave'],
            'protocolo_nfe' => $protocolo['numero_protocolo'],
        ]);

        return [
            'xml_assinado' => $xmlAssinado,
            'chave' => $protocolo['chave'],
            'protocolo' => $protocolo['numero_protocolo'],
        ];
    }

    protected function montarInfNFe(Make $nfe): void
    {
        $std = new \stdClass();
        $std->versao = '4.00';
        $std->Id = null;
        $std->pk_nItem = '';
        $nfe->taginfNFe($std);
    }

    protected function montarIde(Make $nfe, $empresa, int $numero): void
    {
        $std = new \stdClass();
        $std->cUF = $this->codigoUf($empresa->uf);
        $std->cNF = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $std->natOp = 'Venda de mercadoria';
        $std->mod = 65;
        $std->serie = $empresa->serie_nfce;
        $std->nNF = $numero;
        $std->dhEmi = date('Y-m-d\TH:i:sP');
        $std->tpNF = 1; // saida
        $std->idDest = 1; // operacao interna
        $std->cMunFG = $empresa->cod_municipio;
        $std->tpImp = 4; // DANFE NFCe
        $std->tpEmis = 1; // normal
        $std->cDV = 0; // calculado automaticamente pela lib
        $std->tpAmb = (int) $empresa->ambiente;
        $std->finNFe = 1; // normal
        $std->indFinal = 1; // consumidor final
        $std->indPres = 1; // presencial
        $std->procEmi = 0;
        $std->verProc = '1.0.0';

        $nfe->tagide($std);
    }

    protected function montarEmit(Make $nfe, $empresa): void
    {
        $std = new \stdClass();
        $std->CNPJ = $empresa->cnpj;
        $std->xNome = $empresa->razao_social;
        $std->xFant = $empresa->nome_fantasia;
        $std->IE = $empresa->ie;
        $std->CRT = $empresa->crt;
        $nfe->tagemit($std);

        $endereco = new \stdClass();
        $endereco->xLgr = $empresa->logradouro;
        $endereco->nro = $empresa->numero;
        $endereco->xCpl = $empresa->complemento;
        $endereco->xBairro = $empresa->bairro;
        $endereco->cMun = $empresa->cod_municipio;
        $endereco->xMun = $empresa->municipio;
        $endereco->UF = $empresa->uf;
        $endereco->CEP = $empresa->cep;
        $endereco->cPais = '1058';
        $endereco->xPais = 'Brasil';
        $nfe->tagenderEmit($endereco);
    }

    protected function montarItens(Make $nfe, Venda $venda): void
    {
        foreach ($venda->itens as $index => $item) {
            $produto = $item->produto;
            $n = $index + 1;

            $prod = new \stdClass();
            $prod->item = $n;
            $prod->cProd = $produto->codigo_interno;
            $prod->cEAN = $produto->codigo_barras ?: 'SEM GTIN';
            $prod->xProd = $produto->nome . ($item->variante ? " - {$item->variante->cor} {$item->variante->tamanho}" : '');
            $prod->NCM = $produto->ncm;
            $prod->CFOP = $produto->cfop_padrao;
            $prod->uCom = $produto->unidade_comercial;
            $prod->qCom = $item->quantidade;
            $prod->vUnCom = number_format($item->preco_unitario, 10, '.', '');
            $prod->vProd = number_format($item->subtotal, 2, '.', '');
            $prod->cEANTrib = $produto->codigo_barras ?: 'SEM GTIN';
            $prod->uTrib = $produto->unidade_tributavel;
            $prod->qTrib = $item->quantidade;
            $prod->vUnTrib = number_format($item->preco_unitario, 10, '.', '');
            $prod->indTot = 1;
            $nfe->tagprod($prod);

            $imposto = new \stdClass();
            $imposto->item = $n;
            $imposto->vTotTrib = 0;
            $nfe->tagimposto($imposto);

            $icms = new \stdClass();
            $icms->item = $n;
            $icms->orig = $produto->origem_mercadoria;
            $icms->CSOSN = $produto->csosn;
            $nfe->tagICMSSN($icms);

            // PIS e COFINS - Simples Nacional geralmente CST 99 (outras operacoes)
            $pis = new \stdClass();
            $pis->item = $n;
            $pis->CST = '99';
            $pis->vBC = 0;
            $pis->pPIS = 0;
            $pis->vPIS = 0;
            $nfe->tagPIS($pis);

            $cofins = new \stdClass();
            $cofins->item = $n;
            $cofins->CST = '99';
            $cofins->vBC = 0;
            $cofins->pCOFINS = 0;
            $cofins->vCOFINS = 0;
            $nfe->tagCOFINS($cofins);
        }
    }

    protected function montarTotais(Make $nfe, Venda $venda): void
    {
        $std = new \stdClass();
        $std->vBC = 0;
        $std->vICMS = 0;
        $std->vICMSDeson = 0;
        $std->vFCP = 0;
        $std->vBCST = 0;
        $std->vST = 0;
        $std->vFCPST = 0;
        $std->vFCPSTRet = 0;
        $std->vProd = number_format($venda->total, 2, '.', '');
        $std->vFrete = 0;
        $std->vSeg = 0;
        $std->vDesc = 0;
        $std->vII = 0;
        $std->vIPI = 0;
        $std->vIPIDevol = 0;
        $std->vPIS = 0;
        $std->vCOFINS = 0;
        $std->vOutro = 0;
        $std->vNF = number_format($venda->total, 2, '.', '');
        $nfe->tagICMSTot($std);
    }

    protected function montarTransporte(Make $nfe): void
    {
        $std = new \stdClass();
        $std->modFrete = 9; // sem transporte
        $nfe->tagtransp($std);
    }

    protected function montarPagamento(Make $nfe, Venda $venda): void
    {
        $pagMap = ['dinheiro' => '01', 'credito' => '03', 'debito' => '04', 'pix' => '17'];

        $std = new \stdClass();
        $std->vTroco = 0;
        $nfe->tagpag($std);

        $det = new \stdClass();
        $det->indPag = 0;
        $det->tPag = $pagMap[$venda->forma_pagamento] ?? '99';
        $det->vPag = number_format($venda->total, 2, '.', '');
        $nfe->tagDetPag($det);
    }

    protected function montarResponsavelTecnico(Make $nfe): void
    {
        // Dados minimos - ajustar com seu CNPJ de desenvolvedor de software se for registrar como responsavel tecnico
        $std = new \stdClass();
        $std->CNPJ = config('nfe.resp_tecnico_cnpj', '');
        $std->xContato = config('nfe.resp_tecnico_contato', '');
        $std->email = config('nfe.resp_tecnico_email', '');
        $std->fone = config('nfe.resp_tecnico_fone', '');
        if ($std->CNPJ) {
            $nfe->taginfRespTec($std);
        }
    }

    protected function codigoUf(string $uf): int
    {
        $codigos = [
            'AC'=>12,'AL'=>17,'AP'=>16,'AM'=>13,'BA'=>29,'CE'=>23,'DF'=>53,'ES'=>32,
            'GO'=>52,'MA'=>21,'MT'=>51,'MS'=>50,'MG'=>31,'PA'=>15,'PB'=>25,'PR'=>41,
            'PE'=>26,'PI'=>22,'RJ'=>33,'RN'=>24,'RS'=>43,'RO'=>11,'RR'=>14,'SC'=>42,
            'SP'=>35,'SE'=>28,'TO'=>17,
        ];
        return $codigos[strtoupper($uf)] ?? 35;
    }

    protected function extrairProtocolo(string $resposta): array
    {
        $xml = new \SimpleXMLElement($resposta);
        $ns = $xml->getNamespaces(true);
        // A estrutura exata varia; vamos tratar e ajustar no teste real com a resposta impressa
        return [
            'autorizada' => false,
            'chave' => null,
            'numero_protocolo' => null,
            'motivo' => 'Ainda não testado com resposta real da SEFAZ',
        ];
    }
}