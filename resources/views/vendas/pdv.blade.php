@extends('layouts.caixa')

@section('titulo', 'PDV - Venda')

@section('conteudo')

@if (!empty($pdvInativo))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <strong>Este PDV foi inativado no servidor.</strong>
        Novas vendas estão bloqueadas. Feche o caixa; contingências (F1) e cancelamentos continuam disponíveis.
        {{-- AJUSTE: use o nome real da rota que abre a tela de fechamento --}}
        <a href="{{ route('caixa.fechar-form') }}" class="underline font-semibold ml-1">Fechar caixa</a>
    </div>
@endif

<div class="h-screen flex flex-col overflow-hidden">

@include('caixa.partials.header-pdv')

<!-- Modal reimprimir cupom fiscal -->
<div id="modal-reimpressao" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Reimprimir Cupom Fiscal</h2>
            <button onclick="fecharModalReimpressao()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6">
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nº da NFC-e</label>
                    <input type="text" id="reimp-numero" inputmode="numeric" autocomplete="off" placeholder="Ex.: 123"
                           class="w-40 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Data da venda</label>
                    <input type="date" id="reimp-data"
                           class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition">
                </div>
                <p class="text-xs text-gray-400 flex-1 min-w-48">Com o número da NFC-e a busca ignora a data. Sem número, mostra as vendas da data escolhida.</p>
            </div>

            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[22%]">
                    <col class="w-[30%]">
                    <col class="w-[24%]">
                    <col class="w-[24%]">
                </colgroup>
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 px-2">NFC-e / Série</th>
                        <th class="py-2 px-2">Data e hora</th>
                        <th class="py-2 px-2 text-right">Total</th>
                        <th class="py-2 px-2">Situação</th>
                    </tr>
                </thead>
                <tbody id="linhas-reimpressao"></tbody>
            </table>

            <p id="msg-reimpressao" class="hidden text-sm text-center mt-3"></p>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter (ou clique) para escolher o cupom.</p>
        </div>
    </div>
</div>

<!-- Modal puxar pedido de venda -->
<div id="modal-pedidos" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Puxar Pedido de Venda</h2>
            <button onclick="fecharModalPedidos()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6">
            <input type="text" id="busca-pedido-modal" placeholder="Buscar por nº do pedido ou nome do cliente..." autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-[14%]">
                    <col class="w-[16%]">
                    <col class="w-[40%]">
                    <col class="w-[10%]">
                    <col class="w-[20%]">
                </colgroup>
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 px-2">Nº</th>
                        <th class="py-2 px-2">Data</th>
                        <th class="py-2 px-2">Cliente</th>
                        <th class="py-2 px-2 text-center">Itens</th>
                        <th class="py-2 px-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody id="linhas-pedidos"></tbody>
            </table>

            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para puxar os itens para o carrinho.</p>
        </div>
    </div>
</div>

<!-- Modal de busca de produto -->
<div id="modal-busca-produto" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Produto</h2>
            <button onclick="fecharModalBusca()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6">
            <p id="indicador-multiplicador" class="text-sm text-blue-600 font-semibold mb-2 hidden"></p>
            <input type="text" id="busca-produto-modal" placeholder="Buscar por nome, código ou código de barras..."
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col class="w-40">
                    <col>
                    <col class="w-28">
                    <col class="w-20">
                </colgroup>
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 px-2">Código</th>
                        <th class="py-2 px-2">Produto</th>
                        <th class="py-2 px-2">Preço</th>
                        <th class="py-2 px-2">Estoque</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-produto"></tbody>
            </table>

            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>


<!-- Modal produto de balança -->
<div id="modal-balanca" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Produto de Balança</h2>
            <button onclick="fecharModalBalanca()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <p id="balanca-nome-produto" class="text-sm text-gray-500 mb-1"></p>
        <p id="balanca-preco-kg" class="text-sm text-gray-600 mb-4"></p>

        <label class="block text-sm font-medium mb-1">Peso (KG)</label>
        <input type="number" step="0.001" min="0.001" id="balanca-peso" placeholder="Ex: 1.350"
               class="w-full border rounded-lg px-3 py-2 mb-2 text-lg font-mono">

        <p id="balanca-total-calculado" class="text-right text-blue-600 font-semibold text-sm mb-4"></p>
        <p id="balanca-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarProdutoBalanca()"
                class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold">
            Adicionar ao Carrinho
        </button>
    </div>
</div>


<!-- Modal de contingencias -->
<div id="modal-contingencias" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-start px-6 pt-5 pb-3">
            <div>
                <h2 class="text-lg font-bold">Vendas em Contingência</h2>
                <p class="text-xs text-gray-400">Clique em uma venda para ver o motivo e os itens.</p>
            </div>
            <button onclick="fecharModalContingencias()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex flex-wrap justify-between items-center gap-2 px-6 pb-3">
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" id="selecionar-todas" onchange="toggleTodas(this.checked)">
                Selecionar todas
            </label>
            <div class="flex items-center gap-3">
                <span id="progresso-emissao" class="text-sm text-gray-500 hidden"></span>
                <button id="btn-emitir-selecionadas" onclick="emitirSelecionadas()"
                        class="bg-gray-800 hover:bg-gray-700 text-white rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50">
                    Emitir selecionadas
                </button>
            </div>
        </div>

        <div class="overflow-y-auto border-t border-gray-100">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 text-amber-50 text-xs uppercase sticky top-0">
                    <tr>
                        <th class="w-10 px-4 py-2"></th>
                        <th class="text-left px-4 py-2">Venda</th>
                        <th class="text-left px-4 py-2">NFC-e</th>
                        <th class="text-left px-4 py-2">Série</th>
                        <th class="text-right px-4 py-2">Total</th>
                        <th class="text-left px-4 py-2">Data</th>
                        <th class="text-left px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody id="lista-contingencias" class="divide-y divide-gray-100">
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- Modal de autorização do supervisor (compartilhado entre F4 e F5) -->
<div id="modal-autorizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Autorização do Supervisor</h2>
            <button onclick="fecharModalAutorizacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <p class="text-sm text-gray-500 mb-4" id="autorizacao-descricao"></p>

        <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="col-span-1">
                <label class="block text-sm font-medium mb-1">Código</label>
                <input type="text" inputmode="numeric" autocomplete="off" id="autorizacao-usuario"
                       class="w-full border rounded px-3 py-2">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Supervisor</label>
                <input type="text" id="autorizacao-nome" readonly tabindex="-1" placeholder="—"
                       class="w-full border border-gray-200 bg-gray-50 rounded px-3 py-2 text-gray-800">
            </div>
        </div>

        <label class="block text-sm font-medium mb-1">Senha</label>
        <input type="password" id="autorizacao-senha" class="w-full border rounded px-3 py-2 mb-4">

        <p id="autorizacao-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarAutorizacao()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold">
            Autorizar
        </button>
    </div>
</div>


<!-- Modal de escolha de tipo de desconto -->
<div id="modal-tipo-desconto" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-bold">Tipo de Desconto</h2>
            <button onclick="fecharModalTipoDesconto()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <button onclick="escolherTipoDesconto('valor')"
                    class="flex flex-col items-center justify-center gap-2 border-2 border-gray-200 hover:border-blue-500 hover:bg-blue-50 rounded-xl p-6 transition">
                <span class="text-3xl">R$</span>
                <span class="text-sm font-semibold text-gray-700">1 - Por Valor</span>
            </button>
            <button onclick="escolherTipoDesconto('porcentagem')"
                    class="flex flex-col items-center justify-center gap-2 border-2 border-gray-200 hover:border-purple-500 hover:bg-purple-50 rounded-xl p-6 transition">
                <span class="text-3xl">%</span>
                <span class="text-sm font-semibold text-gray-700">2 - Por Porcentagem</span>
            </button>
        </div>
    </div>
</div>


<!-- Modal desconto em item (F4) - so aparece depois de autorizado -->
<div id="modal-desconto-item" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Desconto em Item</h2>
            <button onclick="fecharModalDescontoItem()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1">Número do item</label>
        <input type="number" min="1" id="desconto-item-numero" class="w-full border rounded px-3 py-2 mb-1">
        <p id="desconto-item-preview" class="text-xs text-gray-500 mb-3"></p>

        <label class="block text-sm font-medium mb-1" for-valor>Valor do desconto (R$)</label>
        <input type="number" step="0.01" min="0" id="desconto-item-valor" class="w-full border rounded px-3 py-2 mb-4">

        <p id="desconto-item-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarDescontoItem()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded font-semibold">
            Aplicar desconto
        </button>
    </div>
</div>

<!-- Modal desconto global (F5) - so aparece depois de autorizado -->
<div id="modal-desconto-global" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Desconto Geral</h2>
            <button onclick="fecharModalDescontoGlobal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1" for-valor>Valor do desconto (R$)</label>
        <input type="number" step="0.01" min="0" id="desconto-global-valor" class="w-full border rounded px-3 py-2 mb-4">

        <p id="desconto-global-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarDescontoGlobal()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded font-semibold">
            Aplicar desconto
        </button>
    </div>
