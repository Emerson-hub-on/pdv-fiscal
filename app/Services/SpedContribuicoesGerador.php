<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\SpedConfiguracao;
use App\Models\Unidade;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Gera o arquivo da EFD-Contribuições (PIS/COFINS) de um mês, para empresas do regime normal (CRT 3).
 * O regime (cumulativo = Lucro Presumido / não cumulativo = Lucro Real) vem da Configuração do SPED.
 *
 * Escopo desta versão:
 *  - Bloco 0: abertura, contador, regime (0110), estabelecimento (0140) com participantes,
 *             unidades e itens, e as contas contábeis usadas (0500)
 *  - Bloco C (escrituração individualizada, C010 IND_ESCRI = 2):
 *      NF-e de emissão própria (C100 + C170), NFC-e (C100 + C175) e,
 *      no não cumulativo, NF-e de entrada com direito a crédito (C100 + C170)
 *  - Bloco M (apuração): créditos (M100/M105, M500/M505), contribuição (M200/M205/M210, M600/M605/M610)
 *             e receitas sem incidência (M400/M410, M800/M810)
 *  - Bloco 1: controle dos saldos de crédito (1100/1500), levados de um mês para o outro pelo histórico de gerações
 *  - Blocos A, D, F e I abertos "sem dados"; Bloco 9 com os totais
 *
 * Documentos cancelados não são escriturados (não geram receita nem crédito).
 * A EFD-Contribuições é descontinuada para fatos geradores a partir de 01/2027 (NT 011/2026).
 */
class SpedContribuicoesGerador
{
    /** COD_VER do 0000 (leiaute 006, vigente desde 2021; atualize se sair leiaute novo) */
    private const COD_VER = '006';

    /** CSTs de saída que têm base e alíquota no arquivo */
    private const CST_SAIDA_COM_BASE = ['01', '02', '49', '99'];

    /** CST de ICMS 2 dígitos sem origem: usado quando o item não tem CST (não deveria acontecer no CRT 3) */
    private const CST_ICMS_PADRAO = '90';

    private const COD_EST = '1';

    /** CSTs de saída sem incidência que vão para o M400/M800 (exigem a natureza da receita, NAT_REC) */
    public const CST_SEM_INCIDENCIA = ['04', '05', '06', '07', '08', '09'];

    /** Tipo de crédito (tabela 4.3.6) por CST de entrada, alíquota básica */
    public const COD_CRED = ['50' => '101', '51' => '201', '52' => '301'];

    /** Código da contribuição apurada (tabela 4.3.5) por CST de saída: [não cumulativo, cumulativo] */
    private const COD_CONT = ['01' => ['01', '51'], '02' => ['02', '52']];

    /** Código de receita do DARF (M205/M605): [não cumulativo, cumulativo] */
    private const COD_REC_PIS    = ['691201', '810902'];
    private const COD_REC_COFINS = ['585601', '217201'];

    private Carbon $inicio;
    private Carbon $fim;
    private Empresa $empresa;
    private SpedConfiguracao $config;
    private string $regime;
    private array $opcoes;

    private array $blocos = [];
    private array $participantes = [];
    private array $unidades = [];
    private array $itens = [];
    private array $contas = [];

    /** Bases acumuladas no Bloco C para o Bloco M */
    private array $debitos = [];     // [cst|aliqPis|aliqCofins] => rec_brt, bc
    private array $creditosApu = []; // [cst] => [nat => bc]
    private array $semIncidencia = []; // [cst] => [nat_rec => valor]
    private array $saldosCredito = ['pis' => [], 'cofins' => []]; // saldos que seguem para o próximo mês

    private array $resumo = [
        'nfe_proprias' => 0, 'nfce' => 0, 'entradas' => 0, 'entradas_sem_credito' => 0,
        'receita' => 0.0, 'pis_debito' => 0.0, 'cofins_debito' => 0.0,
        'pis_credito' => 0.0, 'cofins_credito' => 0.0,
    ];

    /**
     * @param array $opcoes finalidade ('0' original | '1' retificadora), num_rec_anterior (?string)
     * @return array{conteudo: string, total_linhas: int, resumo: array}
     */
    public function gerar(Carbon $competencia, array $opcoes): array
    {
        $this->inicio  = $competencia->copy()->startOfMonth()->startOfDay();
        $this->fim     = $competencia->copy()->endOfMonth()->endOfDay();
        $this->empresa = Empresa::firstOrFail();
        $this->config  = SpedConfiguracao::firstOrFail();
        $this->opcoes  = $opcoes;
        $this->regime  = (string) $this->config->regime_pis_cofins;

        if ((int) $this->empresa->crt !== 3 || !in_array($this->regime, ['cumulativo', 'nao_cumulativo'], true)) {
            throw ValidationException::withMessages([
                'sped' => 'A EFD-Contribuições só é gerada para empresa do regime normal (CRT 3) com o regime do PIS/COFINS definido na Configuração do SPED.',
            ]);
        }

        // Bloco C primeiro: ele descobre participantes, unidades, itens e contas que o Bloco 0 lista
        $this->montarBlocoC();
        $this->montarBloco0();

        foreach (['A', 'D', 'F', 'I'] as $bloco) {
            $this->fecharBloco($bloco, []);
        }

        $controle = $this->montarBlocoM();
        $this->montarBloco1($controle);

        // Bloco P (CPRB) deixou de ser obrigatório a partir de 01/2025 (NT 009/2024)
        if ($this->inicio->year < 2025) {
            $this->fecharBloco('P', []);
        }

        $conteudo = $this->montarArquivo();

        foreach (['receita', 'pis_debito', 'cofins_debito', 'pis_credito', 'cofins_credito'] as $k) {
            $this->resumo[$k] = round($this->resumo[$k], 2);
        }

        $this->resumo['saldos_credito'] = $this->saldosCredito;

        return [
            'conteudo'     => $conteudo['texto'],
            'total_linhas' => $conteudo['total_linhas'],
            'resumo'       => $this->resumo,
        ];
    }

