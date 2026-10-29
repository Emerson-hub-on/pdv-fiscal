<?php

namespace App\Services;

use App\Models\Credenciadora;
use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\Inventario;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\SpedConfiguracao;
use App\Models\Unidade;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Gera o arquivo da EFD ICMS/IPI (SPED Fiscal) de um mês.
 *
 * Escopo desta versão (o resto entra nas próximas etapas):
 *  - Bloco 0: abertura, empresa, contador, participantes, unidades, itens e conversões (0220)
 *  - Bloco C: NF-e de emissão própria (C100 + C190), NFC-e (C100 + C190) e
 *             NF-e de entrada de terceiros (C100 + C170 + C190)
 *  - Bloco E: apuração do ICMS próprio (E100, E110 e E116)
 *  - Bloco H: inventário fechado escolhido na geração (H005 + H010)
 *  - Blocos B, D, G, K e 1 abertos "sem movimento"; Bloco 9 com os totais
 *
 * Fica de fora por enquanto: ICMS-ST (E200), IPI (E500), DIFAL (E300), H020 (motivos 02 a 05),
 * produção e estoque (K), documentos inutilizados e ajustes de apuração (C197/E111).
 */
class SpedFiscalGerador
{
    /** COD_VER do registro 0000 por ano da escrituração (tabela de versões do leiaute) */
    private const VERSOES_LEIAUTE = [
        2024 => '018',
        2025 => '019',
        2026 => '020',
    ];