</div>


<!-- Modal cancelar item (F6) - so aparece depois de autorizado -->
<div id="modal-cancelar-item" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Cancelar Item</h2>
            <button onclick="fecharModalCancelarItem()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <label class="block text-sm font-medium mb-1">Número do item</label>
        <input type="number" min="1" id="cancelar-item-numero" class="w-full border rounded px-3 py-2 mb-1">
        <p id="cancelar-item-preview" class="text-xs text-gray-500 mb-4"></p>

        <p id="cancelar-item-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button onclick="confirmarCancelarItem()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
            Cancelar item
        </button>
    </div>
</div>


<!-- Modal de inutilização -->
<div id="modal-inutilizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Inutilizar Numeração NFC-e</h2>
            <button onclick="fecharModalInutilizacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="bg-yellow-100 text-yellow-800 border border-yellow-300 rounded px-3 py-2 mb-4 text-xs">
            Atenção: esta ação é irreversível perante a SEFAZ. Use apenas para números pulados ou com falha técnica que nunca chegaram a ser autorizados.
        </div>

        <label class="block text-sm font-medium mb-1">Número inicial</label>
        <input type="number" id="inut-numero-inicial" class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1">Número final</label>
        <input type="number" id="inut-numero-final" class="w-full border rounded px-3 py-2 mb-3">

        <label class="block text-sm font-medium mb-1">Justificativa (mín. 15 caracteres)</label>
        <textarea id="inut-justificativa" rows="3" class="w-full border rounded px-3 py-2 mb-4"></textarea>

        <p id="inut-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button id="btn-inutilizar" onclick="confirmarInutilizacao()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold disabled:opacity-50">
            Inutilizar
        </button>
    </div>
</div>

<!-- Modal de cancelamento -->
<div id="modal-cancelamento" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-start px-6 pt-5 pb-3">
            <div>
                <h2 class="text-lg font-bold">Cancelar NFC-e</h2>
                <p class="text-xs text-gray-400">Últimas 20 vendas emitidas. Clique em uma para cancelar.</p>
            </div>
            <button onclick="fecharModalCancelamento()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto border-t border-gray-100">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 text-amber-50 text-xs uppercase sticky top-0">
                    <tr>
                        <th class="text-left px-4 py-2">NFC-e</th>
                        <th class="text-right px-4 py-2">Total</th>
                        <th class="text-left px-4 py-2">Data</th>
                        <th class="text-right px-4 py-2 w-40"></th>
                    </tr>
                </thead>
                <tbody id="lista-cancelamento" class="divide-y divide-gray-100">
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
    

<!-- Container do Grid ajustado para ocupar a altura restante da tela -->
<div class="grid grid-cols-3 gap-6 shadow-md flex-1 overflow-hidden pt-4 pb-4 max-w-6xl mx-auto px-6 w-full">
    
    <!-- Coluna da Esquerda (Carrinho) -->
    <div class="col-span-2 bg-white rounded-xl shadow-lg overflow-hidden flex flex-col h-full">
        <div class="p-4 shrink-0">
            <div class="relative">
                <input type="text" id="busca-produto" placeholder="🔍 Buscar por nome, código ou código de barras..."
                    class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition" autofocus>
            </div>
            <p id="badge-pedido" class="hidden mt-2 text-xs font-semibold text-blue-700 bg-blue-50 rounded-lg px-3 py-1.5"></p>
        </div>

        <!-- Container da tabela com scroll interno se passar da altura -->
        <div class="flex-1 overflow-y-auto">
            <table class="w-full">
                <thead class="bg-slate-800 sticky top-0 z-10">
                    <tr class="text-left text-xs text-slate-300 uppercase tracking-wide">
                        <th class="py-3 pl-4">Item</th>
                        <th class="py-3">Produto</th>
                        <th class="py-3">Qtd</th>
                        <th class="py-3">Preço</th>
                        <th class="py-3">Desconto</th>
                        <th class="py-3 pr-4">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="linhas-carrinho"></tbody>
            </table>
            <p id="carrinho-vazio" class="text-center text-gray-400 py-16">Nenhum item adicionado.</p>
        </div>
    </div>

    <!-- Coluna da Direita (Total e Pagamento) -->
    <div class="bg-white rounded-xl shadow-lg p-5 h-fit">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total</p>
        <p id="total-venda" class="text-4xl font-bold text-slate-900 mb-5">R$ 0,00</p>

        <div class="bg-gray-50 rounded-lg p-3 text-sm mb-5">
            <p class="flex justify-between">Desconto por item <strong id="desconto-item-exibido" class="text-green-600">R$ 0,00</strong></p>
        </div>

        <button id="btn-prosseguir" onclick="irParaPagamento()"
                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3.5 rounded-lg font-bold text-lg transition shadow-md disabled:opacity-50">
            Prosseguir para pagamento →
        </button>

        <p id="erro-itens" class="text-red-600 text-sm mt-2 hidden"></p>
    </div>
</div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/cupom-fiscal.js') }}"></script>


@php
    $itensParaCarrinho = collect($itensIniciais)->map(fn($i) => [
        'chave' => $i['produto_id'] . '-' . ($i['produto_variante_id'] ?? '0'),
        'produto_id' => $i['produto_id'],
        'produto_variante_id' => $i['produto_variante_id'],
        'nome' => $i['nome'],
        'preco' => $i['preco'],
        'quantidade' => $i['quantidade'],
        'desconto' => $i['desconto_bruto'] ?? 0,
    ])->values();
@endphp

<script>


let carrinho = @json($itensParaCarrinho);
let pedidoVendaId = @json($pedidoVendaIdInicial ?? null);       // pedido de venda de onde vieram os itens
let pedidoVendaNumero = @json($pedidoVendaNumeroInicial ?? null);
let quantidadeMultiplicador = 1;
let resultadosAtuais = [];
let indiceSelecionado = -1;
let descontoGlobal = {{ $descontoGlobalInicial }};
let tipoDescontoPendente = null;
const liberacoes = @json($liberacoes);
const chavePermissao = {
    item: 'desconto_item',
    global: 'desconto_global',
    cancelar_item: 'cancelar_item',
    limpar_pdv: 'cancelar_cupom',
    cancelar_nfce: 'cancelar_nfce',
    inutilizar: 'inutilizar',
};
let timeoutBusca;
let indiceDropdownCancelamento = -1;
let tipoDescontoEscolhido = null;
let _handlerTipoDesconto = null;
let produtoBalancaPendente = null;
let timeoutNomeSupervisor;
let ultimaBuscaSupervisor = 0;
const inputBusca = document.getElementById('busca-produto');
const inputBuscaModal = document.getElementById('busca-produto-modal');
const linhasBuscaDiv = document.getElementById('linhas-busca-produto');



function definirNomeSupervisor(texto, erro = false) {
    const campo = document.getElementById('autorizacao-nome');
    campo.value = texto;
    campo.classList.toggle('text-red-600', erro);
    campo.classList.toggle('text-gray-800', !erro);
}

async function buscarNomeSupervisor() {
    const codigo = document.getElementById('autorizacao-usuario').value.trim();
    const busca = ++ultimaBuscaSupervisor;

    if (!/^\d+$/.test(codigo)) {
        definirNomeSupervisor('');
        return;
    }

    try {
        const url = `{{ route('supervisor.usuario') }}?codigo=${encodeURIComponent(codigo)}`;
        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const dados = await resp.json();

        if (busca !== ultimaBuscaSupervisor) return; // chegou uma resposta mais nova

        if (dados.nome) {
            definirNomeSupervisor(dados.nome);
        } else {
            definirNomeSupervisor(dados.aviso || 'Supervisor não encontrado', true);
        }
    } catch (e) {
        if (busca === ultimaBuscaSupervisor) definirNomeSupervisor('');
    }
}

document.getElementById('autorizacao-usuario').addEventListener('input', () => {
    clearTimeout(timeoutNomeSupervisor);
    timeoutNomeSupervisor = setTimeout(buscarNomeSupervisor, 250);
});


function abrirModalBalanca(produto, variante) {
    produtoBalancaPendente = { produto, variante };

    document.getElementById('balanca-nome-produto').innerText = produto.nome;
    document.getElementById('balanca-preco-kg').innerText = `R$ ${Number(produto.preco_venda).toFixed(1)} por KG`;
    document.getElementById('balanca-peso').value = '';
    document.getElementById('balanca-total-calculado').innerText = '';
    document.getElementById('balanca-erro').classList.add('hidden');

    document.getElementById('modal-balanca').classList.remove('hidden');
    document.getElementById('modal-balanca').classList.add('flex');

    setTimeout(() => document.getElementById('balanca-peso').focus(), 100);
}

function fecharModalBalanca() {
    document.getElementById('modal-balanca').classList.add('hidden');
    document.getElementById('modal-balanca').classList.remove('flex');
    produtoBalancaPendente = null;
}

