<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EntradaNota;
use App\Models\Inventario;
use App\Models\Fornecedor;
use App\Models\Mdfe;
use App\Models\Municipio;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\SpedConfiguracao;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Confere, antes de gerar o SPED, se os dados do período têm o que o arquivo vai exigir.
 * Não gera nada: só lista o que precisa ser corrigido (erro) ou observado (aviso).
 */
class SpedVerificacaoService
{
    /** CST de ICMS 2 dígitos que normalmente carregam base de cálculo */
    private const CST_COM_BASE = ['00', '10', '20', '70', '90'];

    private bool $industrial = false;

    /** icms_ipi (SPED Fiscal) ou contribuicoes (EFD-Contribuições) */
    private string $tipo = 'icms_ipi';
    private ?string $regimePis = null;
    private array $problemas = [];
    private array $resumo = [];

    private array $produtoIds = [];
    private array $clienteIds = [];
    private array $fornecedorIds = [];

    public function verificar(Carbon $inicio, string $tipo = 'icms_ipi'): array
    {
        $inicio = $inicio->copy()->startOfMonth();
        $fim    = $inicio->copy()->endOfMonth();
        $this->tipo = $tipo;

        $this->verificarEmpresa();
        $this->verificarConfiguracao();

        if ($this->tipo === 'contribuicoes') {
            $this->verificarContribuicoes($inicio, $fim);
        } else {
            $this->verificarInventario($inicio);
            $this->verificarPagamentosEletronicos($inicio, $fim);
        }

        $this->verificarSaidasNfe($inicio, $fim);
        $this->verificarNfce($inicio, $fim);
        $this->verificarEntradas($inicio, $fim);
        $this->verificarProdutos();
        $this->verificarParticipantes();

        // Erros primeiro, depois avisos
        usort($this->problemas, fn ($a, $b) => [$a['nivel'] === 'erro' ? 0 : 1, $a['grupo']] <=> [$b['nivel'] === 'erro' ? 0 : 1, $b['grupo']]);

        return [
            'inicio'    => $inicio,
            'fim'       => $fim,
            'resumo'    => $this->resumo,
            'problemas' => $this->problemas,
        ];
    }

    // ------------------------------------------------------------------
    // Empresa
    // ------------------------------------------------------------------

    private function verificarEmpresa(): void
    {
        $e = Empresa::first();

        if (!$e) {
            $this->registrar('erro', 'Empresa', 'Empresa não cadastrada', [['texto' => 'Cadastre os dados da empresa', 'url' => route('empresa.editar')]]);

            return;
        }

        $faltas = [];

        if (strlen($this->digitos($e->cnpj)) !== 14)  $faltas[] = 'CNPJ';
        if (!$e->razao_social)                         $faltas[] = 'razão social';
        if (!$e->ie)                                   $faltas[] = 'inscrição estadual';
        if (!in_array((string) $e->crt, ['1', '2', '3'], true)) $faltas[] = 'regime tributário (CRT)';
        if (strlen($this->digitos($e->cep)) !== 8)     $faltas[] = 'CEP';
        if (!$e->logradouro || !$e->numero || !$e->bairro) $faltas[] = 'endereço completo';

        $cod = (string) $e->cod_municipio;
        if (!preg_match('/^\d{7}$/', $cod) || Mdfe::ufDoMunicipio($cod) !== strtoupper((string) $e->uf)) {
            $faltas[] = 'município/UF (código IBGE inválido ou de outra UF)';
        }

        $this->registrar('erro', 'Empresa', 'Dados da empresa incompletos: ' . implode(', ', $faltas), $faltas ? [['texto' => 'Abrir cadastro da empresa', 'url' => route('empresa.editar')]] : []);
    }

    private function verificarConfiguracao(): void
    {
        $config = SpedConfiguracao::first();

        if (!$config || !$config->contadorCompleto()) {
            $this->registrar('erro', 'Configuração do SPED', 'Dados do contador (registro 0100) não cadastrados ou incompletos', [
                ['texto' => 'Abrir Configuração do SPED', 'url' => route('sped-config.editar')],
            ]);
        }

        $this->industrial = (bool) $config?->isIndustrial();
    }

