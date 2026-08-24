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
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use NFePHP\NFe\Complements;

class FiscalEmissorService
{
    protected NfeService $nfeService;
    protected ?string $chaveGerada = null;
    protected float $totalIBSUF = 0.0;
    protected float $totalIBSMun = 0.0;
    protected float $totalIBS = 0.0;
    protected float $totalCBS = 0.0;
    protected float $totalBCIBSCBS = 0.0;
    protected float $totalPIS = 0.0;
    protected float $totalCOFINS = 0.0;

    public function emitir(Venda $venda): array
    {
        $venda->load('itens.produto.ncm', 'itens.produto.tributacao', 'itens.variante', 'caixa.pdv');
        $pdv = $venda->caixa->pdv;

        if ($venda->numero_nfce) {
            $numero = $venda->numero_nfce;
            $serie = $venda->serie_nfce;
        } else {
            $numero = $pdv->numero_atual_nfce + 1;
            $serie = $pdv->serie_nfce;

            $pdv->update(['numero_atual_nfce' => $numero]);
            $venda->update(['numero_nfce' => $numero, 'serie_nfce' => $serie]);
        }

        try {
            $this->nfeService = new NfeService($pdv);
            $empresa = $this->nfeService->empresa();
            $tools = $this->nfeService->tools();
        } catch (\Throwable $e) {
            $venda->update([
                'status' => 'contingencia',
                'motivo_rejeicao' => 'Erro ao preparar emissão (certificado/config fiscal): ' . $e->getMessage(),
            ]);

            throw new Exception("Erro ao preparar emissão (NFC-e nº {$numero}): " . $e->getMessage());
        }

        $idLote = str_pad($numero, 15, '0', STR_PAD_LEFT);

        // Se essa venda ja foi comprometida em contingencia SEFAZ antes (tpEmis=9),
        // NAO remontamos o XML - reenviamos exatamente o mesmo documento ja gerado/impresso
        if ($venda->tp_emis == 9 && $venda->xml_contingencia) {
            $xmlAssinado = $venda->xml_contingencia;
            $this->chaveGerada = $venda->chave_nfe;

            try {
                $resposta = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);
            } catch (\Throwable $e) {
                throw new Exception("Ainda sem conexão com a SEFAZ (NFC-e nº {$numero} continua em contingência).");
            }

            return $this->processarResposta($resposta, $venda, $xmlAssinado);
        }

        // PASSO 1: montar e assinar o XML - falha aqui e problema de DADOS, nao de conexao
        try {
            $nfe = new Make('PL_010');

            $this->montarInfNFe($nfe);
            $this->montarIde($nfe, $empresa, $pdv, $numero, tpEmis: 1);
            $this->montarEmit($nfe, $empresa);
            $this->montarItens($nfe, $venda);
            $this->montarTotais($nfe, $venda);
            $this->montarTransporte($nfe);
            $this->montarPagamento($nfe, $venda);
            $this->montarResponsavelTecnico($nfe);

            $xmlBruto = $nfe->getXML();

            if (!$xmlBruto) {
                throw new Exception('Erro ao montar XML: ' . implode(' | ', $nfe->getErrors()));
            }

            $xmlAssinado = $tools->signNFe($xmlBruto);
        } catch (\Throwable $e) {
            // Erro de dado/schema - venda fica em contingencia normal (reenviavel via F1
            // depois de corrigir o cadastro), NUNCA vira contingencia SEFAZ real
            $venda->update([
                'status' => 'contingencia',
                'motivo_rejeicao' => $e->getMessage(),
            ]);

            throw new Exception("Erro ao montar/validar XML (NFC-e nº {$numero}): " . $e->getMessage());
        }