    // ==================================================================
    // BLOCO 0
    // ==================================================================

    private function montarBloco0(): void
    {
        $e = $this->empresa;
        $c = $this->config;
        $retificadora = ($this->opcoes['finalidade'] ?? '0') === '1';

        $linhas = [];

        // IND_ATIV: 0 = industrial ou equiparado; 2 = comércio
        $linhas[] = ['0000', self::COD_VER, $retificadora ? '1' : '0', '',
            $retificadora ? strtoupper(trim((string) ($this->opcoes['num_rec_anterior'] ?? ''))) : '',
            $this->data($this->inicio), $this->data($this->fim), $this->texto($e->razao_social, 100),
            $this->digitos($e->cnpj), strtoupper($e->uf), $e->cod_municipio, '', '00',
            $c->isIndustrial() ? '0' : '2'];

        $linhas[] = ['0001', '0'];

        $linhas[] = ['0100', $this->texto($c->contador_nome, 100), $c->contador_cpf, $this->texto($c->contador_crc, 15),
            $c->contador_cnpj ?? '', $c->contador_cep, $this->texto($c->contador_endereco, 60), $this->texto($c->contador_numero, 10),
            $this->texto($c->contador_complemento, 60), $this->texto($c->contador_bairro, 60), $c->contador_fone ?? '', '',
            $this->texto($c->contador_email), $c->contador_cod_municipio];

        // 0110: COD_INC_TRIB (1 = não cumulativo, 2 = cumulativo), IND_APRO_CRED (1 = apropriação direta),
        // COD_TIPO_CONT (1 = alíquota básica), IND_REG_CUM (9 = competência, escrituração detalhada; só no cumulativo)
        $linhas[] = $this->regime === 'nao_cumulativo'
            ? ['0110', '1', '1', '1', '']
            : ['0110', '2', '', '1', '9'];

        // 0140: um estabelecimento (a própria empresa); 0150, 0190 e 0200 ficam abaixo dele
        $linhas[] = ['0140', self::COD_EST, $this->texto($e->razao_social, 100), $this->digitos($e->cnpj),
            strtoupper($e->uf), $this->digitos($e->ie), $e->cod_municipio, $this->texto($e->im), ''];

        ksort($this->participantes);
        foreach ($this->participantes as $p) {
            $linhas[] = $p;
        }

        $descricoes = Unidade::whereIn('sigla', array_keys($this->unidades))->pluck('descricao', 'sigla');
        ksort($this->unidades);
        foreach (array_keys($this->unidades) as $sigla) {
            $linhas[] = ['0190', $sigla, $this->texto($descricoes[$sigla] ?? $sigla, 100)];
        }

        ksort($this->itens);
        foreach ($this->itens as $item) {
            $linhas[] = $item;
        }

        // 0500: contas contábeis citadas no COD_CTA (DT_ALT, COD_NAT_CC, IND_CTA, NIVEL, COD_CTA, NOME_CTA, COD_CTA_REF, CNPJ_EST)
        foreach ($this->contas as $codigo => [$natureza, $nome]) {
            // NIVEL: pela máscara do código (1.1.4.01 = nível 4); sem separador, nível 1
            $nivel = count(array_filter(preg_split('/[.\-]/', $codigo), fn ($p) => $p !== ''));
            $linhas[] = ['0500', $this->data($this->inicio), $natureza, 'A', (string) max($nivel, 1), $codigo, $this->texto($nome, 60), '', ''];
        }

        $this->fecharBloco('0', $linhas);
    }

    private function participante(string $prefixo, $pessoa): string
    {
        $codigo = $prefixo . $pessoa->id;

        if (!isset($this->participantes[$codigo])) {
            $doc = $this->digitos($pessoa->cpf_cnpj);

            $this->participantes[$codigo] = ['0150', $codigo, $this->texto($pessoa->nome, 100), '01058',
                strlen($doc) === 14 ? $doc : '', strlen($doc) === 11 ? $doc : '',
                $this->digitos($pessoa->ie), $pessoa->cod_municipio, '',
                $this->texto($pessoa->logradouro, 60), $this->texto($pessoa->numero, 10),
                $this->texto($pessoa->complemento, 60), $this->texto($pessoa->bairro, 60)];
        }

        return $codigo;
    }