document.getElementById('balanca-peso')?.addEventListener('input', function () {
    const peso = parseFloat(this.value) || 0;
    const preco = produtoBalancaPendente ? Number(produtoBalancaPendente.produto.preco_venda) : 0;
    const total = peso * preco;

    document.getElementById('balanca-total-calculado').innerText = peso > 0
        ? `Total: R$ ${total.toFixed(2)}`
        : '';
});

document.getElementById('balanca-peso')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmarProdutoBalanca();
    }
});

function confirmarProdutoBalanca() {
    const peso = parseFloat(document.getElementById('balanca-peso').value) || 0;
    const erroP = document.getElementById('balanca-erro');

    if (peso <= 0) {
        erroP.innerText = 'Informe um peso válido (maior que zero).';
        erroP.classList.remove('hidden');
        return;
    }

    const { produto, variante } = produtoBalancaPendente;
    adicionarAoCarrinhoBalanca(produto, variante, peso);
    fecharModalBalanca();
}

function adicionarAoCarrinhoBalanca(produto, variante, peso) {
    const chaveBase = produto.id + '-' + (variante ? variante.id : '0') + '-balanca';

    // Produto de balança NUNCA agrupa — cada pesagem é uma linha separada
    const chave = chaveBase + '-' + Date.now();

    carrinho.push({
        chave,
        produto_id: produto.id,
        produto_variante_id: variante ? variante.id : null,
        nome: produto.nome + ` (R$ ${peso.toFixed(1)} por KG)`,
        preco: parseFloat(produto.preco_venda) * peso, // preço total = preco_kg * peso
        quantidade: 1, // sempre 1 — o "peso" já está incorporado no preço
        eh_balanca: true,
        peso_kg: peso,
    });

    renderizarCarrinho();
}


// Atualize ou adicione este ouvinte de eventos global para as setas e Enter
document.addEventListener('keydown', (e) => {
    const dropdown = document.getElementById('dropdown-cancelamento');
    const aberto = dropdown && !dropdown.classList.contains('hidden');

    if (aberto) {
        const opcoes = dropdown.querySelectorAll('.opcao-dropdown');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            indiceDropdownCancelamento = (indiceDropdownCancelamento + 1) % opcoes.length;
            atualizarFocoDropdownCancelamento(opcoes);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            indiceDropdownCancelamento = (indiceDropdownCancelamento - 1 + opcoes.length) % opcoes.length;
            atualizarFocoDropdownCancelamento(opcoes);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (indiceDropdownCancelamento >= 0 && opcoes[indiceDropdownCancelamento]) {
                opcoes[indiceDropdownCancelamento].click();
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            fecharDropdownCancelamento();
        }
    }
});


function atualizarFocoDropdownCancelamento(opcoes) {
    opcoes.forEach((opcao, index) => {
        if (index === indiceDropdownCancelamento) {
            opcao.classList.add('bg-slate-800', 'text-white');
            opcao.classList.remove('text-slate-300');
            opcao.focus();
        } else {
            opcao.classList.remove('bg-slate-800', 'text-white');
            opcao.classList.add('text-slate-300');
        }
    });
}

// Modifique a função que abre o dropdown para resetar o índice
function toggleDropdownCancelamento() {
    const dropdown = document.getElementById('dropdown-cancelamento');
    dropdown.classList.toggle('hidden');
    
    if (!dropdown.classList.contains('hidden')) {
        indiceDropdownCancelamento = -1; // Reseta ao abrir
    }
}

function fecharDropdownCancelamento() {
    const dropdown = document.getElementById('dropdown-cancelamento');
    if (dropdown) {
        dropdown.classList.add('hidden');
        indiceDropdownCancelamento = -1;
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'F1') { e.preventDefault(); abrirModalContingencias(); }
    if (e.key === 'F2') { e.preventDefault(); abrirModalInutilizacao(); }
    if (e.key === 'F3') { e.preventDefault(); toggleDropdownCancelamento(); }
    if (e.key === 'F4') { e.preventDefault(); abrirModalDescontoItem(); }
    if (e.key === 'F6') { e.preventDefault(); abrirModalPedidos(); }
    if (e.key === 'F7') { e.preventDefault(); abrirModalReimpressao(); }
    if (e.key === 'Escape') { fecharTodosModais(); }
});


inputBuscaModal.addEventListener('keydown', (e) => {
    // Enter forca a busca imediata, mesmo se o texto for so numeros
    // (util pra codigo de produto puramente numerico, sem multiplicador)
    if (e.key === 'Enter' && resultadosAtuais.length === 0 && inputBuscaModal.value.trim().length > 0) {
        e.preventDefault();
        clearTimeout(timeoutBusca);   // <-- novo
        const { multiplicador, termo } = interpretarBusca(inputBuscaModal.value);
        quantidadeMultiplicador = multiplicador;
        if (termo.length > 0) {
            buscarProduto(termo);
        }
        return;
    }

    if (resultadosAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceSelecionado = (indiceSelecionado + 1) % resultadosAtuais.length;
        renderizarResultados();
        scrollParaSelecionado();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceSelecionado = (indiceSelecionado - 1 + resultadosAtuais.length) % resultadosAtuais.length;
        renderizarResultados();
        scrollParaSelecionado();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceSelecionado >= 0) {
            selecionarResultado(indiceSelecionado);
        }
    }
});


inputBuscaModal.addEventListener('input', () => {
    clearTimeout(timeoutBusca);
    const valor = inputBuscaModal.value;

    if (/^\d+$/.test(valor)) {
        atualizarIndicadorMultiplicador();

        // Código numérico longo (EAN 8/12/13/14): busca sozinho após uma pausa curta
        if (valor.length >= 8) {
            timeoutBusca = setTimeout(() => buscarProduto(valor), 150);
        }
        return;
    }

    const { multiplicador, termo } = interpretarBusca(valor);
    quantidadeMultiplicador = multiplicador;
    atualizarIndicadorMultiplicador();

    if (termo.length < 1) {
        fecharModalBusca();
        resultadosAtuais = [];
        indiceSelecionado = -1;
        return;
    }

    timeoutBusca = setTimeout(() => buscarProduto(termo), 300);
});


inputBusca.addEventListener('keydown', (e) => {
    // Mesma trava - se o modal ja esta aberto, esse campo nao participa mais da busca
    if (!document.getElementById('modal-busca-produto').classList.contains('hidden')) {
        return;
    }

    if (e.key === 'Enter' && inputBusca.value.trim().length > 0) {
        e.preventDefault();
        clearTimeout(timeoutBusca);   // <-- novo
        const { multiplicador, termo } = interpretarBusca(inputBusca.value);
        quantidadeMultiplicador = multiplicador;
        if (termo.length > 0) {
            buscarProduto(termo);
        }
    }
});



// Fecha o dropdown se o operador clicar fora dele
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('dropdown-cancelamento');
    const container = dropdown?.closest('.relative');
    if (dropdown && !dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
        fecharDropdownCancelamento();
    }
});



function interpretarBusca(valor) {
    const v = valor.trim();

    // "5*qualquer coisa"  ou  "5x7891234567890" (x só vale se o resto for número/código)
    const match = v.match(/^(\d+)\*(.*)$/) || v.match(/^(\d+)[xX](\d+)$/);

    if (match) {
        return {
            multiplicador: Math.max(1, parseInt(match[1])),
            termo: match[2].trim(),
        };
    }

    return { multiplicador: 1, termo: v };
}



function atualizarIndicadorMultiplicador() {
    const indicador = document.getElementById('indicador-multiplicador');

    if (quantidadeMultiplicador > 1) {
        indicador.innerText = `Quantidade: ${quantidadeMultiplicador}x`;
        indicador.classList.remove('hidden');
    } else {
        indicador.classList.add('hidden');
    }
}



function fecharModalTipoDesconto() {
    document.getElementById('modal-tipo-desconto').classList.add('hidden');
    document.getElementById('modal-tipo-desconto').classList.remove('flex');
    tipoDescontoEscolhido = null;

    if (_handlerTipoDesconto) {
        document.removeEventListener('keydown', _handlerTipoDesconto);
        _handlerTipoDesconto = null;
    }
}

function escolherTipoDesconto(tipo) {
    tipoDescontoEscolhido = tipo;

    if (_handlerTipoDesconto) {
        document.removeEventListener('keydown', _handlerTipoDesconto);
        _handlerTipoDesconto = null;
    }

    document.getElementById('modal-tipo-desconto').classList.add('hidden');
    document.getElementById('modal-tipo-desconto').classList.remove('flex');

    if (tipoDescontoPendente === 'item') {
        abrirLancamentoDescontoItem();
    } else if (tipoDescontoPendente === 'global') {
        abrirLancamentoDescontoGlobal();
    }
}

function scrollParaSelecionado() {
    const elemento = linhasBuscaDiv.querySelector(`[data-index="${indiceSelecionado}"]`);
    elemento?.scrollIntoView({ block: 'nearest' });
}



