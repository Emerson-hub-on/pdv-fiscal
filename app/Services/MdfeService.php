<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Mdfe;
use App\Models\MdfeMunicipio;
use App\Models\MdfeVeiculo;
use Exception;
use NFePHP\Common\Certificate;
use NFePHP\Common\Keys;
use NFePHP\MDFe\Complements;
use NFePHP\MDFe\Make;
use NFePHP\MDFe\Tools;

/**
 * Emissão de MDF-e (modelo 58, layout 3.00, modal rodoviário) com nfephp-org/sped-mdfe.
 */
class MdfeService
{
    protected Empresa $empresa;
    protected Tools $tools;
    protected ?string $chaveGerada = null;

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

    protected function criarTools(): Tools
    {
        $config = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb'       => (int) $this->empresa->ambiente,
            'razaosocial' => $this->empresa->razao_social,
            'siglaUF'     => $this->empresa->uf,
            'cnpj'        => $this->empresa->cnpj,
            'schemes'     => 'PL_MDFe_300b',
            'versao'      => '3.00',
        ];

        $certificate = Certificate::readPfx(
            base64_decode($this->empresa->certificado_base64),
            $this->empresa->certificado_senha
        );

        return new Tools(json_encode($config), $certificate);
    }

    // ------------------------------------------------------------------
    // Emissão
    // ------------------------------------------------------------------

    /**
     * Monta, assina e envia (síncrono) o MDF-e. O número e a série já devem estar gravados no modelo.
     *
     * @return array{chave_acesso: string, protocolo: string, xml: string, codigo_numerico: string}
     */
    public function emitir(Mdfe $mdfe): array
    {
        $mdfe->load(['percursos', 'municipios.documentos', 'veiculos.condutores']);

        $xmlBruto = null;
        $xmlAssinado = null;
        $codigoNumerico = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        // PASSO 1: montar e assinar — erro aqui é problema de DADOS
        try {
            $make = new Make();

            $this->montarIde($make, $mdfe, $codigoNumerico);
            $this->montarEmit($make);
            $this->montarRodoviario($make, $mdfe);
            $this->montarDocumentos($make, $mdfe);
            $this->montarTotais($make, $mdfe);
            $this->montarInfAdicional($make, $mdfe);
            $this->montarResponsavelTecnico($make);

            $xmlBruto = $make->getXML();

            if (!$xmlBruto) {
                throw new Exception('Erro ao montar XML: ' . implode(' | ', $make->getErrors()));
            }

            // A biblioteca recalcula a chave na montagem: vale a dela
            $this->chaveGerada = $make->getChave() ?: $this->chaveGerada;

            $xmlAssinado = $this->tools->signMDFe($xmlBruto);
        } catch (\Throwable $e) {
            $this->salvarXmlEmDisco($xmlAssinado ?: $xmlBruto, '');
            throw new Exception("Erro ao montar/validar XML (MDF-e nº {$mdfe->numero}): " . $e->getMessage());
        }

        // PASSO 2: enviar para a SEFAZ (síncrono, um MDF-e por vez)
        try {
            $resposta = $this->tools->sefazEnviaLote([$xmlAssinado], (string) $mdfe->numero, 1);
        } catch (\Throwable $e) {
            throw new Exception("Falha de comunicação com a SEFAZ (MDF-e nº {$mdfe->numero}): " . $e->getMessage());
        }

        return $this->processarResposta($resposta, $xmlAssinado, $codigoNumerico);
    }

    protected function processarResposta(string $resposta, string $xmlAssinado, string $codigoNumerico): array
    {
        $protocolo = $this->extrairProtocolo($resposta);

        if (!$protocolo['autorizado']) {
            \Illuminate\Support\Facades\Log::info('SEFAZ MDF-e - resposta bruta rejeitada', ['resposta' => $resposta]);
            $this->salvarXmlEmDisco($xmlAssinado, '');
            throw new Exception('Rejeitado pela SEFAZ: ' . $protocolo['motivo']);
        }

        try {
            $xmlProcessado = Complements::toAuthorize($xmlAssinado, $resposta);
        } catch (\Throwable $e) {
            $xmlProcessado = $xmlAssinado;
        }

        $this->salvarXmlEmDisco($xmlProcessado, '');

        return [
            'chave_acesso'    => $protocolo['chave'] ?: $this->chaveGerada,
            'protocolo'       => $protocolo['numero_protocolo'],
            'xml'             => $xmlProcessado,
            'codigo_numerico' => $codigoNumerico,
        ];
    }

    protected function montarIde(Make $make, Mdfe $mdfe, string $codigoNumerico): void
    {
        $dhEmi = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $cUF   = $this->codigoUf($this->empresa->uf);

        $chave = Keys::build(
            (string) $cUF,
            $dhEmi->format('y'),
            $dhEmi->format('m'),
            $this->empresa->cnpj,
            '58',
            (string) $mdfe->serie,
            (string) $mdfe->numero,
            '1', // tpEmis: normal (sem contingência nesta versão)
            $codigoNumerico
        );
        $this->chaveGerada = $chave;

        $std = new \stdClass();
        $std->cUF      = $cUF;
        $std->tpAmb    = (int) $this->empresa->ambiente;
        $std->tpEmit   = $mdfe->tipo_emitente;
        $std->tpTransp = $mdfe->tipo_transportador;
        $std->mod      = '58';
        $std->serie    = $mdfe->serie;
        $std->nMDF     = $mdfe->numero;
        $std->cMDF     = $codigoNumerico;
        $std->cDV      = substr($chave, -1);
        $std->modal    = '1'; // rodoviário
        $std->dhEmi    = $dhEmi->format('Y-m-d\TH:i:sP');
        $std->tpEmis   = '1';
        $std->procEmi  = '0';
        $std->verProc  = '1.0.0';
        $std->UFIni    = $mdfe->uf_inicio;
        $std->UFFim    = $mdfe->uf_fim;

        if ($mdfe->inicio_viagem) {
            $std->dhIniViagem = $mdfe->inicio_viagem
                ->copy()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:sP');
        }

        $make->tagide($std);

        foreach ($mdfe->municipios->where('tipo', MdfeMunicipio::CARREGAMENTO) as $m) {
            $carrega = new \stdClass();
            $carrega->cMunCarrega = $m->codigo_ibge;
            $carrega->xMunCarrega = $m->nome;
            $make->taginfMunCarrega($carrega);
        }

        foreach ($mdfe->percursos as $p) {
            $percurso = new \stdClass();
            $percurso->UFPer = $p->uf;
            $make->taginfPercurso($percurso);
        }
    }

    protected function montarEmit(Make $make): void
    {
        $std = new \stdClass();
        $std->CNPJ  = $this->empresa->cnpj;
        $std->IE    = $this->empresa->ie;
        $std->xNome = $this->empresa->razao_social;
        $std->xFant = $this->empresa->nome_fantasia;
        $make->tagemit($std);

        $end = new \stdClass();
        $end->xLgr    = $this->empresa->logradouro;
        $end->nro     = $this->empresa->numero;
        $end->xCpl    = $this->empresa->complemento;
        $end->xBairro = $this->empresa->bairro;
        $end->cMun    = $this->empresa->cod_municipio;
        $end->xMun    = $this->empresa->municipio;
        $end->CEP     = $this->empresa->cep;
        $end->UF      = $this->empresa->uf;
        $make->tagenderEmit($end);
    }

    protected function montarRodoviario(Make $make, Mdfe $mdfe): void
    {
        $tracao = $mdfe->veiculos->firstWhere('papel', MdfeVeiculo::TRACAO);

        if (!$tracao) {
            throw new Exception('MDF-e sem veículo de tração.');
        }

        if ($mdfe->rntrc) {
            $antt = new \stdClass();
            $antt->RNTRC = $mdfe->rntrc;
            $make->taginfANTT($antt);
        }

        // ---- Veículo de tração (com os condutores) ----
        $veic = $this->dadosVeiculo($tracao, true);
        $veic->condutor = $tracao->condutores->map(function ($c) {
            $condutor = new \stdClass();
            $condutor->xNome = $c->nome;
            $condutor->CPF   = $c->cpf;
            return $condutor;
        })->values()->all();

        if (!$veic->condutor) {
            throw new Exception('MDF-e sem condutor.');
        }

        $make->tagveicTracao($veic);

        // ---- Reboques ----
        foreach ($mdfe->veiculos->where('papel', MdfeVeiculo::REBOQUE) as $reboque) {
            $make->tagveicReboque($this->dadosVeiculo($reboque, false));
        }
    }

    /** Dados comuns de tração e reboque; o reboque não leva tipo de rodado. */
    protected function dadosVeiculo(MdfeVeiculo $v, bool $tracao): \stdClass
    {
        $std = new \stdClass();
        $std->placa = $v->placa;
        $std->tara  = (string) $v->tara_kg;
        $std->capKG = $v->capacidade_kg !== null ? (string) $v->capacidade_kg : null;
        $std->capM3 = $v->capacidade_m3 !== null ? (string) $v->capacidade_m3 : null;
        $std->tpCar = $v->tipo_carroceria;
        $std->UF    = $v->uf;

        if ($tracao) {
            $std->tpRod = $v->tipo_rodado;
        }

        // O proprietário só entra quando o veículo não é do emitente
        if ($v->proprietario_documento) {
            $prop = new \stdClass();

            if (strlen($v->proprietario_documento) === 11) {
                $prop->CPF = $v->proprietario_documento;
            } else {
                $prop->CNPJ = $v->proprietario_documento;
            }

            $prop->RNTRC  = $v->proprietario_rntrc;
            $prop->xNome  = $v->proprietario_nome;
            $prop->IE     = $v->proprietario_ie;
            $prop->UF     = $v->proprietario_uf;
            $prop->tpProp = (string) $v->proprietario_tipo;

            $std->prop = $prop;
        }

        return $std;
    }

    /**
     * Cada município de descarga é um infMunDescarga; a posição dele (nItem) liga as NF-e ao município.
     */
    protected function montarDocumentos(Make $make, Mdfe $mdfe): void
    {
        $descargas = $mdfe->municipios->where('tipo', MdfeMunicipio::DESCARREGAMENTO)->values();

        if ($descargas->isEmpty() || $mdfe->documentos()->count() === 0) {
            throw new Exception('MDF-e sem NF-e vinculadas.');
        }

        foreach ($descargas as $i => $municipio) {
            $mun = new \stdClass();
            $mun->cMunDescarga = $municipio->codigo_ibge;
            $mun->xMunDescarga = $municipio->nome;
            $mun->nItem        = $i;
            $make->taginfMunDescarga($mun);

            foreach ($municipio->documentos as $doc) {
                $nfe = new \stdClass();
                $nfe->chNFe = $doc->chave_acesso;
                $nfe->nItem = $i;
                $make->taginfNFe($nfe);
            }
        }
    }

    protected function montarTotais(Make $make, Mdfe $mdfe): void
    {
        if ((float) $mdfe->peso_carga_kg <= 0) {
            throw new Exception('Informe o peso bruto das NF-e: o peso total da carga está zerado.');
        }

        $std = new \stdClass();
        $std->vCarga = number_format((float) $mdfe->valor_carga, 2, '.', '');
        $std->cUnid  = '01'; // quilograma
        $std->qCarga = number_format((float) $mdfe->peso_carga_kg, 4, '.', '');
        $make->tagtot($std); // qNFe a biblioteca conta sozinha
    }

    protected function montarInfAdicional(Make $make, Mdfe $mdfe): void
    {
        if (!$mdfe->informacoes_adicionais && !$mdfe->informacoes_fisco) {
            return;
        }

        $std = new \stdClass();
        $std->infAdFisco = $mdfe->informacoes_fisco ?: null;
        $std->infCpl     = $mdfe->informacoes_adicionais ?: null;
        $make->taginfAdic($std);
    }

    protected function montarResponsavelTecnico(Make $make): void
    {
        $cnpj = config('nfe.resp_tecnico_cnpj', '');

        if (!$cnpj) {
            return;
        }

        $std = new \stdClass();
        $std->CNPJ     = $cnpj;
        $std->xContato = config('nfe.resp_tecnico_contato', '');
        $std->email    = config('nfe.resp_tecnico_email', '');
        $std->fone     = config('nfe.resp_tecnico_fone', '');
        $make->taginfRespTec($std);
    }

    // ------------------------------------------------------------------
    // Eventos
    // ------------------------------------------------------------------

    /** @return array{protocolo: ?string, motivo: ?string, xml: string} */
    public function cancelar(Mdfe $mdfe, string $justificativa): array
    {
        $this->exigirAutorizado($mdfe, 'cancelar');

        $resposta = $this->tools->sefazCancela($mdfe->chave_acesso, $justificativa, $mdfe->protocolo);

        return $this->conferirEvento($resposta, 'cancelamento', $mdfe);
    }

    /** Encerra o MDF-e informando o município onde a viagem terminou. */
    public function encerrar(Mdfe $mdfe, string $codigoMunicipio, string $uf): array
    {
        $this->exigirAutorizado($mdfe, 'encerrar');

        $hoje = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

        $resposta = $this->tools->sefazEncerra(
            $mdfe->chave_acesso,
            $mdfe->protocolo,
            (string) $this->codigoUf($uf),
            $codigoMunicipio,
            $hoje
        );

        return $this->conferirEvento($resposta, 'encerramento', $mdfe);
    }

    protected function exigirAutorizado(Mdfe $mdfe, string $acao): void
    {
        if (!$mdfe->chave_acesso || !$mdfe->protocolo) {
            throw new Exception("MDF-e sem chave de acesso ou protocolo de autorização — não é possível {$acao}.");
        }
    }

    protected function conferirEvento(string $resposta, string $nome, Mdfe $mdfe): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($resposta);

        $infEvento = $dom->getElementsByTagName('infEvento')->item(0);
        $cStat   = $infEvento?->getElementsByTagName('cStat')->item(0)?->nodeValue;
        $xMotivo = $infEvento?->getElementsByTagName('xMotivo')->item(0)?->nodeValue;
        $nProt   = $infEvento?->getElementsByTagName('nProt')->item(0)?->nodeValue;

        // 135 = evento registrado e vinculado ao MDF-e
        if ($cStat !== '135') {
            $motivo = $xMotivo ?? $dom->getElementsByTagName('xMotivo')->item(0)?->nodeValue ?? 'SEFAZ não retornou motivo.';
            throw new Exception("Falha no {$nome}: {$motivo}");
        }

        $this->salvarXmlEmDisco($resposta, "-{$nome}", $mdfe->chave_acesso);

        return ['protocolo' => $nProt, 'motivo' => $xMotivo, 'xml' => $resposta];
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    protected function extrairProtocolo(string $resposta): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($resposta);

        $infProt = $dom->getElementsByTagName('infProt')->item(0);

        if (!$infProt) {
            // Rejeição já no lote: o motivo vem na raiz do retorno
            $cStat   = $dom->getElementsByTagName('cStat')->item(0)?->nodeValue;
            $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0)?->nodeValue;

            return [
                'autorizado'       => false,
                'chave'            => null,
                'numero_protocolo' => null,
                'motivo'           => trim(($cStat ? "{$cStat} - " : '') . ($xMotivo ?: 'Lote não processado (sem protocolo retornado).')),
            ];
        }

        $cStat = $infProt->getElementsByTagName('cStat')->item(0)?->nodeValue;
        $xMotivo = $infProt->getElementsByTagName('xMotivo')->item(0)?->nodeValue;

        return [
            'autorizado'       => $cStat === '100',
            'chave'            => $infProt->getElementsByTagName('chMDFe')->item(0)?->nodeValue,
            'numero_protocolo' => $infProt->getElementsByTagName('nProt')->item(0)?->nodeValue,
            'motivo'           => trim(($cStat ? "{$cStat} - " : '') . $xMotivo),
        ];
    }

    protected function salvarXmlEmDisco(?string $conteudo, string $sufixo, ?string $chave = null): void
    {
        $chave = $chave ?: $this->chaveGerada;

        if (!$conteudo || !$chave) {
            return;
        }

        $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $pasta = storage_path("app/XML_mdfe/{$agora->format('y')}/{$agora->format('m')}/{$agora->format('d')}");

        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        file_put_contents("{$pasta}/{$chave}{$sufixo}.xml", $conteudo);
    }

    /** Código IBGE da UF (cUF). */
    protected function codigoUf(string $uf): int
    {
        $codigos = [
            'RO' => 11, 'AC' => 12, 'AM' => 13, 'RR' => 14, 'PA' => 15, 'AP' => 16, 'TO' => 17,
            'MA' => 21, 'PI' => 22, 'CE' => 23, 'RN' => 24, 'PB' => 25, 'PE' => 26, 'AL' => 27,
            'SE' => 28, 'BA' => 29, 'MG' => 31, 'ES' => 32, 'RJ' => 33, 'SP' => 35,
            'PR' => 41, 'SC' => 42, 'RS' => 43, 'MS' => 50, 'MT' => 51, 'GO' => 52, 'DF' => 53,
        ];

        $codigo = $codigos[strtoupper($uf)] ?? null;

        if ($codigo === null) {
            throw new Exception("UF inválida: {$uf}.");
        }

        return $codigo;
    }
}
