@php
    $ehEdicao = $entrada->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';
    $travadoXml = $ehEdicao && ! $somenteLeitura && $entrada->tipo_entrada === 'xml';
    $travarCab  = $somenteLeitura || $travadoXml;   // campos do documento da nota

@endphp

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            mostrarAviso({{ Illuminate\Support\Js::from($errors->all()) }}.join('\n'), 'erro');
        });
    </script>
@endif

<form id="form-entrada" method="POST"
      action="{{ $ehEdicao ? route('entradas-nota.update', $entrada) : route('entradas-nota.store') }}"
      class="flex flex-col gap-6 w-fit max-w-full min-w-[min(64rem,100%)] mt-6 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    {{-- Campos dos itens (gerados pelo JS a partir da grade) --}}
    <div id="campos-itens"></div>

    {{-- ===================== Dados da nota ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-3 mb-4">
            @if ($ehEdicao)
                <a href="{{ route('entradas-nota.index') }}" title="Voltar para Notas de Entrada"
                   class="text-gray-500 hover:text-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif
            <h1 class="text-lg font-semibold">
                @if (! $ehEdicao)
                    Nova Nota Fiscal (Entrada)
                @elseif ($somenteLeitura)
                    Nota Fiscal de Entrada nº {{ $entrada->numero }} (finalizada em {{ $entrada->finalizada_em?->format('d/m/Y H:i') }})
                @else
                    Editar Nota Fiscal de Entrada (rascunho)
                @endif
            </h1>
        </div>
        @if ($travadoXml)
            <div class="mb-4 flex items-start gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                <span>Nota importada do XML: os dados do documento ficam travados. Você pode alterar apenas a
                    <strong>Operação / CFOP da entrada</strong> e a <strong>Forma de pagamento</strong>.</span>
            </div>
        @endif

            <div class="flex flex-col gap-4 max-w-6xl">

                {{-- Linha 1: fornecedor --}}
                <div>
                    <label class="{{ $labelCls }}">Fornecedor</label>
                    <div class="flex gap-2">
                        <select id="fornecedor_id" name="fornecedor_id" class="{{ $inputCls }}" @disabled($travarCab)>
                            <option value="">Selecionar fornecedor...</option>
                            @foreach ($fornecedores as $f)
                                <option value="{{ $f->id }}" data-uf="{{ $f->uf }}" @selected(old('fornecedor_id', $entrada->fornecedor_id) == $f->id)>
                                    {{ $f->nome_exibicao }} - {{ $f->documento_formatado }}
                                </option>
                            @endforeach
                        </select>
                        @unless ($travarCab)
                            <button type="button" onclick="abrirModalFornecedor()"
                                    class="shrink-0 border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                                + Novo
                            </button>
                        @endunless
                    </div>
                    @error('fornecedor_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                {{-- Linha 2: documento --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[22rem]">
                        <label class="{{ $labelCls }}">Chave de acesso (44 dígitos)</label>
                        <input type="text" name="chave_acesso" maxlength="44" inputmode="numeric"
                            value="{{ old('chave_acesso', $entrada->chave_acesso) }}"
                            class="{{ $inputCls }}" @disabled($travarCab)>
                        @error('chave_acesso') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-24">
                        <label class="{{ $labelCls }}">Número</label>
                        <input type="text" name="numero" maxlength="9" value="{{ old('numero', $entrada->numero) }}"
                            class="{{ $inputCls }}" @disabled($travarCab)>
                        @error('numero') <p class="{{ $erroCls }} w-max max-w-[18rem]">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-36">
                        <label class="{{ $labelCls }}">Modelo</label>
                        <select name="modelo" class="{{ $inputCls }}" @disabled($travarCab)>
                            <option value="55" @selected(old('modelo', $entrada->modelo) === '55')>55 - NF-e</option>
                            <option value="01" @selected(old('modelo', $entrada->modelo) === '01')>01 - NF (papel)</option>
                        </select>
                        @error('modelo') <p class="{{ $erroCls }} w-max max-w-[18rem]">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-16">
                        <label class="{{ $labelCls }}">Série</label>
                        <input type="text" name="serie" maxlength="3" value="{{ old('serie', $entrada->serie) }}"
                            class="{{ $inputCls }}" @disabled($travarCab)>
                        @error('serie') <p class="{{ $erroCls }} w-max max-w-[18rem]">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-36">
                        <label class="{{ $labelCls }}">Data de emissão</label>
                        <input type="date" name="data_emissao"
                            value="{{ old('data_emissao', $entrada->data_emissao?->format('Y-m-d')) }}"
                            class="{{ $inputCls }}" @disabled($travarCab)>
                        @error('data_emissao') <p class="{{ $erroCls }} w-max max-w-[18rem]">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-36">
                        <label class="{{ $labelCls }}">Data de entrada</label>
                        <input type="date" name="data_entrada"
                            value="{{ old('data_entrada', $entrada->data_entrada?->format('Y-m-d')) }}"
                            class="{{ $inputCls }}" @disabled($travarCab)>
                        @error('data_entrada') <p class="{{ $erroCls }} w-max max-w-[18rem]">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Linha 3: operação e pagamento --}}
                @php
                    $opSelecionada = collect($operacoes)->firstWhere('id', old('operacao_entrada_id', $entrada->operacao_entrada_id));
                @endphp
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Operação / CFOP da entrada <span class="text-red-500">*</span></label>
                        <input type="hidden" name="operacao_entrada_id" id="operacao_entrada_id"
                            value="{{ old('operacao_entrada_id', $entrada->operacao_entrada_id) }}">
                        <button type="button" onclick="abrirModalOperacao()" @disabled($somenteLeitura ?? false)
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition disabled:bg-gray-50 disabled:cursor-not-allowed">
                            <span id="operacao_entrada_label" class="{{ $opSelecionada ? 'text-gray-800' : 'text-gray-500' }}">
                                {{ $opSelecionada ? (($opSelecionada['cfops'] ?: '—') . ' — ' . $opSelecionada['descricao']) : 'Clique para selecionar...' }}
                            </span>
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pagamento</label>
                        <select name="forma_pagamento_id" @disabled($somenteLeitura)
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed">
                            <option value="">Não informada</option>
                            @foreach ($formasPagamento as $forma)
                                <option value="{{ $forma->id }}" @selected(old('forma_pagamento_id', $entrada->forma_pagamento_id) == $forma->id)>
                                    {{ $forma->ind_pag ? 'A prazo' : 'À vista' }} — {{ $forma->descricao }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
    </div>

    {{-- ===================== Itens ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-3">Itens da nota</h2>

        @error('itens') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror

        @unless ($somenteLeitura)
            <input type="text" id="input-busca-item" placeholder="Nome, código interno ou código de barras..."
                   autocomplete="off" oninput="abrirModalProdutoComTexto(this)"
                   class="w-full max-w-2xl border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">

        @endunless

        {{-- Cresce até ~10 itens (30rem inclui cabeçalho e totais); acima disso rola por dentro --}}
        <div id="area-itens" class="overflow-auto max-h-[30rem]">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0 z-10">
                    <tr>
                        <th class="text-left px-3 py-2">Itens</th>
                        <th class="text-left px-3 py-2">Cód. forn.</th>
                        <th class="text-left px-3 py-2">Cód. Barras</th>
                        <th class="text-left px-3 py-2">Descrição</th>
                        <th class="text-left px-3 py-2">CFOP</th>
                        <th class="text-left px-3 py-2">
                            {{ $fiscal['regime'] === 'normal' ? 'CST' : 'CSOSN' }}
                        </th>
                        <th class="text-right px-3 py-2">Qtd</th>
                        <th class="text-right px-3 py-2">Por cx</th>
                        <th class="text-right px-3 py-2">Qtd. estoque</th>
                        <th class="text-right px-3 py-2">Vl Unit</th>
                        <th class="text-right px-3 py-2">Vl Total</th>
                        <th class="text-right px-3 py-2">Desconto</th>
                        <th class="text-right px-3 py-2">Desconto %</th>
                        <th class="text-right px-3 py-2">Outras desp.</th>
                        <th class="text-right px-3 py-2">Frete</th>
                        <th class="text-right px-3 py-2">BC ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. ICMS</th>
                        <th class="text-right px-3 py-2">% ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. IPI</th>
                        <th class="text-right px-3 py-2">% IPI</th>
                        <th class="text-left px-3 py-2">Lote</th>
                        <th class="text-left px-3 py-2">Validade</th>

                    </tr>
                </thead>
                <tbody id="linhas-grid-itens" class="divide-y divide-gray-100"></tbody>
                <tfoot class="bg-gray-50 font-medium sticky bottom-0 z-10">
                    <tr class="border-t-2 border-gray-200">
                        <td class="px-3 py-2 text-left">
                            <span id="tot-itens">0</span>
                        </td>
                        <td colspan="5"></td>
                        <td class="px-3 py-2 text-right" id="tot-qtd">0</td>
                        <td></td>
                        <td class="px-3 py-2 text-right" id="tot-estoque">0</td>
                        <td></td>
                        <td class="px-3 py-2 text-right" id="tot-total">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-desconto">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-desconto-pct" title="Desconto sobre o valor bruto dos itens">0,00%</td>
                        <td class="px-3 py-2 text-right" id="tot-outras">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-frete">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-bc-icms">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-vl-icms">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-aliq-icms" title="Média ponderada pela BC do ICMS">0,00%</td>
                        <td class="px-3 py-2 text-right" id="tot-vl-ipi">R$ 0,00</td>
                        <td class="px-3 py-2 text-right" id="tot-aliq-ipi" title="Média ponderada pela base do IPI">0,00%</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td colspan="10" class="px-3 py-2 text-right">Total da nota</td>
                        <td class="px-3 py-2 text-right" id="total-nota">R$ 0,00</td>
                        <td colspan="11"></td>
                    </tr>
                </tfoot>
            </table>
            <p id="grid-vazia" class="text-center text-gray-400 py-8">Nenhum item adicionado ainda.</p>
        </div>
    </div>

    {{-- ===================== Totais e observação ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="grid grid-cols-3 gap-4 max-w-5xl">
            <div>
                <label class="{{ $labelCls }}">Frete (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_frete" name="valor_frete"
                       value="{{ old('valor_frete', (float) $entrada->valor_frete) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
            </div>
            <div>
                <label class="{{ $labelCls }}">Outras despesas (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_outras" name="valor_outras"
                       value="{{ old('valor_outras', (float) $entrada->valor_outras) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
            </div>
            <div>
                <label class="{{ $labelCls }}">Desconto na nota (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_desconto" name="valor_desconto"
                       value="{{ old('valor_desconto', (float) $entrada->valor_desconto) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
            </div>

            <div class="col-span-3">
                <label class="{{ $labelCls }}">Observação</label>
                <textarea name="observacao" rows="3" maxlength="1000" class="{{ $inputCls }}"
                          @disabled($somenteLeitura)>{{ old('observacao',$entrada->observacao) }}</textarea>
            </div>

            <label class="col-span-3 flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="atualizar_custo" value="1"
                       @checked(old('atualizar_custo', $entrada->atualizar_custo)) @disabled($somenteLeitura)>
                Atualizar o preço de custo dos produtos ao finalizar
            </label>
        </div>

        <div class="flex flex-wrap gap-3 mt-6">
            @unless ($somenteLeitura)
                <button type="submit" name="acao" value="salvar"
                        class="bg-green-700 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-green-800">
                    {{ $ehEdicao ? 'Salvar Alterações' : 'Salvar Rascunho' }}
                </button>
                <button type="submit" name="acao" value="finalizar"
                        onclick="return confirm('Finalizar a entrada? O estoque será atualizado e a nota não poderá mais ser alterada.')"
                        class="bg-gray-800 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-gray-700">
                    Finalizar Entrada
                </button>
            @endunless
            <a href="{{ route('entradas-nota.index') }}"
               class="border border-gray-300 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-50">
                {{ $somenteLeitura ? 'Voltar' : 'Cancelar' }}
            </a>
        </div>
    </div>

    <div id="area-avisos" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]"></div>
</form>

@unless ($somenteLeitura)
<!-- Modal de busca de produto -->
<div id="modal-busca-produto" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Produto</h2>
            <button type="button" onclick="fecharModalProduto()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-produto-modal" placeholder="Buscar por nome, código ou código de barras..."
                   autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Código</th>
                        <th class="py-2">Produto</th>
                        <th class="py-2">Custo</th>
                        <th class="py-2">Estoque</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-produto"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar. Produtos com variação ainda não aparecem aqui.</p>
        </div>
    </div>
</div>

<!-- Modal de edição do item -->
<div id="modal-editor-item" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900 rounded-t-xl">
            <h2 id="editor-modal-titulo" class="text-lg font-bold text-white">Adicionar item à nota</h2>
            <button type="button" onclick="cancelarEditor()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="px-6 pt-4">
            <p id="editor-produto-nome" class="text-sm font-semibold text-gray-800"></p>
        </div>

        <div class="flex border-b border-gray-200 px-6 mt-3">
            <button type="button" onclick="editorTab('dados')" id="ed-tab-btn-dados"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition border-blue-600 text-blue-600">
                Dados do item
            </button>
            <button type="button" onclick="editorTab('impostos')" id="ed-tab-btn-impostos"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700">
                Impostos
            </button>
        </div>

        <div class="p-6 overflow-y-auto">
            {{-- ===== Aba: Dados do item ===== --}}
            <div id="ed-painel-dados" class="flex flex-col gap-4">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $labelCls }}">Código</label>
                        <input type="text" id="ed-codigo" readonly class="{{ $inputCls }} bg-gray-100">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Cód. Barras</label>
                        <input type="text" id="ed-codbarras" readonly class="{{ $inputCls }} bg-gray-100">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Cód. no fornecedor</label>
                        <input type="text" id="ed-codforn" maxlength="60" class="{{ $inputCls }}">
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $labelCls }}">Descrição</label>
                        <input type="text" id="ed-descricao" maxlength="200" class="{{ $inputCls }}">
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $labelCls }}">Qtd</label>
                        <input type="number" id="ed-qtd" step="0.001" min="0.001" oninput="aoDigitarValores('valor')" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Por caixa (UN)</label>
                        <input type="number" id="ed-porcaixa" step="1" min="1" value="1" oninput="atualizarTotalEditor()" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Vl Unit (R$)</label>
                        <input type="number" id="ed-unit" step="0.0001" min="0" oninput="aoDigitarValores('valor')" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Desconto (R$)</label>
                        <input type="number" id="ed-desc" step="0.01" min="0" value="0" oninput="aoDigitarValores('valor')" class="{{ $inputCls }}">
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">Desconto %</label>
                        <input type="number" id="ed-desc-pct" step="0.01" min="0" max="100" value="0" oninput="aoDigitarValores('percentual')" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Outras despesas (R$)</label>
                        <input type="number" id="ed-outras" step="0.01" min="0" value="0" oninput="aoDigitarValores('valor')" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Frete (R$)</label>
                        <input type="number" id="ed-frete" step="0.01" min="0" value="0" oninput="aoDigitarValores('valor')" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Vl Total</label>
                        <input type="text" id="ed-total" readonly class="{{ $inputCls }} bg-gray-100">
                    </div>

                    <p id="ed-estoque-dica" class="col-span-4 text-xs text-gray-500 hidden"></p>

                    <div class="col-span-2">
                        <label class="{{ $labelCls }}">Lote</label>
                        <input type="text" id="ed-lote" maxlength="30" class="{{ $inputCls }}">
                    </div>
                    <div class="col-span-2">
                        <label class="{{ $labelCls }}">Validade</label>
                        <input type="date" id="ed-validade" class="{{ $inputCls }}">
                    </div>
                </div>
            </div>

            {{-- ===== Aba: Impostos ===== --}}
            <div id="ed-painel-impostos" class="hidden">
                <div class="flex flex-col gap-5">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Classificação fiscal</span>
                            <button type="button" onclick="restaurarFiscalAutomatico()" class="text-xs text-blue-600 hover:underline">Restaurar automático</button>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="{{ $labelCls }}">CFOP de entrada</label>
                                <select id="ed-cfop" class="{{ $inputCls }} bg-white"></select>
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">{{ $fiscal['regime'] === 'normal' ? 'CST' : 'CSOSN' }} de entrada</label>
                                <select id="ed-cst" class="{{ $inputCls }} bg-white"></select>
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Gera crédito de ICMS</label>
                                <select id="ed-credito" class="{{ $inputCls }} bg-white">
                                    <option value="">Não informado</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                        </div>

                        <p id="ed-fiscal-dica" class="text-xs text-gray-400 mt-2"></p>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">ICMS</span>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="{{ $labelCls }}">BC ICMS (R$)</label>
                                <input type="number" id="ed-bc-icms" step="0.01" min="0" value="0" oninput="atualizarIcmsEditor()" class="{{ $inputCls }}">
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">Vlr. ICMS (R$)</label>
                                <input type="number" id="ed-vl-icms" step="0.01" min="0" value="0" class="{{ $inputCls }}">
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">% ICMS</label>
                                <input type="number" id="ed-aliq-icms" step="0.0001" min="0" max="100" value="0" oninput="atualizarIcmsEditor()" class="{{ $inputCls }}">
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">O Vlr. ICMS é calculado (BC × %) ao digitar a BC ou o %, e pode ser ajustado manualmente.</p>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">IPI</span>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="{{ $labelCls }}">Vlr. IPI (R$)</label>
                                <input type="number" id="ed-vl-ipi" step="0.01" min="0" value="0" class="{{ $inputCls }}">
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">% IPI</label>
                                <input type="number" id="ed-aliq-ipi" step="0.0001" min="0" max="100" value="0" oninput="atualizarIpiEditor()" class="{{ $inputCls }}">
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">O Vlr. IPI é calculado sobre Qtd × Vl Unit + outras despesas + frete quando o % é informado, e pode ser ajustado manualmente.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-2">
            <button type="button" id="btn-remover-item" onclick="removerItemEmEdicao()"
                    class="hidden border border-red-300 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg text-sm font-medium transition">
                Remover item da nota
            </button>
            <button type="button" onclick="cancelarEditor()"
                    class="ml-auto bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancelar</button>
            <button type="button" id="btn-salvar-item" onclick="adicionarLinhaNaGrid()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                Adicionar à nota
            </button>
        </div>
    </div>
</div>

<!-- Modal de cadastro rápido de fornecedor -->
<div id="modal-fornecedor" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Cadastrar Fornecedor</h2>
            <button type="button" onclick="fecharModalFornecedor()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-3">
            <div>
                <label class="{{ $labelCls }}">CNPJ / CPF</label>
                <input type="text" id="forn-doc" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="{{ $labelCls }}">Razão social</label>
                <input type="text" id="forn-razao" maxlength="150" class="{{ $inputCls }}">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelCls }}">Nome fantasia</label>
                    <input type="text" id="forn-fantasia" maxlength="150" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Inscrição estadual</label>
                    <input type="text" id="forn-ie" maxlength="20" class="{{ $inputCls }}">
                </div>
            </div>

            <p id="forn-erro" class="text-red-600 text-sm hidden"></p>

            <button type="button" onclick="salvarFornecedor()"
                    class="w-full bg-green-700 hover:bg-green-800 text-white py-2.5 rounded-lg text-sm font-medium">
                Cadastrar fornecedor
            </button>
        </div>
    </div>
