@php $produto = $produto ?? null; @endphp

@php
    $ncmSelecionado = old('ncm_id')
        ? \App\Models\Ncm::find(old('ncm_id'))
        : $produto?->ncm;

    $tributacaoSelecionada = old('tributacao_id')
        ? \App\Models\Tributacao::find(old('tributacao_id'))
        : $produto?->tributacao;

    $cestSelecionado = old('cest_id')
        ? \App\Models\Cest::find(old('cest_id'))
        : $produto?->cest;

    $classTribSelecionado = old('class_trib_ibs_cbs_id')
        ? \App\Models\ClassificacaoTributaria::find(old('class_trib_ibs_cbs_id'))
        : $produto?->classificacaoTributaria;

    $pisCofinsSelecionado = old('pis_cofins_id')
        ? \App\Models\ClassificacaoPisCofins::find(old('pis_cofins_id'))
        : $produto?->pisCofins;

    $ipiSelecionado = old('ipi_id')
        ? \App\Models\ClassificacaoIpi::find(old('ipi_id'))
        : $produto?->ipi;

    // CRT 3 = Regime Normal (Lucro Presumido/Real) - único regime em que
    // PIS/COFINS por produto é obrigatório de verdade. Ajuste o acesso
    // abaixo se você já carrega a empresa de outra forma no seu app.
    $pisCofinsObrigatorio = \App\Models\Empresa::atual()->crt == 3;