function fecharTodosModais() {
    const idsModais = [
        'modal-contingencias',
        'modal-inutilizacao',
        'modal-cancelamento',
        'modal-desconto-item',
        'modal-desconto-global',
        'modal-cancelar-item',
        'modal-autorizacao',
        'modal-tipo-desconto',
        'modal-busca-produto',
        'modal-balanca',
        'modal-pedidos',
        'modal-reimpressao',
    ];

    idsModais.forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });

    if (_handlerTipoDesconto) {
        document.removeEventListener('keydown', _handlerTipoDesconto);
        _handlerTipoDesconto = null;
    }

    fecharDropdownCancelamento();
    tipoDescontoPendente = null;
}



inputBusca.addEventListener('input', () => {
    // Se o modal ja esta aberto, quem manda na busca e o campo de dentro dele - ignora esse
    if (!document.getElementById('modal-busca-produto').classList.contains('hidden')) {
        return;
    }

    clearTimeout(timeoutBusca);
    const valor = inputBusca.value;

    if (/^\d+$/.test(valor)) {
        // Código numérico longo (EAN 8/12/13/14): busca sozinho após uma pausa curta
        if (valor.length >= 8) {
            timeoutBusca = setTimeout(() => buscarProduto(valor), 150);
        }
        return;
    }

    const { multiplicador, termo } = interpretarBusca(valor);
    quantidadeMultiplicador = multiplicador;

    if (termo.length < 1) {
        return;
    }

    timeoutBusca = setTimeout(() => buscarProduto(termo), 300);
});



function bateCodigoExato(op, termo) {
    const t = String(termo).trim();
    if (op.variante) return String(op.variante.codigo_barras ?? '') === t;
    return String(op.produto.codigo_barras ?? '') === t
        || String(op.produto.codigo_interno ?? '') === t;
}

async function buscarProduto(termo, quantidade = quantidadeMultiplicador) {
    const resp = await fetch(`{{ route('vendas.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();

    resultadosAtuais = [];
    produtos.forEach(p => {
        if (p.tem_variacao && p.variantes.length > 0) {
            const exatas = p.variantes.filter(v => v.codigo_barras && v.codigo_barras === termo);
            (exatas.length ? exatas : p.variantes).forEach(v => resultadosAtuais.push({ produto: p, variante: v }));
        } else {
            resultadosAtuais.push({ produto: p, variante: null });
        }
    });

    // Leitor de código de barras: se só um item bate EXATAMENTE com o código,
    // vai direto pro carrinho (com o multiplicador, se houver) sem abrir o modal.
    const exatos = resultadosAtuais.filter(op => bateCodigoExato(op, termo));
    if (exatos.length === 1) {
        const op = exatos[0];
        resultadosAtuais = [];
        indiceSelecionado = -1;
        fecharModalBusca();

        if (op.produto.produto_balanca) {
            abrirModalBalanca(op.produto, op.variante);
        } else {
            adicionarAoCarrinho(op.produto, op.variante, quantidade);
        }
        return;
    }

    indiceSelecionado = resultadosAtuais.length > 0 ? 0 : -1;
    abrirModalBusca();
    renderizarResultados();
}


function abrirModalBusca() {
    const modal = document.getElementById('modal-busca-produto');
    const jaEstavaAberto = !modal.classList.contains('hidden');

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!jaEstavaAberto) {
        // So sincroniza o texto do campo de tras na PRIMEIRA vez que o modal abre.
        // Enquanto o modal ja estiver aberto, o campo dele manda sozinho - nunca sobrescreve.
        inputBuscaModal.value = inputBusca.value;
        inputBusca.value = '';
        inputBuscaModal.focus();
    }
}

function fecharModalBusca() {
    document.getElementById('modal-busca-produto').classList.add('hidden');
    document.getElementById('modal-busca-produto').classList.remove('flex');
    inputBusca.value = '';
    inputBuscaModal.value = '';
    quantidadeMultiplicador = 1;
    document.getElementById('indicador-multiplicador').classList.add('hidden');
    inputBusca.focus();
}


function renderizarResultados() {
    if (resultadosAtuais.length === 0) {
        linhasBuscaDiv.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }

linhasBuscaDiv.innerHTML = resultadosAtuais.map((op, index) => {
    const destacado = index === indiceSelecionado;
    const codigo = op.variante?.codigo_barras || op.produto.codigo_barras || op.produto.codigo_interno;
    const nome = op.variante
        ? `${op.produto.nome} — ${op.variante.cor ?? ''} ${op.variante.tamanho ?? ''}`
        : op.produto.nome;
    const preco = Number(op.produto.preco_venda).toFixed(2);
    const estoque = op.variante ? op.variante.estoque : op.produto.estoque;

    return `
        <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
            data-index="${index}"
            onclick="selecionarResultado(${index})">
            <td class="py-3 px-2 font-mono text-sm truncate ${destacado ? 'text-slate-300' : 'text-gray-500'}">${codigo}</td>
            <td class="py-3 px-2 font-medium truncate" title="${nome}">${nome}</td>
            <td class="py-3 px-2 whitespace-nowrap ${destacado ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">R$ ${preco}</td>
            <td class="py-3 px-2 ${destacado ? 'text-slate-300' : 'text-gray-500'}">${estoque}</td>
        </tr>
    `;
}).join('');
}


function selecionarResultado(index) {
    const op = resultadosAtuais[index];

    // Guarda o multiplicador ANTES de fechar o modal
    const quantidadeSelecionada = quantidadeMultiplicador;

    resultadosAtuais = [];
    indiceSelecionado = -1;
    fecharModalBusca();

    if (op.produto.produto_balanca) {
        abrirModalBalanca(op.produto, op.variante);
    } else {
        adicionarAoCarrinho(op.produto, op.variante, quantidadeSelecionada);
    }
}


function adicionarAoCarrinho(produto, variante, quantidade = quantidadeMultiplicador) {
    const chaveBase = produto.id + '-' + (variante ? variante.id : '0');
    const existente = carrinho.find(i => i.chave === chaveBase && !i.cancelado);

    if (existente) {
        existente.quantidade += quantidade;
    } else {
        const chave = carrinho.some(i => i.chave === chaveBase)
            ? chaveBase + '-' + Date.now()
            : chaveBase;
        carrinho.push({
            chave,
            produto_id: produto.id,
            produto_variante_id: variante ? variante.id : null,
            nome: produto.nome + (variante ? ` — ${variante.cor ?? ''} ${variante.tamanho ?? ''}` : ''),
            preco: parseFloat(produto.preco_venda),
            quantidade: quantidade,
        });
    }

    quantidadeMultiplicador = 1; // reseta pra proxima busca
    renderizarCarrinho();
}


function renderizarCarrinho() {
    const tbody = document.getElementById('linhas-carrinho');
    const vazio = document.getElementById('carrinho-vazio');

    if (carrinho.length === 0) {
        tbody.innerHTML = '';
        vazio.classList.remove('hidden');
        atualizarTotais();
        return;
    }

    vazio.classList.add('hidden');

    const itensCalculados = calcularItensComDescontoRateado();

tbody.innerHTML = itensCalculados.map((item, index) => `
    <tr class="border-b border-gray-100 hover:bg-slate-50 transition ${item.cancelado ? 'bg-red-50' : ''}">
        <td class="py-3 pl-4 text-gray-400 font-mono text-sm">${index + 1}</td>
        <td class="py-3 font-medium ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-800'}">${item.nome}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-600'}">${item.quantidade}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-600'}">R$ ${item.preco.toFixed(2)}</td>
        <td class="py-3 ${item.cancelado ? 'text-red-500 line-through' : (item.descontoEfetivo > 0 ? 'text-green-600 font-medium' : 'text-gray-400')}">R$ ${item.descontoEfetivo.toFixed(2)}</td>
        <td class="py-3 pr-4 font-semibold ${item.cancelado ? 'text-red-500 line-through' : 'text-gray-900'}">R$ ${item.subtotalLiquido.toFixed(2)}</td>
        ${item.cancelado ? `<td class="py-3 pr-4 text-red-600 text-xs font-bold text-right">✕ Cancelado</td>` : ''}
    </tr>
`).join('');

    atualizarTotais();
}



function abrirModalContingencias() {
    document.getElementById('modal-contingencias').classList.remove('hidden');
    document.getElementById('modal-contingencias').classList.add('flex');
    carregarContingencias();
}

function fecharModalContingencias() {
    document.getElementById('modal-contingencias').classList.add('hidden');
    document.getElementById('modal-contingencias').classList.remove('flex');
}

function moedaBR(valor) {
    return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}


const BADGE_CONTINGENCIA = '<span class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-medium">Contingência</span>';

function definirStatusLinha(id, html) {
    const linha = document.querySelector(`.check-contingencia[value="${id}"]`)?.closest('tr');
    const celula = linha?.querySelector('.status-celula');
    if (celula) celula.innerHTML = html;
}

function marcarLinhaNaFila(id) {
    definirStatusLinha(id, '<span class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-xs font-medium">Na fila</span>');
}

function marcarLinhaEmitindo(id) {
    definirStatusLinha(id, `
        <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-700 animate-pulse">
            <span class="inline-block w-3.5 h-3.5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></span>
            Emitindo venda...
        </span>`);
}

function restaurarLinhaContingencia(id) {
    definirStatusLinha(id, BADGE_CONTINGENCIA);
}

async function carregarContingencias() {
    const resp = await fetch('{{ route("contingencias.listar") }}');
    const vendas = await resp.json();

    const container = document.getElementById('lista-contingencias');

    if (vendas.length === 0) {
        container.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Nenhuma venda em contingência.</td></tr>';
        return;
    }

    container.innerHTML = vendas.map(v => `
        <tr class="hover:bg-gray-50 cursor-pointer" onclick="toggleExpandir('${v.id}')">
            <td class="px-4 py-2" onclick="event.stopPropagation()">
                <input type="checkbox" class="check-contingencia" value="${v.id}">
            </td>
            <td class="px-4 py-2">#${String(v.venda_id ?? v.id).split('-')[0]}</td>
            <td class="px-4 py-2">${v.numero_nfce ?? '—'}</td>
            <td class="px-4 py-2">${v.serie_nfce ?? '—'}</td>
            <td class="px-4 py-2 text-right">${moedaBR(v.total)}</td>
            <td class="px-4 py-2 text-gray-500">${v.criada_em}</td>
            <td class="px-4 py-2 status-celula">
                ${BADGE_CONTINGENCIA}
            </td>
        </tr>
        <tr id="detalhe-${v.id}" class="hidden bg-gray-50">
            <td></td>
            <td colspan="6" class="px-4 py-3 text-sm">
                <p class="text-red-600 mb-2"><strong>Motivo:</strong> ${v.motivo ?? 'Não informado'}</p>
                <p class="text-gray-600"><strong>Itens:</strong> ${v.itens.join(', ')}</p>
                ${v.chave_nfe ? `<p class="text-xs text-gray-400 break-all mt-2">Chave: ${v.chave_nfe}</p>` : ''}
            </td>
        </tr>
    `).join('');
}

function toggleExpandir(id) {
    document.getElementById(`detalhe-${id}`).classList.toggle('hidden');
}

function toggleTodas(marcado) {
    document.querySelectorAll('.check-contingencia').forEach(cb => {
        if (!cb.disabled) cb.checked = marcado;
    });
}

async function emitirSelecionadas() {
    const ids = Array.from(document.querySelectorAll('.check-contingencia:checked')).map(cb => cb.value);

    if (ids.length === 0) {
        alert('Selecione ao menos uma venda.');
        return;
    }

    const btn = document.getElementById('btn-emitir-selecionadas');
    const progresso = document.getElementById('progresso-emissao');

    btn.disabled = true;
    progresso.classList.remove('hidden');
    document.querySelectorAll('.check-contingencia').forEach(cb => cb.disabled = true);
    ids.forEach(marcarLinhaNaFila);

    let sucesso = 0;
    let falha = 0;
    const falhas = {};

    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        progresso.innerText = `${i + 1} / ${ids.length} processando...`;
        marcarLinhaEmitindo(id);

        try {
            const resp = await fetch(`/contingencias/${id}/reenviar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
            });

            let resultado;
            try {
                resultado = await resp.json();
            } catch (e) {
                resultado = { sucesso: false, erro: `Resposta inesperada do servidor (HTTP ${resp.status}).` };
            }

            if (resultado.sucesso) {
                sucesso++;
                marcarLinhaEmitida(id);
            } else {
                falha++;
                falhas[id] = resultado.erro;
                restaurarLinhaContingencia(id);
                atualizarMotivoLinha(id, resultado.erro);
            }
        } catch (e) {
            falha++;
            falhas[id] = 'Erro de conexão ao tentar emitir.';
            restaurarLinhaContingencia(id);
            atualizarMotivoLinha(id, falhas[id]);
        }

        progresso.innerText = `${i + 1} / ${ids.length} — ${sucesso} emitida(s), ${falha} pendente(s)`;
    }

    btn.disabled = false;

    setTimeout(async () => {
        progresso.classList.add('hidden');
        await carregarContingencias(); // atualiza a lista, removendo as que foram emitidas

        // A lista recarregada recolhe os detalhes: reabre as que falharam, com o motivo
        Object.entries(falhas).forEach(([id, erro]) => atualizarMotivoLinha(id, erro));
    }, 1500);
}