</div>
@endunless

<script>
const itensIniciais  = {{ Illuminate\Support\Js::from($itens) }};
const somenteLeitura = {{ Illuminate\Support\Js::from($somenteLeitura) }};
const urlBuscaProdutos = {{ Illuminate\Support\Js::from(route('entradas-nota.produtos')) }};
const urlFornecedorRapido = {{ Illuminate\Support\Js::from(route('fornecedores.rapido')) }};
const csrfToken = {{ Illuminate\Support\Js::from(csrf_token()) }};

let itensNota = [];
let produtoEditor = null;   // produto selecionado no editor
let indiceEdicao = null;    // posição do item da grade em edição (null = item novo)
let fiscalPreservado = null;   // dados fiscais do item em edição   
let fiscalEditor = null;   // valores automáticos do item em edição (para detectar mudança manual)
let resultadosBusca = [];
let indiceBusca = 0;
let timerBusca = null;

const moeda = v => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const numero = id => parseFloat(document.getElementById(id)?.value) || 0;
const arred2 = v => Math.round(v * 100) / 100;
const pct = v => (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
const dataBr = iso => iso ? iso.split('-').reverse().join('/') : '—';

function mostrarAviso(mensagem, tipo = 'erro') {
    const cores = {
        erro:    'bg-red-50 border-red-300 text-red-800',
        sucesso: 'bg-green-50 border-green-300 text-green-800',
        info:    'bg-blue-50 border-blue-300 text-blue-800',
    };

    const aviso = document.createElement('div');
    aviso.className = `border rounded-lg shadow-lg px-4 py-3 text-sm flex gap-3 items-start ${cores[tipo] ?? cores.info}`;
    aviso.setAttribute('role', 'alert');

    const texto = document.createElement('div');
    texto.className = 'flex-1 whitespace-pre-line';
    texto.textContent = mensagem;

    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'text-lg leading-none opacity-60 hover:opacity-100';
    fechar.innerHTML = '&times;';
    fechar.onclick = () => aviso.remove();

    aviso.append(texto, fechar);
    document.getElementById('area-avisos').appendChild(aviso);

    setTimeout(() => aviso.remove(), tipo === 'erro' ? 10000 : 4000);
}

// ---------------------------------------------------------------
// Grade de itens
// ---------------------------------------------------------------
function totalItem(item) {
    return arred2((item.quantidade * item.valor_unitario) - item.valor_desconto);
}

// ---------------------------------------------------------------
// Classificação fiscal dos itens (CFOP e CST/CSOSN de entrada)
// ---------------------------------------------------------------
const regrasFiscais = {{ Illuminate\Support\Js::from($fiscal) }};
const rotuloCst = regrasFiscais.regime === 'normal' ? 'CST' : 'CSOSN';

function operacaoAtual() {
    return document.getElementById('operacao_entrada_id')?.value || '';
}

// Item digitado (sem XML): presume o CFOP de origem pela UF do fornecedor
function cfopOrigemPresumido() {
    const ufFornecedor = (document.getElementById('fornecedor_id')?.selectedOptions[0]?.dataset.uf || '').toUpperCase();
    const ufEmpresa = (regrasFiscais.ufEmpresa || '').toUpperCase();

    if (!ufFornecedor || !ufEmpresa) return null;
    return ufFornecedor === ufEmpresa ? '5102' : '6102';
}

// Regime normal mostra a origem na frente do CST (ex.: 000), como no XML
function exibirCstEntrada(codigo, origem) {
    return (regrasFiscais.regime === 'normal' && origem !== '' && origem != null) ? `${origem}${codigo}` : codigo;
}



const flag = v => v === true || v === '1' || v === 1;
const creditoDe = v => (v === '' || v == null) ? null : flag(v);

function fiscalDoItem(item) {
    const tipo = item.csosn_origem ? 'csosn' : (item.cst_origem ? 'cst' : null);
    const codigoOrigem = item.csosn_origem || item.cst_origem || null;

    // Nota finalizada ou item informado manualmente: vale o que está gravado no item
    if (somenteLeitura || flag(item.fiscal_manual)) {
        return {
            semOperacao: false,
            manual: flag(item.fiscal_manual),
            presumido: false,
            cfopOrigem: item.cfop_origem || null,
            cfopEntrada: item.cfop_entrada || null,
            tipo,
            codigoOrigem,
            cst: item.cst_entrada ? { cst: item.cst_entrada, credito: creditoDe(item.gera_credito) } : null,
        };
    }

    const operacao = operacaoAtual();
    const presumido = !item.cfop_origem;
    const cfopOrigem = item.cfop_origem || cfopOrigemPresumido();

    return {
        semOperacao: !operacao,
        manual: false,
        presumido,
        cfopOrigem,
        cfopEntrada: operacao && cfopOrigem ? (regrasFiscais.cfops[operacao]?.[cfopOrigem] ?? null) : null,
        tipo,
        codigoOrigem,
        cst: tipo ? (regrasFiscais.csts[`${tipo}:${codigoOrigem}`] ?? null) : null,
    };
}


function elemento(tag, classes, texto, titulo = '') {
    const el = document.createElement(tag);
    el.className = classes;
    el.textContent = texto;
    if (titulo) el.title = titulo;
    return el;
}

function marcaManual() {
    return elemento('span', 'ml-1 text-xs text-gray-400', '✎', 'Informado manualmente');
}


function celulaCfop(f) {
    const td = document.createElement('td');
    td.className = 'px-3 py-2 text-left';

    if (f.semOperacao) {
        td.appendChild(elemento('span', 'text-xs text-gray-400', 'Escolha a operação'));
        return td;
    }

    if (f.cfopEntrada) {
        const descricao = regrasFiscais.descricoes[f.cfopEntrada] ?? '';
        const origem = f.cfopOrigem
            ? ` · CFOP do fornecedor: ${f.cfopOrigem}${f.presumido ? ' (presumido pela UF)' : ''}`
            : '';

        td.appendChild(elemento('span',
            'inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-xs font-semibold',
            f.cfopEntrada, descricao + origem));
        if (f.manual) td.appendChild(marcaManual());
    } else {
        td.appendChild(elemento('span', 'inline-block px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-medium', 'Pendente',
            f.manual ? 'CFOP não definido neste item'
                : (f.cfopOrigem ? `Sem regra para o CFOP ${f.cfopOrigem} nesta operação`
                                : 'Sem CFOP de origem: selecione o fornecedor (UF) ou importe pelo XML')));
    }

    return td;
}


function celulaCst(f, item) {
    const td = document.createElement('td');
    td.className = 'px-3 py-2 text-left';

    if (f.cst) {
        const origem = f.tipo
            ? ` · ${f.tipo.toUpperCase()} do fornecedor: ${f.tipo === 'cst' ? `${item.origem_mercadoria ?? ''}${item.cst_origem}` : item.csosn_origem}`
            : '';
        const credito = f.cst.credito === null ? 'não informado' : (f.cst.credito ? 'sim' : 'não');

        td.appendChild(elemento('span',
            'inline-block px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-mono text-xs font-semibold',
            exibirCstEntrada(f.cst.cst, item.origem_mercadoria),
            `${rotuloCst} de entrada${origem} · gera crédito de ICMS: ${credito}`));
        if (f.manual) td.appendChild(marcaManual());
    } else if (!f.tipo) {
        td.appendChild(elemento('span', 'text-xs text-gray-400', '—', 'Sem CST/CSOSN do XML (item digitado)'));
    } else {
        td.appendChild(elemento('span', 'inline-block px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-medium', 'Pendente',
            `Sem conversão para o ${f.tipo.toUpperCase()} ${f.codigoOrigem} no seu regime`));
    }

    return td;
}


// ---------------------------------------------------------------
// Campos fiscais do editor de item
// ---------------------------------------------------------------
function preencherSelect(select, opcoes) {
    select.innerHTML = '';
    select.add(new Option('— não definido —', ''));
    opcoes.forEach(([valor, texto]) => select.add(new Option(texto, valor)));
}

function popularOpcoesFiscais() {
    const cfop = document.getElementById('ed-cfop');
    if (!cfop) return;   // nota finalizada: não há editor

    preencherSelect(cfop, regrasFiscais.cfopsEntrada.map(c => [c.codigo, `${c.codigo} — ${c.descricao}`]));
    preencherSelect(document.getElementById('ed-cst'), regrasFiscais.opcoesCst.map(o => [o.codigo, `${o.codigo} — ${o.descricao}`]));
}

function paraCampos(f) {
    return {
        cfop: f.cfopEntrada || '',
        cst: f.cst?.cst || '',
        credito: f.cst ? (f.cst.credito === null ? '' : (f.cst.credito ? '1' : '0')) : '',
    };
}

function aplicarNoEditor(v) {
    document.getElementById('ed-cfop').value = v.cfop;
    document.getElementById('ed-cst').value = v.cst;
    document.getElementById('ed-credito').value = v.credito;
}

function preencherEditorFiscal(item) {
    // o que o sistema calcularia sem intervenção manual
    fiscalEditor = paraCampos(fiscalDoItem({ ...item, fiscal_manual: false }));

    const manual = flag(item.fiscal_manual);
    aplicarNoEditor(manual ? paraCampos(fiscalDoItem(item)) : fiscalEditor);

    document.getElementById('ed-fiscal-dica').textContent = manual
        ? 'Informado manualmente.'
        : 'Calculado automaticamente. Altere os campos para informar manualmente.';
}

function restaurarFiscalAutomatico() {
    if (!fiscalEditor) return;
    aplicarNoEditor(fiscalEditor);
    document.getElementById('ed-fiscal-dica').textContent = 'Voltou ao cálculo automático.';
}

function lerEditorFiscal() {
    const v = {
        cfop: document.getElementById('ed-cfop').value,
        cst: document.getElementById('ed-cst').value,
        credito: document.getElementById('ed-credito').value,
    };
    const auto = fiscalEditor ?? { cfop: '', cst: '', credito: '' };

    return { ...v, manual: v.cfop !== auto.cfop || v.cst !== auto.cst || v.credito !== auto.credito };
}


function renderizarGrid() {
    const corpo = document.getElementById('linhas-grid-itens');
    const campos = document.getElementById('campos-itens');
    corpo.innerHTML = '';
    campos.innerHTML = '';

    itensNota.forEach((item, i) => {
        const total = totalItem(item);

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50';

        const adicionarCelulas = (celulas) => celulas.forEach(([texto, alinhamento]) => {
            const td = document.createElement('td');
            td.className = `px-3 py-2 ${alinhamento}`;
            td.textContent = texto;
            tr.appendChild(td);
        });

        const fiscal = fiscalDoItem(item);

        const bruto = item.quantidade * item.valor_unitario;
        const descontoPct = bruto > 0 ? (item.valor_desconto / bruto) * 100 : 0;

        // Ordem: Itens, Cód. forn., Cód. Barras, Descrição, CFOP, CST/CSOSN, Qtd, Por cx, Qtd. estoque, ...
        adicionarCelulas([
            [String(i + 1), 'text-left font-medium text-gray-500'],
            [item.codigo_fornecedor || '—', 'text-left'],
            [item.codigo_barras || '—', 'text-left'],
        ]);

        // Descrição: uma linha só, com "..." quando passa da largura.
        const tdDescricao = document.createElement('td');
        tdDescricao.className = 'px-3 py-2 text-left';
        const textoDescricao = document.createElement('div');
        textoDescricao.className = 'max-w-[16rem] truncate';
        textoDescricao.textContent = item.descricao;
        if (somenteLeitura) textoDescricao.title = item.descricao;
        tdDescricao.appendChild(textoDescricao);
        tr.appendChild(tdDescricao);

        tr.appendChild(celulaCfop(fiscal));
        tr.appendChild(celulaCst(fiscal, item));
        
        adicionarCelulas([
            [Number(item.quantidade).toLocaleString('pt-BR', { maximumFractionDigits: 3 }), 'text-right'],
            [String(porCaixaDe(item)), 'text-right'],
            [fmtQtd(item.quantidade * porCaixaDe(item)), 'text-right'],
            [moeda(item.valor_unitario), 'text-right'],
            [moeda(total), 'text-right font-medium'],
            [moeda(item.valor_desconto), 'text-right'],
            [pct(descontoPct), 'text-right'],
            [moeda(item.valor_outras_despesas), 'text-right'],
            [moeda(item.valor_frete), 'text-right'],
            [moeda(item.bc_icms), 'text-right'],
            [moeda(item.valor_icms), 'text-right'],
            [pct(item.aliquota_icms), 'text-right'],
            [moeda(item.valor_ipi), 'text-right'],
            [pct(item.aliquota_ipi), 'text-right'],
            [item.lote || '—', 'text-left'],
            [dataBr(item.validade), 'text-left'],
        ]);

        // Nota em edição: a linha inteira abre o modal do item
        if (!somenteLeitura) {
            tr.classList.add('cursor-pointer');
            tr.onclick = () => editarLinha(i);
        }

        corpo.appendChild(tr);

        // Campos enviados ao servidor
        const camposFiscaisManuais = ['cfop_entrada', 'cst_entrada', 'gera_credito'];

        ['produto_id', 'codigo', 'codigo_barras', 'descricao', 'codigo_fornecedor', 'quantidade','qtd_por_caixa', 'valor_unitario', 'valor_desconto',
        'valor_outras_despesas', 'valor_frete', 'bc_icms', 'valor_icms', 'aliquota_icms', 'valor_ipi', 'aliquota_ipi', 'lote', 'validade',
        'cfop_origem', 'cst_origem', 'csosn_origem', 'origem_mercadoria', 'fiscal_manual', ...camposFiscaisManuais]
            .forEach(campo => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `itens[${i}][${campo}]`;
                input.value = (camposFiscaisManuais.includes(campo) && !flag(item.fiscal_manual)) ? '' : (item[campo] ?? '');
                campos.appendChild(input);
            });
    });

    document.getElementById('grid-vazia').classList.toggle('hidden', itensNota.length > 0);
    atualizarTotais();
}


