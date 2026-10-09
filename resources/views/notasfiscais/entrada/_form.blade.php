@php
    $ehEdicao = $entrada->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';
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

        <div class="grid grid-cols-2 gap-4 max-w-5xl">
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Fornecedor</label>
                <div class="flex gap-2">
                    <select id="fornecedor_id" name="fornecedor_id" class="{{ $inputCls }}" @disabled($somenteLeitura)>
                        <option value="">Selecionar fornecedor...</option>
                        @foreach ($fornecedores as $f)
                            <option value="{{ $f->id }}" @selected(old('fornecedor_id', $entrada->fornecedor_id) == $f->id)>
                                {{ $f->nome_exibicao }} — {{ $f->documento_formatado }}
                            </option>
                        @endforeach
                    </select>
                    @unless ($somenteLeitura)
                        <button type="button" onclick="abrirModalFornecedor()"
                                class="shrink-0 border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                            + Novo
                        </button>
                    @endunless
                </div>
                @error('fornecedor_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Modelo</label>
                <select name="modelo" class="{{ $inputCls }}" @disabled($somenteLeitura)>
                    <option value="55" @selected(old('modelo', $entrada->modelo) === '55')>55 - NF-e</option>
                    <option value="01" @selected(old('modelo', $entrada->modelo) === '01')>01 - NF (papel)</option>
                </select>
                @error('modelo') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Natureza da operação</label>
                <input type="text" name="natureza_operacao" maxlength="60"
                       value="{{ old('natureza_operacao', $entrada->natureza_operacao) }}"
                       placeholder="Ex.: Compra para comercialização"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
            </div>

            <div>
                <label class="{{ $labelCls }}">Número</label>
                <input type="text" name="numero" maxlength="9" value="{{ old('numero', $entrada->numero) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
                @error('numero') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Série</label>
                <input type="text" name="serie" maxlength="3" value="{{ old('serie', $entrada->serie) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
                @error('serie') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-2">
                <label class="{{ $labelCls }}">Chave de acesso (44 dígitos)</label>
                <input type="text" name="chave_acesso" maxlength="44" inputmode="numeric"
                       value="{{ old('chave_acesso', $entrada->chave_acesso) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
                @error('chave_acesso') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Data de emissão</label>
                <input type="date" name="data_emissao"
                       value="{{ old('data_emissao', $entrada->data_emissao?->format('Y-m-d')) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
                @error('data_emissao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Data de entrada</label>
                <input type="date" name="data_entrada"
                       value="{{ old('data_entrada', $entrada->data_entrada?->format('Y-m-d')) }}"
                       class="{{ $inputCls }}" @disabled($somenteLeitura)>
                @error('data_entrada') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
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

            <div id="editor-item" class="hidden flex flex-col gap-3 mb-4 bg-gray-50 rounded-lg p-4">
                <div class="text-sm font-semibold text-gray-800" id="editor-produto-nome"></div>

                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Qtd</label>
                        <input type="number" id="ed-qtd" step="0.001" min="0.001" oninput="atualizarTotalEditor()"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Vl Unit (R$)</label>
                        <input type="number" id="ed-unit" step="0.0001" min="0" oninput="atualizarTotalEditor()"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Desconto (R$)</label>
                        <input type="number" id="ed-desc" step="0.01" min="0" value="0" oninput="atualizarTotalEditor()"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Vl Total</label>
                        <input type="text" id="ed-total" readonly
                               class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cód. no fornecedor</label>
                        <input type="text" id="ed-codforn" maxlength="60"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Lote</label>
                        <input type="text" id="ed-lote" maxlength="30"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Validade</label>
                        <input type="date" id="ed-validade"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" onclick="adicionarLinhaNaGrid()"
                            class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700 w-fit">
                        Adicionar à nota
                    </button>
                    <button type="button" onclick="cancelarEditor()"
                            class="border border-red-300 text-red-600 rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-red-50 w-fit">
                        Cancelar
                    </button>
                </div>
            </div>
        @endunless

        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Descrição</th>
                        <th class="text-left px-3 py-2">Cód. forn.</th>
                        <th class="text-right px-3 py-2">Qtd</th>
                        <th class="text-right px-3 py-2">Vl Unit</th>
                        <th class="text-right px-3 py-2">Desconto</th>
                        <th class="text-right px-3 py-2">Vl Total</th>
                        <th class="text-left px-3 py-2">Lote</th>
                        <th class="text-left px-3 py-2">Validade</th>
                        <th class="text-right px-3 py-2">Ações</th>
                    </tr>
                </thead>
                <tbody id="linhas-grid-itens" class="divide-y divide-gray-100"></tbody>
                <tfoot class="bg-gray-50 font-medium">
                    <tr>
                        <td colspan="5" class="px-3 py-1 text-right text-gray-500">Produtos</td>
                        <td class="px-3 py-1 text-right" id="total-produtos">R$ 0,00</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="px-3 py-2 text-right">Total da nota</td>
                        <td class="px-3 py-2 text-right" id="total-nota">R$ 0,00</td>
                        <td colspan="3"></td>
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
                          @disabled($somenteLeitura)>{{ old('observacao', $entrada->observacao) }}</textarea>
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
let produtoEditor = null;      // produto selecionado no editor
let resultadosBusca = [];
let indiceBusca = 0;
let timerBusca = null;

const moeda = v => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const numero = id => parseFloat(document.getElementById(id)?.value) || 0;
const arred2 = v => Math.round(v * 100) / 100;
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

function renderizarGrid() {
    const corpo = document.getElementById('linhas-grid-itens');
    const campos = document.getElementById('campos-itens');
    corpo.innerHTML = '';
    campos.innerHTML = '';

    let produtos = 0;

    itensNota.forEach((item, i) => {
        const total = totalItem(item);
        produtos += total;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50';

        const celulas = [
            [item.descricao, 'text-left'],
            [item.codigo_fornecedor || '—', 'text-left'],
            [Number(item.quantidade).toLocaleString('pt-BR', { maximumFractionDigits: 3 }), 'text-right'],
            [moeda(item.valor_unitario), 'text-right'],
            [moeda(item.valor_desconto), 'text-right'],
            [moeda(total), 'text-right'],
            [item.lote || '—', 'text-left'],
            [dataBr(item.validade), 'text-left'],
        ];

        celulas.forEach(([texto, alinhamento]) => {
            const td = document.createElement('td');
            td.className = `px-3 py-2 ${alinhamento}`;
            td.textContent = texto;
            tr.appendChild(td);
        });

        const tdAcoes = document.createElement('td');
        tdAcoes.className = 'px-3 py-2 text-right';

        if (!somenteLeitura) {
            const editar = document.createElement('button');
            editar.type = 'button';
            editar.textContent = 'Editar';
            editar.className = 'text-gray-600 hover:text-gray-900 text-sm mr-3';
            editar.onclick = () => editarLinha(i);

            const remover = document.createElement('button');
            remover.type = 'button';
            remover.textContent = 'Remover';
            remover.className = 'text-red-600 hover:text-red-800 text-sm';
            remover.onclick = () => { itensNota.splice(i, 1); renderizarGrid(); };

            tdAcoes.append(editar, remover);
        }

        tr.appendChild(tdAcoes);
        corpo.appendChild(tr);

        // Campos enviados ao servidor
        ['produto_id', 'descricao', 'codigo_fornecedor', 'quantidade', 'valor_unitario', 'valor_desconto', 'lote', 'validade']
            .forEach(campo => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `itens[${i}][${campo}]`;
                input.value = item[campo] ?? '';
                campos.appendChild(input);
            });
    });

    document.getElementById('grid-vazia').classList.toggle('hidden', itensNota.length > 0);
    atualizarTotais(produtos);
}

function atualizarTotais(produtos = null) {
    if (produtos === null) {
        produtos = itensNota.reduce((soma, item) => soma + totalItem(item), 0);
    }

    const total = produtos + numero('valor_frete') + numero('valor_outras') - numero('valor_desconto');

    document.getElementById('total-produtos').textContent = moeda(produtos);
    document.getElementById('total-nota').textContent = moeda(total);
}

['valor_frete', 'valor_outras', 'valor_desconto'].forEach(id =>
    document.getElementById(id).addEventListener('input', () => atualizarTotais()));

// ---------------------------------------------------------------
// Editor do item
// ---------------------------------------------------------------
function atualizarTotalEditor() {
    const total = arred2((numero('ed-qtd') * numero('ed-unit')) - numero('ed-desc'));
    document.getElementById('ed-total').value = moeda(total);
}

function abrirEditor(produto, dados = {}) {
    produtoEditor = produto;

    document.getElementById('editor-produto-nome').textContent = produto.nome;
    document.getElementById('ed-qtd').value = dados.quantidade ?? 1;
    document.getElementById('ed-unit').value = dados.valor_unitario ?? (produto.preco_custo ?? 0);
    document.getElementById('ed-desc').value = dados.valor_desconto ?? 0;
    document.getElementById('ed-codforn').value = dados.codigo_fornecedor ?? '';
    document.getElementById('ed-lote').value = dados.lote ?? '';
    document.getElementById('ed-validade').value = dados.validade ?? '';

    document.getElementById('editor-item').classList.remove('hidden');
    atualizarTotalEditor();
    document.getElementById('ed-qtd').focus();
    document.getElementById('ed-qtd').select();
}

function cancelarEditor() {
    produtoEditor = null;
    document.getElementById('editor-item').classList.add('hidden');
}

function adicionarLinhaNaGrid() {
    if (!produtoEditor) return;

    const quantidade = numero('ed-qtd');
    const valorUnitario = numero('ed-unit');
    const desconto = numero('ed-desc');

    if (quantidade <= 0) { mostrarAviso('Informe uma quantidade maior que zero.'); return; }
    if (valorUnitario < 0) { mostrarAviso('O valor unitário não pode ser negativo.'); return; }
    if (desconto > quantidade * valorUnitario) { mostrarAviso('O desconto não pode ser maior que o valor do item.'); return; }

    itensNota.push({
        produto_id: produtoEditor.id,
        descricao: produtoEditor.nome,
        codigo_fornecedor: document.getElementById('ed-codforn').value.trim(),
        quantidade,
        valor_unitario: valorUnitario,
        valor_desconto: desconto,
        lote: document.getElementById('ed-lote').value.trim(),
        validade: document.getElementById('ed-validade').value,
    });

    cancelarEditor();
    renderizarGrid();
    document.getElementById('input-busca-item').focus();
}

function editarLinha(i) {
    const item = itensNota[i];
    itensNota.splice(i, 1);
    renderizarGrid();

    abrirEditor({ id: item.produto_id, nome: item.descricao }, item);
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

    // Enter nos campos do editor adiciona o item em vez de enviar o formulário
    document.getElementById('editor-item').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            adicionarLinhaNaGrid();
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
        select.add(new Option(json.nome, json.id, true, true));
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
    descricao: i.descricao,
    codigo_fornecedor: i.codigo_fornecedor ?? '',
    quantidade: parseFloat(i.quantidade) || 0,
    valor_unitario: parseFloat(i.valor_unitario) || 0,
    valor_desconto: parseFloat(i.valor_desconto) || 0,
    lote: i.lote ?? '',
    validade: i.validade ?? '',
}));

renderizarGrid();
</script>