function marcarLinhaEmitida(id) {
    const check = document.querySelector(`.check-contingencia[value="${id}"]`);
    const linha = check?.closest('tr');
    if (!linha) return;

    linha.classList.add('opacity-40');
    check.checked = false;
    check.disabled = true;

    const celula = linha.querySelector('.status-celula');
    if (celula) {
        celula.innerHTML = '<span class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-xs font-medium">✓ Emitida</span>';
    }
}

function atualizarMotivoLinha(id, motivo) {
    const detalhe = document.getElementById(`detalhe-${id}`);
    if (detalhe) {
        detalhe.querySelector('p').innerHTML = `<strong>Motivo:</strong> ${motivo}`;
        detalhe.classList.remove('hidden'); // expande automaticamente pra mostrar o novo motivo
    }
}


function abrirModalInutilizacao() {
    solicitarAutorizacao('inutilizar', 'Autorização necessária para inutilizar uma numeração.');
}

function mostrarModalInutilizacao() {
    document.getElementById('inut-numero-inicial').value = '';
    document.getElementById('inut-numero-final').value = '';
    document.getElementById('inut-justificativa').value = '';
    document.getElementById('inut-erro').classList.add('hidden');

    document.getElementById('modal-inutilizacao').classList.remove('hidden');
    document.getElementById('modal-inutilizacao').classList.add('flex');
}

function fecharModalInutilizacao() {
    document.getElementById('modal-inutilizacao').classList.add('hidden');
    document.getElementById('modal-inutilizacao').classList.remove('flex');
}

async function confirmarInutilizacao() {
    const numeroInicial = document.getElementById('inut-numero-inicial').value;
    const numeroFinal = document.getElementById('inut-numero-final').value;
    const justificativa = document.getElementById('inut-justificativa').value;
    const erroP = document.getElementById('inut-erro');
    const btn = document.getElementById('btn-inutilizar');

    if (btn.disabled) return;

    erroP.classList.add('hidden');

    if (!numeroInicial || !numeroFinal || justificativa.length < 15) {
        erroP.innerText = 'Preencha os números e uma justificativa com pelo menos 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm(`Confirma a inutilização da numeração ${numeroInicial} a ${numeroFinal}? Esta ação não pode ser desfeita.`)) {
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Inutilizando...';
    btn.classList.add('animate-pulse');

    try {
        const resp = await fetch('{{ route("inutilizacao.executar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ numero_inicial: numeroInicial, numero_final: numeroFinal, justificativa }),
        });

        let resultado;
        try {
            resultado = await resp.json();
        } catch (e) {
            resultado = { sucesso: false, erro: `Resposta inesperada do servidor (HTTP ${resp.status}). Veja storage/logs/laravel.log.` };
        }

        if (resultado.sucesso) {
            alert('Numeração inutilizada com sucesso. Protocolo: ' + resultado.protocolo);
            fecharModalInutilizacao();
        } else {
            const validacao = resultado.errors ? Object.values(resultado.errors).flat().join(' ') : null;
            erroP.innerText = resultado.erro ?? validacao ?? resultado.message ?? 'Não foi possível inutilizar.';
            erroP.classList.remove('hidden');
        }
    } catch (e) {
        erroP.innerText = 'Erro de conexão ao tentar inutilizar.';
        erroP.classList.remove('hidden');
    }

    btn.disabled = false;
    btn.innerText = 'Inutilizar';
    btn.classList.remove('animate-pulse');
}


function abrirModalCancelamento() {
    document.getElementById('modal-cancelamento').classList.remove('hidden');
    document.getElementById('modal-cancelamento').classList.add('flex');
    carregarVendasCancelamento();
}

function fecharModalCancelamento() {
    document.getElementById('modal-cancelamento').classList.add('hidden');
    document.getElementById('modal-cancelamento').classList.remove('flex');
}