function atualizarTotais() {
    const soma = (fn) => itensNota.reduce((acc, item) => acc + (Number(fn(item)) || 0), 0);

    const produtos = soma(totalItem);
    const bruto    = soma(item => item.quantidade * item.valor_unitario);
    const desconto = soma(item => item.valor_desconto);
    const outras   = soma(item => item.valor_outras_despesas);
    const frete    = soma(item => item.valor_frete);
    const bcIcms   = soma(item => item.bc_icms);
    const vlIcms   = soma(item => item.valor_icms);
    const vlIpi    = soma(item => item.valor_ipi);
    const baseIpi  = bruto + outras + frete;

    const texto = (id, valor) => { document.getElementById(id).textContent = valor; };

    // Totais abaixo de cada coluna (Por cx e Vl Unit não são somáveis e ficam em branco)
    texto('tot-itens', itensNota.length);
    texto('tot-qtd', fmtQtd(soma(item => item.quantidade)));
    texto('tot-estoque', fmtQtd(soma(item => item.quantidade * porCaixaDe(item))));
    texto('tot-total', moeda(produtos));
    texto('tot-desconto', moeda(desconto));
    texto('tot-desconto-pct', pct(bruto > 0 ? (desconto / bruto) * 100 : 0));
    texto('tot-outras', moeda(outras));
    texto('tot-frete', moeda(frete));
    texto('tot-bc-icms', moeda(bcIcms));
    texto('tot-vl-icms', moeda(vlIcms));
    texto('tot-aliq-icms', pct(bcIcms > 0 ? (vlIcms / bcIcms) * 100 : 0));
    texto('tot-vl-ipi', moeda(vlIpi));
    texto('tot-aliq-ipi', pct(baseIpi > 0 ? (vlIpi / baseIpi) * 100 : 0));

    // total da nota: itens + outras despesas/frete dos itens + campos do rodapé − desconto geral
    const total = produtos + outras + frete + numero('valor_frete') + numero('valor_outras') - numero('valor_desconto');
    texto('total-nota', moeda(total));
}

