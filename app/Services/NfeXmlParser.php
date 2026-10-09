<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use RuntimeException;

class NfeXmlParser
{
    private DOMXPath $xp;

    public function parse(string $conteudo): array
    {
        // bloqueia DOCTYPE (proteção contra XXE)
        if (stripos($conteudo, '<!DOCTYPE') !== false) {
            throw new RuntimeException('XML inválido.');
        }

        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($conteudo, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $ok) {
            throw new RuntimeException('O arquivo não é um XML válido.');
        }

        $this->xp = new DOMXPath($dom);
        $this->xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');

        $inf = $this->xp->query('//n:infNFe')->item(0);

        if (! $inf) {
            throw new RuntimeException('O XML não é de uma NF-e.');
        }

        if ($this->v('n:ide/n:mod', $inf) !== '55') {
            throw new RuntimeException('Só é possível importar NF-e modelo 55.');
        }

        $chave = preg_replace('/\D/', '', $inf->getAttribute('Id'));
        if (strlen($chave) !== 44) {
            $chave = $this->v('//n:protNFe/n:infProt/n:chNFe');
        }

        $dataEmissao = substr($this->v('n:ide/n:dhEmi', $inf) ?: $this->v('n:ide/n:dEmi', $inf), 0, 10);

        $documento = preg_replace('/\D/', '', $this->v('n:emit/n:CNPJ', $inf) ?: $this->v('n:emit/n:CPF', $inf));
        $ieXml     = strtoupper($this->v('n:emit/n:IE', $inf));

        if ($ieXml === '') {
            $indicador = 'nao_contribuinte';
        } elseif ($ieXml === 'ISENTO') {
            $indicador = 'isento';
        } else {
            $indicador = 'contribuinte';
        }

        $fornecedor = [
            'tipo_pessoa'   => strlen($documento) === 14 ? 'juridica' : 'fisica',
            'nome'          => $this->v('n:emit/n:xNome', $inf),
            'nome_fantasia' => $this->v('n:emit/n:xFant', $inf) ?: null,
            'cpf_cnpj'      => $documento,
            'indicador_ie'  => $indicador,
            'ie'            => $indicador === 'contribuinte' ? preg_replace('/\D/', '', $ieXml) : null,
            'telefone'      => preg_replace('/\D/', '', $this->v('n:emit/n:enderEmit/n:fone', $inf)) ?: null,
            'cep'           => preg_replace('/\D/', '', $this->v('n:emit/n:enderEmit/n:CEP', $inf)) ?: null,
            'logradouro'    => $this->v('n:emit/n:enderEmit/n:xLgr', $inf) ?: null,
            'numero'        => $this->v('n:emit/n:enderEmit/n:nro', $inf) ?: null,
            'complemento'   => $this->v('n:emit/n:enderEmit/n:xCpl', $inf) ?: null,
            'bairro'        => $this->v('n:emit/n:enderEmit/n:xBairro', $inf) ?: null,
            'municipio'     => $this->v('n:emit/n:enderEmit/n:xMun', $inf) ?: null,
            'cod_municipio' => $this->v('n:emit/n:enderEmit/n:cMun', $inf) ?: null,
            'uf'            => $this->v('n:emit/n:enderEmit/n:UF', $inf) ?: null,
        ];

        if (strlen($documento) !== 11 && strlen($documento) !== 14) {
            throw new RuntimeException('Não foi possível ler o CNPJ/CPF do emitente no XML.');
        }

        $itens = [];
        foreach ($this->xp->query('n:det', $inf) as $det) {
            $ean = $this->v('n:prod/n:cEAN', $det);
            $vProd = (float) $this->v('n:prod/n:vProd', $det);
            $vDesc = (float) $this->v('n:prod/n:vDesc', $det);
            $cst   = $this->v('n:imposto/n:ICMS/*/n:CST', $det);
            $csosn = $this->v('n:imposto/n:ICMS/*/n:CSOSN', $det);

            $itens[] = [
                'cfop'   => $this->v('n:prod/n:CFOP', $det),
                'cst'    => $cst,
                'csosn'  => $csosn,
                'com_st' => in_array($cst, ['10', '30', '60', '70'], true)
                || in_array($csosn, ['201', '202', '203', '500'], true),
                'codigo'         => $this->v('n:prod/n:cProd', $det),
                'ean'            => preg_match('/^\d{8,14}$/', $ean) ? $ean : '',
                'descricao'      => $this->v('n:prod/n:xProd', $det),
                'unidade'        => $this->v('n:prod/n:uCom', $det),
                'ncm'            => $this->v('n:prod/n:NCM', $det),
                'origem'         => $this->v('n:imposto/n:ICMS/*/n:orig', $det),
                'quantidade'     => (float) $this->v('n:prod/n:qCom', $det),
                'unidade_tributavel' => $this->v('n:prod/n:uTrib', $det),
                'valor_unitario' => (float) $this->v('n:prod/n:vUnCom', $det),
                'valor_desconto' => $vDesc,
                'valor_total'    => round($vProd - $vDesc, 2),
                'lote'           => mb_substr($this->v('n:prod/n:rastro/n:nLote', $det), 0, 30) ?: null,
                'validade'       => $this->v('n:prod/n:rastro/n:dVal', $det) ?: null,
            ];
        }

        return [
            'chave'             => $chave,
            'modelo'            => '55',
            'serie'             => $this->v('n:ide/n:serie', $inf) ?: null,
            'numero'            => $this->v('n:ide/n:nNF', $inf),
            'data_emissao'      => $dataEmissao,
            'natureza_operacao' => mb_substr($this->v('n:ide/n:natOp', $inf), 0, 60),
            'valor_frete'       => (float) $this->v('n:total/n:ICMSTot/n:vFrete', $inf),
            'valor_outras'      => (float) $this->v('n:total/n:ICMSTot/n:vOutro', $inf),
            'fornecedor'        => $fornecedor,
            'itens'             => $itens,
        ];
    }

    private function v(string $expr, ?DOMNode $ctx = null): string
    {
        return trim((string) $this->xp->evaluate("string($expr)", $ctx));
    }
}