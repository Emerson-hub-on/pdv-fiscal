@php $produto = $produto ?? null; @endphp

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
    </div>

    {{-- Tab: Dados Gerais --}}
    <div id="tab-geral" class="tab-painel p-6 grid grid-cols-2 gap-5">

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $produto->nome ?? '') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
            <button type="button" onclick="abrirModalCatalogo('categoria')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="categoria_label" class="text-gray-600">{{ old('categoria_id') ? '' : ($produto?->categoria->nome ?? 'Selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
            <button type="button" onclick="abrirModalCatalogo('marca')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="marca_label" class="text-gray-600">{{ old('marca_id') ? '' : ($produto?->marca->nome ?? 'Selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
            <button type="button" onclick="abrirModalCatalogo('grupo')"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="grupo_label" class="text-gray-600">{{ old('grupo_id') ? '' : ($produto?->grupo->nome ?? 'Selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código interno <span class="text-red-500">*</span></label>
            <input type="text" name="codigo_interno" value="{{ old('codigo_interno', $produto->codigo_interno ?? '') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código de barras (EAN)</label>
            <input type="text" name="codigo_barras" value="{{ old('codigo_barras', $produto->codigo_barras ?? '') }}"
                   placeholder="Deixe em branco se não tiver"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>
    </div>

    {{-- Tab: Dados Fiscais --}}
    <div id="tab-fiscal" class="tab-painel p-6 grid grid-cols-2 gap-5 hidden">

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">NCM <span class="text-red-500">*</span></label>
            <button type="button" onclick="abrirModalNcm()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition">
                <span id="ncm_label" class="text-gray-600">{{ old('ncm_id') ? '' : ($produto?->ncm ? $produto->ncm->codigo . ' — ' . $produto->ncm->descricao : 'Selecionar...') }}</span>
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CEST</label>
            <input type="text" name="cest" maxlength="7" value="{{ old('cest', $produto->cest ?? '') }}"
                   placeholder="Só se o NCM exigir ICMS-ST"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CFOP padrão <span class="text-red-500">*</span></label>
            <input type="text" name="cfop_padrao" maxlength="4" value="{{ old('cfop_padrao', $produto->cfop_padrao ?? '5102') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CSOSN <span class="text-red-500">*</span></label>
            <input type="text" name="csosn" maxlength="3" value="{{ old('csosn', $produto->csosn ?? '102') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
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
            <input type="text" name="class_trib_ibs_cbs" maxlength="6" value="{{ old('class_trib_ibs_cbs', $produto->class_trib_ibs_cbs ?? '') }}"
                   placeholder="cClassTrib (Reforma Tributária)"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
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

        <div class="col-span-2 flex items-center gap-3 py-1">
            <input type="hidden" name="tem_variacao" value="0">
            <input type="checkbox" id="tem_variacao" name="tem_variacao" value="1"
                   {{ old('tem_variacao', $produto->tem_variacao ?? false) ? 'checked' : '' }}
                   onchange="toggleVariacao(this.checked)"
                   class="w-4 h-4 text-blue-600 rounded">
            <label for="tem_variacao" class="text-sm font-medium text-gray-700">Produto tem variação (cor/tamanho)</label>
        </div>

        <div class="col-span-2 flex items-center gap-3 py-1">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="produto_balanca" value="0">
                <input type="checkbox" id="produto_balanca" name="produto_balanca" value="1"
                       {{ old('produto_balanca', $produto->produto_balanca ?? false) ? 'checked' : '' }}
                       class="sr-only peer"
                       onchange="validarProdutoBalanca(this)">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                <span class="ml-3 text-sm font-medium text-gray-700">Produto de balança (KG)</span>
            </label>
            <span id="aviso-balanca" class="text-xs text-orange-500 hidden">
                ⚠️ Disponível apenas quando a unidade comercial for KG
            </span>
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

{{-- Botões de ação --}}
<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar produto
    </button>
    <a href="{{ route('produtos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>

<script>
let indiceVariante = 0;

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
</script>