['valor_frete', 'valor_outras', 'valor_desconto'].forEach(id =>
    document.getElementById(id).addEventListener('input', () => atualizarTotais()));

// ---------------------------------------------------------------
// Editor do item
// ---------------------------------------------------------------
const fmtQtd = v => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
const porCaixaDe = item => Math.max(parseInt(item.qtd_por_caixa, 10) || 1, 1);

// origemDesconto: 'valor' (editou o Desconto R$) ou 'percentual' (editou o Desconto %)
function atualizarTotalEditor(origemDesconto = 'valor') {
    const subtotal = numero('ed-qtd') * numero('ed-unit');
    let desconto = numero('ed-desc');

    if (origemDesconto === 'percentual') {
        desconto = arred2(subtotal * numero('ed-desc-pct') / 100);
        document.getElementById('ed-desc').value = desconto.toFixed(2);
    } else {
        document.getElementById('ed-desc-pct').value = (subtotal > 0 ? (desconto / subtotal) * 100 : 0).toFixed(2);
    }

    const total = arred2(subtotal - desconto);
    document.getElementById('ed-total').value = moeda(total);

    const porCaixa = Math.max(parseInt(document.getElementById('ed-porcaixa').value, 10) || 1, 1);
    const unidades = numero('ed-qtd') * porCaixa;
    const dica = document.getElementById('ed-estoque-dica');

    if (porCaixa > 1 && unidades > 0) {
        dica.textContent = `Entram no estoque: ${fmtQtd(unidades)} UN (${fmtQtd(numero('ed-qtd'))} × ${porCaixa}) · custo por UN: ${moeda(total / unidades)}`;
        dica.classList.remove('hidden');
    } else {
        dica.classList.add('hidden');
    }
}

