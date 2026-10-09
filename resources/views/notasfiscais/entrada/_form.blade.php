@php
    $campo = 'w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100 disabled:text-gray-500';
    $rotulo = 'block text-xs text-gray-500 mb-1';
    $erro = 'text-xs text-red-600 mt-1';
@endphp

@if ($errors->any())
    <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3 mb-6 text-sm">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $mensagem)
                <li>{{ $mensagem }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ===================== Dados da nota ===================== --}}
<div class="bg-white border border-gray-200 rounded-lg p-5 mb-6">
    <h2 class="font-semibold mb-4">Dados da nota</h2>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-6">
            <label class="{{ $rotulo }}">Fornecedor *</label>
            <div class="flex gap-2">
                <select id="fornecedor_id" name="fornecedor_id" class="{{ $campo }}" @disabled($somenteLeitura)>
                    <option value="">Selecione...</option>
                    @foreach ($fornecedores as $f)
                        <option value="{{ $f->id }}" @selected(old('fornecedor_id', $entrada->fornecedor_id) == $f->id)>
                            {{ $f->nome_exibicao }} — {{ $f->documento_formatado }}
                        </option>
                    @endforeach
                </select>
                @unless ($somenteLeitura)
                    <button type="button" id="btn-novo-fornecedor"
                            class="shrink-0 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-3 py-2 rounded transition cursor-pointer">
                        + Novo
                    </button>
                @endunless
            </div>
            @error('fornecedor_id') <p class="{{ $erro }}">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Modelo *</label>
            <select name="modelo" class="{{ $campo }}" @disabled($somenteLeitura)>
                <option value="55" @selected(old('modelo', $entrada->modelo) === '55')>55 - NF-e</option>
                <option value="01" @selected(old('modelo', $entrada->modelo) === '01')>01 - NF (papel)</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Série</label>
            <input type="text" name="serie" maxlength="3" value="{{ old('serie', $entrada->serie) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Número *</label>
            <input type="text" name="numero" maxlength="9" value="{{ old('numero', $entrada->numero) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
            @error('numero') <p class="{{ $erro }}">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-6">
            <label class="{{ $rotulo }}">Chave de acesso (44 dígitos)</label>
            <input type="text" name="chave_acesso" maxlength="44" inputmode="numeric"
                   value="{{ old('chave_acesso', $entrada->chave_acesso) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
            @error('chave_acesso') <p class="{{ $erro }}">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Data de emissão *</label>
            <input type="date" name="data_emissao"
                   value="{{ old('data_emissao', $entrada->data_emissao?->format('Y-m-d')) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
            @error('data_emissao') <p class="{{ $erro }}">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Data de entrada *</label>
            <input type="date" name="data_entrada"
                   value="{{ old('data_entrada', $entrada->data_entrada?->format('Y-m-d')) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
            @error('data_entrada') <p class="{{ $erro }}">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Natureza da operação</label>
            <input type="text" name="natureza_operacao" maxlength="60"
                   value="{{ old('natureza_operacao', $entrada->natureza_operacao) }}"
                   placeholder="Ex.: Compra p/ comercialização"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
        </div>
    </div>

    {{-- Cadastro rápido de fornecedor --}}
    @unless ($somenteLeitura)
        <div id="painel-fornecedor" class="hidden mt-4 border border-gray-200 rounded-lg bg-gray-50 p-4">
            <p class="text-sm font-medium mb-3">Cadastro rápido de fornecedor</p>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-3">
                    <label class="{{ $rotulo }}">CNPJ / CPF *</label>
                    <input type="text" id="forn-doc" class="{{ $campo }}">
                </div>
                <div class="md:col-span-4">
                    <label class="{{ $rotulo }}">Razão social *</label>
                    <input type="text" id="forn-razao" class="{{ $campo }}">
                </div>
                <div class="md:col-span-3">
                    <label class="{{ $rotulo }}">Nome fantasia</label>
                    <input type="text" id="forn-fantasia" class="{{ $campo }}">
                </div>
                <div class="md:col-span-2">
                    <label class="{{ $rotulo }}">IE</label>
                    <input type="text" id="forn-ie" class="{{ $campo }}">
                </div>
            </div>
            <p id="forn-erro" class="{{ $erro }} hidden"></p>
            <div class="flex gap-2 mt-3">
                <button type="button" id="btn-salvar-fornecedor"
                        class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded transition cursor-pointer">
                    Cadastrar fornecedor
                </button>
                <button type="button" id="btn-cancelar-fornecedor"
                        class="text-sm text-gray-500 hover:underline cursor-pointer">Fechar</button>
            </div>
        </div>
    @endunless