async function carregarVendasCancelamento() {
    const resp = await fetch('{{ route("cancelamento.listar") }}');
    const vendas = await resp.json();

    const container = document.getElementById('lista-cancelamento');

    if (vendas.length === 0) {
        container.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Nenhuma venda emitida encontrada.</td></tr>';
        return;
    }

    container.innerHTML = vendas.map(v => `
        <tr class="hover:bg-gray-50 cursor-pointer" onclick="toggleExpandirCancelamento('${v.id}')">
            <td class="px-4 py-2 font-medium">${v.numero_nfce ?? '—'}</td>
            <td class="px-4 py-2 text-right">${moedaBR(v.total)}</td>
            <td class="px-4 py-2 text-gray-500">${v.criada_em}</td>
            <td class="px-4 py-2 text-right">
                <span id="cancel-badge-${v.id}" class="hidden text-xs font-semibold text-blue-700 animate-pulse mr-2">Cancelando venda...</span>
                <span class="text-gray-400 text-xs">▼</span>
            </td>
        </tr>
        <tr id="cancel-detalhe-${v.id}" class="hidden bg-gray-50">
            <td colspan="4" class="px-4 py-3 text-sm">
                <p class="text-gray-600 mb-1"><strong>Itens:</strong> ${v.itens.join(', ')}</p>
                <p class="text-xs text-gray-400 break-all mb-3">Chave: ${v.chave_nfe ?? '—'}</p>

                <label class="block text-xs font-medium mb-1">Justificativa (mín. 15 caracteres)</label>
                <textarea id="just-${v.id}" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-2 text-sm"></textarea>

                <p id="cancel-erro-${v.id}" class="text-red-600 text-xs mb-2 hidden"></p>
                <p id="cancel-status-${v.id}" class="hidden text-sm text-blue-700 font-medium mb-2">
                    <span class="inline-block w-4 h-4 mr-2 align-middle border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></span>Cancelando venda...
                </p>

                <button id="cancel-btn-${v.id}" onclick="confirmarCancelamento('${v.id}')"
                        class="border border-red-300 text-red-700 rounded-lg px-4 py-2 text-xs font-semibold hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Confirmar cancelamento
                </button>
            </td>
        </tr>
    `).join('');
}

function toggleExpandirCancelamento(id) {
    document.getElementById(`cancel-detalhe-${id}`).classList.toggle('hidden');
}

async function confirmarCancelamento(id) {
    const campo = document.getElementById(`just-${id}`);
    const erroP = document.getElementById(`cancel-erro-${id}`);
    const statusP = document.getElementById(`cancel-status-${id}`);
    const badge = document.getElementById(`cancel-badge-${id}`);
    const btn = document.getElementById(`cancel-btn-${id}`);

    if (btn.disabled) return; // evita clique duplo

    const justificativa = campo.value;
    erroP.classList.add('hidden');

    if (justificativa.length < 15) {
        erroP.innerText = 'A justificativa precisa ter no mínimo 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm('Confirma o cancelamento desta NFC-e? Esta ação é irreversível.')) return;

    const ocupado = (sim) => {
        btn.disabled = sim;
        campo.disabled = sim;
        btn.innerText = sim ? 'Cancelando...' : 'Confirmar cancelamento';
        statusP.classList.toggle('hidden', !sim);
        badge.classList.toggle('hidden', !sim);
    };

    ocupado(true);

    try {
        const resp = await fetch(`/cancelamento/${id}/cancelar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ justificativa }),
        });

        let resultado;
        try {
            resultado = await resp.json();
        } catch (e) {
            resultado = { sucesso: false, erro: `Resposta inesperada do servidor (HTTP ${resp.status}).` };
        }

        if (resultado.sucesso) {
            ocupado(false);
            alert('NFC-e cancelada com sucesso. Protocolo: ' + resultado.protocolo);
            carregarVendasCancelamento();
            return;
        }

        erroP.innerText = resultado.erro;
        erroP.classList.remove('hidden');
    } catch (e) {
        erroP.innerText = 'Erro de conexão ao tentar cancelar.';
        erroP.classList.remove('hidden');
    }

    ocupado(false);
}



function calcularItensComDescontoRateado() {
    const itensAtivos = carrinho.filter(i => !i.cancelado);
    const subtotalBrutoGeral = itensAtivos.reduce((soma, i) => soma + (i.preco * i.quantidade), 0);

    const rateios = {};
    let somaRateios = 0;

    itensAtivos.forEach((item, index) => {
        const subtotalBruto = item.preco * item.quantidade;
        let rateio;

        if (index === itensAtivos.length - 1) {
            rateio = descontoGlobal - somaRateios;
        } else {
            rateio = subtotalBrutoGeral > 0
                ? Math.round((descontoGlobal * (subtotalBruto / subtotalBrutoGeral)) * 100) / 100
                : 0;
            somaRateios += rateio;
        }

        rateios[item.chave] = rateio;
    });

    return carrinho.map(item => {
        const subtotalBruto = item.preco * item.quantidade;

        if (item.cancelado) {
            return { ...item, descontoEfetivo: 0, subtotalBruto, subtotalLiquido: 0 };
        }

        const descontoItem = item.desconto ?? 0;
        const rateio = rateios[item.chave] ?? 0;
        const descontoEfetivo = Math.min(descontoItem + rateio, subtotalBruto);

        return { ...item, descontoEfetivo, subtotalBruto, subtotalLiquido: subtotalBruto - descontoEfetivo };
    });
}



function atualizarTotais() {
    const itensCalculados = calcularItensComDescontoRateado();
    const totalLiquido = itensCalculados.reduce((soma, i) => soma + i.subtotalLiquido, 0);
    const descontoPorItem = carrinho
        .filter(i => !i.cancelado)
        .reduce((soma, i) => soma + (i.desconto ?? 0), 0);

    document.getElementById('total-venda').innerText = 'R$ ' + totalLiquido.toFixed(2).replace('.', ',');
    document.getElementById('desconto-item-exibido').innerText = 'R$ ' + descontoPorItem.toFixed(2).replace('.', ',');
}


function executarAcaoAutorizada() {
    if (tipoDescontoPendente === 'item' || tipoDescontoPendente === 'global') {
        // Desconto: vai para escolha de tipo primeiro
        document.getElementById('modal-tipo-desconto').classList.remove('hidden');
        document.getElementById('modal-tipo-desconto').classList.add('flex');
        _handlerTipoDesconto = function (e) {
            if (e.key === '1') { e.preventDefault(); escolherTipoDesconto('valor'); }
            if (e.key === '2') { e.preventDefault(); escolherTipoDesconto('porcentagem'); }
        };
        document.addEventListener('keydown', _handlerTipoDesconto);

    } else if (tipoDescontoPendente === 'cancelar_item') {
        abrirLancamentoCancelarItem();
    } else if (tipoDescontoPendente === 'limpar_pdv') {
        executarLimparPdv();
    } else if (tipoDescontoPendente === 'cancelar_nfce') {
        abrirModalCancelamento();
    } else if (tipoDescontoPendente === 'inutilizar') {
        mostrarModalInutilizacao();
    }
}

function solicitarAutorizacao(tipo, descricao) {    
    tipoDescontoPendente = tipo;    
    // Operador liberado para esta ação: executa direto, sem pedir supervisor    
    if (liberacoes[chavePermissao[tipo]]) {        
        executarAcaoAutorizada();        
        return;    
    }    
    abrirModalAutorizacao(descricao);
}

function solicitarCancelamentoNfce() {    
    solicitarAutorizacao('cancelar_nfce', 'Autorização necessária para cancelar uma NFC-e.');
}

function abrirModalDescontoItem() {    
    if (carrinho.length === 0) {        
        alert('Adicione um item ao carrinho primeiro.');        
        return;    
    }    
    solicitarAutorizacao('item', 'Autorização necessária para aplicar desconto em item.');
}

function abrirModalCancelarItem() {    
    if (carrinho.length === 0) {        
        alert('Não há itens no carrinho.');        
        return;    
    }    
    solicitarAutorizacao('cancelar_item', 'Autorização necessária para cancelar um item.');
}

function abrirModalLimparPdv() {    
    if (carrinho.length === 0) {        
        alert('O carrinho já está vazio.');        
        return;    
    }    
    solicitarAutorizacao('limpar_pdv', 'Autorização necessária para cancelar o cupom (limpar todos os itens).');
}

function abrirModalDescontoGlobal() {
    tipoDescontoPendente = 'global';

    // Operador liberado para o desconto geral: segue direto, sem pedir supervisor
    if (liberacoes.desconto_global) {
        abrirEscolhaTipoDesconto();
        return;
    }

    document.getElementById('autorizacao-descricao').innerText = 'Autorização necessária para aplicar desconto geral.';
    document.getElementById('autorizacao-usuario').value = '';
    definirNomeSupervisor('');
    document.getElementById('autorizacao-senha').value = '';
    document.getElementById('autorizacao-erro').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('hidden');
    document.getElementById('modal-autorizacao').classList.add('flex');
}


document.getElementById('desconto-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('desconto-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome}` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-3' : 'text-xs text-red-500 mb-3';
});



function fecharModalDescontoItem() {
    document.getElementById('modal-desconto-item').classList.add('hidden');
    document.getElementById('modal-desconto-item').classList.remove('flex');
}

function confirmarDescontoItem() {
    const numero = parseInt(document.getElementById('desconto-item-numero').value);
    const indice = numero - 1;
    let entrada = parseFloat(document.getElementById('desconto-item-valor').value) || 0;
    const erroP = document.getElementById('desconto-item-erro');

    const item = carrinho[indice];

    if (!item) {
        erroP.innerText = 'Número de item inválido.';
        erroP.classList.remove('hidden');
        return;
    }

    const subtotalBruto = item.preco * item.quantidade;

    let desconto;
    if (tipoDescontoEscolhido === 'porcentagem') {
        if (entrada < 0 || entrada > 100) {
            erroP.innerText = 'Porcentagem deve ser entre 0 e 100.';
            erroP.classList.remove('hidden');
            return;
        }
        desconto = Math.round((subtotalBruto * entrada / 100) * 100) / 100;
    } else {
        desconto = entrada;
    }

    item.desconto = Math.min(desconto, subtotalBruto);

    renderizarCarrinho();
    fecharModalDescontoItem();
    tipoDescontoEscolhido = null;
}

function fecharModalDescontoGlobal() {
    document.getElementById('modal-desconto-global').classList.add('hidden');
    document.getElementById('modal-desconto-global').classList.remove('flex');
}

function abrirModalAutorizacao(descricao) {
    document.getElementById('autorizacao-descricao').innerText = descricao;
    document.getElementById('autorizacao-usuario').value = '';
    definirNomeSupervisor('');
    document.getElementById('autorizacao-senha').value = '';
    document.getElementById('autorizacao-erro').classList.add('hidden');

    document.getElementById('modal-autorizacao').classList.remove('hidden');
    document.getElementById('modal-autorizacao').classList.add('flex');
}

async function confirmarAutorizacao() {
    const usuario = document.getElementById('autorizacao-usuario').value;
    const senha = document.getElementById('autorizacao-senha').value;
    const erroP = document.getElementById('autorizacao-erro');

    if (!usuario || !senha) {
        erroP.innerText = 'Informe usuário e senha do supervisor.';
        erroP.classList.remove('hidden');
        return;
    }

    try {
        const resp = await fetch('{{ route("supervisor.autorizar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                codigo: usuario,
                password: senha,
                acao: chavePermissao[tipoDescontoPendente],
            }),
        });

        const resultado = await resp.json();

        if (!resultado.autorizado) {
            erroP.innerText = resultado.motivo || 'Código ou senha do supervisor inválidos.';
            erroP.classList.remove('hidden');
            return;
        }
    } catch (e) {
        erroP.innerText = 'Erro de conexão ao validar supervisor.';
        erroP.classList.remove('hidden');
        return;
    }

    // Autorizado - fecha o modal de senha e executa a ação correspondente
    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');
    executarAcaoAutorizada();
}