// Digitação nos campos de valor: recalcula o total e, se houver % de IPI, o Vlr. IPI.
// (só ao digitar — abrir um item existente não sobrescreve valores ajustados manualmente)
function aoDigitarValores(origemDesconto = 'valor') {
    atualizarTotalEditor(origemDesconto);
    if (numero('ed-aliq-ipi') > 0) atualizarIpiEditor();
}

function atualizarIcmsEditor() {
    document.getElementById('ed-vl-icms').value = arred2(numero('ed-bc-icms') * numero('ed-aliq-icms') / 100).toFixed(2);
}

function atualizarIpiEditor() {
    const base = (numero('ed-qtd') * numero('ed-unit')) + numero('ed-outras') + numero('ed-frete');
    document.getElementById('ed-vl-ipi').value = arred2(base * numero('ed-aliq-ipi') / 100).toFixed(2);
}

function editorTab(nome) {
    ['dados', 'impostos'].forEach(n => {
        const ativo = n === nome;
        const btn = document.getElementById('ed-tab-btn-' + n);

        document.getElementById('ed-painel-' + n).classList.toggle('hidden', !ativo);
        btn.classList.toggle('border-blue-600', ativo);
        btn.classList.toggle('text-blue-600', ativo);
        btn.classList.toggle('border-transparent', !ativo);
        btn.classList.toggle('text-gray-500', !ativo);
        btn.classList.toggle('hover:text-gray-700', !ativo);
    });
}