    private const CSOSN = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];

    /**
     * Empresa do Simples informa o próprio CSOSN no CST_ICMS (3 dígitos, sem a origem),
     * como fazem os sistemas de mercado e o PVA aceita.
     */
    private const CST_PADRAO = '90';

    private Carbon $inicio;
    private Carbon $fim;
    private Empresa $empresa;
    private SpedConfiguracao $config;
    private array $opcoes;
    private bool $usaCsosn;

    /** Linhas já montadas, por bloco: ['0' => [[campos], ...], 'C' => [...], ...] */
    private array $blocos = [];

    /** Participantes (0150), unidades (0190), itens (0200) e conversões (0220) referenciados */
    private array $participantes = [];
    private array $unidades = [];
    private array $itens = [];
    private array $conversoes = [];

    /** Totais do ICMS para o E110 */
    private float $debitos = 0;
    private float $creditos = 0;

    private array $resumo = [
        'nfe_proprias' => 0, 'nfe_canceladas' => 0, 'nfce' => 0, 'nfce_canceladas' => 0,
        'nfce_ignoradas' => 0, 'entradas' => 0, 'entradas_ignoradas' => 0,
    ];

    /**
     * @param array $opcoes finalidade ('0'|'1'), destacar_icms (bool), saldo_credor_anterior (float),
     *                     dt_vencimento (?string Y-m-d), inventario_id (?int)
     * @return array{conteudo: string, total_linhas: int, resumo: array, apuracao: array}
     */
    public function gerar(Carbon $competencia, array $opcoes): array
    {
        $this->inicio  = $competencia->copy()->startOfMonth()->startOfDay();
        $this->fim     = $competencia->copy()->endOfMonth()->endOfDay();
        $this->empresa = Empresa::firstOrFail();
        $this->config  = SpedConfiguracao::firstOrFail();
        $this->opcoes  = $opcoes;
        $this->usaCsosn = in_array((int) $this->empresa->crt, [1, 4], true);

        // Bloco C primeiro: ele descobre quais participantes, unidades e itens o Bloco 0 precisa listar
        $this->montarBlocoC();
        $apuracao = $this->montarBlocoE();
        $this->montarBlocoH();
        $this->montarBloco1();
        $this->montarBloco0();
        $this->montarBlocosSemMovimento();

        $conteudo = $this->montarArquivo();

        return [
            'conteudo'     => $conteudo['texto'],
            'total_linhas' => $conteudo['total_linhas'],
            'resumo'       => $this->resumo,
            'apuracao'     => $apuracao,
        ];
    }

    // ==================================================================
    // BLOCO 0
    // ==================================================================

    private function montarBloco0(): void
    {
        $e = $this->empresa;
        $c = $this->config;

        $ano = (int) $this->inicio->year;
        // Ano sem versão na tabela: usa a mais recente (atualize a tabela quando sair leiaute novo)
        $versao = self::VERSOES_LEIAUTE[$ano] ?? self::VERSOES_LEIAUTE[max(array_keys(self::VERSOES_LEIAUTE))];

        $linhas = [];

        $linhas[] = ['0000', $versao, $this->opcoes['finalidade'], $this->data($this->inicio), $this->data($this->fim),
            $this->texto($e->razao_social, 100), $this->digitos($e->cnpj), '', strtoupper($e->uf),
            $this->ie($e->ie), $e->cod_municipio, $this->texto($e->im), '', $c->ind_perfil ?: 'A', (string) $c->ind_ativ];

        $linhas[] = ['0001', '0'];

        if ($c->isIndustrial()) {
            $linhas[] = ['0002', $c->clas_estab_ind];
        }

        $linhas[] = ['0005', $this->texto($e->nome_fantasia ?: $e->razao_social, 60), $this->digitos($e->cep),
            $this->texto($e->logradouro, 60), $this->texto($e->numero, 10), $this->texto($e->complemento, 60),
            $this->texto($e->bairro, 60), $this->digitos($c->empresa_fone), '', $this->texto($c->empresa_email)];

        $linhas[] = ['0100', $this->texto($c->contador_nome, 100), $c->contador_cpf, $this->texto($c->contador_crc, 15),
            $c->contador_cnpj ?? '', $c->contador_cep, $this->texto($c->contador_endereco, 60), $this->texto($c->contador_numero, 10),
            $this->texto($c->contador_complemento, 60), $this->texto($c->contador_bairro, 60), $c->contador_fone ?? '', '',
            $this->texto($c->contador_email), $c->contador_cod_municipio];

        ksort($this->participantes);
        foreach ($this->participantes as $p) {
            $linhas[] = $p;
        }

        // Toda unidade usada no 0200 (UNID_INV), no 0220 e no C170 precisa estar no 0190
        $descricoes = Unidade::whereIn('sigla', array_keys($this->unidades))->pluck('descricao', 'sigla');
        ksort($this->unidades);
        foreach (array_keys($this->unidades) as $sigla) {
            $linhas[] = ['0190', $sigla, $this->texto($descricoes[$sigla] ?? $sigla, 100)];
        }

        ksort($this->itens);
        foreach ($this->itens as $codigo => $item) {
            $linhas[] = $item;

            foreach ($this->conversoes[$codigo] ?? [] as $conversao) {
                $linhas[] = $conversao;
            }
        }

        $this->fecharBloco('0', $linhas);
    }

    /** Registra o participante (0150) e devolve o COD_PART. */
    private function participante(string $prefixo, $pessoa): string
    {
        $codigo = $prefixo . $pessoa->id;

        if (!isset($this->participantes[$codigo])) {
            $doc = $this->digitos($pessoa->cpf_cnpj);

            $this->participantes[$codigo] = ['0150', $codigo, $this->texto($pessoa->nome, 100), '01058',
                strlen($doc) === 14 ? $doc : '', strlen($doc) === 11 ? $doc : '',
                $this->ie($pessoa->ie), $pessoa->cod_municipio, '',
                $this->texto($pessoa->logradouro, 60), $this->texto($pessoa->numero, 10),
                $this->texto($pessoa->complemento, 60), $this->texto($pessoa->bairro, 60)];
        }

        return $codigo;
    }

    /** Registra a credenciadora como participante (0150) e devolve o COD_PART. */
    private function participanteCredenciadora(Credenciadora $c): string
    {
        $codigo = 'I' . $c->id;

        $this->participantes[$codigo] ??= ['0150', $codigo, $this->texto($c->nome, 100), '01058', $this->digitos($c->cnpj), '',
            '', $c->cod_municipio, '', $this->texto($c->logradouro, 60), $this->texto($c->numero, 10),
            $this->texto($c->complemento, 60), $this->texto($c->bairro, 60)];

        return $codigo;
    }

    /**
     * Entrada ou saída da NF-e própria. Vale o tpNF do XML autorizado; sem XML, o CFOP
     * (1, 2, 3 = entrada); por último o campo tipo_operacao. Ex.: a NF-e de entrada com CFOP 1949
     * que anula uma saída fora do prazo de cancelamento é escriturada como ENTRADA.
     */
    public static function indicadorOperacao(NotaFiscal $nota): string
    {
        if ($nota->xml && preg_match('/<tpNF>([01])<\/tpNF>/', $nota->xml, $m)) {
            return $m[1];
        }

        $cfop = (string) ($nota->itens?->first()?->cfop ?? '');
        if ($cfop !== '') {
            return in_array($cfop[0], ['1', '2', '3'], true) ? '0' : '1';
        }

        return in_array((string) $nota->tipo_operacao, ['0', 'entrada'], true) ? '0' : '1';
    }

    /** Registra o item (0200) e a unidade dele (0190); devolve o COD_ITEM. */
    private function item(Produto $produto): string
    {
        $codigo = $this->texto($produto->codigo_interno, 60);

        if (!isset($this->itens[$codigo])) {
            $unidade = strtoupper(trim((string) $produto->unidade_comercial));
            $ncm = $this->digitos($produto->ncm?->codigo);

            $this->unidades[$unidade] = true;
            $this->itens[$codigo] = ['0200', $codigo, $this->texto($produto->nome),
                $produto->codigo_barras_valido ? $this->digitos($produto->codigo_barras) : '', '',
                $unidade, $produto->tipo_item ?: '00', $ncm, '',
                $produto->tipo_item === '09' ? '00' : substr($ncm, 0, 2), '', '',
                $this->digitos($produto->cest?->codigo)];
        }

        return $codigo;
    }

    // ==================================================================
    // BLOCO C
    // ==================================================================

    private function montarBlocoC(): void
    {
        $linhas = [];

        foreach ($this->entradas() as $entrada) {
            array_push($linhas, ...$this->linhasEntrada($entrada));
        }

        foreach ($this->nfeProprias() as $nota) {
            array_push($linhas, ...$this->linhasNfePropria($nota));
        }

        foreach ($this->nfces() as $venda) {
            array_push($linhas, ...$this->linhasNfce($venda));
        }

        $this->fecharBloco('C', $linhas);
    }

    // ---------------- Entradas (NF-e de terceiros) ----------------

    private function entradas()
    {
        $entradas = EntradaNota::with(['fornecedor', 'itens.produto.ncm', 'itens.produto.cest', 'itens.cfopEntrada'])
            ->where('status', 'finalizada')
            ->whereBetween('data_entrada', [$this->inicio->toDateString(), $this->fim->toDateString()])
            ->orderBy('data_entrada')->orderBy('id')
            ->get();

        // Esta versão escritura só a NF-e (modelo 55); os demais modelos entram depois
        $this->resumo['entradas_ignoradas'] = $entradas->where('modelo', '!=', '55')->count();

        return $entradas->where('modelo', '55');
    }

    private function linhasEntrada(EntradaNota $entrada): array
    {
        $this->resumo['entradas']++;

        $codPart = $this->participante('F', $entrada->fornecedor);
        $industrial = $this->config->isIndustrial();

        // O desconto do cabeçalho é rateado entre os itens pelo valor bruto
        $rateio = $this->ratear(
            (float) $entrada->valor_desconto,
            $entrada->itens->values()->map(fn ($i) => (float) $i->quantidade * (float) $i->valor_unitario)->all()
        );

        $c170 = [];
        $grupos = [];
        $tot = ['merc' => 0, 'desc' => 0, 'frete' => 0, 'outras' => 0, 'bc' => 0, 'icms' => 0, 'ipi' => 0];

        foreach ($entrada->itens->values() as $n => $item) {
            $produto = $item->produto;
            $codItem = $this->item($produto);

            $unidade = strtoupper(trim((string) ($item->unidade ?: $produto->unidade_comercial)));
            $this->unidades[$unidade] = true;

            // Unidade da nota diferente da unidade de estoque: precisa do fator no 0220
            if ($unidade !== strtoupper(trim((string) $produto->unidade_comercial))) {
                $this->conversoes[$codItem][$unidade] ??= ['0220', $unidade, $this->numero(max((int) $item->qtd_por_caixa, 1), 6), ''];
            }

            $vlItem = round((float) $item->quantidade * (float) $item->valor_unitario, 2);
            $vlDesc = round((float) $item->valor_desconto + $rateio[$n], 2);

            $temCredito = $this->opcoes['destacar_icms'] && $item->gera_credito !== false;
            $bc   = $temCredito ? round((float) $item->bc_icms, 2) : 0.0;
            $aliq = $temCredito ? (float) $item->aliquota_icms : 0.0;
            $icms = $temCredito ? round((float) $item->valor_icms, 2) : 0.0;
            $ipi  = round((float) $item->valor_ipi, 2);

            $cst  = $this->cstIcms($item->cst_csosn_entrada, $item->origem_mercadoria ?? $produto->origem_mercadoria);
            $cfop = (string) $item->cfopEntrada?->codigo;

            // CST do IPI só para contribuinte do IPI (indústria)
            $cstIpi = $industrial && $ipi > 0 ? '00' : ($industrial ? '49' : '');

            $c170[] = ['C170', $n + 1, $codItem, '', $this->numero((float) $item->quantidade, 5), $unidade,
                $this->numero($vlItem), $this->numero($vlDesc), '0', $cst, $cfop, '',
                $this->numero($bc), $this->numero($aliq), $this->numero($icms),
                $this->numero(0), $this->numero(0), $this->numero(0), '',
                $cstIpi, '', $industrial && $ipi > 0 ? $this->numero($vlItem) : '', $industrial && $ipi > 0 ? $this->numero((float) $item->aliquota_ipi) : '',
                $this->numero($ipi),
                '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

            $operacao = $vlItem - $vlDesc + (float) $item->valor_frete + (float) $item->valor_outras_despesas + $ipi;
            $this->somarGrupo($grupos, $cst, $cfop, $aliq, $operacao, $bc, $icms, $ipi);

            $tot['merc']   += $vlItem;
            $tot['desc']   += $vlDesc;
            $tot['frete']  += (float) $item->valor_frete;
            $tot['outras'] += (float) $item->valor_outras_despesas;
            $tot['bc']     += $bc;
            $tot['icms']   += $icms;
            $tot['ipi']    += $ipi;
        }

        $tot['frete']  += (float) $entrada->valor_frete;
        $tot['outras'] += (float) $entrada->valor_outras;

        // Total da nota: o do XML quando houver (inclui IPI), senão o calculado na entrada
        $vlDoc = round((float) ($entrada->valor_total_xml ?? $entrada->valor_total), 2);

        $c100 = ['C100', '0', '1', $codPart, '55', '00', $this->digitos($entrada->serie) ?: '0', (int) $entrada->numero,
            $entrada->chave_acesso, $this->data($entrada->data_emissao), $this->data($entrada->data_entrada),
            $this->numero($vlDoc), '2', $this->numero($tot['desc']), $this->numero(0), $this->numero($tot['merc']),
            $tot['frete'] > 0 ? '1' : '9', $this->numero($tot['frete']), $this->numero(0), $this->numero($tot['outras']),
            $this->numero($tot['bc']), $this->numero($tot['icms']), $this->numero(0), $this->numero(0),
            $this->numero($tot['ipi']), '', '', '', ''];

        return array_merge([$c100], $c170, $this->linhasC190($grupos, $vlDoc, 'entrada'));
    }

    // ---------------- NF-e de emissão própria ----------------

    private function nfeProprias()
    {
        return NotaFiscal::with(['cliente', 'itens.produto'])
            ->whereIn('status', ['emitida', 'cancelada'])
            ->whereNotNull('chave_acesso')
            ->whereBetween('emitida_em', [$this->inicio, $this->fim])
            ->orderBy('numero')
            ->get();
    }

    private function linhasNfePropria(NotaFiscal $nota): array
    {
        $indOper = self::indicadorOperacao($nota);
        $serie = $this->digitos((string) $nota->serie) ?: '0';

        if ($nota->status === 'cancelada') {
            $this->resumo['nfe_canceladas']++;

            return [$this->c100Cancelado($indOper, '55', $serie, (int) $nota->numero, $nota->chave_acesso)];
        }

        $this->resumo['nfe_proprias']++;

        $grupos = [];
        $tot = ['bc' => 0, 'icms' => 0, 'ipi' => 0, 'outras' => 0];

        foreach ($nota->itens as $item) {
            [$bc, $aliq, $icms] = $this->icmsDestacado($item->bc_icms, $item->aliquota_icms, $item->valor_icms);
            $ipi  = round((float) $item->valor_ipi, 2);
            $cst  = $this->cstIcms($item->cst_csosn, $item->produto?->origem_mercadoria);
            $cfop = (string) $item->cfop;

            $operacao = (float) $item->valor_total + (float) $item->valor_frete + (float) $item->valor_outras_despesas + $ipi;
            $this->somarGrupo($grupos, $cst, $cfop, $aliq, $operacao, $bc, $icms, $ipi);

            $tot['bc']     += $bc;
            $tot['icms']   += $icms;
            $tot['ipi']    += $ipi;
            $tot['outras'] += (float) $item->valor_outras_despesas;
        }

        $vlDoc = round((float) $nota->valor_total, 2);
        $codPart = $nota->cliente ? $this->participante('C', $nota->cliente) : '';
        $codSit = (int) $nota->finalidade === 2 ? '06' : '00'; // finalidade 2 = complementar
        $data = Carbon::parse($nota->emitida_em);

        $c100 = ['C100', $indOper, '0', $codPart, '55', $codSit, $serie, (int) $nota->numero, $nota->chave_acesso,
            $this->data($data), $this->data($data), $this->numero($vlDoc), '2',
            $this->numero((float) $nota->valor_desconto), $this->numero(0), $this->numero((float) $nota->valor_produtos),
            (string) ($nota->mod_frete ?? 9), $this->numero((float) $nota->valor_frete), $this->numero(0), $this->numero($tot['outras']),
            $this->numero($tot['bc']), $this->numero($tot['icms']), $this->numero(0), $this->numero(0),
            $this->numero($tot['ipi']), '', '', '', ''];

        // NF-e de emissão própria: o C170 não é informado, só o resumo por CST/CFOP/alíquota
        return array_merge([$c100], $this->linhasC190($grupos, $vlDoc, $indOper === '0' ? 'entrada' : 'saida'));
    }

    // ---------------- NFC-e (modelo 65) ----------------

    private function nfces()
    {
        $vendas = Venda::with(['itens.produto'])
            ->whereNotNull('chave_nfe')
            ->whereBetween('emitida_em', [$this->inicio, $this->fim])
            ->orderBy('serie_nfce')->orderBy('numero_nfce')
            ->get();

        // Sem protocolo = não autorizada (ex.: contingência ainda não transmitida): fica fora do arquivo
        $this->resumo['nfce_ignoradas'] = $vendas->filter(fn ($v) => blank($v->protocolo_nfe))->count();

        return $vendas->filter(fn ($v) => filled($v->protocolo_nfe));
    }

    private function linhasNfce(Venda $venda): array
    {
        $serie = $this->digitos((string) $venda->serie_nfce) ?: '0';
        $numero = (int) $venda->numero_nfce;

        if (str_contains(strtolower((string) $venda->status), 'cancel')) {
            $this->resumo['nfce_canceladas']++;

            return [$this->c100Cancelado('1', '65', $serie, $numero, $venda->chave_nfe)];
        }

        $this->resumo['nfce']++;

        $grupos = [];
        $tot = ['bc' => 0, 'icms' => 0];

        foreach ($venda->itens as $item) {
            [$bc, $aliq, $icms] = $this->icmsDestacado($item->bc_icms, $item->aliquota_icms, $item->valor_icms);
            $cst  = $this->cstIcms($item->cst_csosn, $item->produto?->origem_mercadoria);
            $cfop = (string) $item->cfop;

            $this->somarGrupo($grupos, $cst, $cfop, $aliq, (float) $item->subtotal, $bc, $icms, 0);

            $tot['bc']   += $bc;
            $tot['icms'] += $icms;
        }

        $vlDoc = round((float) $venda->total, 2);
        $desconto = round((float) $venda->desconto, 2);
        $data = Carbon::parse($venda->emitida_em);

        // Modelo 65: COD_PART, ST, IPI, PIS e COFINS não são informados (exceção do Guia Prático)
        $c100 = ['C100', '1', '0', '', '65', '00', $serie, $numero, $venda->chave_nfe,
            $this->data($data), $this->data($data), $this->numero($vlDoc), '0',
            $this->numero($desconto), $this->numero(0), $this->numero($vlDoc + $desconto),
            '9', $this->numero(0), $this->numero(0), $this->numero(0),
            $this->numero($tot['bc']), $this->numero($tot['icms']), '', '', '', '', '', '', ''];

        return array_merge([$c100], $this->linhasC190($grupos, $vlDoc, 'saida'));
    }

    // ---------------- Apoio do Bloco C ----------------

    private function c100Cancelado(string $indOper, string $modelo, string $serie, int $numero, string $chave): array
    {
        return array_merge(['C100', $indOper, '0', '', $modelo, '02', $serie, $numero, $chave], array_fill(0, 20, ''));
    }

    private function somarGrupo(array &$grupos, string $cst, string $cfop, float $aliq, float $operacao, float $bc, float $icms, float $ipi): void
    {
        $chave = $cst . '|' . $cfop . '|' . number_format($aliq, 2, '.', '');

        $grupos[$chave] ??= ['cst' => $cst, 'cfop' => $cfop, 'aliq' => $aliq, 'opr' => 0.0, 'bc' => 0.0, 'icms' => 0.0, 'ipi' => 0.0];
        $grupos[$chave]['opr']  += $operacao;
        $grupos[$chave]['bc']   += $bc;
        $grupos[$chave]['icms'] += $icms;
        $grupos[$chave]['ipi']  += $ipi;
    }

    /**
     * C190 do documento. A soma do VL_OPR precisa bater com o VL_DOC do C100: a diferença
     * (desconto e despesas lançados só no cabeçalho, arredondamentos) é rateada entre os grupos.
     */
    private function linhasC190(array $grupos, float $vlDoc, string $sentido): array
    {
        $grupos = array_values($grupos);
        $operacoes = array_map(fn ($g) => round($g['opr'], 2), $grupos);
        $diferenca = round($vlDoc - array_sum($operacoes), 2);

        foreach ($this->ratear($diferenca, $operacoes) as $i => $ajuste) {
            $operacoes[$i] = round($operacoes[$i] + $ajuste, 2);
        }

        $linhas = [];

        foreach ($grupos as $i => $g) {
            $icms = round($g['icms'], 2);
            $this->apurar($g['cfop'], $icms);

            $linhas[] = ['C190', $g['cst'], $g['cfop'], $this->numero($g['aliq']), $this->numero($operacoes[$i]),
                $this->numero($g['bc']), $this->numero($icms), $this->numero(0), $this->numero(0), $this->numero(0),
                $this->numero($g['ipi']), ''];
        }

        return $linhas;
    }

    /** Soma o ICMS do C190 nos débitos ou créditos do E110, pelo CFOP. */
    private function apurar(string $cfop, float $icms): void
    {
        if ($icms == 0.0 || $cfop === '') {
            return;
        }

        $primeiro = $cfop[0];

        // 1605/5605: transferência de saldo; 5929/6929: NF-e de operação já registrada em cupom/NFC-e
        if ($cfop === '1605') { $this->debitos += $icms; return; }
        if ($cfop === '5605') { $this->creditos += $icms; return; }
        if (in_array($cfop, ['5929', '6929'], true)) { return; }

        if (in_array($primeiro, ['5', '6', '7'], true)) {
            $this->debitos += $icms;
        } elseif (in_array($primeiro, ['1', '2', '3'], true)) {
            $this->creditos += $icms;
        }
    }

    /** ICMS do item respeitando a opção "destacar ICMS" (desligada = tudo zerado). */
    private function icmsDestacado($bc, $aliq, $icms): array
    {
        if (!$this->opcoes['destacar_icms']) {
            return [0.0, 0.0, 0.0];
        }

        return [round((float) $bc, 2), (float) $aliq, round((float) $icms, 2)];
    }

    /** CST_ICMS de 3 dígitos: origem + CST no regime normal; o próprio CSOSN no Simples. */
    private function cstIcms(?string $codigo, $origem): string
    {
        $codigo = $this->digitos($codigo);
        $origem = (string) (int) ($origem ?? 0);

        if ($this->usaCsosn && in_array($codigo, self::CSOSN, true)) {
            return $codigo;
        }

        if (strlen($codigo) === 2) {
            return $origem . $codigo;
        }

        return strlen($codigo) === 3 ? $codigo : $origem . self::CST_PADRAO;
    }

    /** Divide $valor proporcionalmente às bases; o arredondamento vai para a maior base. */
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
    // BLOCO E — apuração do ICMS próprio
    // ==================================================================

    private function montarBlocoE(): array
    {
        $debitos   = round($this->debitos, 2);
        $creditos  = round($this->creditos, 2);
        $saldoAnt  = round((float) ($this->opcoes['saldo_credor_anterior'] ?? 0), 2);

        $apurado   = round($debitos - $creditos - $saldoAnt, 2);
        $recolher  = max($apurado, 0);
        $transportar = $apurado < 0 ? abs($apurado) : 0;

        $linhas = [
            ['E001', '0'],
            ['E100', $this->data($this->inicio), $this->data($this->fim)],
            ['E110', $this->numero($debitos), $this->numero(0), $this->numero(0), $this->numero(0),
                $this->numero($creditos), $this->numero(0), $this->numero(0), $this->numero(0),
                $this->numero($saldoAnt), $this->numero($recolher), $this->numero(0), $this->numero($recolher),
                $this->numero($transportar), $this->numero(0)],
        ];

        if ($recolher > 0) {
            $codReceita = trim((string) $this->config->cod_receita_icms);
            $vencimento = $this->opcoes['dt_vencimento'] ?? null;

            if ($codReceita === '' || !$vencimento) {
                throw ValidationException::withMessages([
                    'dt_vencimento' => 'Há ICMS a recolher (R$ ' . number_format($recolher, 2, ',', '.') . '): informe a data de vencimento '
                        . 'e cadastre o código de receita em Fiscal > SPED > Configuração do SPED.',
                ]);
            }

            $linhas[] = ['E116', '000', $this->numero($recolher), $this->data(Carbon::parse($vencimento)), $this->texto($codReceita),
                '', '', '', '', $this->inicio->format('mY')];
        }

        $this->fecharBloco('E', $linhas);

        return ['debitos' => $debitos, 'creditos' => $creditos, 'saldo_anterior' => $saldoAnt,
            'recolher' => $recolher, 'transportar' => $transportar];
    }

    // ==================================================================
    // BLOCO H — inventário
    // ==================================================================

    private function montarBlocoH(): void
    {
        $id = $this->opcoes['inventario_id'] ?? null;

        if (!$id) {
            $this->fecharBloco('H', []);

            return;
        }

        $inventario = Inventario::with(['itens' => fn ($q) => $q->where('quantidade', '>', 0)->orderBy('produto_id'), 'itens.produto.ncm', 'itens.produto.cest'])
            ->fechados()
            ->findOrFail($id);

        $h010 = [];
        $total = 0.0;

        foreach ($inventario->itens as $item) {
            $codItem = $this->item($item->produto);
            $unidade = strtoupper(trim((string) $item->unidade));
            $this->unidades[$unidade] = true;

            $valor = round((float) $item->valor_total, 2);
            $total += $valor;

            // IND_PROP 0 = item de propriedade do informante e em seu poder
            $h010[] = ['H010', $codItem, $unidade, $this->numero((float) $item->quantidade, 3),
                $this->numero((float) $item->custo_unitario, 6), $this->numero($valor), '0', '', '', '', $this->numero($valor)];
        }

        // VL_INV precisa ser a soma exata dos VL_ITEM
        $linhas = array_merge(
            [['H005', $this->data($inventario->data), $this->numero($total), $inventario->motivo]],
            $h010
        );

        $this->fecharBloco('H', $linhas);
    }

    // ==================================================================
    // Blocos sem movimento e montagem final
    // ==================================================================

    private function montarBlocosSemMovimento(): void
    {
        foreach (['B', 'D', 'G', 'K'] as $bloco) {
            $this->fecharBloco($bloco, []);
        }
    }

    // ==================================================================
    // BLOCO 1 — 1010 (indicadores) e 1601 (pagamentos eletrônicos)
    // ==================================================================

    private function montarBloco1(): void
    {
        $pagamentos = (new SpedPagamentosEletronicos())->calcular($this->inicio, $this->fim);

        $semCredenciadora = $pagamentos['semCredenciadora'];
        if ($semCredenciadora) {
            $lista = collect($semCredenciadora)->map(fn ($v, $meio) => "{$meio}: R$ " . number_format($v, 2, ',', '.'))->implode('; ');

            throw ValidationException::withMessages([
                'sped' => "Há recebimentos eletrônicos sem credenciadora cadastrada ({$lista}). Cadastre a instituição que recebe esses pagamentos.",
            ]);
        }

        $linhas1601 = [];
        foreach ($pagamentos['porCredenciadora'] as ['credenciadora' => $credenciadora, 'total' => $total]) {
            // REG, COD_PART_IP, COD_PART_IT (intermediador/marketplace: não usado), TOT_VS, TOT_ISS, TOT_OUTROS
            $linhas1601[] = ['1601', $this->participanteCredenciadora($credenciadora), '', $this->numero($total), $this->numero(0), $this->numero(0)];
        }

        $this->resumo['credenciadoras_1601'] = count($linhas1601);

        // 1010: só o indicador de operações com cartão/meios eletrônicos (IND_CART, 7º indicador) muda
        $indicadores = array_fill(0, 13, 'N');
        $indicadores[6] = $linhas1601 ? 'S' : 'N';

        $this->fecharBloco('1', array_merge([array_merge(['1010'], $indicadores)], $linhas1601));
    }

    /**
     * Fecha o bloco: acrescenta o X001 (0 = com movimento, 1 = sem) e o X990 (linhas do bloco).
     * Os Blocos 0 e E já trazem o próprio registro de abertura na lista.
     */
    private function fecharBloco(string $bloco, array $linhas): void
    {
        if (!in_array($bloco, ['0', 'E'], true)) {
            array_unshift($linhas, [$bloco . '001', count($linhas) > 0 ? '0' : '1']);
        }

        $linhas[] = [$bloco . '990', count($linhas) + 1];
        $this->blocos[$bloco] = $linhas;
    }

    private function montarArquivo(): array
    {
        $ordem = ['0', 'B', 'C', 'D', 'E', 'G', 'H', 'K', '1'];
        $linhas = [];

        foreach ($ordem as $bloco) {
            array_push($linhas, ...($this->blocos[$bloco] ?? []));
        }

        // Bloco 9: quantidade de linhas por registro
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

        $totalLinhas = count($linhas) + count($bloco9) + 2; // + 9990 e 9999
        $bloco9[] = ['9990', count($bloco9) + 2];          // linhas do bloco 9, inclusive 9990 e 9999
        $bloco9[] = ['9999', $totalLinhas];

        array_push($linhas, ...$bloco9);

        $texto = implode("\r\n", array_map(fn ($campos) => '|' . implode('|', $campos) . '|', $linhas)) . "\r\n";

        return ['texto' => $texto, 'total_linhas' => $totalLinhas];
    }

    // ==================================================================
    // Formatação dos campos
    // ==================================================================

    private function data($data): string
    {
        return $data ? Carbon::parse($data)->format('dmY') : '';
    }

    private function numero(float|int $valor, int $casas = 2): string
    {
        return number_format(round((float) $valor, $casas), $casas, ',', '');
    }

    /** Texto sem o separador "|", sem quebras de linha e com limite de tamanho. */
    private function texto(?string $valor, ?int $maximo = null): string
    {
        $limpo = trim(preg_replace('/[|\r\n\t]+/', ' ', (string) $valor));

        return $maximo ? mb_substr($limpo, 0, $maximo) : $limpo;
    }

    private function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    /** IE só com dígitos; "ISENTO" não é aceito no arquivo (fica vazio). */
    private function ie(?string $valor): string
    {
        return $this->digitos($valor);
    }
}