function abrirLancamentoDescontoItem() {
    document.getElementById('desconto-item-numero').value = '';
    document.getElementById('desconto-item-preview').innerText = '';
    document.getElementById('desconto-item-valor').value = '';
    document.getElementById('desconto-item-erro').classList.add('hidden');

    const label = tipoDescontoEscolhido === 'porcentagem' ? 'Porcentagem de desconto (%)' : 'Valor do desconto (R$)';
    const placeholder = tipoDescontoEscolhido === 'porcentagem' ? 'Ex: 10 para 10%' : 'Ex: 5.00';
    document.querySelector('#modal-desconto-item label[for-valor]').innerText = label;
    document.getElementById('desconto-item-valor').placeholder = placeholder;

    document.getElementById('modal-desconto-item').classList.remove('hidden');
    document.getElementById('modal-desconto-item').classList.add('flex');
}


function abrirLancamentoDescontoGlobal() {
    document.getElementById('desconto-global-valor').value = descontoGlobal || '';
    document.getElementById('desconto-global-erro').classList.add('hidden');

    const label = tipoDescontoEscolhido === 'porcentagem' ? 'Porcentagem de desconto (%)' : 'Valor do desconto (R$)';
    const placeholder = tipoDescontoEscolhido === 'porcentagem' ? 'Ex: 10 para 10%' : 'Ex: 5.00';
    document.querySelector('#modal-desconto-global label[for-valor]').innerText = label;
    document.getElementById('desconto-global-valor').placeholder = placeholder;

    document.getElementById('modal-desconto-global').classList.remove('hidden');
    document.getElementById('modal-desconto-global').classList.add('flex');
}


document.getElementById('desconto-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('desconto-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome}` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-3' : 'text-xs text-red-500 mb-3';
});



function fecharModalAutorizacao() {
    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');
    tipoDescontoPendente = null;
}


function confirmarDescontoGlobal() {
    let entrada = parseFloat(document.getElementById('desconto-global-valor').value) || 0;

    if (tipoDescontoEscolhido === 'porcentagem') {
        const totalAtivo = calcularItensComDescontoRateado()
            .filter(i => !i.cancelado)
            .reduce((soma, i) => soma + i.subtotalLiquido, 0);

        descontoGlobal = Math.round((totalAtivo * entrada / 100) * 100) / 100;
    } else {
        descontoGlobal = entrada;
    }

    atualizarTotais();
    fecharModalDescontoGlobal();
    tipoDescontoEscolhido = null;
}


function abrirLancamentoCancelarItem() {
    document.getElementById('cancelar-item-numero').value = '';
    document.getElementById('cancelar-item-preview').innerText = '';
    document.getElementById('cancelar-item-erro').classList.add('hidden');

    document.getElementById('modal-cancelar-item').classList.remove('hidden');
    document.getElementById('modal-cancelar-item').classList.add('flex');
}

function fecharModalCancelarItem() {
    document.getElementById('modal-cancelar-item').classList.add('hidden');
    document.getElementById('modal-cancelar-item').classList.remove('flex');
}

document.getElementById('cancelar-item-numero')?.addEventListener('input', function () {
    const indice = parseInt(this.value) - 1;
    const preview = document.getElementById('cancelar-item-preview');
    const item = carrinho[indice];

    preview.innerText = item ? `→ ${item.nome} (Qtd: ${item.quantidade})` : 'Número inválido.';
    preview.className = item ? 'text-xs text-green-600 mb-4' : 'text-xs text-red-500 mb-4';
});

function confirmarCancelarItem() {
    const numero = parseInt(document.getElementById('cancelar-item-numero').value);
    const indice = numero - 1;
    const erroP = document.getElementById('cancelar-item-erro');

    const item = carrinho[indice];

    if (!item) {
        erroP.innerText = 'Número de item inválido.';
        erroP.classList.remove('hidden');
        return;
    }

    if (item.cancelado) {
        erroP.innerText = 'Este item já está cancelado.';
        erroP.classList.remove('hidden');
        return;
    }

    item.cancelado = true;

    renderizarCarrinho();
    fecharModalCancelarItem();
}


async function executarLimparPdv() {
    if (!confirm('Confirma o cancelamento do cupom? Todos os itens do carrinho serão removidos.')) {
        return;
    }

    carrinho = [];
    descontoGlobal = 0;
    pedidoVendaId = null;
    pedidoVendaNumero = null;
    atualizarBadgePedido();

    await fetch('{{ route("vendas.limpar-sessao") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    });

    renderizarCarrinho();
}



async function irParaPagamento() {
    const itensAtivos = calcularItensComDescontoRateado().filter(i => !i.cancelado);

    if (itensAtivos.length === 0) {
        document.getElementById('erro-itens').innerText = 'Adicione ao menos um item antes de prosseguir.';
        document.getElementById('erro-itens').classList.remove('hidden');
        return;
    }

    const descontoPorItem = carrinho.reduce((soma, i) => soma + (i.desconto ?? 0), 0);
    const total = itensAtivos.reduce((soma, i) => soma + i.subtotalLiquido, 0);

    const payload = {
        itens: itensAtivos.map(i => ({
            produto_id: i.produto_id,
            produto_variante_id: i.produto_variante_id,
            nome: i.nome,
            quantidade: i.quantidade,
            preco: i.preco,
            desconto: Math.round(i.descontoEfetivo * 100) / 100, // usado na finalizacao da venda
            desconto_bruto: i.desconto ?? 0, // usado so pra recarregar o carrinho depois
            subtotal: Math.round(i.subtotalLiquido * 100) / 100,
        })),
        desconto_item: descontoPorItem,
        desconto_global: descontoGlobal,
        total: Math.round(total * 100) / 100,
        pedido_venda_id: pedidoVendaId,
    };

    const resp = await fetch('{{ route("vendas.preparar-pagamento") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(payload),
    });

    if (resp.ok) {
        window.location.href = '{{ route("vendas.pagamento") }}';
    } else {
        document.getElementById('erro-itens').innerText = 'Erro ao prosseguir. Tente novamente.';
        document.getElementById('erro-itens').classList.remove('hidden');
    }
}

// ---------------------------------------------------------------
// Puxar pedido de venda: os itens do pedido entram no carrinho
// ---------------------------------------------------------------
const inputBuscaPedido = document.getElementById('busca-pedido-modal');
const linhasPedidos = document.getElementById('linhas-pedidos');
let pedidosModal = [];
let indicePedido = -1;
let timeoutBuscaPedido;
let contadorBuscaPedido = 0; // descarta respostas antigas se o operador digitar rápido

function escPedido(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}

function atualizarBadgePedido() {
    const badge = document.getElementById('badge-pedido');
    if (!badge) return;

    if (pedidoVendaId) {
        badge.textContent = 'Itens do pedido de venda nº ' + String(pedidoVendaNumero ?? pedidoVendaId).padStart(6, '0');
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function abrirModalPedidos() {
    if (carrinho.length > 0) {
        alert('Finalize ou cancele o cupom atual antes de puxar um pedido.');
        return;
    }

    const modal = document.getElementById('modal-pedidos');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaPedido.value = '';
    inputBuscaPedido.focus();
    carregarPedidos('');
}

function fecharModalPedidos() {
    const modal = document.getElementById('modal-pedidos');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pedidosModal = [];
    indicePedido = -1;
    inputBusca.focus();
}

async function carregarPedidos(termo) {
    const minhaBusca = ++contadorBuscaPedido;

    try {
        const resp = await fetch(`{{ route('pedidos-caixa.listar') }}?busca=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar os pedidos.`);
        const dados = await resp.json();

        if (minhaBusca !== contadorBuscaPedido) return;

        pedidosModal = dados;
        indicePedido = dados.length > 0 ? 0 : -1;
        renderizarPedidos();
    } catch (e) {
        if (minhaBusca !== contadorBuscaPedido) return;
        pedidosModal = [];
        indicePedido = -1;
        linhasPedidos.innerHTML = `<tr><td colspan="5" class="p-3 text-sm text-red-600 text-center">${escPedido(e.message)}</td></tr>`;
    }
}

function renderizarPedidos() {
    if (pedidosModal.length === 0) {
        linhasPedidos.innerHTML = '<tr><td colspan="5" class="p-3 text-sm text-gray-400 text-center">Nenhum pedido disponível. Use "Atualizar Caixa" para buscar novos pedidos.</td></tr>';
        return;
    }

    linhasPedidos.innerHTML = pedidosModal.map((p, index) => {
        const destacado = index === indicePedido;
        const data = String(p.data_pedido).slice(0, 10).split('-').reverse().join('/');
        const total = 'R$ ' + Number(p.valor_total).toFixed(2).replace('.', ',');
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarPedido(${index})">
                <td class="py-3 px-2 font-mono font-medium">${String(p.numero).padStart(6, '0')}</td>
                <td class="py-3 px-2 whitespace-nowrap">${data}</td>
                <td class="py-3 px-2 truncate">${escPedido(p.cliente_nome) || '—'}</td>
                <td class="py-3 px-2 text-center">${p.itens_count}</td>
                <td class="py-3 px-2 text-right font-semibold whitespace-nowrap">${total}</td>
            </tr>
        `;
    }).join('');

    linhasPedidos.children[indicePedido]?.scrollIntoView({ block: 'nearest' });
}