function abrirEditor(produto, dados = {}) {
    produtoEditor = produto;

    const set = (id, valor) => { document.getElementById(id).value = valor; };
    const editando = indiceEdicao !== null;

    document.getElementById('editor-modal-titulo').textContent = editando ? 'Editar item da nota' : 'Adicionar item à nota';
    document.getElementById('btn-salvar-item').textContent = editando ? 'Salvar alterações' : 'Adicionar à nota';
    document.getElementById('btn-remover-item').classList.toggle('hidden', !editando);
    document.getElementById('editor-produto-nome').textContent = produto.nome;
    set('ed-codigo', produto.codigo_interno ?? '');
    set('ed-codbarras', produto.codigo_barras ?? '');
    set('ed-descricao', dados.descricao ?? produto.nome);
    set('ed-qtd', dados.quantidade ?? 1);
    set('ed-porcaixa', dados.qtd_por_caixa ?? 1);
    set('ed-unit', dados.valor_unitario ?? (produto.preco_custo ?? 0));
    set('ed-desc', dados.valor_desconto ?? 0);
    set('ed-outras', dados.valor_outras_despesas ?? 0);
    set('ed-frete', dados.valor_frete ?? 0);
    set('ed-codforn', dados.codigo_fornecedor ?? '');
    set('ed-lote', dados.lote ?? '');
    set('ed-validade', dados.validade ?? '');
    set('ed-bc-icms', dados.bc_icms ?? 0);
    set('ed-vl-icms', dados.valor_icms ?? 0);
    set('ed-aliq-icms', dados.aliquota_icms ?? 0);
    set('ed-vl-ipi', dados.valor_ipi ?? 0);
    set('ed-aliq-ipi', dados.aliquota_ipi ?? 0);
    preencherEditorFiscal(dados);
    atualizarTotalEditor();

    const modal = document.getElementById('modal-editor-item');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    editorTab('dados');

    document.getElementById('ed-qtd').focus();
    document.getElementById('ed-qtd').select();
}

