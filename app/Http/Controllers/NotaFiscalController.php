<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Produto;
use App\Models\SerieNfe;
use App\Models\InutilizacaoNfe;
use App\Services\NotaFiscalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Models\CfopSaida;
use App\Models\ProdutoVariante;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;



class NotaFiscalController extends Controller
{
    public function index(Request $request)
    {
        $statusFiltro = $request->get('status');
        $tipoFiltro = $request->get('tipo_filtro', 'data');

        $notas = null;
        $inutilizacoes = null;

        if ($statusFiltro === 'inutilizada') {
            $inutilizacoes = InutilizacaoNfe::where('status', 'sucesso')
                ->orderByDesc('id')
                ->paginate(20);
        } else {
            $query = NotaFiscal::with('cliente', 'cfopSaida') // ← troca aqui
                ->when($statusFiltro, fn ($q) => $q->where('status', $statusFiltro));

            switch ($tipoFiltro) {
                case 'numero':
                    if ($request->filled('numero')) {
                        $query->where('numero', $request->get('numero'));
                    }
                    break;

                case 'cliente':
                    if ($request->filled('cliente')) {
                        $termo = $request->get('cliente');
                        $query->whereHas('cliente', fn ($q) => $q->where('nome', 'like', "%{$termo}%"));
                    }
                    break;

                case 'documento':
                    if ($request->filled('documento')) {
                        $documento = preg_replace('/\D/', '', $request->get('documento'));
                        $query->whereHas('cliente', fn ($q) => $q->where('cpf_cnpj', 'like', "%{$documento}%"));
                    }
                    break;

                case 'data':
                default:
                    $dataInicio = $request->filled('data_inicio') ? $request->get('data_inicio') : now()->toDateString();
                    $dataFim = $request->filled('data_fim') ? $request->get('data_fim') : now()->toDateString();

                    $query->whereDate('created_at', '>=', $dataInicio)
                        ->whereDate('created_at', '<=', $dataFim);
                    break;
            }

            $notas = $query->orderByDesc('id')->paginate(20)->withQueryString();
        }

        $series = SerieNfe::ativas()->orderBy('serie')->get();

        return view('notasfiscais.index', compact('notas', 'inutilizacoes', 'series', 'statusFiltro', 'tipoFiltro'));
    }