async function selecionarPedido(index) {
    const p = pedidosModal[index];
    if (!p) return;

    try {
        const url = '{{ route('pedidos-caixa.itens', ['id' => '__ID__']) }}'.replace('__ID__', p.id);
        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await resp.json().catch(() => ({}));

        if (!resp.ok) {
            alert(json.message ?? `Erro ${resp.status} ao carregar o pedido.`);
            return;
        }

        carrinho = json.itens;
        descontoGlobal = 0;
        pedidoVendaId = json.pedido.id;
        pedidoVendaNumero = json.pedido.numero;

        fecharModalPedidos();
        renderizarCarrinho();
        atualizarBadgePedido();
    } catch (e) {
        alert('Falha ao carregar o pedido: ' + e.message);
    }
}

inputBuscaPedido.addEventListener('input', () => {
    clearTimeout(timeoutBuscaPedido);
    timeoutBuscaPedido = setTimeout(() => carregarPedidos(inputBuscaPedido.value.trim()), 300);
});

inputBuscaPedido.addEventListener('keydown', (e) => {
    if (pedidosModal.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indicePedido = (indicePedido + 1) % pedidosModal.length;
        renderizarPedidos();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indicePedido = (indicePedido - 1 + pedidosModal.length) % pedidosModal.length;
        renderizarPedidos();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indicePedido >= 0) selecionarPedido(indicePedido);
    }
});


// ---------------------------------------------------------------
// Reimprimir cupom fiscal (vendas do dia, ou busca pelo nº da NFC-e)
// ---------------------------------------------------------------
const inputNumeroCupom = document.getElementById('reimp-numero');
const inputDataCupom = document.getElementById('reimp-data');
const linhasReimpressao = document.getElementById('linhas-reimpressao');
const msgReimpressao = document.getElementById('msg-reimpressao');
const dataDeHoje = @json(now()->format('Y-m-d'));

let cuponsModal = [];
let indiceCupom = -1;
let timeoutBuscaCupom;
let contadorBuscaCupom = 0;
let imprimindoCupom = false;

function abrirModalReimpressao() {
    const modal = document.getElementById('modal-reimpressao');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    inputNumeroCupom.value = '';
    inputDataCupom.value = dataDeHoje; // por padrão: vendas do dia
    msgReimpressao.classList.add('hidden');
    inputNumeroCupom.focus();
    carregarCupons();
}

function fecharModalReimpressao() {
    const modal = document.getElementById('modal-reimpressao');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    cuponsModal = [];
    indiceCupom = -1;
    inputBusca.focus();
}

async function carregarCupons() {
    const minhaBusca = ++contadorBuscaCupom;
    const params = new URLSearchParams({
        numero: inputNumeroCupom.value.trim(),
        data: inputDataCupom.value || dataDeHoje,
    });

    try {
        const resp = await fetch(`{{ route('vendas.cupons') }}?${params}`, { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar as vendas.`);
        const dados = await resp.json();

        if (minhaBusca !== contadorBuscaCupom) return;

        cuponsModal = dados;
        indiceCupom = dados.length > 0 ? 0 : -1;
        renderizarCupons();
    } catch (e) {
        if (minhaBusca !== contadorBuscaCupom) return;
        cuponsModal = [];
        indiceCupom = -1;
        linhasReimpressao.innerHTML = `<tr><td colspan="4" class="p-3 text-sm text-red-600 text-center">${escPedido(e.message)}</td></tr>`;
    }
}

function renderizarCupons() {
    if (cuponsModal.length === 0) {
        linhasReimpressao.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhuma NFC-e encontrada.</td></tr>';
        return;
    }

    linhasReimpressao.innerHTML = cuponsModal.map((c, index) => {
        const destacado = index === indiceCupom;
        const [dia, hora] = String(c.vendida_em).replace('T', ' ').split(' ');
        const dataHora = dia.split('-').reverse().join('/') + ' ' + (hora ?? '').slice(0, 5);
        const total = 'R$ ' + Number(c.total).toFixed(2).replace('.', ',');
        const situacao = c.status_fiscal === 'contingencia'
            ? '<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800">Contingência</span>'
            : '<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-700">Autorizada</span>';

        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarCupom(${index})">
                <td class="py-3 px-2 font-mono font-medium">${escPedido(c.numero_nfce)} / ${escPedido(c.serie_nfce)}</td>
                <td class="py-3 px-2 whitespace-nowrap">${dataHora}</td>
                <td class="py-3 px-2 text-right font-semibold whitespace-nowrap">${total}</td>
                <td class="py-3 px-2">${situacao}</td>
            </tr>
        `;
    }).join('');

    linhasReimpressao.children[indiceCupom]?.scrollIntoView({ block: 'nearest' });
}

async function selecionarCupom(index) {
    const c = cuponsModal[index];
    if (!c || imprimindoCupom) return;

    if (!confirm(`Deseja imprimir o cupom da NFC-e nº ${c.numero_nfce}?`)) return;

    imprimindoCupom = true;
    msgReimpressao.className = 'text-sm text-center mt-3 text-gray-600';
    msgReimpressao.innerText = 'Imprimindo cupom fiscal...';

    try {
        await imprimirCupomFiscal(c.uuid);
        msgReimpressao.className = 'text-sm text-center mt-3 text-green-700';
        msgReimpressao.innerText = `✓ Cupom da NFC-e nº ${c.numero_nfce} enviado para a impressora.`;
    } catch (e) {
        msgReimpressao.className = 'text-sm text-center mt-3 text-red-600';
        msgReimpressao.innerText = 'Não foi possível imprimir o cupom: ' + e.message;
    } finally {
        imprimindoCupom = false;
    }
}

inputNumeroCupom.addEventListener('input', () => {
    clearTimeout(timeoutBuscaCupom);
    timeoutBuscaCupom = setTimeout(carregarCupons, 300);
});
inputDataCupom.addEventListener('change', carregarCupons);

[inputNumeroCupom, inputDataCupom].forEach((campo) => {
    campo.addEventListener('keydown', (e) => {
        if (cuponsModal.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            indiceCupom = (indiceCupom + 1) % cuponsModal.length;
            renderizarCupons();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            indiceCupom = (indiceCupom - 1 + cuponsModal.length) % cuponsModal.length;
            renderizarCupons();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (indiceCupom >= 0) selecionarCupom(indiceCupom);
        }
    });
});

renderizarCarrinho();
atualizarBadgePedido();
</script>
@endsection