    /** 0200 da EFD-Contribuições: 12 campos (sem CEST, diferente da EFD ICMS/IPI). */
    private function item(Produto $produto): string
    {
        $codigo = $this->texto($produto->codigo_interno, 60);

        if (!isset($this->itens[$codigo])) {
            $unidade = $this->unidade($produto->unidade_comercial);
            $ncm = $this->digitos($produto->ncm?->codigo);

            $this->itens[$codigo] = ['0200', $codigo, $this->texto($produto->nome),
                $produto->codigo_barras_valido ? $this->digitos($produto->codigo_barras) : '', '',
                $unidade, $produto->tipo_item ?: '00', $ncm, '',
                $produto->tipo_item === '09' ? '00' : substr($ncm, 0, 2), '', ''];
        }

        return $codigo;
    }

    private function unidade(?string $sigla): string
    {
        $sigla = strtoupper(trim((string) $sigla)) ?: 'UN';
        $this->unidades[$sigla] = true;

        return $sigla;
    }

    /** Conta contábil (COD_CTA) de receita ou de compras; registra no 0500. */
    private function conta(string $tipo): string
    {
        [$codigo, $nome, $natureza] = $tipo === 'receita'
            ? [$this->config->cod_cta_receita, $this->config->nome_cta_receita, '04']   // 04 = contas de resultado
            : [$this->config->cod_cta_compras, $this->config->nome_cta_compras, '01'];  // 01 = ativo (estoque)

        $codigo = $this->texto($codigo, 255);

        if ($codigo === '') {
            return '';
        }

        $this->contas[$codigo] ??= [$natureza, $nome ?: $codigo];

        return $codigo;
    }

    // ==================================================================
    // BLOCO C
    // ==================================================================

    private function montarBlocoC(): void
    {
        $docs = [];

        if ($this->regime === 'nao_cumulativo') {
            foreach ($this->entradas() as $entrada) {
                array_push($docs, ...$this->linhasEntrada($entrada));
            }
        }

        foreach ($this->nfeProprias() as $nota) {
            array_push($docs, ...$this->linhasNfePropria($nota));
        }

        foreach ($this->nfces() as $venda) {
            array_push($docs, ...$this->linhasNfce($venda));
        }

        // C010 (estabelecimento) só quando houver documento; IND_ESCRI 2 = individualizada por documento
        $linhas = $docs ? array_merge([['C010', $this->digitos($this->empresa->cnpj), '2']], $docs) : [];

        $this->fecharBloco('C', $linhas);
    }

    // ---------------- Entradas com crédito (só no não cumulativo) ----------------

    private function entradas()
    {
        $entradas = EntradaNota::with(['fornecedor', 'itens.produto.ncm', 'itens.cfopEntrada'])
            ->where('status', 'finalizada')
            ->where('modelo', '55')
            ->whereBetween('data_entrada', [$this->inicio->toDateString(), $this->fim->toDateString()])
            ->orderBy('data_entrada')->orderBy('id')
            ->get();

        // Nota sem nenhum item com crédito não precisa ser escriturada; com algum crédito, vai inteira
        $comCredito = $entradas->filter(fn ($e) => $e->itens->contains(
            fn ($i) => in_array((string) $i->cst_pis_cofins, CreditoPisCofinsEntrada::CSTS_COM_CREDITO, true)
        ));

        $this->resumo['entradas_sem_credito'] = $entradas->count() - $comCredito->count();

        return $comCredito;
    }

    private function linhasEntrada(EntradaNota $entrada): array
    {
        $this->resumo['entradas']++;

        $codPart = $this->participante('F', $entrada->fornecedor);
        $codCta = $this->conta('compras');

        $itens = $entrada->itens->values();
        $rateio = $this->ratear(
            (float) $entrada->valor_desconto,
            $itens->map(fn ($i) => (float) $i->quantidade * (float) $i->valor_unitario)->all()
        );

        $c170 = [];
        $tot = ['merc' => 0, 'desc' => 0, 'frete' => 0, 'outras' => 0, 'bc' => 0, 'icms' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0];

        foreach ($itens as $n => $item) {
            $produto = $item->produto;
            $codItem = $this->item($produto);
            $unidade = $this->unidade($item->unidade ?: $produto->unidade_comercial);

            $vlItem = round((float) $item->quantidade * (float) $item->valor_unitario, 2);
            $vlDesc = round((float) $item->valor_desconto + $rateio[$n], 2);
            $ipi = round((float) $item->valor_ipi, 2);

            $credito = in_array((string) $item->cst_pis_cofins, CreditoPisCofinsEntrada::CSTS_COM_CREDITO, true);
            $pis = $this->camposPisCofins((string) $item->cst_pis_cofins, $credito, $item);

            if ($credito) {
                $cstCred = (string) $item->cst_pis_cofins;
                $nat = (string) ($item->nat_bc_cred ?: '01');
                $this->creditosApu[$cstCred][$nat] = ($this->creditosApu[$cstCred][$nat] ?? 0) + $pis['bc'];
            }

            $c170[] = $this->c170($n + 1, $codItem, (float) $item->quantidade, $unidade, $vlItem, $vlDesc,
                $this->cstIcms($item->cst_csosn_entrada, $item->origem_mercadoria ?? $produto->origem_mercadoria),
                (string) $item->cfopEntrada?->codigo,
                (float) $item->bc_icms, (float) $item->aliquota_icms, (float) $item->valor_icms, $ipi, $pis, $codCta);

            $tot['merc']   += $vlItem;
            $tot['desc']   += $vlDesc;
            $tot['frete']  += (float) $item->valor_frete;
            $tot['outras'] += (float) $item->valor_outras_despesas;
            $tot['bc']     += (float) $item->bc_icms;
            $tot['icms']   += (float) $item->valor_icms;
            $tot['ipi']    += $ipi;
            $tot['pis']    += $pis['vl_pis'];
            $tot['cofins'] += $pis['vl_cofins'];
        }

        $tot['frete']  += (float) $entrada->valor_frete;
        $tot['outras'] += (float) $entrada->valor_outras;

        $this->resumo['pis_credito']    += $tot['pis'];
        $this->resumo['cofins_credito'] += $tot['cofins'];

        $vlDoc = round((float) ($entrada->valor_total_xml ?? $entrada->valor_total), 2);

        $c100 = $this->c100('0', '1', $codPart, '55', $this->digitos($entrada->serie) ?: '0', (int) $entrada->numero,
            $entrada->chave_acesso, $entrada->data_emissao, $entrada->data_entrada, $vlDoc, '2', $tot, $tot['frete'] > 0 ? '1' : '9');

        return array_merge([$c100], $c170);
    }