@endphp

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Modal de aviso de validação --}}
<div id="modal-aviso-validacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <div class="flex items-start gap-3 mb-4">
            <div class="text-yellow-500 text-2xl leading-none mt-0.5">⚠️</div>
            <div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Campo obrigatório</h3>
                <p id="modal-aviso-mensagem" class="text-sm text-gray-600"></p>
            </div>
        </div>
        <div class="flex justify-end">
            <button id="modal-aviso-ok"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                OK
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">

    {{-- Tabs --}}
    <div class="flex border-b border-gray-200">
        <button type="button" onclick="trocarTab('geral')" id="tab-btn-geral"
                class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-blue-600 text-blue-600 transition">
            Dados Gerais
        </button>
        <button type="button" onclick="trocarTab('fiscal')" id="tab-btn-fiscal"
                class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
            Dados Fiscais
        </button>
        <button type="button" onclick="trocarTab('preco')" id="tab-btn-preco"
                class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
            Preço e Estoque
        </button>
        <button type="button" onclick="trocarTab('atacado')" id="tab-btn-atacado"
                class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
            Atacado
        </button>
    </div>

    {{-- Tab: Dados Gerais --}}
    <div id="tab-geral" class="tab-painel p-6 grid grid-cols-2 gap-5">

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $produto->nome ?? '') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código de barras (EAN)</label>
            <input type="text" name="codigo_barras" value="{{ old('codigo_barras', $produto->codigo_barras ?? '') }}"
                   placeholder="Deixe em branco se não tiver"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">
                Código interno *
                <span class="text-xs text-gray-400 font-normal">(gerado automaticamente)</span>
            </label>
            <input type="text" name="codigo_interno"
                value="{{ old('codigo_interno', $produto->codigo_interno ?? $proximoCodigo ?? '') }}"
                readonly
                class="w-full border rounded px-3 py-2 bg-gray-50 text-gray-500 cursor-not-allowed">
        </div>

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
            <textarea name="descricao" rows="3"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition resize-none">{{ old('descricao', $produto->descricao ?? '') }}</textarea>
        </div>

        <input type="hidden" name="categoria_id" id="categoria_id" value="{{ old('categoria_id', $produto->categoria_id ?? '') }}">
        <input type="hidden" name="marca_id"     id="marca_id"     value="{{ old('marca_id',     $produto->marca_id     ?? '') }}">
        <input type="hidden" name="grupo_id"     id="grupo_id"     value="{{ old('grupo_id',     $produto->grupo_id     ?? '') }}">
        <input type="hidden" name="ncm_id"       id="ncm_id"       value="{{ old('ncm_id',       $produto->ncm_id       ?? '') }}">
        <input type="hidden" name="cest_id"      id="cest_id"      value="{{ old('cest_id',      $produto->cest_id      ?? '') }}">
        <input type="hidden" name="class_trib_ibs_cbs_id" id="class_trib_ibs_cbs_id" value="{{ old('class_trib_ibs_cbs_id', $produto->class_trib_ibs_cbs_id ?? '') }}">
        <input type="hidden" name="pis_cofins_id" id="pis_cofins_id" value="{{ old('pis_cofins_id', $produto->pis_cofins_id ?? '') }}">
        <input type="hidden" name="ipi_id" id="ipi_id" value="{{ old('ipi_id', $produto->ipi_id ?? '') }}">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
            <button type="button" onclick="abrirModalCatalogo('categoria')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="categoria_label" class="text-gray-600">{{ old('categoria_id') ? '' : ($produto?->categoria->nome ?? 'Clique para selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
            <button type="button" onclick="abrirModalCatalogo('marca')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="marca_label" class="text-gray-600">{{ old('marca_id') ? '' : ($produto?->marca->nome ?? 'Clique para selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
            <button type="button" onclick="abrirModalCatalogo('grupo')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="grupo_label" class="text-gray-600">{{ old('grupo_id') ? '' : ($produto?->grupo->nome ?? 'Clique para selecionar...') }}</span>
            </button>
        </div>

        <div class="flex flex-col justify-center">
            <label class="block text-sm font-medium text-gray-700 mb-1">Produto de balança</label>
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="produto_balanca" value="0">
                    <input type="checkbox" id="produto_balanca" name="produto_balanca" value="1"
                           {{ old('produto_balanca', $produto->produto_balanca ?? false) ? 'checked' : '' }}
                           class="sr-only peer"
                           onchange="validarProdutoBalanca(this)">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-sm text-gray-600">Pesado em KG</span>
                <span id="aviso-balanca" class="text-xs text-orange-500 hidden">
                    ⚠️ Unidade comercial deve ser KG
                </span>
            </div>
        </div>
    </div>

    {{-- Tab: Dados Fiscais --}}
    <div id="tab-fiscal" class="tab-painel p-6 grid grid-cols-2 gap-5 hidden">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">NCM <span class="text-red-500">*</span></label>
            <button type="button" onclick="abrirModalNcm()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="ncm_label" class="text-gray-600">
                    {{ $ncmSelecionado ? $ncmSelecionado->codigo . ' — ' . $ncmSelecionado->descricao : 'Clique para selecionar...' }}
                </span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CEST</label>
            <button type="button" onclick="abrirModalCest()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="cest_label" class="text-gray-600">
                    {{ $cestSelecionado ? $cestSelecionado->codigo . ' — ' . $cestSelecionado->descricao : 'Clique para selecionar (opcional)...' }}
                </span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                PIS/COFINS
                @if ($pisCofinsObrigatorio)
                    <span class="text-red-500">*</span>
                @else
                    <span class="text-xs text-gray-400 font-normal">(obrigatório apenas no Lucro Presumido/Real)</span>
                @endif
            </label>
            <button type="button" onclick="abrirModalPisCofins()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="pis_cofins_label" class="text-gray-600">
                    {{ $pisCofinsSelecionado ? 'CST ' . $pisCofinsSelecionado->codigo . ' — ' . $pisCofinsSelecionado->descricao : 'Clique para selecionar...' }}
                </span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                IPI
                <span class="text-xs text-gray-400 font-normal">(opcional — só pra estabelecimento industrial/equiparado)</span>
            </label>
            <button type="button" onclick="abrirModalIpi()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="ipi_label" class="text-gray-600">
                    {{ $ipiSelecionado ? 'CST ' . $ipiSelecionado->codigo . ' — ' . $ipiSelecionado->descricao : 'Clique para selecionar (opcional)...' }}
                </span>
            </button>
        </div>

<div class="col-span-2">
    <label class="block text-sm font-medium mb-1">Classificação Tributária *</label>
    <button type="button" onclick="abrirModalTributacao()"
            class="w-full border rounded px-3 py-2 text-left bg-white hover:bg-gray-50 text-sm">
        <span id="tributacao_label">
            {{ $tributacaoSelecionada ? $tributacaoSelecionada->labelCompleto() : 'Clique para selecionar...' }}
        </span>
    </button>