    /** Registro 1601: todo recebimento eletrônico (cartão, PIX...) precisa de uma credenciadora. */
    private function verificarPagamentosEletronicos(Carbon $inicio, Carbon $fim): void
    {
        $pagamentos = (new SpedPagamentosEletronicos())->calcular($inicio, $fim->copy()->endOfDay());

        $this->resumo['Pagamentos eletrônicos'] = collect($pagamentos['porCredenciadora'])
            ->mapWithKeys(fn ($p) => [$p['credenciadora']->nome => 'R$ ' . number_format($p['total'], 2, ',', '.')])
            ->all();

        $exemplos = collect($pagamentos['semCredenciadora'])
            ->map(fn ($valor, $meio) => ['texto' => "{$meio}: R$ " . number_format($valor, 2, ',', '.'), 'url' => route('credenciadoras.index')])
            ->values()->all();

        $this->registrar('erro', 'Pagamentos eletrônicos', 'Recebimentos por cartão/PIX sem credenciadora cadastrada (registro 1601)', $exemplos);
    }

    /** No arquivo de fevereiro o PVA cobra o inventário de 31/12 do ano anterior. */
    private function verificarInventario(Carbon $inicio): void
    {
        if ($inicio->month !== 2) {
            return;
        }

        $data = $inicio->copy()->subYear()->endOfYear();

        if (!Inventario::fechados()->whereDate('data', $data->toDateString())->exists()) {
            $this->registrar('aviso', 'Inventário', "Arquivo de fevereiro sem inventário fechado de {$data->format('d/m/Y')} (Bloco H)", [
                ['texto' => 'Calcular e fechar o inventário', 'url' => route('inventarios.index')],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Saídas — NF-e (modelo 55)
    // ------------------------------------------------------------------

    private function verificarSaidasNfe(Carbon $inicio, Carbon $fim): void
    {
        $notas = NotaFiscal::with(['cliente', 'itens.produto'])
            ->whereBetween('emitida_em', [$inicio, $fim])
            ->whereIn('status', ['emitida', 'cancelada'])
            ->orderBy('numero')
            ->get();

        $this->resumo['NF-e de saída'] = $notas->groupBy('status')->map->count()->all();

        $semChave = $semDestinatario = [];
        $achados = ['cst' => [], 'cfop' => [], 'ncm' => [], 'base' => []];

        foreach ($notas as $nota) {
            $ref = ['url' => route('notasfiscais.show', $nota)];
            $rotulo = "NF-e {$nota->numero}/{$nota->serie}";

            if ($nota->status !== 'emitida') {
                continue; // cancelada só entra no arquivo com os dados básicos
            }

            if (strlen((string) $nota->chave_acesso) !== 44) {
                $semChave[] = ['texto' => $rotulo] + $ref;
            }

            if (!$nota->cliente) {
                $semDestinatario[] = ['texto' => $rotulo] + $ref;
            } else {
                $this->clienteIds[$nota->cliente->id] = true;
            }

            $problemasDaNota = ['cst' => [], 'cfop' => [], 'ncm' => [], 'base' => []];

            foreach ($nota->itens->values() as $i => $item) {
                $n = $i + 1;
                $this->produtoIds[$item->produto_id] = true;

                if (!$item->cst_csosn)  $problemasDaNota['cst'][] = $n;
                if (!$item->cfop)       $problemasDaNota['cfop'][] = $n;
                if (!($item->ncm_id ?? $item->produto?->ncm_id)) $problemasDaNota['ncm'][] = $n;

                if (in_array((string) $item->cst_csosn, self::CST_COM_BASE, true) && (float) $item->bc_icms <= 0 && (float) $item->valor_icms <= 0) {
                    $problemasDaNota['base'][] = $n;
                }
            }

            foreach ($problemasDaNota as $chave => $numeros) {
                if ($numeros) {
                    $achados[$chave][] = ['texto' => "{$rotulo} (itens " . implode(', ', array_slice($numeros, 0, 8)) . ')'] + $ref;
                }
            }
        }

        $g = 'NF-e de saída';
        $this->registrar('erro', $g, 'NF-e emitidas sem chave de acesso de 44 dígitos', $semChave);
        $this->registrar('erro', $g, 'NF-e sem destinatário (cliente)', $semDestinatario);
        $this->registrar('erro', $g, 'Itens sem CST/CSOSN', $achados['cst']);
        $this->registrar('erro', $g, 'Itens sem CFOP', $achados['cfop']);
        $this->registrar('erro', $g, 'Itens sem NCM', $achados['ncm']);
        if ($this->tipo === 'icms_ipi') {
            $this->registrar('aviso', $g, 'Itens com CST tributado e base de ICMS zerada', $achados['base']);
        }
    }

    // ------------------------------------------------------------------
    // Saídas — NFC-e (PDV)
    // ------------------------------------------------------------------

    private function verificarNfce(Carbon $inicio, Carbon $fim): void
    {
        $vendas = fn () => Venda::whereNotNull('chave_nfe')->whereBetween('emitida_em', [$inicio, $fim]);

        // Mostra os status que existem de fato: o SPED só deve levar os autorizados e os cancelados
        $this->resumo['NFC-e'] = $vendas()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $itens = fn () => VendaItem::whereHas('venda', fn ($q) => $q->whereNotNull('chave_nfe')->whereBetween('emitida_em', [$inicio, $fim]));

        foreach ($itens()->whereNotNull('produto_id')->distinct()->pluck('produto_id') as $id) {
            $this->produtoIds[$id] = true;
        }

        $semNcm = $itens()->where(fn ($q) => $q->whereNull('ncm')->orWhere('ncm', ''))->count();
        $semCfop = $itens()->where(fn ($q) => $q->whereNull('cfop')->orWhere('cfop', ''))->count();
        $semCst = $itens()->where(fn ($q) => $q->whereNull('cst_csosn')->orWhere('cst_csosn', ''))->count();

        $g = 'NFC-e';
        $this->registrar('erro', $g, "Itens de NFC-e sem CFOP ({$semCfop})", $semCfop ? [['texto' => 'Itens de vendas do período']] : []);

        if ($this->tipo === 'icms_ipi') {
            $this->registrar('erro', $g, "Itens de NFC-e sem NCM ({$semNcm})", $semNcm ? [['texto' => 'Itens de vendas do período; corrija o cadastro dos produtos e confira o histórico']] : []);
            $this->registrar('erro', $g, "Itens de NFC-e sem CST/CSOSN ({$semCst})", $semCst ? [['texto' => 'Itens de vendas do período']] : []);
        }
    }

    // ------------------------------------------------------------------
    // Entradas
    // ------------------------------------------------------------------

    private function verificarEntradas(Carbon $inicio, Carbon $fim): void
    {
        // Contribuições no cumulativo: as entradas não são escrituradas (não há crédito)
        if ($this->tipo === 'contribuicoes' && $this->regimePis !== 'nao_cumulativo') {
            return;
        }

        $entradas = EntradaNota::with(['fornecedor', 'itens'])
            ->where('status', 'finalizada')
            ->whereBetween('data_entrada', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data_entrada')
            ->get();

        $this->resumo['Entradas'] = ['finalizadas' => $entradas->count()];

        $semChave = [];
        $achados = ['cfop' => [], 'cst' => [], 'credito' => [], 'base' => []];

        foreach ($entradas as $entrada) {
            $ref = ['url' => route('entradas-nota.edit', $entrada)];
            $rotulo = "Entrada {$entrada->numero}/{$entrada->serie} — " . ($entrada->fornecedor?->nome_exibicao ?? 'sem fornecedor');

            $this->fornecedorIds[$entrada->fornecedor_id] = true;

            if ($entrada->modelo === '55' && strlen((string) $entrada->chave_acesso) !== 44) {
                $semChave[] = ['texto' => $rotulo] + $ref;
            }

            $p = ['cfop' => [], 'cst' => [], 'credito' => [], 'base' => []];

            foreach ($entrada->itens->values() as $i => $item) {
                $n = $i + 1;
                $this->produtoIds[$item->produto_id] = true;

                if (!$item->cfop_entrada_id)     $p['cfop'][] = $n;
                if (!$item->cst_csosn_entrada)   $p['cst'][] = $n;
                if ($item->gera_credito === null) $p['credito'][] = $n;
                if ((float) $item->valor_icms > 0 && (float) $item->bc_icms <= 0) $p['base'][] = $n;
            }

            foreach ($p as $chave => $numeros) {
                if ($numeros) {
                    $achados[$chave][] = ['texto' => "{$rotulo} (itens " . implode(', ', array_slice($numeros, 0, 8)) . ')'] + $ref;
                }
            }
        }

        $g = 'Entradas';
        $this->registrar('erro', $g, 'Entradas modelo 55 sem chave de acesso de 44 dígitos', $semChave);
        $this->registrar('erro', $g, 'Itens de entrada sem CFOP de entrada', $achados['cfop']);
        if ($this->tipo === 'icms_ipi') {
            $this->registrar('erro', $g, 'Itens de entrada sem CST/CSOSN de entrada', $achados['cst']);
            $this->registrar('aviso', $g, 'Itens sem definição se geram crédito de ICMS', $achados['credito']);
            $this->registrar('aviso', $g, 'Itens com ICMS destacado e base zerada', $achados['base']);
        }
    }

    // ------------------------------------------------------------------
    // EFD-Contribuições (PIS/COFINS)
    // ------------------------------------------------------------------

    private function verificarContribuicoes(Carbon $inicio, Carbon $fim): void
    {
        $g = 'PIS/COFINS';
        $config = SpedConfiguracao::first();
        $empresa = Empresa::first();
        $linkConfig = [['texto' => 'Abrir Configuração do SPED', 'url' => route('sped-config.editar')]];

        if ((int) $empresa?->crt !== 3) {
            $this->registrar('erro', $g, 'A EFD-Contribuições é só para empresas do regime normal (CRT 3). No Simples Nacional o PIS/COFINS é apurado no PGDAS-D.', [['texto' => 'Abrir cadastro da empresa', 'url' => route('empresa.editar')]]);

            return;
        }

        $this->regimePis = $config?->regime_pis_cofins;

        if (!$this->regimePis) {
            $this->registrar('erro', $g, 'Regime do PIS/COFINS (cumulativo ou não cumulativo) não definido', $linkConfig);

            return;
        }

        $this->resumo['PIS/COFINS'] = ['regime' => $this->regimePis === 'nao_cumulativo' ? 'não cumulativo' : 'cumulativo'];

        // COD_CTA: obrigatório desde 11/2017, exceto para quem está dispensado da ECD (Presumido com livro caixa)
        $faltaConta = !$config->cod_cta_receita || ($this->regimePis === 'nao_cumulativo' && !$config->cod_cta_compras);
        if ($faltaConta) {
            $this->registrar($this->regimePis === 'nao_cumulativo' ? 'erro' : 'aviso', $g,
                $this->regimePis === 'nao_cumulativo'
                    ? 'Contas contábeis de receita e de compras (COD_CTA/0500) não cadastradas: obrigatórias no Lucro Real'
                    : 'Conta contábil de receita (COD_CTA/0500) não cadastrada: só é dispensada se a empresa não for obrigada à ECD',
                $linkConfig);
        }

        // Alíquota básica do CST 01 conforme o regime
        [$pPis, $pCofins] = $this->regimePis === 'nao_cumulativo' ? [1.65, 7.60] : [0.65, 3.00];
        $rotuloAliq = number_format($pPis, 2, ',', '') . '% / ' . number_format($pCofins, 2, ',', '') . '%';

        // Itens de saída do período: CST gravado (vem da emissão ou do fiscal:completar-itens)
        $fimDia = $fim->copy()->endOfDay();
        $saidas = [
            'NF-e'  => NotaFiscal::with('itens.produto')->where('status', 'emitida')
                ->whereBetween('emitida_em', [$inicio, $fimDia])->get()->flatMap->itens,
            'NFC-e' => Venda::with('itens.produto')->whereNotNull('chave_nfe')->whereNotNull('protocolo_nfe')
                ->whereBetween('emitida_em', [$inicio, $fimDia])->get()
                ->reject(fn ($v) => str_contains(strtolower((string) $v->status), 'cancel'))
                ->flatMap->itens->filter(fn ($i) => $i->produto_id),
        ];

        foreach ($saidas as $rotulo => $itens) {
            $semCst = $itens->filter(fn ($i) => blank($i->cst_pis_cofins))->count();
            $this->registrar('erro', $g, "Itens de {$rotulo} sem CST de PIS/COFINS gravado ({$semCst})", $semCst ? [
                ['texto' => 'Rode php artisan fiscal:completar-itens --de=' . $inicio->toDateString() . ' --ate=' . $fim->toDateString() . ' (preenche a partir do XML autorizado)'],
            ] : []);

            $cst03 = $itens->where('cst_pis_cofins', '03')->count();
            $this->registrar('erro', $g, "Itens de {$rotulo} com CST 03 (alíquota por unidade de medida): ainda não suportado ({$cst03})", $cst03 ? [['texto' => 'Revise a classificação de PIS/COFINS dos produtos']] : []);

            $divergentes = $itens->filter(fn ($i) => (string) $i->cst_pis_cofins === '01'
                && (round((float) $i->aliquota_pis, 2) != $pPis || round((float) $i->aliquota_cofins, 2) != $pCofins))->count();
            $this->registrar('aviso', $g, "Itens de {$rotulo} com CST 01 e alíquota diferente de {$rotuloAliq} do regime ({$divergentes})", $divergentes ? [
                ['texto' => 'Documento já emitido não muda; corrija a classificação de PIS/COFINS (CST 01) para as próximas emissões', 'url' => route('produtos.index')],
            ] : []);

            // CST 04 a 09 vão para o M400/M410 (M800/M810), que exige a natureza da receita (NAT_REC) do produto
            $semNatureza = $itens->filter(fn ($i) => in_array((string) $i->cst_pis_cofins, SpedContribuicoesGerador::CST_SEM_INCIDENCIA, true)
                && $i->produto && blank($i->produto->nat_rec_pis_cofins))
                ->map->produto->unique('id')
                ->map(fn ($p) => ['texto' => "{$p->codigo_interno} — {$p->nome}", 'url' => route('produtos.edit', $p)])
                ->values()->all();
            $this->registrar('erro', $g, "Produtos vendidos na {$rotulo} com CST 04 a 09 sem a natureza da receita (NAT_REC, registro M410/M810)", $semNatureza);
        }

        // Entradas com crédito (só no não cumulativo)
        if ($this->regimePis === 'nao_cumulativo') {
            $entradas = DB::table('entrada_nota_itens as i')->join('entradas_nota as e', 'e.id', '=', 'i.entrada_nota_id')
                ->where('e.status', 'finalizada')->where('e.modelo', '55')
                ->whereBetween('e.data_entrada', [$inicio->toDateString(), $fim->toDateString()]);

            $semCst = (clone $entradas)->whereNull('i.cst_pis_cofins')->distinct()->pluck('e.id');
            $this->registrar('erro', $g, 'Entradas com itens sem CST de PIS/COFINS de entrada', $semCst->map(fn ($id) => [
                'texto' => "Entrada #{$id} (produto sem classificação ou CFOP pendente; depois rode php artisan fiscal:pis-cofins-entradas)",
                'url'   => route('entradas-nota.edit', $id),
            ])->all());

            $semNatureza = (clone $entradas)->whereIn('i.cst_pis_cofins', CreditoPisCofinsEntrada::CSTS_COM_CREDITO)->whereNull('i.nat_bc_cred')->distinct()->pluck('e.id');
            $this->registrar('erro', $g, 'Entradas com crédito sem natureza da base de crédito', $semNatureza->map(fn ($id) => [
                'texto' => "Entrada #{$id}", 'url' => route('entradas-nota.edit', $id),
            ])->all());

            // CST 53 a 56 (crédito comum a receitas tributadas e não tributadas) exigem rateio (0111): não suportado
            $rateio = (clone $entradas)->whereIn('i.cst_pis_cofins', ['53', '54', '55', '56'])->distinct()->pluck('e.id');
            $this->registrar('erro', $g, 'Entradas com CST 53 a 56 (crédito com rateio proporcional): ainda não suportado', $rateio->map(fn ($id) => [
                'texto' => "Entrada #{$id}: use CST 50 (crédito vinculado à receita tributada)", 'url' => route('entradas-nota.edit', $id),
            ])->all());

            // Saldo de crédito de meses anteriores vem do arquivo do mês passado gerado por aqui
            $mesAnterior = $inicio->copy()->subMonthNoOverflow();
            $temAnterior = \App\Models\SpedGeracao::where('tipo', 'contribuicoes')->whereDate('competencia', $mesAnterior->toDateString())->exists();
            $this->registrar('aviso', $g, "Não há EFD-Contribuições de {$mesAnterior->format('m/Y')} gerada pelo sistema: saldos de crédito de meses anteriores (1100/1500) não serão aproveitados", $temAnterior ? [] : [
                ['texto' => 'Se houver saldo de crédito de meses anteriores, gere primeiro os arquivos dos meses anteriores, em ordem'],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Produtos e participantes movimentados no período
    // ------------------------------------------------------------------

    private function verificarProdutos(): void
    {
        $ids = array_keys($this->produtoIds);
        $this->resumo['Produtos movimentados'] = ['total' => count($ids)];

        if (!$ids) {
            return;
        }

        $unidades = Unidade::pluck('sigla')->map(fn ($s) => strtoupper(trim($s)))->all();
        $semNcm = $semUnidade = [];

        foreach (Produto::with('ncm')->whereIn('id', $ids)->get() as $produto) {
            $ref = ['texto' => "{$produto->codigo_interno} — {$produto->nome}", 'url' => route('produtos.edit', $produto)];

            if (!$produto->ncm_id) {
                $semNcm[] = $ref;
            }

            if (!in_array(strtoupper(trim((string) $produto->unidade_comercial)), $unidades, true)) {
                $semUnidade[] = $ref;
            }
        }

        $this->registrar('erro', 'Produtos', 'Produtos movimentados sem NCM', $semNcm);
        $this->registrar('erro', 'Produtos', 'Produtos cuja unidade não está cadastrada em Unidades', $semUnidade);

        if ($this->industrial) {
            $revenda = [];

            foreach (Produto::whereIn('id', $ids)->where('tipo_item', '00')->get() as $produto) {
                $revenda[] = ['texto' => "{$produto->codigo_interno} — {$produto->nome}", 'url' => route('produtos.edit', $produto)];
            }

            $this->registrar('aviso', 'Produtos', 'Atividade industrial: produtos ainda com tipo de item "00 — mercadoria para revenda" (confira matéria-prima, produto acabado etc.)', $revenda);
        }
    }

    private function verificarParticipantes(): void
    {
        $temTabela = Municipio::query()->exists();

        if (!$temTabela) {
            $this->registrar('aviso', 'Participantes', 'A tabela de municípios está vazia (rode php artisan municipios:importar): o código IBGE dos cadastros não foi conferido', [['texto' => 'Importe os municípios para conferir']]);
        }

        $codigosValidos = $temTabela ? Municipio::pluck('uf', 'codigo_ibge') : collect();

        $conferir = function ($pessoa, string $rotaEditar) use ($temTabela, $codigosValidos): array {
            $ref = ['texto' => (string) $pessoa->nome, 'url' => route($rotaEditar, $pessoa)];
            $achados = ['documento' => null, 'municipio' => null];

            if (!in_array(strlen($this->digitos($pessoa->cpf_cnpj)), [11, 14], true)) {
                $achados['documento'] = $ref;
            }

            $cod = (string) $pessoa->cod_municipio;
            $ok = preg_match('/^\d{7}$/', $cod)
                && strtoupper((string) $pessoa->uf) === Mdfe::ufDoMunicipio($cod)
                && (!$temTabela || $codigosValidos->has($cod));

            if (!$ok) {
                $achados['municipio'] = $ref;
            }

            return $achados;
        };

        $this->resumo['Participantes'] = ['clientes' => count($this->clienteIds), 'fornecedores' => count($this->fornecedorIds)];

        $docCli = $munCli = $docFor = $munFor = [];

        foreach (Cliente::whereIn('id', array_keys($this->clienteIds))->get() as $cliente) {
            $r = $conferir($cliente, 'clientes.edit');
            $r['documento'] && $docCli[] = $r['documento'];
            $r['municipio'] && $munCli[] = $r['municipio'];
        }
        foreach (Fornecedor::whereIn('id', array_keys($this->fornecedorIds))->get() as $fornecedor) {
            $r = $conferir($fornecedor, 'fornecedores.edit');
            $r['documento'] && $docFor[] = $r['documento'];
            $r['municipio'] && $munFor[] = $r['municipio'];
        }

        $this->registrar('erro', 'Participantes', 'Clientes sem CPF/CNPJ válido (11 ou 14 dígitos)', $docCli);
        $this->registrar('erro', 'Participantes', 'Clientes com município/código IBGE inválido', $munCli);
        $this->registrar('erro', 'Participantes', 'Fornecedores sem CPF/CNPJ válido (11 ou 14 dígitos)', $docFor);
        $this->registrar('erro', 'Participantes', 'Fornecedores com município/código IBGE inválido', $munFor);
    }

    // ------------------------------------------------------------------

    private function registrar(string $nivel, string $grupo, string $titulo, array $exemplos): void
    {
        if (!$exemplos) {
            return;
        }

        $this->problemas[] = [
            'nivel'      => $nivel,
            'grupo'      => $grupo,
            'titulo'     => $titulo,
            'quantidade' => count($exemplos),
            'exemplos'   => array_slice($exemplos, 0, 5),
        ];
    }

    private function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }
}