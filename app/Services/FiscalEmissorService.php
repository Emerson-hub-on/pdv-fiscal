<?php

namespace App\Services;

use App\Models\Venda;
use NFePHP\NFe\Make;
use NFePHP\Common\Exception\SefazException;
use NFePHP\Common\Certificate;
use Exception;
use NFePHP\Common\Keys;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\Inutilizacao;
use App\Models\Pdv;
use Illuminate\Support\Facades\DB;

class FiscalEmissorService
{
    protected NfeService $nfeService;

    public function emitir(Venda $venda): array
    {
        $venda->load('itens.produto', 'itens.variante', 'caixa.pdv');
        $pdv = $venda->caixa->pdv;

        $this->nfeService = new NfeService($pdv);
        $empresa = $this->nfeService->empresa();

        if ($venda->numero_nfce) {
            $numero = $venda->numero_nfce;
            $serie = $venda->serie_nfce;
        } else {
            $numero = $pdv->numero_atual_nfce + 1;
            $serie = $pdv->serie_nfce;

            $pdv->update(['numero_atual_nfce' => $numero]);
            $venda->update(['numero_nfce' => $numero, 'serie_nfce' => $serie]);
        }

        $nfe = new Make();

        $this->montarInfNFe($nfe);
        $this->montarIde($nfe, $empresa, $pdv, $numero);
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

        $idLote = str_pad($numero, 15, '0', STR_PAD_LEFT);

        try {
            $resposta = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);
        } catch (\Throwable $e) {
            $venda->update([
                'status' => 'contingencia',
                'motivo_rejeicao' => 'Sem conexão com a SEFAZ: ' . $e->getMessage(),
            ]);

            throw new Exception("Sem conexão com a SEFAZ. Venda registrada em contingência (NFC-e nº {$numero}).");
        }

        $protocolo = $this->extrairProtocolo($resposta);

        if (!$protocolo['autorizada']) {
            $venda->update([
                'status' => 'contingencia',
                'motivo_rejeicao' => $protocolo['motivo'],
            ]);

            throw new Exception('Rejeitada pela SEFAZ: ' . $protocolo['motivo']);
        }

        $venda->update([
            'status' => 'emitida',
            'chave_nfe' => $protocolo['chave'],
            'protocolo_nfe' => $protocolo['numero_protocolo'],
            'motivo_rejeicao' => null,
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

    protected function montarIde(Make $nfe, $empresa, $pdv, int $numero): void
    {
        $dhEmi = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $cNF = str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $cUF = $this->codigoUf($empresa->uf);

        // Calcula a chave de acesso completa (44 digitos) pra extrair o DV real
        $chave = Keys::build(
            (string) $cUF,
            $dhEmi->format('y'),
            $dhEmi->format('m'),
            $empresa->cnpj,
            '65', // modelo NFC-e
            (string) $pdv->serie_nfce,
            (string) $numero,
            '1', // tpEmis normal
            $cNF
        );

        $cDV = substr($chave, -1);

        $std = new \stdClass();
        $std->cUF = $cUF;
        $std->cNF = $cNF;
        $std->natOp = 'Venda de mercadoria';
        $std->mod = 65;
        $std->serie = $pdv->serie_nfce;
        $std->nNF = $numero;
        $std->dhEmi = $dhEmi->format('Y-m-d\TH:i:sP');
        $std->tpNF = 1;
        $std->idDest = 1;
        $std->cMunFG = $empresa->cod_municipio;
        $std->tpImp = 4;
        $std->tpEmis = 1;
        $std->cDV = $cDV; // agora e o digito real, nao mais fixo em 0
        $std->tpAmb = (int) $empresa->ambiente;
        $std->finNFe = 1;
        $std->indFinal = 1;
        $std->indPres = 1;
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
        $dom = new \DOMDocument();
        $dom->loadXML($resposta);

        $infProt = $dom->getElementsByTagName('infProt')->item(0);

        if (!$infProt) {
            $xMotivoLote = $dom->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
            return [
                'autorizada' => false,
                'chave' => null,
                'numero_protocolo' => null,
                'motivo' => $xMotivoLote ?: 'Lote não processado (sem protocolo retornado).',
            ];
        }

        $cStat = $infProt->getElementsByTagName('cStat')->item(0)?->nodeValue;
        $xMotivo = $infProt->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
        $chave = $infProt->getElementsByTagName('chNFe')->item(0)?->nodeValue;
        $protocolo = $infProt->getElementsByTagName('nProt')->item(0)?->nodeValue;

        return [
            'autorizada' => $cStat === '100', // 100 = Autorizado o uso da NF-e
            'chave' => $chave,
            'numero_protocolo' => $protocolo,
            'motivo' => $xMotivo,
        ];
    }

    public function inutilizar(Pdv $pdv, int $numeroInicial, int $numeroFinal, string $justificativa): array
    {
        $this->nfeService = new NfeService($pdv);
        $tools = $this->nfeService->tools();

        $resposta = $tools->sefazInutiliza($pdv->serie_nfce, $numeroInicial, $numeroFinal, $justificativa);

        $dom = new \DOMDocument();
        $dom->loadXML($resposta);

        $infInut = $dom->getElementsByTagName('infInut')->item(0);
        $cStat = $infInut?->getElementsByTagName('cStat')->item(0)?->nodeValue;
        $xMotivo = $infInut?->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
        $nProt = $infInut?->getElementsByTagName('nProt')->item(0)?->nodeValue;

        $sucesso = $cStat === '102';

        Inutilizacao::create([
            'pdv_id' => $pdv->id,
            'serie' => $pdv->serie_nfce,
            'numero_inicial' => $numeroInicial,
            'numero_final' => $numeroFinal,
            'justificativa' => $justificativa,
            'status' => $sucesso ? 'sucesso' : 'erro',
            'protocolo' => $nProt,
            'motivo' => $xMotivo,
            'operador_id' => auth()->id(),
        ]);

        if (!$sucesso) {
            throw new Exception('Falha na inutilização: ' . $xMotivo);
        }

        // Cancela qualquer venda em contingencia que estava presa nessa faixa de numero,
        // e estorna o estoque de cada item vendido nela
        $vendasAfetadas = Venda::where('status', 'contingencia')
            ->where('serie_nfce', $pdv->serie_nfce)
            ->whereHas('caixa', fn($q) => $q->where('pdv_id', $pdv->id))
            ->whereBetween('numero_nfce', [$numeroInicial, $numeroFinal])
            ->with('itens')
            ->get();

        foreach ($vendasAfetadas as $venda) {
            DB::transaction(function () use ($venda, $nProt) {
                foreach ($venda->itens as $item) {
                    if ($item->produto_variante_id) {
                        ProdutoVariante::where('id', $item->produto_variante_id)
                            ->lockForUpdate()
                            ->increment('estoque', $item->quantidade);
                    } else {
                        Produto::where('id', $item->produto_id)
                            ->lockForUpdate()
                            ->increment('estoque', $item->quantidade);
                    }
                }

                $venda->update([
                    'status' => 'cancelada',
                    'motivo_cancelamento' => "Número inutilizado (protocolo {$nProt}). Venda não será emitida.",
                ]);
            });
        }

        return ['protocolo' => $nProt, 'motivo' => $xMotivo];
    }
}