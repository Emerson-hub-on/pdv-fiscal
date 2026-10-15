@php
    $ehEdicao = $pedido->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';

    // Itens: o que o operador digitou (se a validação falhou) ou o que está no banco
    if (is_array(old('itens'))) {
        $itensIniciais = collect(old('itens'))->map(fn ($i) => [
            'produto_id'          => $i['produto_id'] ?? null,
            'produto_variante_id' => $i['produto_variante_id'] ?? null,
            'codigo_barras'       => $i['codigo_barras'] ?? '',
            'descricao'           => $i['descricao'] ?? '',
            'unidade'             => $i['unidade'] ?? 'UN',
            'quantidade'          => $i['quantidade'] ?? 1,
            'valor_unitario'      => $i['valor_unitario'] ?? 0,
        ])->values();
    } else {
        $itensIniciais = $pedido->itens->map(fn ($i) => [
            'produto_id'          => $i->produto_id,
            'produto_variante_id' => $i->produto_variante_id,
            'codigo_barras'       => $i->codigo_barras ?? '',
            'descricao'           => $i->descricao,
            'unidade'             => $i->unidade,
            'quantidade'          => (float) $i->quantidade,
            'valor_unitario'      => (float) $i->valor_unitario,
        ])->values();
    }

    $clienteAtual = old('cliente_id')
        ? \App\Models\Cliente::find(old('cliente_id'))
        : $pedido->cliente;

    $operadorSelecionado = old('operador_id', $pedido->operador_id ?? $operadorPadraoId);

    // ao editar, o operador original continua valendo mesmo que tenha sido desativado depois
    $operadorOriginalForaDaLista = $ehEdicao
        && $pedido->operador
        && ! $operadores->contains('id', $pedido->operador_id);
@endphp

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            mostrarAviso({{ Illuminate\Support\Js::from($errors->all()) }}.join('\n'), 'erro');
        });
    </script>
@endif