    // ---------------- NF-e de emissão própria ----------------

    private function nfeProprias()
    {
        return NotaFiscal::with(['cliente', 'itens.produto.ncm'])
            ->where('status', 'emitida')
            ->whereNotNull('chave_acesso')
            ->whereBetween('emitida_em', [$this->inicio, $this->fim])
            ->orderBy('numero')
            ->get();
    }

    private function linhasNfePropria(NotaFiscal $nota): array
    {
        $this->resumo['nfe_proprias']++;

        $indOper = SpedFiscalGerador::indicadorOperacao($nota);
        $codCta = $this->conta($indOper === '1' ? 'receita' : 'compras');

        $c170 = [];
        $tot = ['merc' => 0, 'desc' => 0, 'frete' => 0, 'outras' => 0, 'bc' => 0, 'icms' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0];

        foreach ($nota->itens->values() as $n => $item) {
            $produto = $item->produto;
            $codItem = $this->item($produto);
            $unidade = $this->unidade($produto->unidade_comercial);

            $vlItem = round((float) $item->quantidade * (float) $item->valor_unitario, 2);
            $vlDesc = round((float) ($item->desconto_efetivo ?? $item->valor_desconto ?? 0), 2);
            $ipi = round((float) $item->valor_ipi, 2);

            $cst = (string) $item->cst_pis_cofins;
            $pis = $this->camposPisCofins($cst, in_array($cst, self::CST_SAIDA_COM_BASE, true), $item);

            $c170[] = $this->c170($n + 1, $codItem, (float) $item->quantidade, $unidade, $vlItem, $vlDesc,
                $this->cstIcms($item->cst_csosn, $produto?->origem_mercadoria), (string) $item->cfop,
                (float) $item->bc_icms, (float) $item->aliquota_icms, (float) $item->valor_icms, $ipi, $pis, $codCta);

            $tot['merc']   += $vlItem;
            $tot['desc']   += $vlDesc;
            $tot['frete']  += (float) $item->valor_frete;
            $tot['outras'] += (float) $item->valor_outras_despesas;
            $tot['bc']     += (float) $item->bc_icms;
            $tot['icms']   += (float) $item->valor_icms;
            $tot['ipi']    += $ipi;
            $tot['pis']    += $pis['vl_pis'];
            $tot['cofins'] += $pis['vl_cofins'];

            if ($indOper === '1') {
                $this->resumo['receita'] += $vlItem - $vlDesc;
                $this->acumularSaida($cst, $pis, $vlItem, $produto);
            }
        }

        if ($indOper === '1') {
            $this->resumo['pis_debito']    += $tot['pis'];
            $this->resumo['cofins_debito'] += $tot['cofins'];
        }

        $data = Carbon::parse($nota->emitida_em);
        $codPart = $nota->cliente ? $this->participante('C', $nota->cliente) : '';
        $codSit = (int) $nota->finalidade === 2 ? '06' : '00';

        $c100 = $this->c100($indOper, '0', $codPart, '55', $this->digitos((string) $nota->serie) ?: '0', (int) $nota->numero,
            $nota->chave_acesso, $data, $data, round((float) $nota->valor_total, 2), '2', $tot,
            (string) ($nota->mod_frete ?? 9), $codSit);

        return array_merge([$c100], $c170);
    }

    // ---------------- NFC-e (modelo 65): C100 + C175 ----------------