</div>

{{-- ===================== Itens ===================== --}}
<div class="bg-white border border-gray-200 rounded-lg p-5 mb-6">
    <h2 class="font-semibold mb-4">Itens</h2>

    @unless ($somenteLeitura)
        <div class="relative mb-4 max-w-xl">
            <label class="{{ $rotulo }}">Buscar produto (nome, referência, código interno ou código de barras)</label>
            <input type="text" id="busca-produto" autocomplete="off" class="{{ $campo }}"
                   placeholder="Digite ao menos 2 caracteres e tecle Enter para adicionar o primeiro resultado">
            <div id="resultado-produto"
                 class="hidden absolute left-0 right-0 z-30 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-72 overflow-y-auto"></div>
        </div>
    @endunless

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-2 py-2 font-medium">Produto</th>
                    <th class="px-2 py-2 font-medium w-28">Cód. forn.</th>
                    <th class="px-2 py-2 font-medium w-24">Qtd.</th>
                    <th class="px-2 py-2 font-medium w-28">Vl. unit.</th>
                    <th class="px-2 py-2 font-medium w-24">Desconto</th>
                    <th class="px-2 py-2 font-medium w-28">Lote</th>
                    <th class="px-2 py-2 font-medium w-36">Validade</th>
                    <th class="px-2 py-2 font-medium w-28 text-right">Total</th>
                    <th class="px-2 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody id="itens-corpo"></tbody>
        </table>
    </div>

    <p id="itens-vazio" class="text-center text-gray-400 text-sm py-6">Nenhum item adicionado.</p>
</div>

{{-- ===================== Totais ===================== --}}
<div class="bg-white border border-gray-200 rounded-lg p-5">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-6">
            <label class="{{ $rotulo }}">Observação</label>
            <textarea name="observacao" rows="4" maxlength="1000" class="{{ $campo }}"
                      @disabled($somenteLeitura)>{{ old('observacao', $entrada->observacao) }}</textarea>

            <label class="flex items-center gap-2 mt-3 text-sm">
                <input type="checkbox" name="atualizar_custo" value="1"
                       @checked(old('atualizar_custo', $entrada->atualizar_custo)) @disabled($somenteLeitura)>
                Atualizar o preço de custo dos produtos ao finalizar
            </label>
        </div>

        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Frete</label>
            <input type="number" step="0.01" min="0" id="valor_frete" name="valor_frete"
                   value="{{ old('valor_frete', (float) $entrada->valor_frete) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
        </div>
        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Outras despesas</label>
            <input type="number" step="0.01" min="0" id="valor_outras" name="valor_outras"
                   value="{{ old('valor_outras', (float) $entrada->valor_outras) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
        </div>
        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">Desconto na nota</label>
            <input type="number" step="0.01" min="0" id="valor_desconto" name="valor_desconto"
                   value="{{ old('valor_desconto', (float) $entrada->valor_desconto) }}"
                   class="{{ $campo }}" @disabled($somenteLeitura)>
        </div>
    </div>

    <div class="flex justify-end gap-8 mt-5 pt-4 border-t border-gray-100 text-sm">
        <div class="text-right">
            <span class="text-gray-500">Produtos</span>
            <p id="total-produtos" class="font-medium">R$ 0,00</p>
        </div>
        <div class="text-right">
            <span class="text-gray-500">Total da nota</span>
            <p id="total-nota" class="text-lg font-bold">R$ 0,00</p>
        </div>
    </div>