function cancelarEditor() {
    produtoEditor = null;
    fiscalPreservado = null;
    fiscalEditor = null;
    indiceEdicao = null;

    const modal = document.getElementById('modal-editor-item');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function removerItemEmEdicao() {
    if (indiceEdicao === null) return;
    if (!confirm('Remover este item da nota?')) return;

    itensNota.splice(indiceEdicao, 1);
    cancelarEditor();
    renderizarGrid();
    document.getElementById('input-busca-item').focus();
}

function adicionarLinhaNaGrid() {
    if (!produtoEditor) return;

    // volta para a aba do campo com problema antes de avisar
    const erro = (aba, mensagem) => { editorTab(aba); mostrarAviso(mensagem); };

    const quantidade = numero('ed-qtd');
    const valorUnitario = numero('ed-unit');
    const desconto = numero('ed-desc');
    const outras = numero('ed-outras');
    const frete = numero('ed-frete');
    const porCaixa = parseInt(document.getElementById('ed-porcaixa').value, 10);

    if (quantidade <= 0) return erro('dados', 'Informe uma quantidade maior que zero.');
    if (valorUnitario < 0) return erro('dados', 'O valor unitário não pode ser negativo.');
    if (desconto > quantidade * valorUnitario) return erro('dados', 'O desconto não pode ser maior que o valor do item.');
    if (outras < 0 || frete < 0) return erro('dados', 'Outras despesas e frete não podem ser negativos.');
    if (!(porCaixa >= 1)) return erro('dados', 'Informe quantas unidades vêm por caixa (mínimo 1).');
    if (['ed-bc-icms', 'ed-vl-icms', 'ed-vl-ipi'].some(id => numero(id) < 0)) return erro('impostos', 'Os valores de ICMS e IPI não podem ser negativos.');
    if (['ed-aliq-icms', 'ed-aliq-ipi'].some(id => numero(id) < 0 || numero(id) > 100)) return erro('impostos', 'As alíquotas devem estar entre 0 e 100.');

    const fiscal = lerEditorFiscal();

    const item = {
        produto_id: produtoEditor.id,
        codigo: produtoEditor.codigo_interno ?? '',
        codigo_barras: produtoEditor.codigo_barras ?? '',
        descricao: document.getElementById('ed-descricao').value.trim() || produtoEditor.nome,
        codigo_fornecedor: document.getElementById('ed-codforn').value.trim(),
        quantidade,
        qtd_por_caixa: porCaixa,
        valor_unitario: valorUnitario,
        valor_desconto: desconto,
        valor_outras_despesas: outras,
        valor_frete: frete,
        bc_icms: numero('ed-bc-icms'),
        valor_icms: numero('ed-vl-icms'),
        aliquota_icms: numero('ed-aliq-icms'),
        valor_ipi: numero('ed-vl-ipi'),
        aliquota_ipi: numero('ed-aliq-ipi'),
        lote: document.getElementById('ed-lote').value.trim(),
        validade: document.getElementById('ed-validade').value,
        ...(fiscalPreservado ?? {}),
        fiscal_manual: fiscal.manual ? '1' : '0',
        cfop_entrada: fiscal.manual ? fiscal.cfop : '',
        cst_entrada: fiscal.manual ? fiscal.cst : '',
        gera_credito: fiscal.manual ? fiscal.credito : '',
    };

    const itemNovo = indiceEdicao === null;
    if (itemNovo) itensNota.push(item);
    else itensNota[indiceEdicao] = item;

    cancelarEditor();
    renderizarGrid();

    // item novo: leva a rolagem até ele
    if (itemNovo) {
        const area = document.getElementById('area-itens');
        area.scrollTop = area.scrollHeight;
    }
    document.getElementById('input-busca-item').focus();
}

function editarLinha(i) {
    const item = itensNota[i];
    fiscalPreservado = {
        cfop_origem: item.cfop_origem, cst_origem: item.cst_origem,
        csosn_origem: item.csosn_origem, origem_mercadoria: item.origem_mercadoria,
    };
    indiceEdicao = i;

    abrirEditor({
        id: item.produto_id,
        nome: item.descricao,
        codigo_interno: item.codigo,
        codigo_barras: item.codigo_barras,
    }, item);
}

// ---------------------------------------------------------------
// Modal de busca de produto
// ---------------------------------------------------------------
function abrirModalProdutoComTexto(input) {
    const texto = input.value;
    input.value = '';

    abrirModalProduto();

    const busca = document.getElementById('busca-produto-modal');
    busca.value = texto;
    buscarProdutos();
}

function abrirModalProduto() {
    const modal = document.getElementById('modal-busca-produto');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.getElementById('busca-produto-modal').focus();
}

function fecharModalProduto() {
    const modal = document.getElementById('modal-busca-produto');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.getElementById('busca-produto-modal').value = '';
    document.getElementById('linhas-busca-produto').innerHTML = '';
    resultadosBusca = [];
}

function renderizarResultados() {
    const corpo = document.getElementById('linhas-busca-produto');
    corpo.innerHTML = '';

    if (!resultadosBusca.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.className = 'py-6 text-center text-gray-400';
        td.textContent = 'Nenhum produto encontrado.';
        tr.appendChild(td);
        corpo.appendChild(tr);
        return;
    }

    resultadosBusca.forEach((p, i) => {
        const tr = document.createElement('tr');
        tr.className = 'cursor-pointer border-b border-gray-100 hover:bg-gray-50 ' + (i === indiceBusca ? 'bg-amber-50' : '');

        [p.codigo_interno ?? '—', p.nome, moeda(p.preco_custo), p.estoque ?? 0].forEach(valor => {
            const td = document.createElement('td');
            td.className = 'py-2 pr-3';
            td.textContent = valor;
            tr.appendChild(td);
        });

        tr.onclick = () => selecionarProduto(p);
        corpo.appendChild(tr);
    });
}

function buscarProdutos() {
    clearTimeout(timerBusca);
    const q = document.getElementById('busca-produto-modal').value.trim();

    if (q.length < 2) {
        resultadosBusca = [];
        document.getElementById('linhas-busca-produto').innerHTML = '';
        return;
    }

    timerBusca = setTimeout(async () => {
        try {
            const resp = await fetch(`${urlBuscaProdutos}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' },
            });
            resultadosBusca = await resp.json();
            indiceBusca = 0;
            renderizarResultados();
        } catch (e) {
            mostrarAviso('Falha ao buscar produtos:\n' + e.message, 'erro');
        }
    }, 250);
}

function selecionarProduto(produto) {
    fecharModalProduto();
    indiceEdicao = null;
    abrirEditor(produto);
}

if (!somenteLeitura) {
    const buscaModal = document.getElementById('busca-produto-modal');
    buscaModal.addEventListener('input', buscarProdutos);

    buscaModal.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' && resultadosBusca.length) {
            e.preventDefault();
            indiceBusca = Math.min(indiceBusca + 1, resultadosBusca.length - 1);
            renderizarResultados();
        } else if (e.key === 'ArrowUp' && resultadosBusca.length) {
            e.preventDefault();
            indiceBusca = Math.max(indiceBusca - 1, 0);
            renderizarResultados();
        } else if (e.key === 'Enter') {
            e.preventDefault(); // leitor de código de barras envia Enter
            if (resultadosBusca.length) selecionarProduto(resultadosBusca[indiceBusca]);
        } else if (e.key === 'Escape') {
            fecharModalProduto();
        }
    });

    // Enter nos campos do modal salva o item; Esc fecha
    document.getElementById('modal-editor-item').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
            e.preventDefault();
            adicionarLinhaNaGrid();
        } else if (e.key === 'Escape') {
            cancelarEditor();
        }
    });
}

// ---------------------------------------------------------------
// Cadastro rápido de fornecedor
// ---------------------------------------------------------------
function abrirModalFornecedor() {
    ['forn-doc', 'forn-razao', 'forn-fantasia', 'forn-ie'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('forn-erro').classList.add('hidden');

    const modal = document.getElementById('modal-fornecedor');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('forn-doc').focus();
}

function fecharModalFornecedor() {
    const modal = document.getElementById('modal-fornecedor');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function salvarFornecedor() {
    const erro = document.getElementById('forn-erro');
    erro.classList.add('hidden');

    try {
        const resp = await fetch(urlFornecedorRapido, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                cnpj_cpf: document.getElementById('forn-doc').value,
                razao_social: document.getElementById('forn-razao').value,
                nome_fantasia: document.getElementById('forn-fantasia').value,
                ie: document.getElementById('forn-ie').value,
            }),
        });

        const json = await resp.json();

        if (!resp.ok) {
            erro.textContent = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message ?? 'Não foi possível cadastrar.');
            erro.classList.remove('hidden');
            return;
        }

        const select = document.getElementById('fornecedor_id');
        const opcao = new Option(json.nome, json.id, true, true);
        opcao.dataset.uf = json.uf ?? '';
        select.add(opcao);
        select.value = json.id;

        fecharModalFornecedor();
        mostrarAviso('Fornecedor cadastrado.', 'sucesso');
    } catch (e) {
        erro.textContent = 'Falha ao cadastrar: ' + e.message;
        erro.classList.remove('hidden');
    }
}

// ---------------------------------------------------------------
// Inicialização
// ---------------------------------------------------------------
itensNota = itensIniciais.map(i => ({
    produto_id: i.produto_id,
    codigo: i.codigo ?? '',
    codigo_barras: i.codigo_barras ?? '',
    descricao: i.descricao,
    codigo_fornecedor: i.codigo_fornecedor ?? '',
    quantidade: parseFloat(i.quantidade) || 0,
    qtd_por_caixa: parseInt(i.qtd_por_caixa, 10) || 1,
    valor_unitario: parseFloat(i.valor_unitario) || 0,
    valor_desconto: parseFloat(i.valor_desconto) || 0,
    valor_outras_despesas: parseFloat(i.valor_outras_despesas) || 0,
    valor_frete: parseFloat(i.valor_frete) || 0,
    bc_icms: parseFloat(i.bc_icms) || 0,
    valor_icms: parseFloat(i.valor_icms) || 0,
    aliquota_icms: parseFloat(i.aliquota_icms) || 0,
    valor_ipi: parseFloat(i.valor_ipi) || 0,
    aliquota_ipi: parseFloat(i.aliquota_ipi) || 0,
    lote: i.lote ?? '',
    validade: i.validade ?? '',
    cfop_origem: i.cfop_origem ?? '',
    cst_origem: i.cst_origem ?? '',
    csosn_origem: i.csosn_origem ?? '',
    origem_mercadoria: i.origem_mercadoria ?? '',
    fiscal_manual: flag(i.fiscal_manual) ? '1' : '0',
    cfop_entrada: i.cfop_entrada ?? '',
    cst_entrada: i.cst_entrada ?? '',
    gera_credito: creditoDe(i.gera_credito) === null ? '' : (creditoDe(i.gera_credito) ? '1' : '0'),
}));
document.getElementById('fornecedor_id').addEventListener('change', renderizarGrid);
if (!somenteLeitura) popularOpcoesFiscais();
renderizarGrid();
</script>