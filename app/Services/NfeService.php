<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Pdv;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use Exception;

class NfeService
{
    protected Empresa $empresa;
    protected Pdv $pdv;
    protected Tools $tools;

    public function __construct(Pdv $pdv)
    {
        $this->empresa = Empresa::first();
        $this->pdv = $pdv;

        if (!$this->empresa) {
            throw new Exception('Dados da empresa não cadastrados. Preencha o cadastro da empresa antes de emitir.');
        }

        if (!$this->empresa->certificado_base64) {
            throw new Exception('Certificado digital não cadastrado.');
        }

        $this->tools = $this->criarTools();
    }

    protected function criarTools(): Tools
    {
        $config = [
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb" => (int) $this->empresa->ambiente, // 1-producao, 2-homologacao
            "razaosocial" => $this->empresa->razao_social,
            "siglaUF" => $this->empresa->uf,
            "cnpj" => $this->empresa->cnpj,
            "schemes" => "PL_009_V4",
            "versao" => '4.00',
            "tokenIBPT" => "",
            "CSC" => $this->pdv->csc,
            "CSCid" => $this->pdv->csc_id,
        ];

        $certificadoConteudo = base64_decode($this->empresa->certificado_base64);
        $certificate = Certificate::readPfx($certificadoConteudo, $this->empresa->certificado_senha);

        $tools = new Tools(json_encode($config), $certificate);
        $tools->model('65'); // 65 = NFC-e

        return $tools;
    }

    public function tools(): Tools
    {
        return $this->tools;
    }

    public function empresa(): Empresa
    {
        return $this->empresa;
    }
}