</div>

<script>
(function () {
    const itensIniciais  = @json($itens);
    const somenteLeitura = @json($somenteLeitura);
    const urlBusca       = @json(route('entradas-nota.produtos'));
    const urlFornecedor  = @json(route('fornecedores.rapido'));
    const csrf           = @json(csrf_token());

    const corpo  = document.getElementById('itens-corpo');
    const vazio  = document.getElementById('itens-vazio');
    const campoCls = 'w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100 disabled:text-gray-500';
    let indice = 0;

    const moeda = v => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const num   = el => parseFloat(el?.value) || 0;

    // ---------- Linhas de itens ----------
    function adicionarLinha(item) {
        const i = indice++;
        const tr = document.createElement('tr');
        tr.className = 'border-t border-gray-100 align-top';
        tr.dataset.produtoId = item.produto_id;

        tr.innerHTML = `
            <td class="px-2 py-2">
                <input type="hidden" data-f="produto_id" name="itens[${i}][produto_id]">
                <input type="hidden" data-f="descricao" name="itens[${i}][descricao]">
                <span data-nome class="block"></span>
            </td>
            <td class="px-2 py-2"><input type="text" data-f="codigo_fornecedor" name="itens[${i}][codigo_fornecedor]" maxlength="60" class="${campoCls}"></td>
            <td class="px-2 py-2"><input type="number" data-f="quantidade" name="itens[${i}][quantidade]" step="0.001" min="0.001" class="${campoCls}"></td>
            <td class="px-2 py-2"><input type="number" data-f="valor_unitario" name="itens[${i}][valor_unitario]" step="0.0001" min="0" class="${campoCls}"></td>
            <td class="px-2 py-2"><input type="number" data-f="valor_desconto" name="itens[${i}][valor_desconto]" step="0.01" min="0" class="${campoCls}"></td>
            <td class="px-2 py-2"><input type="text" data-f="lote" name="itens[${i}][lote]" maxlength="30" class="${campoCls}"></td>
            <td class="px-2 py-2"><input type="date" data-f="validade" name="itens[${i}][validade]" class="${campoCls}"></td>
            <td class="px-2 py-2 text-right whitespace-nowrap" data-total>R$ 0,00</td>
            <td class="px-2 py-2 text-right"></td>
        `;

        const set = (campo, valor) => { tr.querySelector(`[data-f="${campo}"]`).value = valor ?? ''; };
        set('produto_id', item.produto_id);
        set('descricao', item.descricao);
        set('codigo_fornecedor', item.codigo_fornecedor);
        set('quantidade', item.quantidade ?? 1);
        set('valor_unitario', item.valor_unitario ?? 0);
        set('valor_desconto', item.valor_desconto ?? 0);
        set('lote', item.lote);
        set('validade', item.validade);
        tr.querySelector('[data-nome]').textContent = item.descricao;

        if (somenteLeitura) {
            tr.querySelectorAll('input[data-f]').forEach(inp => { if (inp.type !== 'hidden') inp.disabled = true; });
        } else {
            const remover = document.createElement('button');
            remover.type = 'button';
            remover.textContent = '✕';
            remover.title = 'Remover item';
            remover.className = 'text-red-600 hover:text-red-800 cursor-pointer';
            remover.addEventListener('click', () => { tr.remove(); recalcular(); });
            tr.lastElementChild.appendChild(remover);
        }

        corpo.appendChild(tr);
        recalcular();
        return tr;
    }

    function recalcular() {
        let produtos = 0;

        corpo.querySelectorAll('tr').forEach(tr => {
            const q = num(tr.querySelector('[data-f="quantidade"]'));
            const u = num(tr.querySelector('[data-f="valor_unitario"]'));
            const d = num(tr.querySelector('[data-f="valor_desconto"]'));
            const total = Math.round(((q * u) - d) * 100) / 100;

            tr.querySelector('[data-total]').textContent = moeda(total);
            produtos += total;
        });

        const total = produtos
            + num(document.getElementById('valor_frete'))
            + num(document.getElementById('valor_outras'))
            - num(document.getElementById('valor_desconto'));

        document.getElementById('total-produtos').textContent = moeda(produtos);
        document.getElementById('total-nota').textContent = moeda(total);
        vazio.classList.toggle('hidden', corpo.children.length > 0);
    }

    corpo.addEventListener('input', recalcular);
    ['valor_frete', 'valor_outras', 'valor_desconto'].forEach(id =>
        document.getElementById(id).addEventListener('input', recalcular));

    itensIniciais.forEach(adicionarLinha);
    recalcular();

    if (somenteLeitura) return;

    // ---------- Busca de produtos ----------
    const busca = document.getElementById('busca-produto');
    const resultado = document.getElementById('resultado-produto');
    let timer = null;
    let encontrados = [];

    function escolher(p) {
        const existente = corpo.querySelector(`tr[data-produto-id="${p.id}"]`);

        if (existente) {
            const qtd = existente.querySelector('[data-f="quantidade"]');
            qtd.value = (parseFloat(qtd.value) || 0) + 1;
            qtd.focus();
        } else {
            const tr = adicionarLinha({
                produto_id: p.id,
                descricao: p.nome,
                quantidade: 1,
                valor_unitario: p.preco_custo ?? 0,
                valor_desconto: 0,
            });
            tr.querySelector('[data-f="quantidade"]').focus();
        }

        recalcular();
        busca.value = '';
        resultado.classList.add('hidden');
        resultado.innerHTML = '';
        encontrados = [];
    }

    function mostrar(lista) {
        encontrados = lista;
        resultado.innerHTML = '';

        if (!lista.length) {
            const p = document.createElement('p');
            p.className = 'px-3 py-2 text-sm text-gray-400';
            p.textContent = 'Nenhum produto encontrado.';
            resultado.appendChild(p);
        }

        lista.forEach(prod => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-3 py-2 text-sm hover:bg-amber-50 cursor-pointer border-b border-gray-100 last:border-b-0';
            b.textContent = `${prod.nome}` + (prod.codigo_barras ? ` — ${prod.codigo_barras}` : '');
            b.addEventListener('click', () => escolher(prod));
            resultado.appendChild(b);
        });

        resultado.classList.remove('hidden');
    }

    busca.addEventListener('input', () => {
        clearTimeout(timer);
        const q = busca.value.trim();

        if (q.length < 2) {
            resultado.classList.add('hidden');
            return;
        }

        timer = setTimeout(async () => {
            try {
                const resp = await fetch(`${urlBusca}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                mostrar(await resp.json());
            } catch (e) {
                resultado.classList.add('hidden');
            }
        }, 250);
    });

    busca.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault(); // evita enviar o formulário (leitor de código de barras envia Enter)
        if (encontrados.length) escolher(encontrados[0]);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#busca-produto') && !e.target.closest('#resultado-produto')) {
            resultado.classList.add('hidden');
        }
    });

    // ---------- Cadastro rápido de fornecedor ----------
    const painel = document.getElementById('painel-fornecedor');
    const erroForn = document.getElementById('forn-erro');

    document.getElementById('btn-novo-fornecedor').addEventListener('click', () => painel.classList.toggle('hidden'));
    document.getElementById('btn-cancelar-fornecedor').addEventListener('click', () => painel.classList.add('hidden'));

    document.getElementById('btn-salvar-fornecedor').addEventListener('click', async () => {
        erroForn.classList.add('hidden');

        const resp = await fetch(urlFornecedor, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
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
            erroForn.textContent = json.errors ? Object.values(json.errors).flat().join(' ') : 'Não foi possível cadastrar.';
            erroForn.classList.remove('hidden');
            return;
        }

        const select = document.getElementById('fornecedor_id');
        const opt = new Option(json.nome, json.id, true, true);
        select.add(opt);
        select.value = json.id;

        ['forn-doc', 'forn-razao', 'forn-fantasia', 'forn-ie'].forEach(id => document.getElementById(id).value = '');
        painel.classList.add('hidden');
    });
})();
</script>
