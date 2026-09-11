<?php

namespace App\Services;

use App\Models\NotaFiscal;
use NFePHP\NFe\Make;
use NFePHP\Common\Certificate;
use NFePHP\Common\Keys;
use NFePHP\NFe\Complements;
use App\Models\Empresa;
use Exception;

class NotaFiscalService
{
    protected Empresa $empresa;
    protected \NFePHP\NFe\Tools $tools;
    protected ?string $chaveGerada = null;
    protected float $totalICMSBC = 0.0;
    protected float $totalICMS = 0.0;
    protected float $totalPIS = 0.0;
    protected float $totalCOFINS = 0.0;
    protected bool $houveIBSCBS = false;

    public function __construct()
    {
        $this->empresa = Empresa::first();

        if (!$this->empresa) {
            throw new Exception('Dados da empresa não cadastrados. Preencha o cadastro da empresa antes de emitir.');
        }

        if (!$this->empresa->certificado_base64) {
            throw new Exception('Certificado digital não cadastrado.');
        }

        $this->tools = $this->criarTools();
    }

    protected function criarTools(): \NFePHP\NFe\Tools
    {
        $config = [
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb"       => (int) $this->empresa->ambiente,
            "razaosocial" => $this->empresa->razao_social,
            "siglaUF"     => $this->empresa->uf,
            "cnpj"        => $this->empresa->cnpj,
            "schemes"     => "PL_010_V1.30",
            "versao"      => '4.00',
            "tokenIBPT"   => "",
            // sem CSC/CSCid — não existe em NF-e modelo 55
        ];

        $certificadoConteudo = base64_decode($this->empresa->certificado_base64);
        $certificate = Certificate::readPfx($certificadoConteudo, $this->empresa->certificado_senha);

        $tools = new \NFePHP\NFe\Tools(json_encode($config), $certificate);
        $tools->model('55');

        return $tools;
    }

    public function emitir(NotaFiscal $notaFiscal): array
    {
        $notaFiscal->load('itens.produto', 'itens.ncm', 'itens.cest', 'itens.tributacao', 'itens.pisCofins', 'itens.classificacaoTributaria', 'cliente');

        $idLote = str_pad($notaFiscal->numero, 15, '0', STR_PAD_LEFT);

        // PASSO 1: montar e assinar — erro aqui é problema de DADOS
        $xmlBruto = null;
        $xmlAssinado = null;

        try {
            $nfe = new Make('PL_010');

            $this->montarInfNFe($nfe);
            $this->montarIde($nfe, $notaFiscal);
            $this->montarEmit($nfe);
            $this->montarDest($nfe, $notaFiscal);
            $this->montarItens($nfe, $notaFiscal);
            $this->montarTotais($nfe, $notaFiscal);
            $this->montarTransporte($nfe);
            $this->montarPagamento($nfe);
            $this->montarResponsavelTecnico($nfe);

            $xmlBruto = $nfe->getXML();

            if (!$xmlBruto) {
                throw new Exception('Erro ao montar XML: ' . implode(' | ', $nfe->getErrors()));
            }

            $xmlAssinado = $this->tools->signNFe($xmlBruto);
        } catch (\Throwable $e) {
            if ($xmlAssinado) {
                $this->salvarXmlEmDisco($xmlAssinado, $notaFiscal);
            } elseif ($xmlBruto) {
                $this->salvarXmlEmDisco($xmlBruto, $notaFiscal);
            }

            throw new Exception("Erro ao montar/validar XML (NF-e nº {$notaFiscal->numero}): " . $e->getMessage());
        }

        // PASSO 2: enviar pra SEFAZ
        try {
            $resposta = $this->tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);
        } catch (\Throwable $e) {
            throw new Exception("Falha de comunicação com a SEFAZ (NF-e nº {$notaFiscal->numero}): " . $e->getMessage());
        }

