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
 *  - Blocos A, D, F, I e 1 abertos "sem dados"; Bloco 9 com os totais
 *  - Bloco M (apuração): ainda "sem dados" — use "Gerar apurações" no PVA até a próxima etapa
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

        foreach (['A', 'D', 'F', 'I', 'M', '1'] as $bloco) {
            $this->fecharBloco($bloco, []);
        }

        // Bloco P (CPRB) deixou de ser obrigatório a partir de 01/2025 (NT 009/2024)
        if ($this->inicio->year < 2025) {
            $this->fecharBloco('P', []);
        }

        $conteudo = $this->montarArquivo();

        foreach (['receita', 'pis_debito', 'cofins_debito', 'pis_credito', 'cofins_credito'] as $k) {
            $this->resumo[$k] = round($this->resumo[$k], 2);
        }

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
        return Venda::with(['itens'])
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