    private function nfces()
    {
        return Venda::with(['itens.produto'])
            ->whereNotNull('chave_nfe')
            ->whereNotNull('protocolo_nfe')
            ->whereBetween('emitida_em', [$this->inicio, $this->fim])
            ->orderBy('serie_nfce')->orderBy('numero_nfce')
            ->get()
            ->reject(fn ($v) => str_contains(strtolower((string) $v->status), 'cancel'));
    }

    private function linhasNfce(Venda $venda): array
    {
        $this->resumo['nfce']++;

        $codCta = $this->conta('receita');
        $grupos = [];
        $tot = ['merc' => 0, 'desc' => 0, 'frete' => 0, 'outras' => 0, 'bc' => 0, 'icms' => 0, 'ipi' => 0, 'pis' => 0, 'cofins' => 0];

        foreach ($venda->itens as $item) {
            if (!$item->produto_id) {
                continue;
            }

            $bruto = round((float) ($item->valor_bruto ?: (float) $item->preco_unitario * (float) $item->quantidade), 2);
            $desc  = round((float) ($item->desconto_efetivo ?? $item->desconto ?? 0), 2);
            $cst   = (string) $item->cst_pis_cofins;
            $pis   = $this->camposPisCofins($cst, in_array($cst, self::CST_SAIDA_COM_BASE, true), $item);
            $cfop  = (string) $item->cfop;

            $chave = implode('|', [$cfop, $cst, $pis['aliq_pis'], $pis['aliq_cofins']]);
            $grupos[$chave] ??= ['cfop' => $cfop, 'cst' => $cst, 'com_base' => $pis['com_base'],
                'aliq_pis' => $pis['aliq_pis'], 'aliq_cofins' => $pis['aliq_cofins'],
                'opr' => 0.0, 'desc' => 0.0, 'bc' => 0.0, 'pis' => 0.0, 'cofins' => 0.0];

            $grupos[$chave]['opr']    += $bruto;
            $grupos[$chave]['desc']   += $desc;
            $grupos[$chave]['bc']     += $pis['bc'];
            $grupos[$chave]['pis']    += $pis['vl_pis'];
            $grupos[$chave]['cofins'] += $pis['vl_cofins'];

            $tot['merc']   += $bruto;
            $tot['desc']   += $desc;
            $tot['bc']     += (float) $item->bc_icms;
            $tot['icms']   += (float) $item->valor_icms;
            $tot['pis']    += $pis['vl_pis'];
            $tot['cofins'] += $pis['vl_cofins'];

            $this->resumo['receita'] += $bruto - $desc;
            $this->acumularSaida($cst, $pis, $bruto, $item->produto);
        }

        $this->resumo['pis_debito']    += $tot['pis'];
        $this->resumo['cofins_debito'] += $tot['cofins'];

        $c175 = [];
        foreach ($grupos as $g) {
            $comBase = $g['com_base'];

            $c175[] = ['C175', $g['cfop'], $this->numero($g['opr']), $this->numero($g['desc']),
                $g['cst'], $comBase ? $this->numero($g['bc']) : '', $comBase ? $this->numero($g['aliq_pis'], 4) : '', '', '',
                $comBase ? $this->numero($g['pis']) : '',
                $g['cst'], $comBase ? $this->numero($g['bc']) : '', $comBase ? $this->numero($g['aliq_cofins'], 4) : '', '', '',
                $comBase ? $this->numero($g['cofins']) : '',
                $codCta, ''];
        }

        $data = Carbon::parse($venda->emitida_em);

        $c100 = $this->c100('1', '0', '', '65', $this->digitos((string) $venda->serie_nfce) ?: '0', (int) $venda->numero_nfce,
            $venda->chave_nfe, $data, $data, round((float) $venda->total, 2), '0', $tot, '9');

        return array_merge([$c100], $c175);
    }

    /** Guarda a saída para a apuração: CST 01/02 = contribuição (M210/M610); 04 a 09 = M400/M800. */
    private function acumularSaida(string $cst, array $pis, float $receita, ?Produto $produto): void
    {
        if (isset(self::COD_CONT[$cst]) && $pis['bc'] > 0) {
            $chave = $cst . '|' . $pis['aliq_pis'] . '|' . $pis['aliq_cofins'];
            $this->debitos[$chave] ??= ['cst' => $cst, 'aliq_pis' => $pis['aliq_pis'], 'aliq_cofins' => $pis['aliq_cofins'], 'rec' => 0.0, 'bc' => 0.0];
            $this->debitos[$chave]['rec'] += $receita;
            $this->debitos[$chave]['bc']  += $pis['bc'];

            return;
        }

        if (in_array($cst, self::CST_SEM_INCIDENCIA, true)) {
            $nat = (string) ($produto?->nat_rec_pis_cofins ?: '999');
            $this->semIncidencia[$cst][$nat] = ($this->semIncidencia[$cst][$nat] ?? 0) + $receita;
        }
    }

    // ==================================================================
    // BLOCO M — apuração do PIS (M100 a M410) e da COFINS (M500 a M810)
    // ==================================================================