<form id="form-pedido" method="POST"
      action="{{ $ehEdicao ? route('pedidos-venda.update', $pedido) : route('pedidos-venda.store') }}"
      class="flex flex-col gap-6 w-full max-w-5xl mt-2 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    {{-- ===================== Cabeçalho do pedido ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('pedidos-venda.index') }}" title="Voltar para Pedidos de Venda"
               class="text-gray-500 hover:text-gray-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-lg font-semibold">
                {{ $ehEdicao ? 'Editar Pedido de Venda nº ' . $pedido->numeroExibicao() : 'Novo Pedido de Venda' }}
            </h1>
        </div>

        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Nº do pedido</label>
                    <input type="text" disabled
                           value="{{ $ehEdicao ? $pedido->numeroExibicao() : 'Gerado ao finalizar' }}"
                           class="{{ $inputCls }} font-mono">
                </div>

                <div>
                    <label class="{{ $labelCls }}">Data do pedido <span class="text-red-500">*</span></label>
                    <input type="date" name="data_pedido" id="campo-data-pedido"
                           value="{{ old('data_pedido', $pedido->data_pedido?->format('Y-m-d')) }}" class="{{ $inputCls }}">
                    @error('data_pedido') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Previsão de entrega <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="date" name="previsao_entrega"
                           value="{{ old('previsao_entrega', $pedido->previsao_entrega?->format('Y-m-d')) }}" class="{{ $inputCls }}">
                    @error('previsao_entrega') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Operador <span class="text-red-500">*</span></label>
                    <select name="operador_id" id="campo-operador" class="{{ $inputCls }}">
                        <option value="">Selecione o operador...</option>
                        @if ($operadorOriginalForaDaLista)
                            <option value="{{ $pedido->operador_id }}" @selected((int) $operadorSelecionado === (int) $pedido->operador_id)>
                                {{ $pedido->operador->codigo ? $pedido->operador->codigo . ' — ' : '' }}{{ $pedido->operador->name }} (inativo)
                            </option>
                        @endif
                        @foreach ($operadores as $op)
                            <option value="{{ $op->id }}" @selected((int) $operadorSelecionado === (int) $op->id)>
                                {{ $op->codigo ? $op->codigo . ' — ' : '' }}{{ $op->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('operador_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Cliente <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="hidden" name="cliente_id" id="campo-cliente"
                           value="{{ old('cliente_id', $pedido->cliente_id) }}">
                    <div class="flex gap-2">
                        <button type="button" onclick="abrirModalCliente()"
                                class="w-full min-w-0 text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                            <span id="texto-cliente-selecionado" class="block truncate">
                                @if ($clienteAtual)
                                    {{ $clienteAtual->nome_exibicao ?? $clienteAtual->nome }} — {{ $clienteAtual->documento_formatado ?? $clienteAtual->cpf_cnpj }}
                                @else
                                    Selecionar cliente...
                                @endif
                            </span>
                        </button>
                        <button type="button" id="limpar-cliente" onclick="limparCliente()" title="Remover cliente"
                                class="shrink-0 border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ $clienteAtual ? '' : 'hidden' }}">
                            &times;
                        </button>
                    </div>
                    @error('cliente_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Forma de pagamento <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <select name="forma_pagamento_id" class="{{ $inputCls }}">
                        <option value="">—</option>
                        @foreach ($formasPagamento as $fp)
                            <option value="{{ $fp->id }}" @selected((int) old('forma_pagamento_id', $pedido->forma_pagamento_id) === (int) $fp->id)>
                                {{ $fp->descricao }}
                            </option>
                        @endforeach
                    </select>
                    @error('forma_pagamento_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Itens ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-1">Itens do pedido</h2>
        <p class="text-xs text-gray-500 mb-4">
            Leia o código de barras e tecle Enter, ou comece a digitar a descrição para buscar o produto. Só produtos cadastrados podem ser incluídos.
        </p>

        {{-- Linha de inclusão --}}
        <div class="grid grid-cols-12 gap-2 items-end mb-4">
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Código de barras</label>
                <input type="text" id="add-codigo" autocomplete="off" inputmode="numeric" class="{{ $inputCls }} font-mono">
            </div>
            <div class="col-span-4">
                <label class="{{ $labelCls }}">Descrição</label>
                <input type="text" id="add-descricao" maxlength="255" autocomplete="off"
                       placeholder="Digite para buscar o produto..." class="{{ $inputCls }}">
            </div>
            <div class="col-span-1">
                <label class="{{ $labelCls }}">Un.</label>
                <input type="text" id="add-unidade" maxlength="6" value="UN" readonly class="{{ $inputCls }} uppercase bg-gray-50">
            </div>
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Quantidade</label>
                <input type="number" id="add-qtd" step="0.001" min="0.001" value="1" class="{{ $inputCls }}">
            </div>
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Valor unit. (R$)</label>
                <input type="number" id="add-valor" step="0.01" min="0" value="0.00" class="{{ $inputCls }}">
            </div>
            <div class="col-span-1">
                <button type="button" onclick="adicionarItem()" title="Adicionar item"
                        class="w-full bg-gray-700 text-amber-50 rounded-lg px-3 py-2 text-sm hover:bg-gray-500">+</button>
            </div>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[15%]">
                    <col class="w-[31%]">
                    <col class="w-[7%]">
                    <col class="w-[13%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                    <col class="w-[6%]">
                </colgroup>
                <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
                    <tr>
                        <th class="text-left px-2 py-2">Cód. barras</th>
                        <th class="text-left px-2 py-2">Descrição</th>
                        <th class="text-left px-2 py-2">Un.</th>
                        <th class="text-right px-2 py-2">Quantidade</th>
                        <th class="text-right px-2 py-2">Valor unit.</th>
                        <th class="text-right px-2 py-2">Total</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody id="linhas-itens" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>

        <div class="flex items-center justify-between mt-3">
            <p id="resumo-itens" class="text-xs text-gray-500"></p>
            <p class="text-sm text-gray-600">Total do pedido:
                <span id="total-pedido" class="text-lg font-semibold text-gray-900">R$ 0,00</span>
            </p>
        </div>
        @error('itens') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-3">
        <button type="button" onclick="abrirModalFinalizar()"
                class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-6 py-2.5 text-sm font-medium">
            {{ $ehEdicao ? 'Salvar alterações' : 'Finalizar pedido' }}
        </button>
        <a href="{{ route('pedidos-venda.index') }}"
           class="border border-gray-300 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-50">Cancelar</a>
    </div>
</form>

<!-- Modal de finalização (descrição do pedido) -->
<div id="modal-finalizar" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900 rounded-t-xl">
            <h2 class="text-lg font-bold text-white">{{ $ehEdicao ? 'Salvar pedido' : 'Finalizar pedido' }}</h2>
            <button type="button" onclick="fecharModalFinalizar()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <p id="resumo-finalizar" class="text-sm text-gray-600 mb-4"></p>

            <label class="{{ $labelCls }}">Descrição <span class="text-gray-400 font-normal">(opcional)</span></label>
            {{-- fica fora do <form>, mas é enviado junto por causa do atributo form="" --}}
            <textarea name="descricao" id="campo-descricao" form="form-pedido" rows="4" maxlength="1000"
                      placeholder="Ex.: condição de pagamento, forma de entrega, contato..."
                      class="{{ $inputCls }}">{{ old('descricao', $pedido->descricao) }}</textarea>
            @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror

            <div class="flex justify-end gap-3 mt-5">
                <button type="button" onclick="fecharModalFinalizar()"
                        class="border border-gray-300 rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</button>
                <button type="submit" form="form-pedido" id="btn-confirmar-finalizar"
                        class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-5 py-2 text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                    {{ $ehEdicao ? 'Confirmar e salvar' : 'Confirmar e finalizar' }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de busca de cliente -->
<div id="modal-busca-cliente" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Cliente</h2>
            <button type="button" onclick="fecharModalCliente()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-cliente-modal" placeholder="Buscar por nome ou CPF/CNPJ..."
                   autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Nome</th>
                        <th class="py-2">CPF/CNPJ</th>
                        <th class="py-2">UF</th>
                        <th class="py-2">Telefone</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-cliente"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

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
            {{-- Largura fixa: descrição longa é cortada com "…" --}}
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[19%]">
                    <col class="w-[41%]">
                    <col class="w-[8%]">
                    <col class="w-[14%]">
                    <col class="w-[18%]">
                </colgroup>
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 px-2">Código</th>
                        <th class="py-2 px-2">Produto</th>
                        <th class="py-2 px-2">Un.</th>
                        <th class="py-2 px-2 text-right">Estoque</th>
                        <th class="py-2 px-2 text-right">Preço</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-produto"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<div id="area-avisos" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]"></div>

<script>
const itensIniciais     = {{ Illuminate\Support\Js::from($itensIniciais) }};
const urlBuscaProduto   = {{ Illuminate\Support\Js::from(route('pedidos-venda.buscar-produto')) }};
// reaproveita a busca de cliente que já existe em Contas a Receber
const urlBuscaCliente   = {{ Illuminate\Support\Js::from(route('contas-receber.buscar-cliente')) }};

const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const clsCelula = 'w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition';
const brl = v => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

let itens = itensIniciais.map(i => ({ ...i }));
let pendente = null; // produto encontrado na busca, aguardando a quantidade

// ---------------------------------------------------------------
// Avisos
// ---------------------------------------------------------------
function mostrarAviso(mensagem, tipo = 'erro') {
    const area = document.getElementById('area-avisos');
    const div = document.createElement('div');
    div.className = 'rounded-lg px-4 py-3 text-sm shadow-lg whitespace-pre-line border '
        + (tipo === 'erro' ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-300 bg-green-50 text-green-800');
    div.textContent = mensagem;
    area.appendChild(div);
    setTimeout(() => div.remove(), 8000);
}

// Enter dentro de um input nunca envia o formulário (o leitor de código de barras manda Enter)
document.getElementById('form-pedido').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
});

// ---------------------------------------------------------------
// Grade de itens
// ---------------------------------------------------------------
const totalLinha = i => Math.round((parseFloat(i.quantidade) || 0) * (parseFloat(i.valor_unitario) || 0) * 100) / 100;

function renderizarItens() {
    const corpo = document.getElementById('linhas-itens');

    if (itens.length === 0) {
        corpo.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Nenhum item incluído.</td></tr>';
    } else {
        corpo.innerHTML = itens.map((item, i) => `
            <tr>
                <td class="px-2 py-2 font-mono text-xs text-gray-600 truncate" title="${esc(item.codigo_barras)}">
                    ${esc(item.codigo_barras) || '—'}
                    <input type="hidden" name="itens[${i}][codigo_barras]" value="${esc(item.codigo_barras)}">
                    <input type="hidden" name="itens[${i}][produto_id]" value="${esc(item.produto_id)}">
                    <input type="hidden" name="itens[${i}][produto_variante_id]" value="${esc(item.produto_variante_id)}">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="itens[${i}][descricao]" value="${esc(item.descricao)}" maxlength="255" readonly
                           title="${esc(item.descricao)}" class="${clsCelula} bg-gray-50 text-gray-700">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="itens[${i}][unidade]" value="${esc(item.unidade)}" maxlength="6" readonly
                           class="${clsCelula} uppercase bg-gray-50 text-gray-700">
                </td>
                <td class="px-2 py-2">
                    <input type="number" name="itens[${i}][quantidade]" value="${esc(item.quantidade)}" step="0.001" min="0.001"
                           oninput="itens[${i}].quantidade = this.value; atualizarLinha(${i})" class="${clsCelula} text-right">
                </td>
                <td class="px-2 py-2">
                    <input type="number" name="itens[${i}][valor_unitario]" value="${esc(item.valor_unitario)}" step="0.01" min="0"
                           oninput="itens[${i}].valor_unitario = this.value; atualizarLinha(${i})" class="${clsCelula} text-right">
                </td>
                <td id="total-linha-${i}" class="px-2 py-2 text-right font-medium whitespace-nowrap">${brl(totalLinha(item))}</td>
                <td class="px-2 py-2 text-center">
                    <button type="button" onclick="removerItem(${i})" title="Remover item"
                            class="text-red-600 hover:bg-red-50 rounded px-2 py-1">&times;</button>
                </td>
            </tr>`).join('');
    }

    atualizarResumo();
}

// atualiza só o total da linha (sem redesenhar, para não perder o foco do campo)
function atualizarLinha(i) {
    const celula = document.getElementById(`total-linha-${i}`);
    if (celula) celula.textContent = brl(totalLinha(itens[i]));
    atualizarResumo();
}

const totalPedido = () => Math.round(itens.reduce((s, i) => s + totalLinha(i), 0) * 100) / 100;

function atualizarResumo() {
    const qtd = itens.reduce((s, i) => s + (parseFloat(i.quantidade) || 0), 0);
    document.getElementById('resumo-itens').textContent = itens.length === 0
        ? ''
        : `${itens.length} ${itens.length === 1 ? 'item' : 'itens'} · quantidade total: ${qtd.toLocaleString('pt-BR', { maximumFractionDigits: 3 })}`;
    document.getElementById('total-pedido').textContent = brl(totalPedido());
}

function removerItem(i) {
    itens.splice(i, 1);
    renderizarItens();
}

// ---------------------------------------------------------------
// Linha "Adicionar item"
// ---------------------------------------------------------------
const campoCodigo    = document.getElementById('add-codigo');
const campoDescricao = document.getElementById('add-descricao');
const campoUnidade   = document.getElementById('add-unidade');
const campoQtd       = document.getElementById('add-qtd');
const campoValor     = document.getElementById('add-valor');

const modalProduto       = document.getElementById('modal-busca-produto');
const inputBuscaProduto  = document.getElementById('busca-produto-modal');
const linhasBuscaProduto = document.getElementById('linhas-busca-produto');

let resultadosProduto = [];
let indiceProduto = -1;
let timeoutDescricao;
let timeoutBuscaProduto;
let contadorBuscaProduto = 0;     // descarta respostas antigas se o operador digitar rápido
let campoFocoAoFechar = campoDescricao;

const modalProdutoAberto = () => !modalProduto.classList.contains('hidden');

function limparLinhaAdicionar() {
    pendente = null;
    campoCodigo.value = '';
    campoDescricao.value = '';
    campoUnidade.value = 'UN';
    campoQtd.value = 1;
    campoValor.value = '0.00';
    campoCodigo.focus();
}

// Devolve a lista, ou null se falhou / chegou uma busca mais nova
async function consultarProdutos(termo) {
    const minhaBusca = ++contadorBuscaProduto;

    try {
        const resp = await fetch(`${urlBuscaProduto}?termo=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar o servidor.`);
        const dados = await resp.json();
        return minhaBusca === contadorBuscaProduto ? dados : null;
    } catch (e) {
        if (minhaBusca === contadorBuscaProduto) mostrarAviso('Falha ao buscar produtos:\n' + e.message);
        return null;
    }
}

function abrirModalProduto(termo, resultados, origem) {
    campoFocoAoFechar = origem === 'codigo' ? campoCodigo : campoDescricao;
    if (origem === 'descricao') campoDescricao.value = ''; // o texto passa para o campo do modal

    resultadosProduto = resultados;
    indiceProduto = resultados.length > 0 ? 0 : -1;

    modalProduto.classList.remove('hidden');
    modalProduto.classList.add('flex');
    inputBuscaProduto.value = termo;
    renderizarProdutos();
    inputBuscaProduto.focus();
}

function fecharModalProduto() {
    modalProduto.classList.add('hidden');
    modalProduto.classList.remove('flex');
    inputBuscaProduto.value = '';
    resultadosProduto = [];
    indiceProduto = -1;
    campoFocoAoFechar.focus();
}

function renderizarProdutos() {
    if (resultadosProduto.length === 0) {
        linhasBuscaProduto.innerHTML = '<tr><td colspan="5" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }

    linhasBuscaProduto.innerHTML = resultadosProduto.map((r, index) => {
        const destacado = index === indiceProduto;
        const estoque = r.estoque === null || r.estoque === undefined ? '—' : Number(r.estoque).toLocaleString('pt-BR');
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarProduto(${index})">
                <td class="py-3 px-2 font-mono text-sm truncate ${destacado ? 'text-slate-300' : 'text-gray-500'}">${esc(r.codigo_barras || r.codigo_interno)}</td>
                <td class="py-3 px-2 font-medium truncate" title="${esc(r.descricao)}">${esc(r.descricao)}</td>
                <td class="py-3 px-2">${esc(r.unidade)}</td>
                <td class="py-3 px-2 text-right">${estoque}</td>
                <td class="py-3 px-2 text-right font-medium">${brl(r.preco_venda)}</td>
            </tr>
        `;
    }).join('');

    linhasBuscaProduto.children[indiceProduto]?.scrollIntoView({ block: 'nearest' });
}

function selecionarProduto(index) {
    const r = resultadosProduto[index];
    if (!r) return;
    fecharModalProduto();
    escolherProduto(r);
}

// Produto escolhido (pelo leitor ou pelo modal): preenche a linha e vai para a quantidade
function escolherProduto(r) {
    pendente = r;
    campoCodigo.value = r.codigo_barras || '';
    campoDescricao.value = r.descricao;
    campoUnidade.value = r.unidade || 'UN';
    campoValor.value = Number(r.preco_venda || 0).toFixed(2);
    campoQtd.focus();
    campoQtd.select();
}

function adicionarItem() {
    const quantidade = parseFloat(campoQtd.value);
    const valor      = parseFloat(campoValor.value);

    if (!pendente) return mostrarAviso('Busque e selecione um produto cadastrado para incluir no pedido.');
    if (!(quantidade > 0)) return mostrarAviso('A quantidade deve ser maior que zero.');
    if (!(valor >= 0)) return mostrarAviso('Informe o valor unitário do item.');

    // mesmo produto/variação já na lista: soma a quantidade em vez de duplicar a linha
    const existente = itens.find(i => i.produto_id == pendente.produto_id
        && (i.produto_variante_id ?? null) == (pendente.produto_variante_id ?? null));

    if (existente) {
        existente.quantidade = Math.round(((parseFloat(existente.quantidade) || 0) + quantidade) * 1000) / 1000;
    } else {
        itens.push({
            produto_id:          pendente.produto_id,
            produto_variante_id: pendente.produto_variante_id ?? null,
            codigo_barras:       pendente.codigo_barras ?? '',
            descricao:           pendente.descricao,
            unidade:             (campoUnidade.value.trim() || 'UN').toUpperCase(),
            quantidade,
            valor_unitario:      valor,
        });
    }

    renderizarItens();
    limparLinhaAdicionar();
}

// --- Código de barras: Enter busca; um resultado exato entra direto, senão abre o modal ---
campoCodigo.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();

    const codigo = campoCodigo.value.trim();
    if (!codigo) { campoDescricao.focus(); return; }

    const dados = await consultarProdutos(codigo);
    if (dados === null) return;

    const exatos = dados.filter(r => r.codigo_barras === codigo || r.codigo_interno === codigo);
    if (exatos.length === 1) { escolherProduto(exatos[0]); return; }
    if (dados.length > 0)    { abrirModalProduto(codigo, dados, 'codigo'); return; }

    mostrarAviso('Código não encontrado no cadastro de produtos.');
    campoCodigo.select();
});

campoCodigo.addEventListener('input', () => { // código alterado depois de escolher um produto: descarta a escolha
    if (pendente) {
        pendente = null;
        campoDescricao.value = '';
    }
});

// --- Descrição: ao começar a digitar, abre o modal de busca ---
async function buscarPelaDescricao() {
    const termo = campoDescricao.value.trim();
    if (termo.length < 1 || modalProdutoAberto()) return;

    const dados = await consultarProdutos(termo);
    if (dados === null) return;

    // o operador pode ter continuado a digitar enquanto a resposta vinha
    abrirModalProduto(campoDescricao.value.trim() || termo, dados, 'descricao');
}

campoDescricao.addEventListener('input', () => {
    if (pendente) { // mexeu no texto do produto escolhido: descarta a escolha e busca de novo
        pendente = null;
        campoCodigo.value = '';
    }

    clearTimeout(timeoutDescricao);
    if (modalProdutoAberto()) return;

    timeoutDescricao = setTimeout(buscarPelaDescricao, 300);
});

campoDescricao.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();

    if (pendente) { campoQtd.focus(); campoQtd.select(); return; }

    clearTimeout(timeoutDescricao);
    buscarPelaDescricao(); // Enter antes dos 300 ms: busca na hora
});

campoQtd.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); campoValor.focus(); campoValor.select(); }
});
campoValor.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); adicionarItem(); }
});

// --- Campo de busca dentro do modal ---
inputBuscaProduto.addEventListener('input', () => {
    clearTimeout(timeoutBuscaProduto);
    const valor = inputBuscaProduto.value.trim();

    if (valor.length < 1) {
        contadorBuscaProduto++; // invalida buscas em andamento
        resultadosProduto = [];
        indiceProduto = -1;
        renderizarProdutos();
        return;
    }

    timeoutBuscaProduto = setTimeout(async () => {
        const dados = await consultarProdutos(valor);
        if (dados === null) return;
        resultadosProduto = dados;
        indiceProduto = dados.length > 0 ? 0 : -1;
        renderizarProdutos();
    }, 300);
});

inputBuscaProduto.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalProduto(); return; }
    if (resultadosProduto.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceProduto = (indiceProduto + 1) % resultadosProduto.length;
        renderizarProdutos();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceProduto = (indiceProduto - 1 + resultadosProduto.length) % resultadosProduto.length;
        renderizarProdutos();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceProduto >= 0) selecionarProduto(indiceProduto);
    }
});

// ---------------------------------------------------------------
// Busca de cliente (modal)
// ---------------------------------------------------------------
const inputBuscaCliente  = document.getElementById('busca-cliente-modal');
const linhasBuscaCliente = document.getElementById('linhas-busca-cliente');
let clientesAtuais = [];
let indiceCliente = -1;
let timeoutBuscaCliente;
let contadorBuscaCliente = 0; // descarta respostas antigas se o operador digitar rápido

function abrirModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaCliente.value = '';
    inputBuscaCliente.focus();
    buscarClientes('');
}

function fecharModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    clientesAtuais = [];
    indiceCliente = -1;
}

async function buscarClientes(termo) {
    const minhaBusca = ++contadorBuscaCliente;
    let dados;

    try {
        const resp = await fetch(`${urlBuscaCliente}?termo=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar o servidor.`);
        dados = await resp.json();
    } catch (e) {
        if (minhaBusca === contadorBuscaCliente) {
            mostrarAviso('Falha ao buscar clientes:\n' + e.message, 'erro');
            clientesAtuais = [];
            indiceCliente = -1;
            renderizarClientes();
        }
        return;
    }

    if (minhaBusca !== contadorBuscaCliente) return;

    clientesAtuais = dados;
    indiceCliente = dados.length > 0 ? 0 : -1;
    renderizarClientes();
}

function renderizarClientes() {
    if (clientesAtuais.length === 0) {
        linhasBuscaCliente.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum cliente encontrado.</td></tr>';
        return;
    }

    linhasBuscaCliente.innerHTML = clientesAtuais.map((c, index) => {
        const destacado = index === indiceCliente;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarCliente(${index})">
                <td class="py-3 font-medium">${esc(c.nome)}</td>
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${esc(c.cpf_cnpj)}</td>
                <td class="py-3">${esc(c.uf)}</td>
                <td class="py-3">${esc(c.telefone)}</td>
            </tr>
        `;
    }).join('');

    linhasBuscaCliente.children[indiceCliente]?.scrollIntoView({ block: 'nearest' });
}

function selecionarCliente(index) {
    const c = clientesAtuais[index];
    if (!c) return;

    document.getElementById('campo-cliente').value = c.id;
    document.getElementById('texto-cliente-selecionado').textContent = `${c.nome} — ${c.cpf_cnpj}`;
    document.getElementById('limpar-cliente').classList.remove('hidden');
    fecharModalCliente();
}

function limparCliente() {
    document.getElementById('campo-cliente').value = '';
    document.getElementById('texto-cliente-selecionado').textContent = 'Selecionar cliente...';
    document.getElementById('limpar-cliente').classList.add('hidden');
}

inputBuscaCliente?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaCliente);
    timeoutBuscaCliente = setTimeout(() => buscarClientes(inputBuscaCliente.value.trim()), 300);
});

inputBuscaCliente?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalCliente(); return; }
    if (clientesAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceCliente = (indiceCliente + 1) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceCliente = (indiceCliente - 1 + clientesAtuais.length) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceCliente >= 0) selecionarCliente(indiceCliente);
    }
});