        return $this->processarResposta($resposta, $notaFiscal, $xmlAssinado);
    }

    protected function processarResposta(string $resposta, NotaFiscal $notaFiscal, string $xmlAssinado): array
    {
        $protocolo = $this->extrairProtocolo($resposta);

        if (!$protocolo['autorizada']) {
            $this->salvarXmlEmDisco($xmlAssinado, $notaFiscal);
            throw new Exception('Rejeitada pela SEFAZ: ' . $protocolo['motivo']);
        }

        try {
            $xmlProcessado = Complements::toAuthorize($xmlAssinado, $resposta);
        } catch (\Exception $e) {
            $xmlProcessado = $xmlAssinado;
        }

        $this->salvarXmlEmDisco($xmlProcessado, $notaFiscal);

        return [
            'chave_acesso' => $protocolo['chave'],
            'protocolo'    => $protocolo['numero_protocolo'],
            'xml'          => $xmlProcessado,
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

    protected function montarIde(Make $nfe, NotaFiscal $notaFiscal): void
    {
        $dhEmi = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $cNF = str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $cUF = $this->codigoUf($this->empresa->uf);

        $chave = Keys::build(
            (string) $cUF,
            $dhEmi->format('y'),
            $dhEmi->format('m'),
            $this->empresa->cnpj,
            '55',
            (string) $notaFiscal->serie,
            (string) $notaFiscal->numero,
            '1', // tpEmis: normal — sem contingência SEFAZ nesta primeira versão
            $cNF
        );

        $this->chaveGerada = $chave;
        $cDV = substr($chave, -1);

        // Operação interna (mesma UF) ou interestadual, conforme UF do cliente
        $idDest = ($notaFiscal->cliente->uf === $this->empresa->uf) ? 1 : 2;

        // Consumidor final: por ora, deriva do indicador de IE do cliente
        // (não_contribuinte = consumidor final). Ajustável depois se precisar de regra própria.
        $indFinal = $notaFiscal->cliente->indicador_ie === 'nao_contribuinte' ? 1 : 0;

        $std = new \stdClass();
        $std->cUF = $cUF;
        $std->cNF = $cNF;
        $std->natOp = $notaFiscal->natureza_operacao;
        $std->mod = 55;
        $std->serie = $notaFiscal->serie;
        $std->nNF = $notaFiscal->numero;
        $std->dhEmi = $dhEmi->format('Y-m-d\TH:i:sP');
        $std->tpNF = $notaFiscal->tipo_operacao === 'entrada' ? 0 : 1;
        $std->idDest = $idDest;
        $std->cMunFG = $this->empresa->cod_municipio;
        $std->tpImp = 1; // DANFE retrato
        $std->tpEmis = 1;
        $std->cDV = $cDV;
        $std->tpAmb = (int) $this->empresa->ambiente;
        $std->finNFe = $notaFiscal->finalidade;
        $std->indFinal = $indFinal;
        $std->indPres = 9; // operação não presencial (montada no admin, não no balcão)

        // Obrigatório desde NT 2020.006 quando indPres != 1 (presencial):
        // 0 = Operação sem intermediador (venda direta, sem marketplace)
        // 1 = Operação em site/plataforma de terceiro (marketplace)
        $std->indIntermed = 0;

        $std->procEmi = 0;
        $std->verProc = '1.0.0';

        $nfe->tagide($std);
    }

    protected function montarEmit(Make $nfe): void
    {
        $std = new \stdClass();
        $std->CNPJ = $this->empresa->cnpj;
        $std->xNome = $this->empresa->razao_social;
        $std->xFant = $this->empresa->nome_fantasia;
        $std->IE = $this->empresa->ie;
        $std->CRT = $this->empresa->crt;
        $nfe->tagemit($std);

        $endereco = new \stdClass();
        $endereco->xLgr = $this->empresa->logradouro;
        $endereco->nro = $this->empresa->numero;
        $endereco->xCpl = $this->empresa->complemento;
        $endereco->xBairro = $this->empresa->bairro;
        $endereco->cMun = $this->empresa->cod_municipio;
        $endereco->xMun = $this->empresa->municipio;
        $endereco->UF = $this->empresa->uf;
        $endereco->CEP = $this->empresa->cep;
        $endereco->cPais = '1058';
        $endereco->xPais = 'Brasil';
        $nfe->tagenderEmit($endereco);
    }

    /**
     * Diferente da NFC-e (onde o destinatário é opcional), na NF-e modelo 55
     * o cliente e o endereço completo são obrigatórios pelo schema da SEFAZ.
     */
    protected function montarDest(Make $nfe, NotaFiscal $notaFiscal): void
    {
        $cliente = $notaFiscal->cliente;

        $enderecoCompleto = $cliente->logradouro && $cliente->numero && $cliente->bairro
            && $cliente->municipio && $cliente->cod_municipio && $cliente->uf && $cliente->cep;

        if (!$enderecoCompleto) {
            throw new Exception("Endereço do cliente '{$cliente->nome}' está incompleto — obrigatório para NF-e modelo 55.");
        }

        $std = new \stdClass();

        if (strlen($cliente->cpf_cnpj) === 11) {
            $std->CPF = $cliente->cpf_cnpj;
        } else {
            $std->CNPJ = $cliente->cpf_cnpj;
        }

        // Em homologação, a SEFAZ EXIGE esse texto fixo no lugar do nome real
        // (rejeição 228) — só em produção (ambiente=1) o nome do cliente vai de fato.
        $std->xNome = (int) $this->empresa->ambiente === 2
            ? 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL'
            : $cliente->nome;

        $indIEDestMap = [
            'contribuinte' => 1,
            'isento' => 2,
            'nao_contribuinte' => 9,
        ];
        $std->indIEDest = $indIEDestMap[$cliente->indicador_ie] ?? 9;

        if ($cliente->indicador_ie === 'contribuinte' && $cliente->ie) {
            $std->IE = $cliente->ie;
        }

        if ($cliente->email) {
            $std->email = $cliente->email;
        }

        $nfe->tagdest($std);

        $endereco = new \stdClass();
        $endereco->xLgr = $cliente->logradouro;
        $endereco->nro = $cliente->numero;
        $endereco->xCpl = $cliente->complemento;
        $endereco->xBairro = $cliente->bairro;
        $endereco->cMun = $cliente->cod_municipio;
        $endereco->xMun = $cliente->municipio;
        $endereco->UF = $cliente->uf;
        $endereco->CEP = $cliente->cep;
        $endereco->cPais = '1058';
        $endereco->xPais = 'Brasil';
        $nfe->tagenderDest($endereco);
    }

    protected function gtinValido(?string $codigo, bool $marcadoComoValido): bool
    {
        if (!$marcadoComoValido || !$codigo || !ctype_digit($codigo)) {
            return false;
        }

        if (!in_array(strlen($codigo), [8, 12, 13, 14], true)) {
            return false;
        }

        $digitos = str_split($codigo);
        $digitoVerificador = (int) array_pop($digitos);
        $digitos = array_reverse($digitos);

        $soma = 0;
        foreach ($digitos as $posicao => $digito) {
            $peso = ($posicao % 2 === 0) ? 3 : 1;
            $soma += ((int) $digito) * $peso;
        }

        $dvCalculado = (10 - ($soma % 10)) % 10;

        return $dvCalculado === $digitoVerificador;
    }

    protected function montarItens(Make $nfe, NotaFiscal $notaFiscal): void
    {
        $this->totalPIS = 0.0;
        $this->totalCOFINS = 0.0;
        $this->totalICMSBC = 0.0;
        $this->totalICMS = 0.0;
        $this->houveIBSCBS = false;

        $itensComDesconto = $this->ratearDescontoGlobal($notaFiscal);

        foreach ($itensComDesconto as $index => $dado) {
            $item = $dado['item'];
            $descontoEfetivo = $dado['desconto_efetivo'];
            $produto = $item->produto;
            $n = $index + 1;

            $temEanValido = $this->gtinValido($produto->codigo_barras, (bool) $produto->codigo_barras_valido);

            $prod = new \stdClass();
            $prod->item = $n;
            $prod->cProd = $produto->codigo_interno;
            $prod->cEAN = $temEanValido ? $produto->codigo_barras : 'SEM GTIN';
            $prod->xProd = $produto->nome;
            $prod->NCM = $item->ncm?->codigo;
            if ($item->cest) {
                $prod->CEST = $item->cest->codigo;
            }
            $prod->CFOP = $item->cfop;
            $prod->uCom = $produto->unidade_comercial;
            $prod->qCom = $item->quantidade;
            $prod->vUnCom = number_format($item->valor_unitario, 10, '.', '');
            $prod->vProd = number_format($item->valor_unitario * $item->quantidade, 2, '.', '');
            $prod->cEANTrib = $temEanValido ? $produto->codigo_barras : 'SEM GTIN';
            $prod->uTrib = $produto->unidade_tributavel;
            $prod->qTrib = $item->quantidade;
            $prod->vUnTrib = number_format($item->valor_unitario, 10, '.', '');

            if ($descontoEfetivo > 0) {
                $prod->vDesc = number_format($descontoEfetivo, 2, '.', '');
            }

            $prod->indTot = 1;
            $nfe->tagprod($prod);

            $imposto = new \stdClass();
            $imposto->item = $n;
            $imposto->vTotTrib = 0;
            $nfe->tagimposto($imposto);

            $trib = $item->tributacao;

            if ($this->empresa->crt <= 2) {
                $icms = new \stdClass();
                $icms->item = $n;
                $icms->orig = $produto->origem_mercadoria;
                $icms->CSOSN = $trib?->csosn;
                $nfe->tagICMSSN($icms);
            } else {
                $cstIcms = str_pad((string) (int) ($trib?->cst_icms ?? 0), 2, '0', STR_PAD_LEFT);

                $icms = new \stdClass();
                $icms->item = $n;
                $icms->orig = $produto->origem_mercadoria;
                $icms->CST = $cstIcms;

                $cstsComBaseCalculo = ['00', '10', '20', '70', '90'];

                if (in_array($cstIcms, $cstsComBaseCalculo, true)) {
                    $icms->modBC = 3;
                    $icms->vBC = number_format($item->valor_unitario * $item->quantidade, 2, '.', '');
                    $icms->pICMS = number_format($trib->aliquota_icms, 2, '.', '');
                    $icms->vICMS = number_format(($item->valor_unitario * $item->quantidade * $trib->aliquota_icms / 100), 2, '.', '');

                    $this->totalICMSBC += (float) $icms->vBC;
                    $this->totalICMS += (float) $icms->vICMS;
                }

                $nfe->tagICMS($icms);
            }

            // PIS/COFINS — mesma regra do cupom: Simples Nacional zera (embutido no DAS)
            if ($this->empresa->crt <= 2 || !$item->pisCofins) {
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
            } else {
                $classPisCofins = $item->pisCofins;
                $baseCalculoItem = $item->valor_unitario * $item->quantidade;
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

            // IBS/CBS — mesma lógica do cupom
            $classTrib = $item->classificacaoTributaria;
            $obrigaIBSCBS = $this->empresa->crt == 3 || config('fiscal.emitir_ibscbs', false);

            if ($classTrib && $obrigaIBSCBS) {
                $baseCalculoItem = $item->valor_unitario * $item->quantidade;

                $pIBSUFBase  = config('fiscal.aliquotas_ibscbs_transicao.ibs_uf', 0.10);
                $pIBSMunBase = config('fiscal.aliquotas_ibscbs_transicao.ibs_mun', 0.00);
                $pCBSBase    = config('fiscal.aliquotas_ibscbs_transicao.cbs', 0.90);

                $percRedIBS = (float) ($classTrib->percentual_reducao_ibs ?? 0);
                $percRedCBS = (float) ($classTrib->percentual_reducao_cbs ?? 0);

                $pIBSUFEfet  = round($pIBSUFBase * (1 - $percRedIBS / 100), 4);
                $pIBSMunEfet = round($pIBSMunBase * (1 - $percRedIBS / 100), 4);
                $pCBSEfet    = round($pCBSBase * (1 - $percRedCBS / 100), 4);

                $vIBSUF  = round($baseCalculoItem * $pIBSUFEfet / 100, 2);
                $vIBSMun = round($baseCalculoItem * $pIBSMunEfet / 100, 2);
                $vCBS    = round($baseCalculoItem * $pCBSEfet / 100, 2);

                $ibscbs = new \stdClass();
                $ibscbs->item = $n;
                $ibscbs->CST = $classTrib->cst_codigo;
                $ibscbs->cClassTrib = $classTrib->codigo;
                $ibscbs->vBC = number_format($baseCalculoItem, 2, '.', '');

                $ibscbs->gIBSUF_pIBSUF = number_format($pIBSUFBase, 4, '.', '');
                if ($percRedIBS > 0) {
                    $ibscbs->gIBSUF_pRedAliq = number_format($percRedIBS, 4, '.', '');
                    $ibscbs->gIBSUF_pAliqEfet = number_format($pIBSUFEfet, 4, '.', '');
                }
                $ibscbs->gIBSUF_vIBSUF = number_format($vIBSUF, 2, '.', '');

                $ibscbs->gIBSMun_pIBSMun = number_format($pIBSMunBase, 4, '.', '');
                if ($percRedIBS > 0) {
                    $ibscbs->gIBSMun_pRedAliq = number_format($percRedIBS, 4, '.', '');
                    $ibscbs->gIBSMun_pAliqEfet = number_format($pIBSMunEfet, 4, '.', '');
                }
                $ibscbs->gIBSMun_vIBSMun = number_format($vIBSMun, 2, '.', '');

                $ibscbs->gCBS_pCBS = number_format($pCBSBase, 4, '.', '');
                if ($percRedCBS > 0) {
                    $ibscbs->gCBS_pRedAliq = number_format($percRedCBS, 4, '.', '');
                    $ibscbs->gCBS_pAliqEfet = number_format($pCBSEfet, 4, '.', '');
                }
                $ibscbs->gCBS_vCBS = number_format($vCBS, 2, '.', '');

                $nfe->tagIBSCBS($ibscbs);

                $this->houveIBSCBS = true;
            }
        }
    }

    /**
     * Rateia o desconto global (nível nota) proporcionalmente entre os itens,
     * somado ao desconto que cada item já tem individualmente. Mesma lógica
     * usada em vendas, adaptada para NotaFiscalItem.
     */
    protected function ratearDescontoGlobal(NotaFiscal $notaFiscal): array
    {
        $itens = $notaFiscal->itens;
        $descontoGlobal = (float) $notaFiscal->valor_desconto - (float) $itens->sum('valor_desconto');

        if ($descontoGlobal <= 0) {
            return $itens->map(fn($i) => [
                'item' => $i,
                'desconto_efetivo' => (float) ($i->valor_desconto ?? 0),
            ])->toArray();
        }

        $subtotalBrutoTotal = $itens->sum(fn($i) => $i->valor_unitario * $i->quantidade);
        $somaRateios = 0;
        $resultado = [];

        foreach ($itens as $index => $item) {
            $subtotalBruto = $item->valor_unitario * $item->quantidade;
            $descontoItem = (float) ($item->valor_desconto ?? 0);

            if ($index === count($itens) - 1) {
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

    protected function montarTotais(Make $nfe, NotaFiscal $notaFiscal): void
    {
        $vProdBruto = $notaFiscal->itens->sum(fn($item) => $item->valor_unitario * $item->quantidade);

        $std = new \stdClass();
        $std->vBC = number_format($this->totalICMSBC, 2, '.', '');
        $std->vICMS = number_format($this->totalICMS, 2, '.', '');
        $std->vICMSDeson = 0;
        $std->vFCP = 0;
        $std->vBCST = 0;
        $std->vST = 0;
        $std->vFCPST = 0;
        $std->vFCPSTRet = 0;
        $std->vProd = number_format($vProdBruto, 2, '.', '');
        $std->vFrete = number_format($notaFiscal->valor_frete, 2, '.', '');
        $std->vSeg = 0;
        if ($notaFiscal->valor_desconto > 0) {
            $std->vDesc = number_format($notaFiscal->valor_desconto, 2, '.', '');
        }
        $std->vII = 0;
        $std->vIPI = 0;
        $std->vIPIDevol = 0;
        $std->vPIS = number_format($this->totalPIS, 2, '.', '');
        $std->vCOFINS = number_format($this->totalCOFINS, 2, '.', '');
        $std->vOutro = 0;
        $std->vNF = number_format($notaFiscal->valor_total, 2, '.', '');
        $nfe->tagICMSTot($std);

        if ($this->houveIBSCBS) {
            $nfe->tagIBSCBSTot(new \stdClass());
        }
    }

    protected function montarTransporte(Make $nfe): void
    {
        $std = new \stdClass();
        $std->modFrete = 9; // sem transporte declarado por ora
        $nfe->tagtransp($std);
    }

    /**
     * NF-e faturada no admin não tem forma de pagamento coletada no ato —
     * usa "90 = Sem pagamento", válido para faturamento/B2B.
     * Ajustável depois se você quiser registrar a forma de pagamento na nota.
     */
    protected function montarPagamento(Make $nfe): void
    {
        $std = new \stdClass();
        $nfe->tagpag($std);

        $det = new \stdClass();
        $det->indPag = 0;
        $det->tPag = '90';
        $det->vPag = 0;
        $nfe->tagDetPag($det);
    }

    protected function montarResponsavelTecnico(Make $nfe): void
    {
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
            'autorizada' => $cStat === '100',
            'chave' => $chave,
            'numero_protocolo' => $protocolo,
            'motivo' => $xMotivo,
        ];
    }

    protected function salvarXmlEmDisco(string $xml, NotaFiscal $notaFiscal): void
    {
        if (!$this->chaveGerada) {
            return;
        }

        $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $pasta = storage_path("app/XML_nfe/{$agora->format('y')}/{$agora->format('m')}/{$agora->format('d')}");

        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        file_put_contents("{$pasta}/{$this->chaveGerada}.xml", $xml);
    }
}