        // PASSO 2: enviar pra SEFAZ - falha aqui, sim, e problema de conexao -> contingencia SEFAZ real
        // (EXCETO quando a falha e na verdade rejeicao de schema/dados local, que nao e conexao)
        try {
            $resposta = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);
        } catch (\Throwable $e) {
            if ($this->pareceErroDeSchema($e)) {
                // Nao e problema de conexao - e um dado invalido que a lib recusou
                // ANTES de mandar pra SEFAZ. Trata igual ao PASSO 1: contingencia
                // normal (corrigivel), e salva o XML que foi rejeitado.
                $venda->update([
                    'status' => 'contingencia',
                    'motivo_rejeicao' => 'Rejeitado por schema/dados antes do envio: ' . $e->getMessage(),
                ]);

                $this->salvarXmlEmDisco($xmlAssinado, contingencia: true, venda: $venda);

                throw new Exception("XML rejeitado por schema/dados (NFC-e nº {$numero}): " . $e->getMessage());
            }

            return $this->entrarEmContingenciaSefaz($venda, $pdv, $empresa, $numero, $e->getMessage());
        }

        return $this->processarResposta($resposta, $venda, $xmlAssinado);
    }

    /**
     * Distingue uma rejeicao de schema/validacao local (problema de DADO, corrigivel)
     * de uma falha de conexao/timeout de verdade (motivo legitimo pra contingencia SEFAZ).
     * Ajuste as strings abaixo se notar outras mensagens de validacao da lib passando batido.
     */
    protected function pareceErroDeSchema(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        $indicadoresDeSchema = [
            'não é válido',
            'not expected',
            'XSD',
            'schema',
            'Element ',
            'is not expected',
        ];

        foreach ($indicadoresDeSchema as $indicador) {
            if (stripos($msg, $indicador) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monta um documento novo em modo contingencia SEFAZ (tpEmis=9),
     * valido para impressao imediata, mesmo sem internet.
     */
    protected function entrarEmContingenciaSefaz(Venda $venda, Pdv $pdv, Empresa $empresa, int $numero, string $motivoFalha): array
        {
            $dhCont = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:sP');
            $xJust = 'Falha de conectividade com a internet no momento da emissão.';
    
            try {
                $nfe = new Make('PL_010');
    
                $this->montarInfNFe($nfe);
                $this->montarIde($nfe, $empresa, $pdv, $numero, tpEmis: 9, dhCont: $dhCont, xJust: $xJust);
                $this->montarEmit($nfe, $empresa);
                $this->montarItens($nfe, $venda);
                $this->montarTotais($nfe, $venda);
                $this->montarTransporte($nfe);
                $this->montarPagamento($nfe, $venda);
                $this->montarResponsavelTecnico($nfe);
    
                $xmlBruto = $nfe->getXML();
    
                if (!$xmlBruto) {
                    throw new Exception('Erro ao montar XML de contingência: ' . implode(' | ', $nfe->getErrors()));
                }
    
                $tools = $this->nfeService->tools();
                $xmlAssinado = $tools->signNFe($xmlBruto);
            } catch (\Throwable $e2) {
                // A tentativa de contingencia TAMBEM falhou (provavelmente o mesmo
                // problema de dado que causou a falha original) - sem isso aqui,
                // a excecao subia sem nunca salvar nada nem atualizar a venda.
                $venda->update([
                    'status' => 'contingencia',
                    'motivo_rejeicao' => "Falha ao montar contingência: {$e2->getMessage()} | Motivo original: {$motivoFalha}",
                ]);
    
                throw new Exception(
                    "Falha crítica ao emitir NFC-e nº {$numero}: nem o envio normal nem a contingência " .
                    "puderam ser montados. Motivo original: {$motivoFalha} | Erro na contingência: {$e2->getMessage()}"
                );
            }
    
            // O documento ja e valido pra impressao a partir daqui - chave e definitiva
            $venda->update([
                'status' => 'contingencia',
                'tp_emis' => 9,
                'dh_cont' => $dhCont,
                'x_just' => $xJust,
                'xml_contingencia' => $xmlAssinado,
                'chave_nfe' => $this->chaveGerada,
                'motivo_rejeicao' => 'Emitido em contingência SEFAZ: ' . $motivoFalha,
            ]);
    
            $this->salvarXmlEmDisco($xmlAssinado, contingencia: true, venda: $venda);
    
            throw new Exception(
                "Sem conexão com a SEFAZ. NFC-e nº {$numero} emitida em CONTINGÊNCIA (tpEmis=9), " .
                "chave: {$this->chaveGerada}. Documento já é válido para impressão."
            );
        }

    protected function ratearDescontoGlobal(Venda $venda): array
    {
        $itens = $venda->itens->filter(fn($i) => !is_null($i->produto_id))->values();
        $descontoGlobal = $venda->desconto - $itens->sum('desconto');

        if ($descontoGlobal <= 0) {
            return $itens->map(fn($i) => [
                'item' => $i,
                'desconto_efetivo' => $i->desconto ?? 0,
            ])->toArray();
        }

        $subtotalBrutoTotal = $itens->sum(fn($i) => $i->preco_unitario * $i->quantidade);
        $somaRateios = 0;
        $resultado = [];

        foreach ($itens as $index => $item) {
            $subtotalBruto = $item->preco_unitario * $item->quantidade;
            $descontoItem = $item->desconto ?? 0;

            if ($index === count($itens) - 1) {
                // ultimo item absorve a diferenca de arredondamento
                $rateio = $descontoGlobal - $somaRateios;
            } else {
                $rateio = $subtotalBrutoTotal > 0
                    ? round(($descontoGlobal * ($subtotalBruto / $subtotalBrutoTotal)), 2)
                    : 0;
                $somaRateios += $rateio;
            }

            $descontoEfetivo = min($descontoItem + $rateio, $subtotalBruto);

            $resultado[] = [
                'item' => $item,
                'desconto_efetivo' => $descontoEfetivo,
            ];
        }

        return $resultado;
    }

    /**
     * Processa a resposta da SEFAZ (autorizacao ou rejeicao), seja do fluxo normal ou de um reenvio de contingencia.
     */
    protected function processarResposta(string $resposta, Venda $venda, string $xmlAssinado): array
    {
        $protocolo = $this->extrairProtocolo($resposta);

        if (!$protocolo['autorizada']) {
            $venda->update([
                'status' => 'contingencia',
                'motivo_rejeicao' => $protocolo['motivo'],
            ]);

            $this->salvarXmlEmDisco($xmlAssinado, contingencia: true, venda: $venda);

            throw new Exception('Rejeitada pela SEFAZ: ' . $protocolo['motivo']);
        }

        $venda->update([
            'status' => 'emitida',
            'chave_nfe' => $protocolo['chave'],
            'protocolo_nfe' => $protocolo['numero_protocolo'],
            'motivo_rejeicao' => null,
            'emitida_em' => now(),
        ]);

        try {
            $xmlProcessado = Complements::toAuthorize($xmlAssinado, $resposta);
        } catch (\Exception $e) {
            $xmlProcessado = $xmlAssinado;
        }

        $this->salvarXmlEmDisco($xmlProcessado, contingencia: false, venda: $venda);

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

    protected function montarIde(Make $nfe, $empresa, $pdv, int $numero, int $tpEmis = 1, ?string $dhCont = null, ?string $xJust = null): void
    {
        $dhEmi = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $cNF = str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $cUF = $this->codigoUf($empresa->uf);

        $chave = Keys::build(
            (string) $cUF,
            $dhEmi->format('y'),
            $dhEmi->format('m'),
            $empresa->cnpj,
            '65',
            (string) $pdv->serie_nfce,
            (string) $numero,
            (string) $tpEmis,
            $cNF
        );

        $this->chaveGerada = $chave;
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
        $std->tpEmis = $tpEmis;
        $std->cDV = $cDV;
        $std->tpAmb = (int) $empresa->ambiente;
        $std->finNFe = 1;
        $std->indFinal = 1;
        $std->indPres = 1;
        $std->procEmi = 0;
        $std->verProc = '1.0.0';

        // Campos extras exigidos apenas quando em contingencia (tpEmis != 1)
        if ($tpEmis != 1) {
            $std->dhCont = $dhCont;
            $std->xJust = $xJust;
        }

        $nfe->tagide($std);
    }

    protected function salvarXmlEmDisco(string $xmlAssinado, bool $contingencia, Venda $venda): void
    {
        if (!$this->chaveGerada) {
            return;
        }

        // Apaga o arquivo de contingencia anterior dessa mesma venda, se existir,
        // pra nao acumular um arquivo por tentativa
        if ($venda->ultimo_arquivo_xml && file_exists($venda->ultimo_arquivo_xml)) {
            @unlink($venda->ultimo_arquivo_xml);
        }

        $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $ano = $agora->format('y');
        $mes = $agora->format('m');
        $dia = $agora->format('d');

        $pasta = storage_path("app/XML_nfce/{$ano}/{$mes}/{$dia}");

        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        $sufixo = $contingencia ? 'contingencia' : 'nfe';
        $arquivo = "{$pasta}/{$this->chaveGerada}-{$sufixo}.xml";

        file_put_contents($arquivo, $xmlAssinado);

        $venda->update(['ultimo_arquivo_xml' => $arquivo]);
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
            // zera acumuladores de IBS/CBS - essa funcao pode ser chamada 2x na mesma
            // instancia (fluxo normal + fluxo de contingencia), entao sem isso os
            // totais duplicariam na segunda chamada
            $this->totalIBSUF = 0.0;
            $this->totalIBSMun = 0.0;
            $this->totalIBS = 0.0;
            $this->totalCBS = 0.0;
            $this->totalBCIBSCBS = 0.0;
            $this->totalPIS = 0.0;
            $this->totalCOFINS = 0.0;

            $itensComDesconto = $this->ratearDescontoGlobal($venda);

            foreach ($itensComDesconto as $index => $dado) {
                $item = $dado['item'];
                $descontoEfetivo = $dado['desconto_efetivo'];
                $produto = $item->produto;
                $n = $index + 1;
                $trib = $produto->tributacao;

                $prod = new \stdClass();
                $prod->item = $n;
                $prod->cProd = $produto->codigo_interno;
                $prod->cEAN = $produto->codigo_barras ?: 'SEM GTIN';
                $prod->xProd = $produto->nome . ($item->variante ? " - {$item->variante->cor} {$item->variante->tamanho}" : '');
                $prod->NCM = $produto->ncm->codigo;
                if ($produto->cest) {
                    $prod->CEST = $produto->cest->codigo;
                }
                $prod->CFOP = $trib->cfop;
                $prod->uCom = $produto->unidade_comercial;
                $prod->qCom = $item->quantidade;
                $prod->vUnCom = number_format($item->preco_unitario, 10, '.', '');
                $prod->vProd = number_format($item->preco_unitario * $item->quantidade, 2, '.', '');
                $prod->cEANTrib = $produto->codigo_barras ?: 'SEM GTIN';
                $prod->uTrib = $produto->unidade_tributavel;
                $prod->qTrib = $item->quantidade;
                $prod->vUnTrib = number_format($item->preco_unitario, 10, '.', '');

                if ($descontoEfetivo > 0) {
                    $prod->vDesc = number_format($descontoEfetivo, 2, '.', '');
                }

                $prod->indTot = 1;
                $nfe->tagprod($prod);

                $imposto = new \stdClass();
                $imposto->item = $n;
                $imposto->vTotTrib = 0;
                $nfe->tagimposto($imposto);


    // ... dentro do tagICMSSN ou tagICMS, dependendo do CRT:
    $empresa = $this->nfeService->empresa();

    if ($empresa->crt <= 2) {
        // Simples Nacional
        $icms = new \stdClass();
        $icms->item = $n;
        $icms->orig = $produto->origem_mercadoria;
        $icms->CSOSN = $trib->csosn;
        $nfe->tagICMSSN($icms);
    } else {
        // Lucro Presumido / Real
        $icms = new \stdClass();
        $icms->item = $n;
        $icms->orig = $produto->origem_mercadoria;
        $icms->CST = $trib->cst_icms;
        $icms->modBC = 3;
        $icms->vBC = number_format($item->preco_unitario * $item->quantidade, 2, '.', '');
        $icms->pICMS = number_format($trib->aliquota_icms, 2, '.', '');
        $icms->vICMS = number_format(($item->preco_unitario * $item->quantidade * $trib->aliquota_icms / 100), 2, '.', '');
        $nfe->tagICMS($icms);
    }

                // PIS e COFINS
                // Simples Nacional (CRT 1/2): PIS/COFINS embutido no DAS, sempre CST 99 zerado.
                // Lucro Presumido/Real (CRT 3): usa a classificação cadastrada no produto.
                if ($empresa->crt <= 2 || !$produto->pisCofins) {
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

                    $this->totalPIS += 0;
                    $this->totalCOFINS += 0;
                } else {
                    $classPisCofins = $produto->pisCofins;
                    $baseCalculoItem = $item->preco_unitario * $item->quantidade;
                    $pAliquotaPis = (float) ($classPisCofins->aliquota_pis ?? 0);
                    $pAliquotaCofins = (float) ($classPisCofins->aliquota_cofins ?? 0);

                    $pis = new \stdClass();
                    $pis->item = $n;
                    $pis->CST = $classPisCofins->codigo;
                    $pis->vBC = number_format($baseCalculoItem, 2, '.', '');
                    $pis->pPIS = number_format($pAliquotaPis, 4, '.', '');
                    $pis->vPIS = number_format($baseCalculoItem * $pAliquotaPis / 100, 2, '.', '');
                    $nfe->tagPIS($pis);

                    $cofins = new \stdClass();
                    $cofins->item = $n;
                    $cofins->CST = $classPisCofins->codigo;
                    $cofins->vBC = number_format($baseCalculoItem, 2, '.', '');
                    $cofins->pCOFINS = number_format($pAliquotaCofins, 4, '.', '');
                    $cofins->vCOFINS = number_format($baseCalculoItem * $pAliquotaCofins / 100, 2, '.', '');
                    $nfe->tagCOFINS($cofins);

                    $this->totalPIS += (float) $pis->vPIS;
                    $this->totalCOFINS += (float) $cofins->vCOFINS;
                }

                // ==================== IBS/CBS (Reforma Tributária) ====================
                // Os percentuais de transicao (2026: 0,10% IBS-UF / 0,00% IBS-Mun / 0,90% CBS)
                // sao nacionais e mudam por lei nos proximos anos - ficam em config, nunca hardcoded.
                $classTrib = $produto->classificacaoTributaria;

                if ($classTrib && config('fiscal.emitir_ibscbs', false)) {
                    $baseCalculoItem = $item->preco_unitario * $item->quantidade;

                    $pIBSUF  = config('fiscal.aliquotas_ibscbs_transicao.ibs_uf', 0.10);
                    $pIBSMun = config('fiscal.aliquotas_ibscbs_transicao.ibs_mun', 0.00);
                    $pCBS    = config('fiscal.aliquotas_ibscbs_transicao.cbs', 0.90);

                    $vIBSUF  = round($baseCalculoItem * $pIBSUF / 100, 2);
                    $vIBSMun = round($baseCalculoItem * $pIBSMun / 100, 2);
                    $vIBS    = round($vIBSUF + $vIBSMun, 2);
                    $vCBS    = round($baseCalculoItem * $pCBS / 100, 2);

                    $ibscbs = new \stdClass();
                    $ibscbs->item = $n;
                    $ibscbs->CST = $classTrib->cst_codigo;
                    $ibscbs->cClassTrib = $classTrib->codigo;

                    $ibscbs->gIBSCBS = new \stdClass();
                    $ibscbs->gIBSCBS->vBC = number_format($baseCalculoItem, 2, '.', '');

                    $ibscbs->gIBSCBS->gIBSUF = new \stdClass();
                    $ibscbs->gIBSCBS->gIBSUF->pIBSUF = number_format($pIBSUF, 4, '.', '');
                    $ibscbs->gIBSCBS->gIBSUF->vIBSUF = number_format($vIBSUF, 2, '.', '');

                    $ibscbs->gIBSCBS->gIBSMun = new \stdClass();
                    $ibscbs->gIBSCBS->gIBSMun->pIBSMun = number_format($pIBSMun, 4, '.', '');
                    $ibscbs->gIBSCBS->gIBSMun->vIBSMun = number_format($vIBSMun, 2, '.', '');

                    $ibscbs->gIBSCBS->vIBS = number_format($vIBS, 2, '.', '');

                    $ibscbs->gIBSCBS->gCBS = new \stdClass();
                    $ibscbs->gIBSCBS->gCBS->pCBS = number_format($pCBS, 4, '.', '');
                    $ibscbs->gIBSCBS->gCBS->vCBS = number_format($vCBS, 2, '.', '');

                    $nfe->tagIBSCBS($ibscbs);

                    $this->totalIBSUF += $vIBSUF;
                    $this->totalIBSMun += $vIBSMun;
                    $this->totalIBS += $vIBS;
                    $this->totalCBS += $vCBS;
                    $this->totalBCIBSCBS += $baseCalculoItem;
                }
            }
        }

    protected function montarTotais(Make $nfe, Venda $venda): void
        {
            // vProd deve ser a soma dos valores BRUTOS dos itens (antes do desconto)
            $vProdBruto = $venda->itens->sum(fn($item) => $item->preco_unitario * $item->quantidade);

            $std = new \stdClass();
            $std->vBC = 0;
            $std->vICMS = 0;
            $std->vICMSDeson = 0;
            $std->vFCP = 0;
            $std->vBCST = 0;
            $std->vST = 0;
            $std->vFCPST = 0;
            $std->vFCPSTRet = 0;
            $std->vProd = number_format($vProdBruto, 2, '.', '');
            $std->vFrete = 0;
            $std->vSeg = 0;
            if (($venda->desconto ?? 0) > 0) {
                $std->vDesc = number_format($venda->desconto, 2, '.', '');
            }
            $std->vII = 0;
            $std->vIPI = 0;
            $std->vIPIDevol = 0;
            $std->vPIS = number_format($this->totalPIS, 2, '.', '');
            $std->vCOFINS = number_format($this->totalCOFINS, 2, '.', '');
            $std->vOutro = 0;
            $std->vNF = number_format($venda->total, 2, '.', ''); // total liquido (o que o cliente realmente pagou)
            $nfe->tagICMSTot($std);

            // ==================== IBS/CBS Totais ====================
            if ($this->totalBCIBSCBS > 0) {
                $stdIBSCBSTot = new \stdClass();
                $stdIBSCBSTot->vBCIBSCBS = number_format($this->totalBCIBSCBS, 2, '.', '');
                $stdIBSCBSTot->vIBS = number_format($this->totalIBS, 2, '.', '');
                $stdIBSCBSTot->vCBS = number_format($this->totalCBS, 2, '.', '');

                $stdIBSCBSTot->gIBSUF = new \stdClass();
                $stdIBSCBSTot->gIBSUF->vDif = '0.00';
                $stdIBSCBSTot->gIBSUF->vDevTrib = '0.00';
                $stdIBSCBSTot->gIBSUF->vIBSUF = number_format($this->totalIBSUF, 2, '.', '');

                $stdIBSCBSTot->gIBSMun = new \stdClass();
                $stdIBSCBSTot->gIBSMun->vDif = '0.00';
                $stdIBSCBSTot->gIBSMun->vDevTrib = '0.00';
                $stdIBSCBSTot->gIBSMun->vIBSMun = number_format($this->totalIBSMun, 2, '.', '');

                $stdIBSCBSTot->gIBS = new \stdClass();
                $stdIBSCBSTot->gIBS->vCredPres = '0.00';
                $stdIBSCBSTot->gIBS->vCredPresCondSus = '0.00';

                $stdIBSCBSTot->gCBS = new \stdClass();
                $stdIBSCBSTot->gCBS->vDif = '0.00';
                $stdIBSCBSTot->gCBS->vDevTrib = '0.00';
                $stdIBSCBSTot->gCBS->vCredPres = '0.00';
                $stdIBSCBSTot->gCBS->vCredPresCondSus = '0.00';

                $nfe->tagIBSCBSTot($stdIBSCBSTot);
            }
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
        $std->vTroco = number_format($venda->troco ?? 0, 2, '.', '');
        $nfe->tagpag($std);

        foreach ($venda->pagamentos as $pagamento) {
            $det = new \stdClass();
            $det->indPag = 0;
            $det->tPag = $pagMap[$pagamento->forma_pagamento] ?? '99';
            $det->vPag = number_format($pagamento->valor, 2, '.', '');

            if (in_array($pagamento->forma_pagamento, ['credito', 'debito', 'pix'])) {
                $det->tpIntegra = 2;
            }

            $nfe->tagDetPag($det);
        }
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
        $idEvento = $infInut?->getAttribute('Id');

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

        $this->salvarXmlInutilizacaoEmDisco($resposta, $idEvento);

        // Cancela qualquer venda em contingencia/pendente que estava presa nessa faixa de numero,
        // e estorna o estoque de cada item vendido nela
        $vendasAfetadas = Venda::whereIn('status', ['contingencia', 'pendente'])
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

    /**
     * Salva o XML da inutilizacao em disco, na mesma estrutura de pastas por data usada pras NFC-e.
     */
    protected function salvarXmlInutilizacaoEmDisco(string $resposta, ?string $idEvento): void
    {
        if (!$idEvento) {
            $idEvento = 'inutilizacao_' . now()->format('YmdHis'); // fallback, caso a SEFAZ nao retorne o Id
        }

        $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $ano = $agora->format('y');
        $mes = $agora->format('m');
        $dia = $agora->format('d');

        $pasta = storage_path("app/XML_nfce/{$ano}/{$mes}/{$dia}");

        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        $arquivo = "{$pasta}/{$idEvento}-inutilizado.xml";

        file_put_contents($arquivo, $resposta);
    }

        public function cancelar(Venda $venda, string $justificativa): array
    {
        if ($venda->status !== 'emitida') {
            throw new Exception('Só é possível cancelar vendas com NFC-e já emitida.');
        }

        $pdv = $venda->caixa->pdv;
        $this->nfeService = new NfeService($pdv);
        $tools = $this->nfeService->tools();

        $resposta = $tools->sefazCancela($venda->chave_nfe, $justificativa, $venda->protocolo_nfe);

        $dom = new \DOMDocument();
        $dom->loadXML($resposta);

        $infEvento = $dom->getElementsByTagName('infEvento')->item(0);
        $cStat = $infEvento?->getElementsByTagName('cStat')->item(0)?->nodeValue;
        $xMotivo = $infEvento?->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
        $nProt = $infEvento?->getElementsByTagName('nProt')->item(0)?->nodeValue;

        // 135 = Evento registrado e vinculado a NF-e (cancelamento homologado)
        $sucesso = $cStat === '135';

        if (!$sucesso) {
            throw new Exception('Falha no cancelamento: ' . $xMotivo);
        }

        // Estorna o estoque de cada item, igual fizemos na inutilizacao
        DB::transaction(function () use ($venda, $nProt, $justificativa) {
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
                'motivo_cancelamento' => "Cancelado pelo operador: {$justificativa} (protocolo cancelamento: {$nProt})",
            ]);
        });

        return ['protocolo' => $nProt, 'motivo' => $xMotivo];
    }
}