    public function create()
    {
        $clientes = Cliente::ativos()->orderBy('nome')->get();
        $crtEmpresa = Empresa::first()->crt;

        return view('notasfiscais.create', [
            'clientes'    => $clientes,
            'crtEmpresa'  => $crtEmpresa,
            'notaFiscal'  => null,
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validarCabecalhoEItens($request);

        $notaFiscal = DB::transaction(function () use ($dados) {
            $notaFiscal = NotaFiscal::create([
                'cliente_id'                 => $dados['cliente_id'],
                'natureza_operacao'          => $dados['natureza_operacao'],
                'finalidade'                 => $dados['finalidade'],
                'cfop_saida_id'              => $dados['cfop_saida_id'],
                'forma_pagamento_id'         => $dados['forma_pagamento_id'],
                'operador_id'                => $dados['operador_id'],
                'informacoes_complementares' => $dados['informacoes_complementares'] ?? null,
                'notas_referenciadas'        => $dados['notas_referenciadas'],
                'tipo_operacao'              => CfopSaida::findOrFail($dados['cfop_saida_id'])->tipo_operacao,
                'motivo_ajuste'              => $dados['motivo_ajuste'],
                'frete_por_item'             => $dados['frete_por_item'],
                'mod_frete'                  => (int) $dados['mod_frete'], 
                'origem_tipo'                => 'manual',
                'status'                     => 'rascunho',

            ] + $this->camposTransporte($dados));

            $this->substituirItens($notaFiscal, $dados['itens']);

            return $notaFiscal;
        });

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal criada com sucesso.');
    }

    public function edit(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Só é possível editar notas em rascunho.');

        $notaFiscal->load('itens.produto', 'itens.tributacao', 'itens.ipi');
        $clientes = Cliente::ativos()->orderBy('nome')->get();
        $crtEmpresa = Empresa::first()->crt;

        return view('notasfiscais.edit', compact('notaFiscal', 'clientes', 'crtEmpresa'));
    }

    public function update(Request $request, NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Só é possível editar notas em rascunho.');

        $dados = $this->validarCabecalhoEItens($request);

        DB::transaction(function () use ($notaFiscal, $dados) {
            $notaFiscal->cliente_id = $dados['cliente_id'];
            $notaFiscal->natureza_operacao = $dados['natureza_operacao'];
            $notaFiscal->finalidade = $dados['finalidade'];
            $notaFiscal->cfop_saida_id = $dados['cfop_saida_id'];
            $notaFiscal->forma_pagamento_id = $dados['forma_pagamento_id'];
            $notaFiscal->operador_id = $dados['operador_id'];
            $notaFiscal->informacoes_complementares = $dados['informacoes_complementares'] ?? null;
            $notaFiscal->notas_referenciadas = $dados['notas_referenciadas'];
            $notaFiscal->tipo_operacao = CfopSaida::findOrFail($dados['cfop_saida_id'])->tipo_operacao;
            $notaFiscal->motivo_ajuste = $dados['motivo_ajuste'];
            $notaFiscal->frete_por_item = $dados['frete_por_item'];
            $notaFiscal->mod_frete = (int) $dados['mod_frete']; 
            $notaFiscal->fill($this->camposTransporte($dados));
            $notaFiscal->save();

            // Substitui todos os itens — mais simples e seguro que tentar
            // "casar" item por item entre o que veio do formulário e o que já existia.
            $notaFiscal->itens()->delete();
            $this->substituirItens($notaFiscal, $dados['itens']);
        });

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal atualizada com sucesso.');
    }

    /** Confere a senha do operador escolhido e devolve um token que o servidor valida ao salvar a nota. */
    public function autorizarOperador(Request $request)
    {
        $dados = $request->validate([
            'operador_id' => ['required', 'integer'],
            'password'    => ['required', 'string'],
        ]);

        $operador = \App\Models\User::operadoresDaNota()->whereKey($dados['operador_id'])->first();

        if (!$operador || !Hash::check($dados['password'], $operador->password)) {
            return response()->json(['message' => 'Senha incorreta.'], 403);
        }

        Log::info('Operador da NF-e autorizado com a própria senha', [
            'operador_id' => $operador->id,
            'por_usuario' => $request->user()->id,
        ]);

        $token = Crypt::encryptString(json_encode([
            'operador_id' => $operador->id,
            'por'         => $request->user()->id,
            'exp'         => now()->addHours(2)->timestamp,
        ]));

        return response()->json(['token' => $token, 'operador' => $operador->name]);
    }

    private function tokenOperadorValido(string $token, int $operadorId): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($payload)
            && (int) ($payload['operador_id'] ?? 0) === $operadorId
            && (int) ($payload['por'] ?? 0) === (int) auth()->id()
            && (int) ($payload['exp'] ?? 0) >= now()->timestamp;
    }


    private function validarCabecalhoEItens(Request $request): array
    {
        $dados = $request->validate([
            'cliente_id'                 => ['required', 'exists:clientes,id'],
            'natureza_operacao'          => ['required', 'string', 'max:255'],
            'finalidade'                 => ['required', 'in:1,2,3,4,5,6'],
            'motivo_ajuste'              => ['nullable', 'string', 'max:2'],
            'cfop_saida_id'              => ['required', 'exists:cfop_saida,id'],
            'forma_pagamento_id'         => ['required', 'exists:formas_pagamento,id'],
            'operador_token'             => ['nullable', 'string'],
            'operador_id'                => ['required', function ($atributo, $valor, $fail) {
                // Edição de nota antiga: manter o operador que já estava gravado não pede nada
                $atual = request()->route('notaFiscal')?->operador_id;
                if ($atual !== null && (int) $valor === (int) $atual) {
                    return;
                }

                if (!\App\Models\User::operadoresDaNota()->whereKey($valor)->exists()) {
                    $fail('Selecione um operador fiscal ou o administrador (ativos).');
                    return;
                }

                // Quem está logado pode se escolher; qualquer outra pessoa precisa ter autorizado com a própria senha
                if ((int) $valor === (int) auth()->id()) {
                    return;
                }

                if (!$this->tokenOperadorValido((string) request('operador_token'), (int) $valor)) {
                    $fail('O operador selecionado precisa autorizar com a própria senha.');
                }
            }],
            'informacoes_complementares' => ['nullable', 'string', 'max:2000'],
            'notas_referenciadas_json'   => ['nullable', 'string'],
            'itens_json'                 => ['required', 'string'],
            'frete_modo'                 => ['required', 'in:item,global'],
            'frete_total'                => ['nullable', 'numeric', 'min:0'],
            'mod_frete'                  => ['required', 'in:' . implode(',', array_keys(NotaFiscal::MODALIDADES_FRETE))],
            'transportador_id'           => ['nullable', 'exists:transportadores,id'],
            'veiculo_id'                 => ['nullable', 'exists:veiculos,id'],
            'vol_quantidade'             => ['nullable', 'integer', 'min:1'],
            'vol_especie'                => ['nullable', 'string', 'max:60'],
            'vol_marca'                  => ['nullable', 'string', 'max:60'],
            'vol_numeracao'              => ['nullable', 'string', 'max:60'],
            'vol_peso_liquido'           => ['nullable', 'numeric', 'min:0'],
            'vol_peso_bruto'             => ['nullable', 'numeric', 'min:0'],
        ]);

        $finalidade = (int) $dados['finalidade'];

        $itens = json_decode($dados['itens_json'], true);

        if (!is_array($itens) || count($itens) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'itens' => 'Adicione ao menos um item à nota.',
            ]);
        }

        $notasReferenciadas = json_decode($dados['notas_referenciadas_json'] ?? '[]', true) ?: [];
        $notasReferenciadas = array_values(array_filter($notasReferenciadas, fn ($c) => preg_match('/^\d{44}$/', $c)));