    /** @return array saldos de crédito de PIS e COFINS para o Bloco 1 */
    private function montarBlocoM(): array
    {
        $pis    = $this->apurar('pis');
        $cofins = $this->apurar('cofins');

        $this->fecharBloco('M', array_merge($pis['linhas'], $cofins['linhas']));

        foreach (['pis' => $pis, 'cofins' => $cofins] as $tributo => $a) {
            $this->resumo["{$tributo}_debito"]   = $a['contribuicao'];
            $this->resumo["{$tributo}_credito"]  = $a['credito'];
            $this->resumo["{$tributo}_recolher"] = $a['recolher'];
        }

        return ['pis' => $pis['controle'], 'cofins' => $cofins['controle']];
    }

    private function apurar(string $tributo): array
    {
        $ehPis = $tributo === 'pis';
        $nc = $this->regime === 'nao_cumulativo';
        $aliqCred = $ehPis ? CreditoPisCofinsEntrada::ALIQUOTA_PIS_NAO_CUMULATIVO : CreditoPisCofinsEntrada::ALIQUOTA_COFINS_NAO_CUMULATIVO;
        [$regCred, $regDet, $regCons, $regRec, $regCont, $regSem, $regSemDet] = $ehPis
            ? ['M100', 'M105', 'M200', 'M205', 'M210', 'M400', 'M410']
            : ['M500', 'M505', 'M600', 'M605', 'M610', 'M800', 'M810'];
        $codCta = $this->conta('receita');

        // ---- contribuição do período (M210/M610) ----
        $m210 = [];
        $contribuicao = 0.0;
        foreach ($this->debitos as $d) {
            $aliq = $ehPis ? $d['aliq_pis'] : $d['aliq_cofins'];
            $bc = round($d['bc'], 2);
            $valor = round($bc * $aliq / 100, 2);
            $contribuicao += $valor;

            $m210[] = [$regCont, self::COD_CONT[$d['cst']][$nc ? 0 : 1], $this->numero($d['rec']), $this->numero($bc),
                $this->numero(0), $this->numero(0), $this->numero($bc), $this->numero($aliq, 4), '', '',
                $this->numero($valor), $this->numero(0), $this->numero(0), $this->numero(0), $this->numero(0), $this->numero($valor)];
        }
        $contribuicao = round($contribuicao, 2);

        // ---- créditos do período (M100/M105), só no não cumulativo ----
        $linhasCred = [];
        $creditoTotal = 0.0;
        $restante = $contribuicao;      // quanto ainda dá para descontar
        $descontoPeriodo = 0.0;         // M200 campo 03
        $controle = [];                 // linhas do 1100/1500

        // Saldos de meses anteriores (vindos do arquivo do mês passado): usados primeiro, do mais antigo ao mais novo
        $descontoAnterior = 0.0;   // M200 campo 04
        if ($nc) {
            foreach ($this->saldosAnteriores($tributo) as $s) {
                $desconto = round(min((float) $s['fim'], $restante), 2);
                $restante = round($restante - $desconto, 2);
                $descontoAnterior += $desconto;
                $fim = round((float) $s['fim'] - $desconto, 2);

                $controle[] = ['per' => $s['per'], 'cod' => $s['cod'], 'apu' => (float) $s['apu'],
                    'desc_ant' => round((float) $s['apu'] - (float) $s['fim'], 2), 'disp' => (float) $s['fim'],
                    'desc_efd' => $desconto, 'fim' => $fim];
            }
        }

        if ($nc) {
            ksort($this->creditosApu);
            foreach ($this->creditosApu as $cst => $naturezas) {
                $bc = round(array_sum($naturezas), 2);
                $credito = round($bc * $aliqCred / 100, 2);
                $desconto = round(min($credito, $restante), 2);
                $restante = round($restante - $desconto, 2);
                $saldo = round($credito - $desconto, 2);
                $creditoTotal += $credito;
                $descontoPeriodo += $desconto;
                $codCred = self::COD_CRED[$cst] ?? '101';

                $linhasCred[] = [$regCred, $codCred, '0', $this->numero($bc), $this->numero($aliqCred, 4), '', '',
                    $this->numero($credito), $this->numero(0), $this->numero(0), $this->numero(0), $this->numero($credito),
                    $saldo > 0 ? '1' : '0', $this->numero($desconto), $this->numero($saldo)];

                ksort($naturezas);
                foreach ($naturezas as $nat => $base) {
                    $base = round($base, 2);
                    $linhasCred[] = [$regDet, $nat, $cst, $this->numero($base), '', $this->numero($base), $this->numero($base), '', '', ''];
                }

                // Saldo do próprio mês que sobra vai para o controle (1100/1500)
                if ($saldo > 0) {
                    $controle[] = ['per' => $this->inicio->format('mY'), 'cod' => $codCred, 'apu' => $credito,
                        'desc_ant' => 0.0, 'disp' => $credito, 'desc_efd' => $desconto, 'fim' => $saldo];
                }
            }
        }

        // ---- consolidação (M200/M600) e código de receita (M205/M605) ----
        $devidaNc = $nc ? round($contribuicao - $descontoPeriodo - $descontoAnterior, 2) : 0.0;
        $devidaCum = $nc ? 0.0 : $contribuicao;
        $recolher = round($devidaNc + $devidaCum, 2);

        $linhas = $linhasCred;
        $linhas[] = [$regCons, $this->numero($nc ? $contribuicao : 0), $this->numero($descontoPeriodo), $this->numero($descontoAnterior),
            $this->numero($devidaNc), $this->numero(0), $this->numero(0), $this->numero($devidaNc),
            $this->numero($devidaCum), $this->numero(0), $this->numero(0), $this->numero($devidaCum), $this->numero($recolher)];

        if ($recolher > 0) {
            $codRec = ($ehPis ? self::COD_REC_PIS : self::COD_REC_COFINS)[$nc ? 0 : 1];
            $linhas[] = [$regRec, $nc ? '08' : '12', $codRec, $this->numero($recolher)];
        }

        array_push($linhas, ...$m210);

        // ---- receitas sem incidência (M400/M410, M800/M810) ----
        ksort($this->semIncidencia);
        foreach ($this->semIncidencia as $cst => $naturezas) {
            $linhas[] = [$regSem, $cst, $this->numero(array_sum($naturezas)), $codCta, ''];

            ksort($naturezas);
            foreach ($naturezas as $nat => $valor) {
                $linhas[] = [$regSemDet, $nat, $this->numero($valor), $codCta, ''];
            }
        }

        // Saldos que seguem para o próximo mês
        $this->saldosCredito[$tributo] = array_values(array_filter(array_map(fn ($c) => $c['fim'] > 0
            ? ['per' => $c['per'], 'cod' => $c['cod'], 'apu' => round($c['apu'], 2), 'fim' => round($c['fim'], 2)]
            : null, $controle)));

        return ['linhas' => $linhas, 'controle' => $controle, 'contribuicao' => $contribuicao,
            'credito' => round($creditoTotal, 2), 'recolher' => $recolher];
    }