</div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Unidade comercial <span class="text-red-500">*</span></label>
            <input type="text" name="unidade_comercial" maxlength="6" value="{{ old('unidade_comercial', $produto->unidade_comercial ?? 'UN') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Unidade tributável <span class="text-red-500">*</span></label>
            <input type="text" name="unidade_tributavel" maxlength="6" value="{{ old('unidade_tributavel', $produto->unidade_tributavel ?? 'UN') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Origem da mercadoria <span class="text-red-500">*</span></label>
            <select name="origem_mercadoria"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                @foreach ([0 => '0 - Nacional', 1 => '1 - Importado (importação direta)', 2 => '2 - Importado (mercado interno)'] as $valor => $texto)
                    <option value="{{ $valor }}" {{ old('origem_mercadoria', $produto->origem_mercadoria ?? 0) == $valor ? 'selected' : '' }}>
                        {{ $texto }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Classificação IBS/CBS</label>
            <button type="button" onclick="abrirModalClassTrib()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="class_trib_ibs_cbs_label" class="text-gray-600">
                    {{ $classTribSelecionado ? $classTribSelecionado->codigo . ' — ' . $classTribSelecionado->descricao : 'Clique para selecionar (opcional)...' }}
                </span>
            </button>
        </div>
    </div>

    {{-- Tab: Preço e Estoque --}}
    <div id="tab-preco" class="tab-painel p-6 grid grid-cols-2 gap-5 hidden">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Preço de venda <span class="text-red-500">*</span></label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                <input type="number" step="0.01" name="preco_venda" value="{{ old('preco_venda', $produto->preco_venda ?? '') }}" required
                       class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Preço de custo</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                <input type="number" step="0.01" name="preco_custo" value="{{ old('preco_custo', $produto->preco_custo ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div id="bloco-estoque-simples" class="col-span-2 grid grid-cols-2 gap-5 {{ old('tem_variacao', $produto->tem_variacao ?? false) ? 'hidden' : '' }}">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estoque</label>
                <input type="number" name="estoque" value="{{ old('estoque', $produto->estoque ?? 0) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estoque mínimo</label>
                <input type="number" name="estoque_minimo" value="{{ old('estoque_minimo', $produto->estoque_minimo ?? 0) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div class="col-span-2 flex items-center gap-3 py-1">
            <input type="hidden" name="tem_variacao" value="0">
            <input type="checkbox" id="tem_variacao" name="tem_variacao" value="1"
                   {{ old('tem_variacao', $produto->tem_variacao ?? false) ? 'checked' : '' }}
                   onchange="toggleVariacao(this.checked)"
                   class="w-4 h-4 text-blue-600 rounded">
            <label for="tem_variacao" class="text-sm font-medium text-gray-700">Produto tem variação (cor/tamanho)</label>
        </div>

        <div id="bloco-variantes" class="col-span-2 {{ old('tem_variacao', $produto->tem_variacao ?? false) ? '' : 'hidden' }}">
            <h3 class="font-semibold text-gray-700 mb-3">Variações</h3>
            <div class="border border-gray-200 rounded-lg overflow-hidden mb-3">
                <table class="w-full text-sm" id="tabela-variantes">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Cor</th>
                            <th class="px-3 py-2 text-left">Tamanho</th>
                            <th class="px-3 py-2 text-left">SKU</th>
                            <th class="px-3 py-2 text-left">Estoque</th>
                            <th class="px-3 py-2 text-left">Est. mín.</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="linhas-variantes" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <button type="button" onclick="adicionarLinhaVariante()"
                    class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                + Adicionar variação
            </button>
        </div>
    </div>
</div>


<div id="tab-atacado" class="tab-painel p-6 hidden">
 
    <div class="flex items-center gap-3 py-1 mb-5">
        <input type="hidden" name="tem_preco_atacado" value="0">
        <input type="checkbox" id="tem_preco_atacado" name="tem_preco_atacado" value="1"
               {{ old('tem_preco_atacado', $produto->preco_atacado ?? false) ? 'checked' : '' }}
               onchange="toggleAtacado(this.checked)"
               class="w-4 h-4 text-blue-600 rounded">
        <label for="tem_preco_atacado" class="text-sm font-medium text-gray-700">Este produto tem preço de atacado</label>
    </div>
 
    <div id="bloco-atacado" class="grid grid-cols-2 gap-5 {{ old('tem_preco_atacado', $produto->preco_atacado ?? false) ? '' : 'hidden' }}">
 
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Valor original</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                <input type="text" readonly
                       value="{{ number_format((float) old('preco_venda', $produto->preco_venda ?? 0), 2, ',', '.') }}"
                       class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
            </div>
            <p class="text-xs text-gray-400 mt-1">Vem do preço de venda cadastrado em <em>Preço e Estoque</em>.</p>
        </div>
 
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Valor de atacado</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                <input type="number" step="0.01" name="preco_atacado"
                       value="{{ old('preco_atacado', $produto->preco_atacado ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
        </div>
 
        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                A partir de
                <span id="unidade-atacado-label" class="text-gray-400 font-normal">({{ $produto->unidade_comercial ?? 'UN' }})</span>
            </label>
            <input type="number" step="0.001" name="quantidade_minima_atacado"
                   value="{{ old('quantidade_minima_atacado', $produto->quantidade_minima_atacado ?? '') }}"
                   placeholder="Ex: 10"
                   class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>
 
        <div class="col-span-2 border-t pt-4 mt-1 flex items-center gap-3">
            <input type="hidden" name="atacado_tem_prazo" value="0">
            <input type="checkbox" id="atacado_tem_prazo" name="atacado_tem_prazo" value="1"
                   {{ old('atacado_tem_prazo', $produto->atacado_tem_prazo ?? false) ? 'checked' : '' }}
                   onchange="toggleAtacadoPrazo(this.checked)"
                   class="w-4 h-4 text-blue-600 rounded">
            <label for="atacado_tem_prazo" class="text-sm font-medium text-gray-700">Ativar prazo do atacado</label>
        </div>
        <p class="col-span-2 text-xs text-gray-400 -mt-3">
            Desativado, o valor de atacado fica permanente até você removê-lo aqui e salvar de novo.
        </p>
 
        <div id="bloco-atacado-prazo" class="col-span-2 grid grid-cols-2 gap-5 {{ old('atacado_tem_prazo', $produto->atacado_tem_prazo ?? false) ? '' : 'hidden' }}">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data inicial</label>
                <input type="date" name="atacado_data_inicio"
                       value="{{ old('atacado_data_inicio', optional($produto?->atacado_data_inicio)->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data final</label>
                <input type="date" name="atacado_data_fim"
                       value="{{ old('atacado_data_fim', optional($produto?->atacado_data_fim)->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
        </div>
    </div>
</div>

{{-- Botões de ação --}}
<div class="mt-6 flex gap-3">
    <button type="button" onclick="salvarProduto()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar produto
    </button>
    <a href="{{ route('produtos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>

<script>
let indiceVariante = 0;
let codigoBarrasDuplicado = false;
 
document.addEventListener('DOMContentLoaded', () => {
    const campoBarras = document.querySelector('input[name="codigo_barras"]');
    const campoInterno = document.querySelector('input[name="codigo_interno"]');
 
    if (!campoBarras) return;
 
    campoBarras.addEventListener('blur', async function () {
        const valor = this.value.trim();
 
        // Mesma regra do backend: vazio, ou só dígitos com menos de 8 chars
        // (menor que o menor EAN real, o EAN-8) = "código de controle" -> preenche
        // com zeros à esquerda até 13 dígitos, usando o código interno como base
        // se o campo estiver vazio.
        const ehCodigoDeControle = valor === '' || (/^\d+$/.test(valor) && valor.length < 8);
 
        if (ehCodigoDeControle) {
            const base = valor !== ''
                ? valor
                : (campoInterno?.value || '').replace(/\D/g, '');
            this.value = base.padStart(13, '0');
        }
 
        await verificarCodigoBarrasDuplicado(this.value);
    });
});
 
async function verificarCodigoBarrasDuplicado(valor) {
    if (!valor) {
        codigoBarrasDuplicado = false;
        return;
    }
 
    const idAtual = {{ $produto->id ?? 'null' }};
    const url = `{{ route('produtos.verificarCodigoBarras') }}?codigo=${encodeURIComponent(valor)}` +
                (idAtual ? `&excluir=${idAtual}` : '');
 
    try {
        const resp = await fetch(url);
        const data = await resp.json();
        codigoBarrasDuplicado = data.duplicado;
 
        if (codigoBarrasDuplicado) {
            const campoBarras = document.querySelector('input[name="codigo_barras"]');
            campoBarras.value = '';
 
            abrirModalAviso(
                'Já existe um produto cadastrado com esse <strong>código de barras</strong>. ' +
                'O campo foi limpo — informe outro código ou deixe em branco.',
                'geral'
            );
 
            // Depois de fechar o aviso, foca de novo no campo pra já digitar o certo
            document.getElementById('modal-aviso-ok').addEventListener('click', () => {
                campoBarras.focus();
            }, { once: true });
 
            codigoBarrasDuplicado = false; // campo já foi limpo, não há mais duplicidade pendente
        }
    } catch (e) {
        // Falha de rede na checagem - nao bloqueia, mas tambem nao marca como duplicado.
        // A validacao "unique" do backend ainda protege na hora de salvar de verdade.
        codigoBarrasDuplicado = false;
    }
}

// ===================== ENTER = PRÓXIMO CAMPO (não salva) =====================

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;

        const alvo = e.target;

        // Textarea: deixa quebrar linha normalmente
        if (alvo.tagName === 'TEXTAREA') return;

        // Botões (Salvar produto, abrir modais, remover variante, etc.):
        // deixa agir normalmente — inclusive o próprio Salvar produto
        if (alvo.tagName === 'BUTTON') return;

        e.preventDefault();

        const focaveis = Array.from(
            form.querySelectorAll('input:not([type=hidden]), select, textarea, button')
        ).filter((el) => !el.disabled && !el.readOnly && el.offsetParent !== null);

        const indiceAtual = focaveis.indexOf(alvo);
        if (indiceAtual > -1 && indiceAtual < focaveis.length - 1) {
            focaveis[indiceAtual + 1].focus();
        }
    });
});


