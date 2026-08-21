<div class="grid grid-cols-2 gap-4">
    <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">Nome *</label>
        <input type="text" name="nome" value="{{ old('nome', $produto->nome ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">Descrição</label>
        <textarea name="descricao" rows="2"
                  class="w-full border rounded px-3 py-2">{{ old('descricao', $produto->descricao ?? '') }}</textarea>
    </div>

    {{-- Campos hidden para guardar os IDs selecionados --}}
    <input type="hidden" name="categoria_id" id="categoria_id" value="{{ old('categoria_id', $produto->categoria_id ?? '') }}">
    <input type="hidden" name="marca_id" id="marca_id" value="{{ old('marca_id', $produto->marca_id ?? '') }}">
    <input type="hidden" name="grupo_id" id="grupo_id" value="{{ old('grupo_id', $produto->grupo_id ?? '') }}">
    <input type="hidden" name="ncm_id" id="ncm_id" value="{{ old('ncm_id', $produto->ncm_id ?? '') }}">

    <div>
        <label class="block text-sm font-medium mb-1">Categoria</label>
        <button type="button" onclick="abrirModalCatalogo('categoria')"
                class="w-full border rounded px-3 py-2 text-left bg-white hover:bg-gray-50 text-sm">
            <span id="categoria_label">{{ old('categoria_id') ? '' : ($produto->categoria->nome ?? 'Clique para selecionar...') }}</span>
        </button>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Marca</label>
        <button type="button" onclick="abrirModalCatalogo('marca')"
                class="w-full border rounded px-3 py-2 text-left bg-white hover:bg-gray-50 text-sm">
            <span id="marca_label">{{ old('marca_id') ? '' : ($produto->marca->nome ?? 'Clique para selecionar...') }}</span>
        </button>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Grupo</label>
        <button type="button" onclick="abrirModalCatalogo('grupo')"
                class="w-full border rounded px-3 py-2 text-left bg-white hover:bg-gray-50 text-sm">
            <span id="grupo_label">{{ old('grupo_id') ? '' : ($produto->grupo->nome ?? 'Clique para selecionar...') }}</span>
        </button>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Código interno *</label>
        <input type="text" name="codigo_interno" value="{{ old('codigo_interno', $produto->codigo_interno ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Código de barras (EAN)</label>
        <input type="text" name="codigo_barras" value="{{ old('codigo_barras', $produto->codigo_barras ?? '') }}"
               placeholder="Deixe em branco se não tiver"
               class="w-full border rounded px-3 py-2">
    </div>

    <hr class="col-span-2 my-2">
    <h3 class="col-span-2 font-semibold text-gray-700">Dados fiscais</h3>

    <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">NCM *</label>
        <button type="button" onclick="abrirModalNcm()"
                class="w-full border rounded px-3 py-2 text-left bg-white hover:bg-gray-50 text-sm">
            <span id="ncm_label">{{ old('ncm_id') ? '' : ($produto->ncm ? $produto->ncm->codigo . ' — ' . $produto->ncm->descricao : 'Clique para selecionar...') }}</span>
        </button>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">CEST</label>
        <input type="text" name="cest" maxlength="7" value="{{ old('cest', $produto->cest ?? '') }}"
               placeholder="Só se o NCM exigir ICMS-ST"
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">CFOP padrão *</label>
        <input type="text" name="cfop_padrao" maxlength="4" value="{{ old('cfop_padrao', $produto->cfop_padrao ?? '5102') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">CSOSN *</label>
        <input type="text" name="csosn" maxlength="3" value="{{ old('csosn', $produto->csosn ?? '102') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Unidade comercial *</label>
        <input type="text" name="unidade_comercial" maxlength="6" value="{{ old('unidade_comercial', $produto->unidade_comercial ?? 'UN') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Unidade tributável *</label>
        <input type="text" name="unidade_tributavel" maxlength="6" value="{{ old('unidade_tributavel', $produto->unidade_tributavel ?? 'UN') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Origem da mercadoria *</label>
        <select name="origem_mercadoria" class="w-full border rounded px-3 py-2">
            @foreach ([0 => '0 - Nacional', 1 => '1 - Importado (importação direta)', 2 => '2 - Importado (mercado interno)'] as $valor => $texto)
                <option value="{{ $valor }}" {{ old('origem_mercadoria', $produto->origem_mercadoria ?? 0) == $valor ? 'selected' : '' }}>
                    {{ $texto }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Classificação IBS/CBS</label>
        <input type="text" name="class_trib_ibs_cbs" maxlength="6" value="{{ old('class_trib_ibs_cbs', $produto->class_trib_ibs_cbs ?? '') }}"
               placeholder="cClassTrib (Reforma Tributária)"
               class="w-full border rounded px-3 py-2">
    </div>

    <hr class="col-span-2 my-2">
    <h3 class="col-span-2 font-semibold text-gray-700">Preço e estoque</h3>

    <div>
        <label class="block text-sm font-medium mb-1">Preço de venda *</label>
        <input type="number" step="0.01" name="preco_venda" value="{{ old('preco_venda', $produto->preco_venda ?? '') }}" required
               class="w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Preço de custo</label>
        <input type="number" step="0.01" name="preco_custo" value="{{ old('preco_custo', $produto->preco_custo ?? '') }}"
               class="w-full border rounded px-3 py-2">
    </div>

    <div class="col-span-2 flex items-center gap-2">
        <input type="hidden" name="tem_variacao" value="0">
        <input type="checkbox" id="tem_variacao" name="tem_variacao" value="1"
            {{ old('tem_variacao', $produto->tem_variacao ?? false) ? 'checked' : '' }}
            onchange="toggleVariacao(this.checked)">
        <label for="tem_variacao" class="text-sm font-medium">Produto tem variação (cor/tamanho)</label>
    </div>

    <div class="col-span-2 flex items-center gap-3 mt-2">
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="hidden" name="produto_balanca" value="0">
            <input type="checkbox" id="produto_balanca" name="produto_balanca" value="1"
                {{ old('produto_balanca', $produto->produto_balanca ?? false) ? 'checked' : '' }}
                class="sr-only peer"
                onchange="validarProdutoBalanca(this)">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            <span class="ml-3 text-sm font-medium text-gray-700">Produto de balança (KG)</span>
        </label>
        <span id="aviso-balanca" class="text-xs text-orange-500 hidden">
            ⚠️ Disponível apenas quando a unidade comercial for KG
        </span>
    </div>

    <div id="bloco-estoque-simples" class="col-span-2 grid grid-cols-2 gap-4 {{ old('tem_variacao', $produto->tem_variacao ?? false) ? 'hidden' : '' }}">
        <div>
            <label class="block text-sm font-medium mb-1">Estoque</label>
            <input type="number" name="estoque" value="{{ old('estoque', $produto->estoque ?? 0) }}"
                class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Estoque mínimo</label>
            <input type="number" name="estoque_minimo" value="{{ old('estoque_minimo', $produto->estoque_minimo ?? 0) }}"
                class="w-full border rounded px-3 py-2">
        </div>
    </div>

    <div id="bloco-variantes" class="col-span-2 {{ old('tem_variacao', $produto->tem_variacao ?? false) ? '' : 'hidden' }}">
        <h3 class="font-semibold text-gray-700 mb-2">Variações</h3>

        <table class="w-full mb-3" id="tabela-variantes">
            <thead>
                <tr class="text-left text-sm text-gray-500">
                    <th class="pb-1">Cor</th>
                    <th class="pb-1">Tamanho</th>
                    <th class="pb-1">SKU</th>
                    <th class="pb-1">Estoque</th>
                    <th class="pb-1">Estoque mín.</th>
                    <th class="pb-1"></th>
                </tr>
            </thead>
            <tbody id="linhas-variantes"></tbody>
        </table>

        <button type="button" onclick="adicionarLinhaVariante()"
                class="bg-gray-200 hover:bg-gray-300 text-sm px-3 py-1 rounded">
            + Adicionar variação
        </button>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold">
        Salvar
    </button>
    <a href="{{ route('produtos.index') }}" class="bg-gray-200 hover:bg-gray-300 px-6 py-2 rounded">
        Cancelar
    </a>
</div>

<script>
let indiceVariante = 0;


function validarProdutoBalanca(checkbox) {
    const unidade = document.querySelector('input[name="unidade_comercial"]').value.toUpperCase().trim();

    if (checkbox.checked && unidade !== 'KG') {
        checkbox.checked = false;
        document.getElementById('aviso-balanca').classList.remove('hidden');
        setTimeout(() => document.getElementById('aviso-balanca').classList.add('hidden'), 3000);
    }
}

// Também valida se o usuário mudar a unidade depois de já ter marcado balança
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
    tr.innerHTML = `
        <td class="pr-2 py-1">
            <input type="hidden" name="variantes[${i}][id]" value="${id}">
            <input type="text" name="variantes[${i}][cor]" value="${cor}" class="w-full border rounded px-2 py-1">
        </td>
        <td class="pr-2 py-1">
            <input type="text" name="variantes[${i}][tamanho]" value="${tamanho}" class="w-full border rounded px-2 py-1">
        </td>
        <td class="pr-2 py-1">
            <input type="text" name="variantes[${i}][sku]" value="${sku}" class="w-full border rounded px-2 py-1">
        </td>
        <td class="pr-2 py-1">
            <input type="number" name="variantes[${i}][estoque]" value="${estoque}" class="w-20 border rounded px-2 py-1">
        </td>
        <td class="pr-2 py-1">
            <input type="number" name="variantes[${i}][estoque_minimo]" value="${estoqueMinimo}" class="w-20 border rounded px-2 py-1">
        </td>
        <td class="py-1">
            <button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:underline text-sm">Remover</button>
        </td>
    `;
    tbody.appendChild(tr);
}

// Repopula variantes já existentes ao editar um produto
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