    /**
     * Saldos de crédito que sobraram no arquivo do mês anterior (gravados no histórico de gerações).
     * Mês anterior sem arquivo gerado = sem saldos (o operador é avisado na verificação).
     */
    private function saldosAnteriores(string $tributo): array
    {
        $anterior = \App\Models\SpedGeracao::where('tipo', 'contribuicoes')
            ->whereDate('competencia', $this->inicio->copy()->subMonthNoOverflow()->toDateString())
            ->latest()
            ->first();

        $saldos = $anterior?->totais['saldos_credito'][$tributo] ?? [];

        // Do mais antigo ao mais novo (PER_APU_CRED em MMAAAA)
        usort($saldos, fn ($a, $b) => substr($a['per'], 2) . substr($a['per'], 0, 2) <=> substr($b['per'], 2) . substr($b['per'], 0, 2));

        return $saldos;
    }

    // ==================================================================
    // BLOCO 1 — controle de créditos (1100 PIS / 1500 COFINS)
    // ==================================================================

    private function montarBloco1(array $controle): void
    {
        $linhas = [];

        foreach (['pis' => '1100', 'cofins' => '1500'] as $tributo => $reg) {
            // Mais antigos primeiro (PER_APU_CRED em MMAAAA)
            $lista = $controle[$tributo];
            usort($lista, fn ($a, $b) => substr($a['per'], 2) . substr($a['per'], 0, 2) <=> substr($b['per'], 2) . substr($b['per'], 0, 2));

            foreach ($lista as $c) {
                $linhas[] = [$reg, $c['per'], '01', '', $c['cod'], $this->numero($c['apu']), $this->numero(0), $this->numero($c['apu']),
                    $this->numero($c['desc_ant']), $this->numero(0), $this->numero(0), $this->numero($c['disp']),
                    $this->numero($c['desc_efd']), $this->numero(0), $this->numero(0), $this->numero(0), $this->numero(0),
                    $this->numero($c['fim'])];
            }
        }

        $this->fecharBloco('1', $linhas);
    }

    // ---------------- Apoio do Bloco C ----------------

    private function c100(string $indOper, string $indEmit, string $codPart, string $modelo, string $serie, int $numero,
                          ?string $chave, $dtDoc, $dtEs, float $vlDoc, string $indPgto, array $tot, string $indFrt, string $codSit = '00'): array
    {
        return ['C100', $indOper, $indEmit, $codPart, $modelo, $codSit, $serie, $numero, (string) $chave,
            $this->data($dtDoc), $this->data($dtEs), $this->numero($vlDoc), $indPgto,
            $this->numero($tot['desc']), $this->numero(0), $this->numero($tot['merc']),
            $indFrt, $this->numero($tot['frete']), $this->numero(0), $this->numero($tot['outras']),
            $this->numero($tot['bc']), $this->numero($tot['icms']), $this->numero(0), $this->numero(0),
            $this->numero($tot['ipi']), $this->numero($tot['pis']), $this->numero($tot['cofins']),
            $this->numero(0), $this->numero(0)];
    }