// ===================== TABS =====================

function trocarTab(tab) {
    document.querySelectorAll('.tab-painel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-blue-600', 'text-blue-600');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('tab-' + tab).classList.remove('hidden');
    const btn = document.getElementById('tab-btn-' + tab);
    btn.classList.add('border-blue-600', 'text-blue-600');
    btn.classList.remove('border-transparent', 'text-gray-500');
}

// ===================== MODAL DE AVISO =====================

function abrirModalAviso(mensagem, tab) {
    document.getElementById('modal-aviso-mensagem').innerHTML = mensagem;
    const modal = document.getElementById('modal-aviso-validacao');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (tab) trocarTab(tab);
    setTimeout(() => document.getElementById('modal-aviso-ok').focus(), 50);
}

function fecharModalAviso() {
    const modal = document.getElementById('modal-aviso-validacao');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('modal-aviso-ok').addEventListener('click', fecharModalAviso);

document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('modal-aviso-validacao');
    if (e.key === 'Enter' && !modal.classList.contains('hidden')) {
        e.preventDefault();
        fecharModalAviso();
    }
});

// ===================== VALIDAÇÃO E SUBMIT =====================

const validacoes = [
    {
        mensagem: 'O campo <strong>Nome</strong> é obrigatório.',
        tab: 'geral',
        checar: () => !document.querySelector('input[name="nome"]').value.trim(),
    },
    {
        mensagem: 'Já existe um produto cadastrado com esse <strong>código de barras</strong>. Corrija antes de salvar.',
        tab: 'geral',
        checar: () => codigoBarrasDuplicado,
    },
    {
        mensagem: 'O campo <strong>NCM</strong> é obrigatório. Selecione o NCM do produto em <em>Dados Fiscais</em>.',
        tab: 'fiscal',
        checar: () => !document.getElementById('ncm_id').value,
    },
    {
        mensagem: 'O campo <strong>Unidade comercial</strong> é obrigatório. Preencha em <em>Dados Fiscais</em>.',
        tab: 'fiscal',
        checar: () => !document.querySelector('input[name="unidade_comercial"]').value.trim(),
    },
    {
        mensagem: 'O campo <strong>Unidade tributável</strong> é obrigatório. Preencha em <em>Dados Fiscais</em>.',
        tab: 'fiscal',
        checar: () => !document.querySelector('input[name="unidade_tributavel"]').value.trim(),
    },
    {
        mensagem: 'O campo <strong>Preço de venda</strong> é obrigatório. Preencha em <em>Preço e Estoque</em>.',
        tab: 'preco',
        checar: () => !document.querySelector('input[name="preco_venda"]').value,
    },
    {
        mensagem: 'O campo <strong>Classificação Tributária</strong> é obrigatório. Selecione em <em>Dados Fiscais</em>.',
        tab: 'fiscal',
        checar: () => !document.getElementById('tributacao_id').value,
    },
@if ($pisCofinsObrigatorio)
    {
        mensagem: 'O campo <strong>PIS/COFINS</strong> é obrigatório no seu regime tributário (Lucro Presumido/Real). Selecione em <em>Dados Fiscais</em>.',
        tab: 'fiscal',
        checar: () => !document.getElementById('pis_cofins_id').value,
    },
@endif
];