        if (in_array($finalidade, [2, 5, 6], true) && count($notasReferenciadas) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'notas_referenciadas' => 'Esta finalidade exige ao menos uma nota fiscal referenciada.',
            ]);
        }

        // Finalidades com vínculo por item: todo item precisa da chave da nota de origem
        if (in_array($finalidade, config('fiscal.finalidades_referencia_por_item', []), true)) {
            foreach ($itens as $item) {
                if (!preg_match('/^\d{44}$/', (string) ($item['ref_chave_acesso'] ?? ''))) {
                    $nome = $item['descricao'] ?? 'sem descrição';
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'itens' => "O item \"{$nome}\" precisa da chave de acesso (44 dígitos) da nota de origem.",
                    ]);
                }
            }
        }

        // Crédito/Débito exigem o código do motivo
        $motivo = null;
        if (in_array($finalidade, [5, 6], true)) {
            $motivo = $dados['motivo_ajuste'] ?? null;

            if (!$motivo || !array_key_exists($motivo, config("fiscal.motivos_ajuste.{$finalidade}", []))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'motivo_ajuste' => 'Selecione o motivo do ajuste (obrigatório em nota de crédito/débito).',
                ]);
            }
        }

        $freteTotal = round((float) ($dados['frete_total'] ?? 0), 2);

        if ($dados['frete_modo'] === 'item') {
            foreach ($itens as $k => $item) {
                $itens[$k]['valor_frete'] = round(max(0, (float) ($item['valor_frete'] ?? 0)), 2);
            }

            $somaItens = round(array_sum(array_column($itens, 'valor_frete')), 2);

            // A soma do frete dos itens tem que ser exatamente o frete total da nota
            if (abs($somaItens - $freteTotal) > 0.004) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'frete' => sprintf(
                        'A soma do frete dos itens (R$ %s) precisa ser igual ao frete total (R$ %s).',
                        number_format($somaItens, 2, ',', '.'),
                        number_format($freteTotal, 2, ',', '.')
                    ),
                ]);
            }
        } else {
            $itens = $this->ratearFrete($itens, $freteTotal);
        }

        if ((int) $dados['mod_frete'] === 9 && round(array_sum(array_column($itens, 'valor_frete')), 2) > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'frete' => 'Com a opção "Sem frete" o valor do frete deve ser zero.',
            ]);
        }

        $modFrete = (int) $dados['mod_frete'];

        if ($modFrete === 9) {
            $dados['transportador_id'] = null; // sem transporte não se informa transportador nem veículo
            $dados['veiculo_id'] = null;
        } else {
            if ($modFrete === 2 && empty($dados['transportador_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'transportador_id' => 'Informe o transportador quando o frete é por conta de terceiros.',
                ]);
            }

            // Operação interestadual: mesmo critério do idDest do XML (UF do cliente x UF da empresa)
            $clienteUf = \App\Models\Cliente::find($dados['cliente_id'])?->uf;
            $interestadual = $clienteUf !== Empresa::first()->uf;

            if (empty($dados['veiculo_id']) && ($modFrete === 1 || $interestadual)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'veiculo_id' => $modFrete === 1
                        ? 'Informe o veículo: frete por conta do destinatário (FOB).'
                        : 'Informe o veículo: operação interestadual.',
                ]);
            }

            if (!empty($dados['veiculo_id']) && !empty($dados['transportador_id'])) {
                $donoDoVeiculo = \App\Models\Veiculo::find($dados['veiculo_id'])->transportador_id;

                if ($donoDoVeiculo && (int) $donoDoVeiculo !== (int) $dados['transportador_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'veiculo_id' => 'O veículo selecionado pertence a outra transportadora.',
                    ]);
                }
            }
        }

        if (isset($dados['vol_peso_liquido'], $dados['vol_peso_bruto'])
            && (float) $dados['vol_peso_liquido'] > (float) $dados['vol_peso_bruto']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'vol_peso_liquido' => 'O peso líquido não pode ser maior que o peso bruto.',
            ]);
        }

        $dados['frete_por_item'] = $dados['frete_modo'] === 'item';
        unset($dados['frete_modo'], $dados['frete_total']);

        $dados['itens'] = $itens;
        $dados['notas_referenciadas'] = $notasReferenciadas;
        $dados['motivo_ajuste'] = $motivo;
        unset($dados['itens_json'], $dados['notas_referenciadas_json']);

        return $dados;
    }

    private function camposTransporte(array $dados): array
    {
        return [
            'transportador_id' => $dados['transportador_id'] ?? null,
            'veiculo_id'       => $dados['veiculo_id'] ?? null,
            'vol_quantidade'   => $dados['vol_quantidade'] ?? null,
            'vol_especie'      => $dados['vol_especie'] ?? null,
            'vol_marca'        => $dados['vol_marca'] ?? null,
            'vol_numeracao'    => $dados['vol_numeracao'] ?? null,
            'vol_peso_liquido' => $dados['vol_peso_liquido'] ?? null,
            'vol_peso_bruto'   => $dados['vol_peso_bruto'] ?? null,
        ];
    }

    /**
     * Cria os NotaFiscalItem a partir do array vindo do JS, com snapshot
     * da tributação do produto no momento do cadastro.
     */
    private function substituirItens(NotaFiscal $notaFiscal, array $itens): void
    {
        foreach ($itens as $itemDados) {
            $produto = Produto::findOrFail($itemDados['produto_id']);

            $quantidade    = (float) $itemDados['quantidade'];
            $valorUnitario = (float) $itemDados['valor_unitario'];
            $valorDesconto = (float) ($itemDados['valor_desconto'] ?? 0);
            $valorTotal    = ($quantidade * $valorUnitario) - $valorDesconto;
            $descricao     = trim($itemDados['descricao'] ?? '') ?: null;
            $refChave      = trim($itemDados['ref_chave_acesso'] ?? '') ?: null;
            $refNitem      = $itemDados['ref_nitem'] ?? null;
            $basesManuais  = !empty($itemDados['bases_manuais']);

            $notaFiscal->itens()->create([
                'produto_id'            => $produto->id,
                'produto_variante_id'   => $itemDados['produto_variante_id'] ?? null,
                'descricao'             => $descricao,
                'ref_chave_acesso'      => $refChave,
                'ref_nitem'             => $refNitem ?: null,
                'ncm_id'                => $produto->ncm_id,
                'cest_id'               => $produto->cest_id,
                'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
                'tributacao_id'         => $produto->tributacao_id,
                'pis_cofins_id'         => $produto->pis_cofins_id,
                'ipi_id'                => $produto->ipi_id,
                'quantidade'            => $quantidade,
                'valor_unitario'        => $valorUnitario,
                'valor_desconto'        => $valorDesconto,
                'valor_outras_despesas' => max(0, (float) ($itemDados['valor_outras_despesas'] ?? 0)),
                'valor_frete'           => round(max(0, (float) ($itemDados['valor_frete'] ?? 0)), 2),
                'valor_total'           => $valorTotal,
                'bc_icms_manual'        => $basesManuais ? ($itemDados['bc_icms'] ?? null) : null,
                'valor_icms_manual'     => $basesManuais ? ($itemDados['valor_icms'] ?? null) : null,
                'aliquota_icms_manual'  => $basesManuais ? ($itemDados['aliquota_icms'] ?? null) : null,
                'valor_ipi_manual'      => $basesManuais ? ($itemDados['valor_ipi'] ?? null) : null,
                'aliquota_ipi_manual'   => $basesManuais ? ($itemDados['aliquota_ipi'] ?? null) : null,
            ]);
        }

        $notaFiscal->recalcularTotais();
    }

    public function recalcular(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Só é possível recalcular notas em rascunho.');

        $notaFiscal->load([
            'itens.ncm', 'itens.cest', 'itens.classificacaoTributaria', 'itens.tributacao', 'itens.pisCofins', 'itens.ipi',
            'itens.produto.ncm', 'itens.produto.cest', 'itens.produto.classificacaoTributaria',
            'itens.produto.tributacao', 'itens.produto.pisCofins', 'itens.produto.ipi',
        ]);

        // Mapa: campo no item => [relação no item, relação no produto, campo de exibição, rótulo]
        $camposFiscais = [
            'ncm_id'                => ['itemRel' => 'ncm', 'produtoRel' => 'ncm', 'campo' => 'codigo', 'label' => 'NCM'],
            'cest_id'               => ['itemRel' => 'cest', 'produtoRel' => 'cest', 'campo' => 'codigo', 'label' => 'CEST'],
            'class_trib_ibs_cbs_id' => ['itemRel' => 'classificacaoTributaria', 'produtoRel' => 'classificacaoTributaria', 'campo' => 'codigo', 'label' => 'Classificação IBS/CBS'],
            'tributacao_id'         => ['itemRel' => 'tributacao', 'produtoRel' => 'tributacao', 'campo' => 'descricao', 'label' => 'Tributação'],
            'pis_cofins_id'         => ['itemRel' => 'pisCofins', 'produtoRel' => 'pisCofins', 'campo' => 'codigo', 'label' => 'PIS/COFINS'],
            'ipi_id'                => ['itemRel' => 'ipi', 'produtoRel' => 'ipi', 'campo' => 'codigo', 'label' => 'IPI'],
        ];

        $alteracoes = [];

        foreach ($notaFiscal->itens as $item) {
            $produto = $item->produto;
            $camposParaAtualizar = [];
            $mudancasDoItem = [];

            foreach ($camposFiscais as $campoId => $info) {
                $idAntigo = $item->{$campoId};
                $idNovo = $produto->{$campoId};

                if ($idAntigo == $idNovo) {
                    continue; // nada mudou nesse campo — não entra no relatório nem no update
                }

                $labelAntigo = $item->{$info['itemRel']}?->{$info['campo']} ?? '—';
                $labelNovo = $produto->{$info['produtoRel']}?->{$info['campo']} ?? '—';

                $camposParaAtualizar[$campoId] = $idNovo;
                $mudancasDoItem[] = "{$info['label']}: {$labelAntigo} → {$labelNovo}";
            }

            if (!empty($camposParaAtualizar)) {
                $item->update($camposParaAtualizar);
                $alteracoes[] = [
                    'produto' => $produto->nome,
                    'mudancas' => $mudancasDoItem,
                ];
            }
        }

        $notaFiscal->recalcularTotais();

        if (empty($alteracoes)) {
            return back()->with('sucesso', 'Nenhuma alteração encontrada — os dados fiscais dos itens já estavam atualizados.');
        }

        return back()->with('recalculo_alteracoes', $alteracoes);
    }

    public function show(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load(['itens.produto', 'itens.tributacao', 'itens.ipi', 'cliente']);

        return view('notasfiscais.show', compact('notaFiscal'));
    }

    public function destroy(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Só é possível excluir notas em rascunho.');

        $notaFiscal->delete();

        return redirect()->route('notasfiscais.index')->with('sucesso', 'Nota fiscal excluída.');
    }

    /**
     * Busca de clientes para o modal da nota.
     * Sem termo: os 20 primeiros em ordem alfabética.
     * Com termo: nome (contém) ou CPF/CNPJ (só dígitos).
     */
    public function buscarCliente(Request $request)
    {
        $termo   = trim((string) $request->get('termo', ''));
        $digitos = preg_replace('/\D/', '', $termo);

        $clientes = Cliente::ativos()
            ->when($termo !== '', function ($q) use ($termo, $digitos) {
                $q->where(function ($q) use ($termo, $digitos) {
                    $q->where('nome', 'like', "%{$termo}%");

                    if ($digitos !== '') {
                        $q->orWhere('cpf_cnpj', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'cpf_cnpj', 'uf', 'telefone']);

        return response()->json($clientes->map(fn ($c) => [
            'id'       => $c->id,
            'nome'     => $c->nome,
            'cpf_cnpj' => $c->cpf_cnpj_formatado,
            'uf'       => $c->uf,
            'telefone' => $c->telefone,
        ]));
    }


    /**
     * Busca de operadores para o modal da nota: só quem tem o tipo "fiscal".
     * Sem termo: os 20 primeiros em ordem alfabética. Com termo: nome ou código.
     */
    public function buscarOperador(Request $request)
    {
        $termo = trim((string) $request->get('termo', ''));

        $operadores = \App\Models\User::operadoresDaNota()
            ->when($termo !== '', function ($q) use ($termo) {
                $q->where(function ($q) use ($termo) {
                    $q->where('name', 'like', "%{$termo}%");

                    if (ctype_digit($termo)) {
                        $q->orWhere('codigo', (int) $termo);
                    }
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'codigo']);

        return response()->json($operadores);
    }

    /**
     * Busca produto por código de barras ou nome — mesmo padrão do buscarProduto do PDV.
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo');

        $produtos = Produto::ativos()
            ->with(['tributacao:id,cst_icms,csosn,aliquota_icms', 'ipi:id,codigo,aliquota', 'variantes'])
            ->where(function ($q) use ($termo) {
                $q->where('codigo_barras', $termo)
                    ->orWhere('codigo_interno', $termo)
                    ->orWhere('nome', 'like', "{$termo}%");
            })
            ->limit(10)
            ->get(['id', 'nome', 'codigo_interno', 'codigo_barras', 'preco_venda', 'estoque', 'tem_variacao',
                'ncm_id', 'cest_id', 'class_trib_ibs_cbs_id', 'tributacao_id', 'pis_cofins_id', 'ipi_id']);

        $resultados = [];

        foreach ($produtos as $produto) {
            if ($produto->tem_variacao && $produto->variantes->isNotEmpty()) {
                foreach ($produto->variantes as $variante) {
                    $resultados[] = $this->formatarResultadoBusca($produto, $variante);
                }
            } else {
                $resultados[] = $this->formatarResultadoBusca($produto, null);
            }
        }

        return response()->json($resultados);
    }
    

    /**
     * Uma linha por produto simples, ou uma linha por VARIANTE quando o
     * produto tem variação — mesmo padrão do caixa, pra garantir que o
     * operador escolha a variante certa e o estoque debite na linha certa.
     */
    private function formatarResultadoBusca(Produto $produto, ?ProdutoVariante $variante): array
    {
        return [
            'produto_id'            => $produto->id,
            'produto_variante_id'   => $variante?->id,
            'nome'                  => $variante ? "{$produto->nome} — {$variante->cor} {$variante->tamanho}" : $produto->nome,
            'codigo_interno'        => $produto->codigo_interno,
            'codigo_barras'         => $produto->codigo_barras,
            'preco_venda'           => $produto->preco_venda,
            'estoque'               => $variante ? $variante->estoque : $produto->estoque,
            'ncm_id'                => $produto->ncm_id,
            'cest_id'               => $produto->cest_id,
            'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
            'tributacao_id'         => $produto->tributacao_id,
            'pis_cofins_id'         => $produto->pis_cofins_id,
            'ipi_id'                => $produto->ipi_id,
            'tributacao'            => $produto->tributacao,
            'ipi'                   => $produto->ipi,
        ];
    }

    public function adicionarItem(Request $request, NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já emitida, não pode ser alterada.');

        $dados = $request->validate([
            'produto_id'      => ['required', 'exists:produtos,id'],
            'quantidade'      => ['required', 'numeric', 'min:0.001'],
            'valor_unitario'  => ['required', 'numeric', 'min:0'],
            'valor_desconto'  => ['nullable', 'numeric', 'min:0'],
            'cfop'            => ['required', 'string', 'size:4'],
        ]);

        $produto = Produto::findOrFail($dados['produto_id']);

        $valorTotal = ($dados['quantidade'] * $dados['valor_unitario']) - ($dados['valor_desconto'] ?? 0);

        $notaFiscal->itens()->create([
            'produto_id'             => $produto->id,
            'cfop'                   => $dados['cfop'],
            // snapshot da tributação do produto no momento em que entra na nota
            'ncm_id'                 => $produto->ncm_id,
            'cest_id'                => $produto->cest_id,
            'class_trib_ibs_cbs_id'  => $produto->class_trib_ibs_cbs_id,
            'tributacao_id'          => $produto->tributacao_id,
            'pis_cofins_id'          => $produto->pis_cofins_id,
            'ipi_id'                 => $produto->ipi_id,
            'quantidade'             => $dados['quantidade'],
            'valor_unitario'         => $dados['valor_unitario'],
            'valor_desconto'         => $dados['valor_desconto'] ?? 0,
            'valor_total'            => $valorTotal,
        ]);

        $notaFiscal->recalcularTotais();

        return back()->with('sucesso', 'Item adicionado.');
    }

    public function removerItem(NotaFiscal $notaFiscal, NotaFiscalItem $item)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já emitida, não pode ser alterada.');
        abort_if($item->nota_fiscal_id !== $notaFiscal->id, 404);

        $item->delete();
        $notaFiscal->recalcularTotais();

        return back()->with('sucesso', 'Item removido.');
    }

    public function emitir(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load('itens', 'cfopSaida');

        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já foi emitida ou cancelada.');
        abort_if(!$notaFiscal->cliente_id, 422, 'Selecione o cliente antes de emitir.');
        abort_if($notaFiscal->itens->count() === 0, 422, 'Adicione ao menos um item antes de emitir.');

        try {
            DB::transaction(function () use ($notaFiscal) {
                $serie = SerieNfe::ativas()->lockForUpdate()->firstOrFail();
                $proximoNumero = $serie->numero_atual + 1;

                $notaFiscal->serie_nfe_id = $serie->id;
                $notaFiscal->serie = $serie->serie;
                $notaFiscal->numero = $proximoNumero;
                $notaFiscal->save();

                $serie->update(['numero_atual' => $proximoNumero]);

                $resultado = (new NotaFiscalService())->emitir($notaFiscal);

                $notaFiscal->status = 'emitida';
                $notaFiscal->chave_acesso = $resultado['chave_acesso'];
                $notaFiscal->protocolo = $resultado['protocolo'];
                $notaFiscal->xml = $resultado['xml'];
                $notaFiscal->emitida_em = now();
                $notaFiscal->save();

                // Estoque só é mexido depois da autorização da SEFAZ.
                // Saída debita; entrada credita.
                $cfop = $notaFiscal->cfopSaida;

                if ($cfop->movimenta_estoque) {
                    foreach ($notaFiscal->itens as $item) {
                        $query = $item->produto_variante_id
                            ? \App\Models\ProdutoVariante::where('id', $item->produto_variante_id)->lockForUpdate()
                            : Produto::where('id', $item->produto_id)->lockForUpdate();

                        $cfop->tipo_operacao === 'entrada'
                            ? $query->increment('estoque', $item->quantidade)
                            : $query->decrement('estoque', $item->quantidade);
                    }
                }
            });
        } catch (\Throwable $e) {
            $notaFiscal->refresh();
            $notaFiscal->motivo_rejeicao = $e->getMessage();
            $notaFiscal->save();

            return back()->withErrors(['emissao' => 'Falha ao emitir: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal emitida com sucesso!');
    }

    
    public function formCancelar(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 403, 'Só é possível cancelar notas emitidas.');

        return view('notasfiscais.cancelar', compact('notaFiscal'));
    }

    public function cancelar(Request $request, NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 403, 'Só é possível cancelar notas emitidas.');

        $dados = $request->validate([
            'motivo_cancelamento' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $notaFiscal->load('itens', 'cfopSaida');

        try {
            $resultado = (new NotaFiscalService())->cancelar($notaFiscal, $dados['motivo_cancelamento']);
        } catch (\Throwable $e) {
            return back()->withErrors(['cancelamento' => 'Falha ao cancelar: ' . $e->getMessage()]);
        }

        // Só mexe em estoque e status local DEPOIS de confirmado pela SEFAZ —
        // se a chamada acima lançar exceção, nada abaixo é executado.
        DB::transaction(function () use ($notaFiscal, $dados, $resultado) {
            if ($notaFiscal->cfopSaida->movimenta_estoque) {
                foreach ($notaFiscal->itens as $item) {
                    $query = $item->produto_variante_id
                        ? \App\Models\ProdutoVariante::where('id', $item->produto_variante_id)->lockForUpdate()
                        : Produto::where('id', $item->produto_id)->lockForUpdate();

                    $notaFiscal->cfopSaida->tipo_operacao === 'entrada'
                        ? $query->decrement('estoque', $item->quantidade)
                        : $query->increment('estoque', $item->quantidade);
                }
            }

            $notaFiscal->status = 'cancelada';
            $notaFiscal->motivo_cancelamento = $dados['motivo_cancelamento'];
            $notaFiscal->protocolo_cancelamento = $resultado['protocolo'];
            $notaFiscal->cancelado_em = now();
            $notaFiscal->save();
        });

        return redirect()->route('notasfiscais.index')->with('sucesso', 'Nota fiscal cancelada com sucesso. Protocolo: ' . $resultado['protocolo']);
    }


    /** Rateia o frete global entre os itens, proporcional ao valor de cada um; a soma fecha em centavos. */
    private function ratearFrete(array $itens, float $freteTotal): array
    {
        $totalCentavos = (int) round($freteTotal * 100);

        $pesos = array_map(
            fn ($i) => max(0.0, ((float) $i['quantidade'] * (float) $i['valor_unitario']) - (float) ($i['valor_desconto'] ?? 0)),
            $itens
        );
        $somaPesos = array_sum($pesos);

        $centavos = [];
        foreach ($pesos as $k => $peso) {
            $centavos[$k] = $somaPesos > 0
                ? (int) floor($totalCentavos * $peso / $somaPesos)
                : intdiv($totalCentavos, count($pesos));
        }

        // sobra do arredondamento vai para o item de maior valor
        $sobra = $totalCentavos - array_sum($centavos);
        if ($sobra !== 0) {
            $centavos[array_keys($pesos, max($pesos))[0]] += $sobra;
        }

        foreach ($itens as $k => $_) {
            $itens[$k]['valor_frete'] = $centavos[$k] / 100;
        }

        return $itens;
    }

    public function danfe(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 404);

        // (new NotaFiscalService())->gerarDanfe($notaFiscal) -> retorna PDF (usando NFePHP\DA\NFe\Danfe)
        abort(501, 'Geração de DANFE ainda não implementada.');
    }

    public function xml(NotaFiscal $notaFiscal)
    {
        abort_if(!$notaFiscal->xml, 404);

        return response($notaFiscal->xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=nfe-{$notaFiscal->numero}.xml",
        ]);
    }

    

    public function previsualizar(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load(['itens.produto', 'itens.ncm', 'itens.tributacao', 'itens.ipi', 'cliente']);
        $empresa = Empresa::first();

        $totalBaseIcms = 0;
        $totalValorIcms = 0;

        $itens = $notaFiscal->itens->values()->map(function ($item, $index) use ($notaFiscal, &$totalBaseIcms, &$totalValorIcms) {
            $trib = $item->tributacao;
            $subtotalBruto = $item->valor_unitario * $item->quantidade;
            $baseImpostos = $item->base_impostos;
            $cstOuCsosn = $trib?->csosn ?? $trib?->cst_icms ?? '—';

            $cstsComBaseCalculo = ['00', '10', '20', '70', '90'];

            if ($item->bc_icms_manual !== null) {
                // Destaque manual (CFOP com "destacar_bases" ativo) — prioridade sobre o cálculo automático
                $baseIcms = (float) $item->bc_icms_manual;
                $valorIcms = (float) ($item->valor_icms_manual ?? 0);
                $aliquotaIcms = (float) ($item->aliquota_icms_manual ?? 0);
            } elseif ($trib && $trib->cst_icms && in_array($trib->cst_icms, $cstsComBaseCalculo, true)) {
                $baseIcms = $baseImpostos;
                $aliquotaIcms = (float) $trib->aliquota_icms;
                $valorIcms = $baseIcms * $aliquotaIcms / 100;
            } else {
                $baseIcms = 0;
                $valorIcms = 0;
                $aliquotaIcms = 0;
            }

            $totalBaseIcms += $baseIcms;
            $totalValorIcms += $valorIcms;

            $ipi = $item->ipi;

            if ($item->valor_ipi_manual !== null) {
                $valorIpi = (float) $item->valor_ipi_manual;
                $aliquotaIpi = (float) ($item->aliquota_ipi_manual ?? 0);
            } elseif ($ipi && $ipi->codigo === '50' && $ipi->aliquota) {
                $aliquotaIpi = (float) $ipi->aliquota;
                $valorIpi = $baseImpostos * $aliquotaIpi / 100;
            } else {
                $valorIpi = 0;
                $aliquotaIpi = 0;
            }

            return [
                'numero'         => $index + 1,
                'codigo'         => $item->produto->codigo_interno,
                'descricao'      => $item->descricao ?? $item->produto->nome,
                'ncm'            => $item->ncm->codigo ?? '—',
                'cst'            => $cstOuCsosn,
                'cfop'           => $item->cfopEfetivo($notaFiscal),
                'unidade'        => $item->produto->unidade_comercial,
                'quantidade'     => number_format($item->quantidade, 3, ',', '.'),
                'valor_unitario' => number_format($item->valor_unitario, 2, ',', '.'),
                'valor_total'    => number_format($item->valor_total, 2, ',', '.'),
                'valor_outras_despesas' => number_format($item->valor_outras_despesas, 2, ',', '.'),
                'bc_icms'        => number_format($baseIcms, 2, ',', '.'),
                'valor_icms'     => number_format($valorIcms, 2, ',', '.'),
                'aliquota_icms'  => number_format($aliquotaIcms, 2, ',', '.'),
                'valor_ipi'      => number_format($valorIpi, 2, ',', '.'),
                'aliquota_ipi'   => number_format($aliquotaIpi, 2, ',', '.'),
                'bases_manuais'  => $item->bc_icms_manual !== null || $item->valor_ipi_manual !== null,
                
            ];
        });

        $dados = [
            'emitida'           => $notaFiscal->status !== 'rascunho',
            'status'            => $notaFiscal->status,
            'numero'            => $notaFiscal->numero ?? '(a definir)',
            'serie'             => $notaFiscal->serie ?? '(a definir)',
            'natureza_operacao' => $notaFiscal->natureza_operacao,
            'tipo_operacao'     => $notaFiscal->tipo_operacao === 'entrada' ? '0-Entrada' : '1-Saída',
            'chave_acesso'      => $notaFiscal->chave_acesso ? $this->formatarChave($notaFiscal->chave_acesso) : null,
            'protocolo'         => $notaFiscal->protocolo,
            'data_emissao'      => $notaFiscal->emitida_em?->format('d/m/Y H:i') ?? '—',
            'ambiente'          => (int) $empresa->ambiente === 2 ? 'HOMOLOGAÇÃO' : 'PRODUÇÃO',

            'emitente' => [
                'razao_social'  => $empresa->razao_social,
                'nome_fantasia' => $empresa->nome_fantasia,
                'cnpj'          => $this->formatarCnpj($empresa->cnpj),
                'ie'            => $empresa->ie,
                'endereco'      => $empresa->logradouro . ', ' . $empresa->numero . ($empresa->complemento ? ' - ' . $empresa->complemento : ''),
                'bairro'        => $empresa->bairro,
                'municipio'     => $empresa->municipio,
                'uf'            => $empresa->uf,
                'cep'           => $this->formatarCep($empresa->cep),
            ],

            'destinatario' => [
                'nome'      => $notaFiscal->cliente->nome,
                'documento' => $notaFiscal->cliente->cpf_cnpj_formatado,
                'ie'        => $notaFiscal->cliente->ie ?? 'ISENTO',
                'endereco'  => $notaFiscal->cliente->logradouro . ', ' . $notaFiscal->cliente->numero . ($notaFiscal->cliente->complemento ? ' - ' . $notaFiscal->cliente->complemento : ''),
                'bairro'    => $notaFiscal->cliente->bairro,
                'municipio' => $notaFiscal->cliente->municipio,
                'uf'        => $notaFiscal->cliente->uf,
                'cep'       => $this->formatarCep($notaFiscal->cliente->cep),
                'telefone'  => $notaFiscal->cliente->telefone,
            ],

            'totais' => [
                'base_calculo_icms' => number_format($totalBaseIcms, 2, ',', '.'),
                'valor_icms'        => number_format($totalValorIcms, 2, ',', '.'),
                'valor_produtos'    => number_format($notaFiscal->valor_produtos, 2, ',', '.'),
                'valor_frete'       => number_format($notaFiscal->valor_frete, 2, ',', '.'),
                'valor_desconto'    => number_format($notaFiscal->valor_desconto, 2, ',', '.'),
                'valor_total_nota'  => number_format($notaFiscal->valor_total, 2, ',', '.'),
                'valor_outras_despesas' => number_format($notaFiscal->itens->sum('valor_outras_despesas'), 2, ',', '.'),
            ],

            'transporte' => [
                'modalidade'     => NotaFiscal::MODALIDADES_FRETE[(int) $notaFiscal->mod_frete] ?? '—',
                'transportador'  => $notaFiscal->transportador?->nome,
                'documento'      => $notaFiscal->transportador?->documento_formatado,
                'ie'             => $notaFiscal->transportador?->ie,
                'endereco'       => $notaFiscal->transportador?->endereco_nfe,
                'municipio_uf'   => $notaFiscal->transportador
                    ? $notaFiscal->transportador->municipio . '/' . $notaFiscal->transportador->uf
                    : null,
                'placa'          => $notaFiscal->veiculo?->placa_formatada,
                'uf_placa'       => $notaFiscal->veiculo?->uf,
                'rntrc'          => $notaFiscal->veiculo?->rntrc,
                'vol_quantidade' => $notaFiscal->vol_quantidade,
                'vol_especie'    => $notaFiscal->vol_especie,
                'vol_marca'      => $notaFiscal->vol_marca,
                'vol_numeracao'  => $notaFiscal->vol_numeracao,
                'peso_bruto'     => $notaFiscal->vol_peso_bruto !== null ? number_format((float) $notaFiscal->vol_peso_bruto, 3, ',', '.') : null,
                'peso_liquido'   => $notaFiscal->vol_peso_liquido !== null ? number_format((float) $notaFiscal->vol_peso_liquido, 3, ',', '.') : null,
            ],

            'itens' => $itens,
            'informacoes_complementares' => collect([
                $notaFiscal->textoNotasReferenciadas(),
                $notaFiscal->informacoes_complementares,
            ])->filter()->implode(' | ') ?: null,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('notasfiscais.pdf.previsualizacao', compact('dados'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("previsualizacao-nf-{$notaFiscal->id}.pdf");
    }

    private function formatarCnpj(string $cnpj): string
    {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    private function formatarCep(?string $cep): string
    {
        return $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : '—';
    }

    private function formatarChave(string $chave): string
    {
        return implode(' ', str_split($chave, 4));
    }
}