    /** C170 da EFD-Contribuições: 37 campos. */
    private function c170(int $n, string $codItem, float $qtd, string $unidade, float $vlItem, float $vlDesc,
                          string $cstIcms, string $cfop, float $bcIcms, float $aliqIcms, float $icms, float $ipi,
                          array $pis, string $codCta): array
    {
        $comBase = $pis['com_base'];

        return ['C170', $n, $codItem, '', $this->numero($qtd, 5), $unidade,
            $this->numero($vlItem), $this->numero($vlDesc), '0', $cstIcms, $cfop, '',
            $this->numero($bcIcms), $this->numero($aliqIcms), $this->numero($icms),
            $this->numero(0), $this->numero(0), $this->numero(0), '',
            '', '', '', '', $this->numero($ipi),
            $pis['cst'], $comBase ? $this->numero($pis['bc']) : '', $comBase ? $this->numero($pis['aliq_pis'], 4) : '', '', '',
            $this->numero($pis['vl_pis']),
            $pis['cst'], $comBase ? $this->numero($pis['bc']) : '', $comBase ? $this->numero($pis['aliq_cofins'], 4) : '', '', '',
            $this->numero($pis['vl_cofins']),
            $codCta];
    }

    /**
     * Base, alíquotas e valores do item como foram gravados. CST sem base (04 a 09, 70 a 75, 98...)
     * sai com base/alíquota vazias e valores zerados, mesmo que a linha tenha base gravada.
     */
    private function camposPisCofins(string $cst, bool $comBase, $item): array
    {
        return [
            'cst'         => $cst,
            'com_base'    => $comBase,
            'bc'          => $comBase ? round((float) $item->bc_pis_cofins, 2) : 0.0,
            'aliq_pis'    => $comBase ? round((float) $item->aliquota_pis, 4) : 0.0,
            'aliq_cofins' => $comBase ? round((float) $item->aliquota_cofins, 4) : 0.0,
            'vl_pis'      => $comBase ? round((float) $item->valor_pis, 2) : 0.0,
            'vl_cofins'   => $comBase ? round((float) $item->valor_cofins, 2) : 0.0,
        ];
    }

    private function cstIcms(?string $codigo, $origem): string
    {
        $codigo = $this->digitos($codigo);
        $origem = (string) (int) ($origem ?? 0);

        if (strlen($codigo) === 2) {
            return $origem . $codigo;
        }

        return strlen($codigo) === 3 ? $codigo : $origem . self::CST_ICMS_PADRAO;
    }

    private function ratear(float $valor, array $bases): array
    {
        $resultado = array_fill(0, count($bases), 0.0);

        if (!$bases || $valor == 0.0) {
            return $resultado;
        }

        $total = array_sum($bases);
        $maior = array_keys($bases, max($bases))[0];

        foreach ($bases as $i => $base) {
            $resultado[$i] = $total > 0 ? round($valor * $base / $total, 2) : 0.0;
        }

        $resultado[$maior] = round($resultado[$maior] + ($valor - array_sum($resultado)), 2);

        return $resultado;
    }

    // ==================================================================
    // Montagem final
    // ==================================================================

    private function fecharBloco(string $bloco, array $linhas): void
    {
        if ($bloco !== '0') {
            array_unshift($linhas, [$bloco . '001', count($linhas) > 0 ? '0' : '1']);
        }

        $linhas[] = [$bloco . '990', count($linhas) + 1];
        $this->blocos[$bloco] = $linhas;
    }

    private function montarArquivo(): array
    {
        $ordem = ['0', 'A', 'C', 'D', 'F', 'I', 'M', 'P', '1'];
        $linhas = [];

        foreach ($ordem as $bloco) {
            array_push($linhas, ...($this->blocos[$bloco] ?? []));
        }

        $porRegistro = [];
        foreach ($linhas as $l) {
            $porRegistro[$l[0]] = ($porRegistro[$l[0]] ?? 0) + 1;
        }

        $registros9 = array_keys($porRegistro);
        array_push($registros9, '9001', '9900', '9990', '9999');

        $bloco9 = [['9001', '0']];
        foreach ($registros9 as $reg) {
            $qtd = match ($reg) {
                '9001', '9990', '9999' => 1,
                '9900' => count($registros9),
                default => $porRegistro[$reg],
            };
            $bloco9[] = ['9900', $reg, $qtd];
        }

        $totalLinhas = count($linhas) + count($bloco9) + 2;
        $bloco9[] = ['9990', count($bloco9) + 2];
        $bloco9[] = ['9999', $totalLinhas];

        array_push($linhas, ...$bloco9);

        $texto = implode("\r\n", array_map(fn ($campos) => '|' . implode('|', $campos) . '|', $linhas)) . "\r\n";

        return ['texto' => $texto, 'total_linhas' => $totalLinhas];
    }

    // ==================================================================
    // Formatação
    // ==================================================================

    private function data($data): string
    {
        return $data ? Carbon::parse($data)->format('dmY') : '';
    }

    private function numero(float|int $valor, int $casas = 2): string
    {
        return number_format(round((float) $valor, $casas), $casas, ',', '');
    }

    private function texto(?string $valor, ?int $maximo = null): string
    {
        $limpo = trim(preg_replace('/[|\r\n\t]+/', ' ', (string) $valor));

        return $maximo ? mb_substr($limpo, 0, $maximo) : $limpo;
    }

    private function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }
}