function salvarProduto() {
    for (const regra of validacoes) {
        if (regra.checar()) {
            abrirModalAviso(regra.mensagem, regra.tab);
            return;
        }
    }
    // Todas as validações passaram — submete o form pai
    document.querySelector('form').submit();
}

// ===================== BALANÇA =====================

function validarProdutoBalanca(checkbox) {
    const unidade = document.querySelector('input[name="unidade_comercial"]').value.toUpperCase().trim();
    if (checkbox.checked && unidade !== 'KG') {
        checkbox.checked = false;
        document.getElementById('aviso-balanca').classList.remove('hidden');
        setTimeout(() => document.getElementById('aviso-balanca').classList.add('hidden'), 3000);
    }
}

document.querySelector('input[name="unidade_comercial"]')?.addEventListener('input', () => {
    const checkbox = document.getElementById('produto_balanca');
    const unidade = document.querySelector('input[name="unidade_comercial"]').value.toUpperCase().trim();
    if (checkbox.checked && unidade !== 'KG') {
        checkbox.checked = false;
        document.getElementById('aviso-balanca').classList.remove('hidden');
        setTimeout(() => document.getElementById('aviso-balanca').classList.add('hidden'), 3000);
    }
});


// ===================== VARIAÇÕES =====================

function toggleVariacao(temVariacao) {
    document.getElementById('bloco-estoque-simples').classList.toggle('hidden', temVariacao);
    document.getElementById('bloco-variantes').classList.toggle('hidden', !temVariacao);
}