// ---------------------------------------------------------------
// Modal "Finalizar pedido" (descrição)
// ---------------------------------------------------------------
const modalFinalizar = document.getElementById('modal-finalizar');

function abrirModalFinalizar() {
    if (!document.getElementById('campo-data-pedido').value) return mostrarAviso('Informe a data do pedido.');
    if (!document.getElementById('campo-operador').value)    return mostrarAviso('Selecione o operador.');
    if (itens.length === 0)                                  return mostrarAviso('Inclua ao menos um item no pedido.');

    const semQtd = itens.some(i => !(parseFloat(i.quantidade) > 0));
    if (semQtd) return mostrarAviso('A quantidade de cada item deve ser maior que zero.');

    document.getElementById('resumo-finalizar').textContent =
        `${itens.length} ${itens.length === 1 ? 'item' : 'itens'} · total ${brl(totalPedido())}`;

    modalFinalizar.classList.remove('hidden');
    modalFinalizar.classList.add('flex');
    document.getElementById('campo-descricao').focus();
}

function fecharModalFinalizar() {
    modalFinalizar.classList.add('hidden');
    modalFinalizar.classList.remove('flex');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modalFinalizar.classList.contains('hidden')) fecharModalFinalizar();
});

// evita enviar duas vezes (duplo clique no botão de confirmar)
document.getElementById('form-pedido').addEventListener('submit', () => {
    const btn = document.getElementById('btn-confirmar-finalizar');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
});

renderizarItens();
campoCodigo.focus();
</script>