function adicionarLinhaVariante(cor = '', tamanho = '', sku = '', estoque = 0, estoqueMinimo = 0, id = '') {
    const tbody = document.getElementById('linhas-variantes');
    const i = indiceVariante++;
    const tr = document.createElement('tr');
    tr.className = 'bg-white';
    tr.innerHTML = `
        <td class="px-3 py-2">
            <input type="hidden" name="variantes[${i}][id]" value="${id}">
            <input type="text" name="variantes[${i}][cor]" value="${cor}"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
        </td>
        <td class="px-3 py-2">
            <input type="text" name="variantes[${i}][tamanho]" value="${tamanho}"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
        </td>
        <td class="px-3 py-2">
            <input type="text" name="variantes[${i}][sku]" value="${sku}"
                   class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
        </td>
        <td class="px-3 py-2">
            <input type="number" name="variantes[${i}][estoque]" value="${estoque}"
                   class="w-20 border border-gray-200 rounded px-2 py-1 text-sm">
        </td>
        <td class="px-3 py-2">
            <input type="number" name="variantes[${i}][estoque_minimo]" value="${estoqueMinimo}"
                   class="w-20 border border-gray-200 rounded px-2 py-1 text-sm">
        </td>
        <td class="px-3 py-2">
            <button type="button" onclick="this.closest('tr').remove()"
                    class="text-red-500 hover:text-red-700 text-sm font-medium">Remover</button>
        </td>
    `;
    tbody.appendChild(tr);
}

document.addEventListener('DOMContentLoaded', () => {
    @if (isset($produto) && $produto->variantes->count())
        @foreach ($produto->variantes as $variante)
            adicionarLinhaVariante(
                "{{ $variante->cor }}", "{{ $variante->tamanho }}", "{{ $variante->sku }}",
                {{ $variante->estoque }}, {{ $variante->estoque_minimo }}, {{ $variante->id }}
            );
        @endforeach
    @endif
});


document.addEventListener('DOMContentLoaded', () => {
    @if ($errors->has('ncm_id') || $errors->has('tributacao_id') || $errors->has('cest_id') || $errors->has('unidade_comercial') || $errors->has('unidade_tributavel') || $errors->has('origem_mercadoria') || $errors->has('class_trib_ibs_cbs_id'))
        trocarTab('fiscal');
    @elseif ($errors->has('preco_venda') || $errors->has('preco_custo') || $errors->has('estoque'))
        trocarTab('preco');
    @endif
});



function toggleAtacado(ativo) {
    document.getElementById('bloco-atacado').classList.toggle('hidden', !ativo);
}
 
function toggleAtacadoPrazo(ativo) {
    document.getElementById('bloco-atacado-prazo').classList.toggle('hidden', !ativo);
}
 
// Mantém o label "A partir de (UN)" sincronizado com a Unidade comercial da aba Dados Fiscais
document.addEventListener('DOMContentLoaded', () => {
    const unidadeInput = document.querySelector('input[name="unidade_comercial"]');
    const labelUnidade = document.getElementById('unidade-atacado-label');
 
    if (unidadeInput && labelUnidade) {
        const atualizar = () => labelUnidade.innerText = `(${unidadeInput.value || 'UN'})`;
        atualizar();
        unidadeInput.addEventListener('input', atualizar);
    }
});
</script>