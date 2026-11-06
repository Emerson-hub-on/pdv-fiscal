@extends('layouts.caixa')

@section('titulo', 'Pagamento')

@section('conteudo')
@php
    // Padrões visuais do sistema (Tailwind) usados nesta tela
    $rotuloCx = 'block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5';
    $campoCx = 'w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 hover:border-slate-300 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition';
    $botaoTopoCx = 'inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-sm font-medium px-4 py-2 rounded-xl transition cursor-pointer';
    $atalhoCx = 'text-[10px] font-semibold text-amber-50/70 bg-white/10 rounded px-1.5 py-0.5';
    $qtdItens = count($itens ?? []);
@endphp

{{-- Cabeçalho fixo --}}
<div class="fixed top-0 left-0 right-0 z-40 bg-[#10284A] shadow-lg">
    <div class="flex justify-between items-center px-6 py-4">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-white/10 text-amber-50 flex items-center justify-center">
                <x-form.icone nome="cartao" tamanho="w-5 h-5" />
            </span>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Caixa · Venda</p>
                <h1 class="text-xl font-bold text-amber-50 tracking-tight">Pagamento</h1>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="abrirModalCliente()" class="{{ $botaoTopoCx }}">
                <x-form.icone nome="pessoa" />
                <span id="btn-cliente-label">Adicionar consumidor</span> <span class="{{ $atalhoCx }}">F4</span>
            </button>
            <button type="button" onclick="abrirModalDescontoGlobal()" class="{{ $botaoTopoCx }}">
                <x-form.icone nome="dinheiro" />
                Desconto Geral <span class="{{ $atalhoCx }}">F5</span>
            </button>
            <button type="button" onclick="abrirModalConfirmarVoltar()" class="{{ $botaoTopoCx }}">
                <x-form.icone nome="voltar" />
                Voltar aos itens <span class="{{ $atalhoCx }}">Esc</span>
            </button>
        </div>
    </div>
</div>

{{-- Os dois quadros centralizados no meio da tela (descontando o cabeçalho fixo) --}}
<div class="min-h-screen flex items-center justify-center px-6 pt-24 pb-10">
    <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">

        {{-- Quadro esquerdo: pagamentos + finalizar --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 flex flex-col">
            <div class="flex items-center gap-2.5 px-6 pt-5 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-[#10284A] text-amber-50 flex items-center justify-center">
                    <x-form.icone nome="dinheiro" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Pagamentos</h2>
                    <p class="text-xs text-slate-500">Digite o valor, Enter e escolha a forma</p>
                </div>
            </div>

            <div class="p-6 flex flex-col gap-4 flex-1">
                <div id="resumo-cliente" class="hidden rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5 text-sm">
                    <div class="flex justify-between items-center gap-3">
                        <span class="flex items-center gap-2 min-w-0 text-amber-900">
                            <x-form.icone nome="pessoa" class="shrink-0" />
                            <span id="resumo-cliente-label" class="truncate font-medium"></span>
                        </span>
                        <button type="button" onclick="removerClienteSelecionado()" class="shrink-0 text-xs font-medium text-amber-800 hover:text-red-600 hover:underline">Remover</button>
                    </div>
                </div>

                <div>
                    <label class="{{ $rotuloCx }}">Lançados</label>
                    <div id="lista-pagamentos" class="space-y-2"></div>
                </div>

                <div class="flex gap-2">
                    <input type="number" step="0.01" id="novo-valor-pagamento" placeholder="R$ 0,00" autofocus
                           class="w-32 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-base font-semibold text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">

                    <div class="relative flex-1">
                        <button type="button" id="btn-forma-pagamento" onclick="toggleDropdownFormaPagamento()"
                                class="w-full h-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-left text-slate-800 hover:bg-white hover:border-[#10284A]/40 focus:outline-none focus:ring-2 focus:ring-[#10284A]/30 transition">
                            Dinheiro
                        </button>
                        <div id="dropdown-forma-pagamento" class="absolute bg-white ring-1 ring-slate-200 rounded-xl shadow-lg w-full mt-1 hidden z-10 overflow-hidden">
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50" data-forma="dinheiro" data-index="0" onclick="selecionarFormaPagamento('dinheiro')">Dinheiro</div>
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50" data-forma="pix" data-index="1" onclick="selecionarFormaPagamento('pix')">PIX</div>
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50" data-forma="credito" data-index="2" onclick="selecionarFormaPagamento('credito')">Cartão de Crédito</div>
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50" data-forma="debito" data-index="3" onclick="selecionarFormaPagamento('debito')">Cartão de Débito</div>
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50 border-t border-slate-100" data-forma="credito_loja" data-index="4" onclick="selecionarFormaPagamento('credito_loja')">Crédito em loja (troca)</div>
                            <div class="px-3 py-2.5 cursor-pointer text-sm text-slate-700 hover:bg-slate-50" data-forma="convenio" data-index="5" onclick="selecionarFormaPagamento('convenio')">Convênio (fiado)</div>
                        </div>
                    </div>
                </div>

                <div class="mt-auto rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 flex justify-between items-center">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Restante a pagar</span>
                    <strong id="restante-pagamento" class="text-lg">R$ 0,00</strong>
                </div>

                <button id="btn-finalizar" onclick="finalizarVenda()"
                        class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white py-3.5 rounded-xl font-bold text-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 transition disabled:opacity-50">
                    <x-form.icone nome="check" tamanho="w-5 h-5" />
                    Finalizar venda <span class="text-xs font-semibold bg-white/20 rounded px-1.5 py-0.5">F2</span>
                </button>
                <p id="erro-venda" class="text-red-600 text-sm hidden"></p>
            </div>
        </div>

        {{-- Quadro direito: total e descontos --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 flex flex-col overflow-hidden">
            <div class="bg-[#10284A] px-6 py-6">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total a pagar</p>
                <p id="total-pagamento-exibido" class="text-5xl font-bold text-amber-50 mt-1 tracking-tight">
                    R$ {{ number_format($total, 2, ',', '.') }}
                </p>
                <p class="text-xs text-slate-300 mt-2">{{ $qtdItens }} {{ $qtdItens === 1 ? 'item' : 'itens' }} na venda</p>
            </div>

            <div class="p-6 flex flex-col gap-3 flex-1">
                <p class="{{ $rotuloCx }}">Descontos</p>
                <div class="rounded-xl ring-1 ring-slate-200 divide-y divide-slate-100 text-sm">
                    <p class="flex justify-between px-4 py-3 text-slate-600">Desconto por item
                        <strong class="text-emerald-600">R$ {{ number_format($desconto_item, 2, ',', '.') }}</strong>
                    </p>
                    <p class="flex justify-between px-4 py-3 text-slate-600">Desconto geral
                        <strong id="desconto-global-exibido" class="text-emerald-600">R$ 0,00</strong>
                    </p>
                    <p class="flex justify-between px-4 py-3 bg-slate-50 font-medium text-slate-800">Total de desconto
                        <strong id="desconto-total-exibido" class="text-emerald-700">R$ {{ number_format($desconto_item, 2, ',', '.') }}</strong>
                    </p>
                </div>

                <div class="mt-auto grid grid-cols-3 gap-2 text-center text-[11px] text-slate-500">
                    <span class="rounded-lg bg-slate-50 ring-1 ring-slate-200 py-2"><strong class="block text-slate-700 text-xs">F2</strong>Finalizar</span>
                    <span class="rounded-lg bg-slate-50 ring-1 ring-slate-200 py-2"><strong class="block text-slate-700 text-xs">F4</strong>Consumidor</span>
                    <span class="rounded-lg bg-slate-50 ring-1 ring-slate-200 py-2"><strong class="block text-slate-700 text-xs">F5</strong>Desconto</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Adicionar Consumidor -->
<div id="modal-cliente" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Adicionar consumidor</h2>
                <p class="text-xs text-slate-300">Busque o cliente, cadastre um novo ou informe só o CPF na nota</p>
            </div>
            <button type="button" onclick="fecharModalCliente()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto">

        <div class="flex gap-2 mb-3">
            <input type="text" id="cliente-busca" autocomplete="off" placeholder="Buscar por nome ou CPF/CNPJ..."
                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition" oninput="buscarCliente()">
            <button type="button" onclick="abrirFormNovoCliente()"
                    class="bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition text-sm px-3 py-2 whitespace-nowrap">
                + Novo
            </button>
            <button type="button" onclick="abrirFormCpfNaNota()"
                    class="bg-white border border-slate-200 hover:border-[#10284A]/40 hover:text-[#10284A] text-slate-700 text-sm font-medium px-3 py-2 rounded-xl whitespace-nowrap transition">
                CPF na nota
            </button>
        </div>

        <div id="form-cpf-na-nota" class="hidden bg-amber-50 ring-1 ring-amber-200 rounded-xl p-4 mb-3">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">CPF do consumidor</label>
            <input type="text" id="cpf-na-nota-valor" placeholder="000.000.000-00" maxlength="14"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition mb-2">
            <p class="text-xs text-gray-400 mb-2">Só o CPF vai na nota fiscal — não cria cadastro de cliente.</p>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormCpfNaNota()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="confirmarCpfNaNota()"
                        class="bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition text-sm px-4 py-1.5">Confirmar</button>
            </div>
        </div>

        <div id="form-novo-cliente" class="hidden bg-slate-50 ring-1 ring-slate-200 rounded-xl p-4 mb-3">
            <div class="flex gap-4 mb-2 text-sm">
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="novo-cliente-tipo" value="fisica" checked onchange="atualizarPlaceholderDoc()">
                    Pessoa Física
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="novo-cliente-tipo" value="juridica" onchange="atualizarPlaceholderDoc()">
                    Pessoa Jurídica
                </label>
            </div>
            <input type="text" id="novo-cliente-nome" placeholder="Nome / Razão social"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition bg-white mb-2">
            <input type="text" id="novo-cliente-documento" placeholder="CPF"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition bg-white mb-2">
            <p class="text-xs text-gray-400 mb-2">Cadastro rápido — complete o endereço depois na tela de Clientes, se precisar.</p>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoCliente()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoCliente()"
                        class="bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition text-sm px-4 py-1.5">Salvar e selecionar</button>
            </div>
        </div>

        <div class="rounded-xl ring-1 ring-slate-200 overflow-hidden">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col>
                    <col class="w-44">
                    <col class="w-14">
                </colgroup>
                <thead class="bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-3 py-2">Nome</th>
                        <th class="text-left px-3 py-2">CPF/CNPJ</th>
                        <th class="text-left px-3 py-2">UF</th>
                    </tr>
                </thead>
                <tbody id="cliente-lista" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
        <p id="cliente-vazio" class="text-sm text-slate-400 text-center py-4 hidden">Nenhum cliente encontrado.</p>
        <p class="text-xs text-slate-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<!-- Modal de autorização do supervisor -->
<div id="modal-autorizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Autorização do supervisor</h2>
            </div>
            <button type="button" onclick="fecharModalAutorizacao()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
        <p class="text-sm text-slate-500 mb-4" id="autorizacao-descricao"></p>
        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Código do supervisor</label>
        <input type="text" inputmode="numeric" id="autorizacao-usuario" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition mb-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Senha</label>
        <input type="password" id="autorizacao-senha" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition mb-4">
        <p id="autorizacao-erro" class="text-red-600 text-sm mb-3 hidden"></p>
        <button onclick="confirmarAutorizacao()" class="w-full bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition py-2.5">
            Autorizar
        </button>
        </div>
    </div>
</div>

<!-- Modal de escolha de tipo de desconto -->
<div id="modal-tipo-desconto" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Tipo de desconto</h2>
            </div>
            <button type="button" onclick="fecharModalTipoDesconto()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
        <div class="grid grid-cols-2 gap-4">
            <button onclick="escolherTipoDesconto('valor')"
                    class="flex flex-col items-center justify-center gap-2 border-2 border-slate-200 hover:border-[#10284A] hover:bg-amber-50 rounded-xl p-6 transition">
                <span class="text-3xl font-bold text-[#10284A]">R$</span>
                <span class="text-sm font-semibold text-gray-700">Por Valor</span>
            </button>
            <button onclick="escolherTipoDesconto('porcentagem')"
                    class="flex flex-col items-center justify-center gap-2 border-2 border-slate-200 hover:border-[#10284A] hover:bg-amber-50 rounded-xl p-6 transition">
                <span class="text-3xl font-bold text-[#10284A]">%</span>
                <span class="text-sm font-semibold text-gray-700">Por Porcentagem</span>
            </button>
        </div>
        <p class="text-center text-xs text-slate-400 mt-4">Ou pressione <strong>1</strong> para valor · <strong>2</strong> para porcentagem</p>
        </div>
    </div>
</div>

<!-- Modal desconto global -->
<div id="modal-desconto-global" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Desconto geral</h2>
            </div>
            <button type="button" onclick="fecharModalDescontoGlobal()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5" for-valor>Valor do desconto (R$)</label>
        <input type="number" step="0.01" min="0" id="desconto-global-valor" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition text-base font-semibold mb-4">
        <p id="desconto-global-erro" class="text-red-600 text-sm mb-3 hidden"></p>
        <button onclick="confirmarDescontoGlobal()" class="w-full bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition py-2.5">
            Aplicar desconto
        </button>
        </div>
    </div>
</div>


<!-- Modal de confirmação de volta -->
<div id="modal-confirmar-voltar" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Voltar aos itens?</h2>
            </div>
            <button type="button" onclick="fecharModalConfirmarVoltar()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
        <p class="text-sm text-slate-500 mb-6">Os pagamentos lançados serão perdidos. Deseja realmente voltar?</p>
        <div class="grid grid-cols-2 gap-4">
            <button onclick="confirmarVoltar()"
                    class="flex flex-col items-center justify-center gap-1 border-2 border-gray-200 hover:border-green-500 hover:bg-green-50 rounded-xl p-4 transition">
                <span class="text-2xl font-bold text-green-600">1</span>
                <span class="text-sm font-semibold text-gray-700">Sim, voltar</span>
            </button>
            <button onclick="fecharModalConfirmarVoltar()"
                    class="flex flex-col items-center justify-center gap-1 border-2 border-gray-200 hover:border-red-500 hover:bg-red-50 rounded-xl p-4 transition">
                <span class="text-2xl font-bold text-red-500">2</span>
                <span class="text-sm font-semibold text-gray-700">Não, continuar</span>
            </button>
        </div>
        <p class="text-center text-xs text-slate-400 mt-4">Pressione <strong>1</strong> para confirmar · <strong>2</strong> para cancelar</p>
        </div>
    </div>
</div>

<!-- Modal: crédito em loja (troca / haver do cliente) -->
<div id="modal-credito-loja" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Crédito em loja</h2>
                <p id="credito-loja-subtitulo" class="text-xs text-slate-300"></p>
            </div>
            <button type="button" onclick="fecharModalCreditoLoja()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <label class="block text-xs font-medium text-gray-600 mb-1">Código do vale-troca</label>
            <div class="flex gap-2 mb-4">
                <input type="text" id="credito-loja-codigo" placeholder="VT000000" maxlength="12"
                       class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition uppercase"
                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); buscarCreditosLoja(); }">
                <button type="button" onclick="buscarCreditosLoja()"
                        class="bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition text-sm px-4 py-2">Buscar</button>
            </div>

            <ul id="credito-loja-lista" class="divide-y divide-gray-100 border border-gray-200 rounded-lg max-h-64 overflow-y-auto"></ul>
            <p id="credito-loja-vazio" class="hidden text-sm text-gray-500 text-center py-4"></p>
            <p class="text-xs text-gray-400 mt-3">Crédito em loja não dá troco: usa até o valor que falta pagar.</p>
        </div>
    </div>
</div>

<!-- Modal: convênio (venda fiado no limite de crédito do cliente) -->
<div id="modal-convenio" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Convênio</h2>
                <p id="convenio-subtitulo" class="text-xs text-slate-300">Venda fiado no limite de crédito do cliente</p>
            </div>
            <button type="button" onclick="fecharModalConvenio()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            {{-- Passo 1: qual o consumidor (quando ainda não foi identificado) --}}
            <div id="convenio-passo-cliente" class="hidden">
                <label class="block text-xs font-medium text-gray-600 mb-1">Consumidor do convênio</label>
                <input type="text" id="convenio-cliente-busca" placeholder="Buscar por nome ou CPF/CNPJ..." autocomplete="off"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition mb-3"
                       oninput="buscarClienteConvenio()">
                <div class="max-h-64 overflow-y-auto rounded-xl">
                <div class="rounded-xl ring-1 ring-slate-200">
            <table class="w-full text-sm table-fixed">
                <colgroup>
                    <col>
                    <col class="w-44">
                    <col class="w-14">
                </colgroup>
                <thead class="sticky top-0 bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-3 py-2">Nome</th>
                        <th class="text-left px-3 py-2">CPF/CNPJ</th>
                        <th class="text-left px-3 py-2">UF</th>
                    </tr>
                </thead>
                <tbody id="convenio-cliente-lista" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
                </div>
                <p id="convenio-cliente-vazio" class="hidden text-sm text-gray-500 text-center py-4">Nenhum cliente encontrado.</p>
            </div>

            {{-- Passo 2: limite do cliente e quanto usar --}}
            <div id="convenio-passo-limite" class="hidden">
                <div class="flex justify-between items-start gap-3 mb-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Cliente</p>
                        <p id="convenio-cliente-nome" class="font-semibold text-slate-800 truncate"></p>
                    </div>
                    <button type="button" onclick="trocarClienteConvenio()" class="shrink-0 text-xs font-medium text-[#10284A] hover:underline">Trocar consumidor</button>
                </div>

                <div id="convenio-sem-limite" class="hidden rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"></div>

                <div id="convenio-com-limite" class="hidden">
                    <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2.5">
                            <p class="text-[11px] text-gray-500 uppercase">Limite total</p>
                            <p id="convenio-limite" class="font-bold text-slate-800"></p>
                        </div>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2.5">
                            <p class="text-[11px] text-gray-500 uppercase">Já usado</p>
                            <p id="convenio-usado" class="font-bold text-slate-800"></p>
                        </div>
                        <div class="rounded-lg bg-[#10284A] p-2.5">
                            <p class="text-[11px] text-slate-300 uppercase">Disponível</p>
                            <p id="convenio-disponivel" class="font-bold text-amber-50"></p>
                        </div>
                    </div>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Valor no convênio (R$)</label>
                    <input type="number" step="0.01" min="0.01" id="convenio-valor"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition text-base font-semibold mb-2"
                           onkeydown="if (event.key === 'Enter') { event.preventDefault(); confirmarConvenio(); }">
                    <p id="convenio-aviso" class="hidden text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5 mb-2"></p>
                    <p id="convenio-erro" class="hidden text-sm text-red-600 mb-2"></p>

                    <button type="button" onclick="confirmarConvenio()"
                            class="w-full bg-[#10284A] hover:bg-[#163a68] text-amber-50 rounded-xl font-semibold transition py-2.5">
                        Lançar no convênio <span class="opacity-70 text-xs">Enter</span>
                    </button>
                    <p id="convenio-prazo" class="text-xs text-gray-400 mt-3"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de emissão (F2): carregando → venda emitida / em contingência → imprimir o comprovante? -->
<div id="modal-emissao" class="hidden fixed inset-0 z-[60] bg-black/60 items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8 text-center">
        {{-- Carregando --}}
        <div id="emissao-carregando">
            <div class="mx-auto w-14 h-14 rounded-full border-4 border-emerald-100 border-t-emerald-600 animate-spin"></div>
            <p class="mt-5 text-lg font-bold text-gray-800">Emitindo venda...</p>
            <p id="emissao-demora" class="mt-1 text-sm text-gray-500 invisible">Só mais um momento...</p>
        </div>

        {{-- Resultado --}}
        <div id="emissao-resultado" class="hidden">
            <div id="emissao-icone" class="mx-auto w-16 h-16 rounded-full flex items-center justify-center"></div>
            <p id="emissao-titulo" class="mt-4 text-lg font-bold text-gray-800"></p>
            <p id="emissao-detalhe" class="mt-1 text-sm text-gray-500 break-words"></p>

            <div id="emissao-pergunta">
                <p class="mt-5 text-sm font-medium text-gray-700">Deseja imprimir o comprovante?</p>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <button type="button" onclick="responderImpressaoVenda(true)"
                            class="bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-lg font-semibold cursor-pointer">
                        Sim <span class="opacity-70 text-xs">Enter</span>
                    </button>
                    <button type="button" onclick="responderImpressaoVenda(false)"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2.5 rounded-lg font-semibold cursor-pointer">
                        Não <span class="opacity-60 text-xs">Esc</span>
                    </button>
                </div>
            </div>

            {{-- Venda registrada, mas a NFC-e não saiu (rejeição, erro de configuração...) --}}
            <div id="emissao-falha" class="hidden">
                <div class="grid grid-cols-2 gap-3 mt-5">
                    <button type="button" onclick="emitirVendaFinalizada()"
                            class="bg-blue-600 hover:bg-blue-500 text-white py-2.5 rounded-lg font-semibold cursor-pointer">
                        Tentar de novo <span class="opacity-70 text-xs">Enter</span>
                    </button>
                    <button type="button" onclick="irParaNovaVenda()"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2.5 rounded-lg font-semibold cursor-pointer">
                        Nova venda <span class="opacity-60 text-xs">Esc</span>
                    </button>
                </div>
            </div>

            <p id="emissao-impressao" class="hidden mt-4 text-sm text-gray-600"></p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/cupom-fiscal.js') }}"></script>
<script>
const itensDaVenda = @json($itens);
const totalDaVenda = {{ $total }};
let pagamentos = [];
let descontoGlobal = 0;
let tipoDescontoPendente = null;
let formaSelecionada = 'dinheiro';
let indiceFormaDestacada = 0;
let tipoDescontoEscolhido = null;
const liberacoes = @json($liberacoes);
let _handlerTipoDesconto = null;
let _handlerConfirmarVoltar = null;
let clienteSelecionadoId = null;
let cpfNaNotaValor = null;
let timeoutCliente;


const formasPagamento = [
    { valor: 'dinheiro', label: 'Dinheiro' },
    { valor: 'pix', label: 'PIX' },
    { valor: 'credito', label: 'Cartão de Crédito' },
    { valor: 'debito', label: 'Cartão de Débito' },
    { valor: 'credito_loja', label: 'Crédito em loja (troca)' },
    { valor: 'convenio', label: 'Convênio (fiado)' },
];

// ===================== LINHA DE CLIENTE NAS LISTAS (Nome | CPF/CNPJ | UF) =====================
// Nome comprido (comum em CNPJ) termina em "..." na própria coluna: o nome completo fica no cadastro do cliente
function linhaCliente(c, aoClicar) {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-amber-50 text-slate-700 cursor-pointer';

    // Nome cortado com "..." (truncate); o selo de cadastrado no caixa fica sempre visível ao lado
    const nome = document.createElement('td');
    nome.className = 'px-3 py-2.5';
    const caixaNome = document.createElement('div');
    caixaNome.className = 'flex items-center gap-1.5 min-w-0';
    const texto = document.createElement('span');
    texto.className = 'truncate font-medium';
    texto.textContent = c.nome ?? c.label;
    caixaNome.append(texto);
    if (c.pendente) {
        const selo = document.createElement('span');
        selo.className = 'shrink-0 text-[10px] font-semibold rounded bg-amber-100 text-amber-800 px-1.5 py-0.5';
        selo.textContent = 'cadastrado no caixa';
        caixaNome.append(selo);
    }
    nome.append(caixaNome);

    const doc = document.createElement('td');
    doc.className = 'px-3 py-2.5 whitespace-nowrap font-mono text-xs';
    doc.textContent = c.cpf_cnpj_formatado || c.cpf_cnpj || '—';

    const uf = document.createElement('td');
    uf.className = 'px-3 py-2.5';
    uf.textContent = c.uf || '—';

    tr.append(nome, doc, uf);
    tr.addEventListener('click', aoClicar);
    return tr;
}

// ===================== LISTAS NAVEGÁVEIS PELO TECLADO =====================
// No campo de busca: ↑ ↓ trocam o item marcado, Enter escolhe. O mouse sobre um item também o marca.
function listaNavegavel(campoId, listaId) {
    const estado = { itens: [], indice: -1, escolher: null };
    const lista = () => document.getElementById(listaId);

    function marcar() {
        [...lista().children].forEach((li, i) => {
            const ativo = i === estado.indice;
            li.classList.toggle('bg-[#10284A]', ativo);
            li.classList.toggle('text-amber-50', ativo);
            li.classList.toggle('text-slate-700', !ativo);
            li.classList.toggle('hover:bg-amber-50', !ativo);
            if (ativo) li.scrollIntoView({ block: 'nearest' });
        });
    }

    document.getElementById(campoId).addEventListener('keydown', (e) => {
        const total = estado.itens.length;
        if (!total) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const passo = e.key === 'ArrowDown' ? 1 : -1;
            estado.indice = (estado.indice + passo + total) % total;
            marcar();
        } else if (e.key === 'Enter' && estado.indice >= 0) {
            e.preventDefault();
            estado.escolher(estado.itens[estado.indice]);
        }
    });

    return {
        // Chamado depois de montar a lista: o 1º item já vem marcado
        definir(itens, escolher) {
            estado.itens = itens;
            estado.escolher = escolher;
            estado.indice = itens.length ? 0 : -1;
            [...lista().children].forEach((li, i) => li.addEventListener('mouseenter', () => {
                estado.indice = i;
                marcar();
            }));
            marcar();
        },
    };
}

// ===================== CLIENTE (Adicionar Consumidor) =====================

function abrirModalCliente() {
    document.getElementById('cliente-busca').value = '';
    document.getElementById('form-novo-cliente').classList.add('hidden');
    document.getElementById('form-cpf-na-nota').classList.add('hidden');
    document.getElementById('modal-cliente').classList.remove('hidden');
    document.getElementById('modal-cliente').classList.add('flex');

    buscarCliente();
    setTimeout(() => document.getElementById('cliente-busca').focus(), 100);
}

function fecharModalCliente() {
    document.getElementById('modal-cliente').classList.add('hidden');
    document.getElementById('modal-cliente').classList.remove('flex');
}

const navegacaoClientes = listaNavegavel('cliente-busca', 'cliente-lista');

function buscarCliente() {
    clearTimeout(timeoutCliente);
    timeoutCliente = setTimeout(async () => {
        const termo = document.getElementById('cliente-busca').value;

        const resp = await fetch(`{{ route('clientes.buscar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('cliente-lista');
        const vazio = document.getElementById('cliente-vazio');

        lista.replaceChildren();

        if (items.length === 0) {
            navegacaoClientes.definir([], null);
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        // Nome digitado no caixa: vai como texto, nunca como HTML
        items.forEach(c => lista.appendChild(linhaCliente(c, () => selecionarCliente(c.id, c.label))));
        navegacaoClientes.definir(items, c => selecionarCliente(c.id, c.label));
    }, 200);
}

function selecionarCliente(id, label) {
    descartarConvenioDeOutroCliente(id);
    clienteSelecionadoId = id;
    cpfNaNotaValor = null; // mutuamente exclusivo
    document.getElementById('btn-cliente-label').innerText = 'Consumidor identificado';
    document.getElementById('resumo-cliente').classList.remove('hidden');
    document.getElementById('resumo-cliente-label').innerText = label;
    fecharModalCliente();
}

function removerClienteSelecionado() {
    descartarConvenioDeOutroCliente(null);
    clienteSelecionadoId = null;
    cpfNaNotaValor = null;
    document.getElementById('btn-cliente-label').innerText = 'Adicionar consumidor';
    document.getElementById('resumo-cliente').classList.add('hidden');
}

function abrirFormCpfNaNota() {
    fecharFormNovoCliente();
    document.getElementById('form-cpf-na-nota').classList.remove('hidden');
    document.getElementById('cpf-na-nota-valor').value = '';
    document.getElementById('cpf-na-nota-valor').focus();
}

document.getElementById('cpf-na-nota-valor').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmarCpfNaNota();
    }
});

function fecharFormCpfNaNota() {
    document.getElementById('form-cpf-na-nota').classList.add('hidden');
}

function confirmarCpfNaNota() {
    const cpfDigitado = document.getElementById('cpf-na-nota-valor').value.trim();
    const cpfLimpo = cpfDigitado.replace(/\D/g, '');

    if (cpfLimpo.length !== 11) {
        alert('CPF inválido — deve ter 11 dígitos.');
        return;
    }

    cpfNaNotaValor = cpfLimpo;
    descartarConvenioDeOutroCliente(null);
    clienteSelecionadoId = null; // mutuamente exclusivo

    const cpfFormatado = cpfLimpo.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');

    document.getElementById('btn-cliente-label').innerText = 'CPF na nota informado';
    document.getElementById('resumo-cliente').classList.remove('hidden');
    document.getElementById('resumo-cliente-label').innerText = `CPF na nota: ${cpfFormatado}`;

    fecharFormCpfNaNota();
    fecharModalCliente();
}

function abrirFormNovoCliente() {
    fecharFormCpfNaNota();
    document.getElementById('form-novo-cliente').classList.remove('hidden');
    document.getElementById('novo-cliente-nome').value = '';
    document.getElementById('novo-cliente-documento').value = '';
    document.getElementById('novo-cliente-nome').focus();
}

function fecharFormNovoCliente() {
    document.getElementById('form-novo-cliente').classList.add('hidden');
}

function atualizarPlaceholderDoc() {
    const tipo = document.querySelector('input[name="novo-cliente-tipo"]:checked').value;
    document.getElementById('novo-cliente-documento').placeholder = tipo === 'juridica' ? 'CNPJ' : 'CPF';
}

async function salvarNovoCliente() {
    const tipo = document.querySelector('input[name="novo-cliente-tipo"]:checked').value;
    const nome = document.getElementById('novo-cliente-nome').value.trim();
    const documento = document.getElementById('novo-cliente-documento').value.trim();

    if (!nome) { alert('Informe o nome do cliente.'); return; }
    if (!documento) { alert('Informe o CPF/CNPJ.'); return; }

    const resp = await fetch('{{ route("clientes.criarRapido") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ tipo_pessoa: tipo, nome, cpf_cnpj: documento }),
    });

    const item = await resp.json();

    if (item.errors) {
        alert(Object.values(item.errors).flat().join('\n'));
        return;
    }

    fecharFormNovoCliente();
    selecionarCliente(item.id, item.label);
}

// ===================== TABS/MODAIS ORIGINAIS =====================

function abrirModalConfirmarVoltar() {
    document.getElementById('modal-confirmar-voltar').classList.remove('hidden');
    document.getElementById('modal-confirmar-voltar').classList.add('flex');

    _handlerConfirmarVoltar = function (e) {
        if (e.key === '1') { e.preventDefault(); confirmarVoltar(); }
        if (e.key === '2') { e.preventDefault(); fecharModalConfirmarVoltar(); }
    };
    document.addEventListener('keydown', _handlerConfirmarVoltar);
}

function fecharModalConfirmarVoltar() {
    document.getElementById('modal-confirmar-voltar').classList.add('hidden');
    document.getElementById('modal-confirmar-voltar').classList.remove('flex');

    if (_handlerConfirmarVoltar) {
        document.removeEventListener('keydown', _handlerConfirmarVoltar);
        _handlerConfirmarVoltar = null;
    }
}

function confirmarVoltar() {
    window.location.href = '{{ route("vendas.pdv") }}';
}


function toggleDropdownFormaPagamento() {
    const dropdown = document.getElementById('dropdown-forma-pagamento');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
        destacarFormaDropdown();
    }
}

function abrirDropdownFormaPagamento() {
    const dropdown = document.getElementById('dropdown-forma-pagamento');
    dropdown.classList.remove('hidden');
    indiceFormaDestacada = formasPagamento.findIndex(f => f.valor === formaSelecionada);
    destacarFormaDropdown();
}

function fecharDropdownFormaPagamento() {
    document.getElementById('dropdown-forma-pagamento').classList.add('hidden');
}

function destacarFormaDropdown() {
    document.querySelectorAll('#dropdown-forma-pagamento > div').forEach((el, i) => {
        el.classList.toggle('bg-amber-50', i === indiceFormaDestacada);
        el.classList.toggle('text-[#10284A]', i === indiceFormaDestacada);
        el.classList.toggle('font-semibold', i === indiceFormaDestacada);
    });
}

function selecionarFormaPagamento(valor) {
    formaSelecionada = valor;
    const label = formasPagamento.find(f => f.valor === valor).label;
    document.getElementById('btn-forma-pagamento').innerText = label;
    fecharDropdownFormaPagamento();
    document.getElementById('btn-forma-pagamento').focus();
}

document.getElementById('btn-forma-pagamento').addEventListener('keydown', (e) => {
    const dropdown = document.getElementById('dropdown-forma-pagamento');

    if (dropdown.classList.contains('hidden')) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceFormaDestacada = (indiceFormaDestacada + 1) % formasPagamento.length;
        destacarFormaDropdown();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceFormaDestacada = (indiceFormaDestacada - 1 + formasPagamento.length) % formasPagamento.length;
        destacarFormaDropdown();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        selecionarFormaPagamento(formasPagamento[indiceFormaDestacada].valor);
        adicionarPagamento();
    }
});


document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modalAberto = ['modal-autorizacao', 'modal-desconto-global', 'modal-tipo-desconto', 'modal-confirmar-voltar', 'modal-cliente', 'modal-credito-loja', 'modal-convenio']
            .some(id => !document.getElementById(id).classList.contains('hidden'));
        if (modalAberto) {
            fecharTodosModais();
        } else {
            abrirModalConfirmarVoltar(); // era window.location.href
        }
    }
    if (e.key === 'F2') { e.preventDefault(); finalizarVenda(); }
    if (e.key === 'F4') { e.preventDefault(); abrirModalCliente(); }
    if (e.key === 'F5') { e.preventDefault(); abrirModalDescontoGlobal(); }
});


function fecharTodosModais() {
    ['modal-autorizacao', 'modal-desconto-global', 'modal-tipo-desconto', 'modal-confirmar-voltar', 'modal-cliente', 'modal-credito-loja', 'modal-convenio'].forEach(id => {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).classList.remove('flex');
    });

    if (_handlerTipoDesconto) {
        document.removeEventListener('keydown', _handlerTipoDesconto);
        _handlerTipoDesconto = null;
    }

    if (_handlerConfirmarVoltar) {
        document.removeEventListener('keydown', _handlerConfirmarVoltar);
        _handlerConfirmarVoltar = null;
    }
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
    document.getElementById('autorizacao-senha').value = '';
    document.getElementById('autorizacao-erro').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('hidden');
    document.getElementById('modal-autorizacao').classList.add('flex');
}


function abrirEscolhaTipoDesconto() {
    document.getElementById('modal-tipo-desconto').classList.remove('hidden');
    document.getElementById('modal-tipo-desconto').classList.add('flex');

    _handlerTipoDesconto = function (e) {
        if (e.key === '1') { e.preventDefault(); escolherTipoDesconto('valor'); }
        if (e.key === '2') { e.preventDefault(); escolherTipoDesconto('porcentagem'); }
    };
    document.addEventListener('keydown', _handlerTipoDesconto);
}


function fecharModalAutorizacao() {
    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ codigo: usuario, password: senha, acao: 'desconto_global' }),
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

    document.getElementById('modal-autorizacao').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('flex');
    abrirEscolhaTipoDesconto();
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

    abrirLancamentoDescontoGlobal();
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


function fecharModalDescontoGlobal() {
    document.getElementById('modal-desconto-global').classList.add('hidden');
    document.getElementById('modal-desconto-global').classList.remove('flex');
}

function confirmarDescontoGlobal() {
    let entrada = parseFloat(document.getElementById('desconto-global-valor').value) || 0;
    const erroP = document.getElementById('desconto-global-erro');

    if (tipoDescontoEscolhido === 'porcentagem') {
        if (entrada < 0 || entrada > 100) {
            erroP.innerText = 'Porcentagem deve ser entre 0 e 100.';
            erroP.classList.remove('hidden');
            return;
        }
        const totalAtivo = Math.max(totalDaVenda, 0);
        descontoGlobal = Math.round((totalAtivo * entrada / 100) * 100) / 100;
    } else {
        descontoGlobal = entrada;
    }

    tipoDescontoEscolhido = null;
    fecharModalDescontoGlobal();
    atualizarResumoDesconto();
}

function atualizarResumoDesconto() {
    const totalBruto = {{ $total }} + {{ $desconto_item }};
    const totalLiquido = totalBruto - {{ $desconto_item }} - descontoGlobal;

    document.getElementById('total-pagamento-exibido').innerText = 'R$ ' + Math.max(totalLiquido, 0).toFixed(2).replace('.', ',');
    document.getElementById('desconto-global-exibido').innerText = 'R$ ' + descontoGlobal.toFixed(2).replace('.', ',');
    document.getElementById('desconto-total-exibido').innerText = 'R$ ' + ({{ $desconto_item }} + descontoGlobal).toFixed(2).replace('.', ',');

    atualizarRestante();
}


document.getElementById('novo-valor-pagamento').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const valor = parseFloat(document.getElementById('novo-valor-pagamento').value);

        if (!valor || valor <= 0) {
            alert('Informe um valor válido.');
            return;
        }

        document.getElementById('btn-forma-pagamento').focus();
        abrirDropdownFormaPagamento();
    }
});



function atualizarRestante() {
    const totalComDesconto = Math.max(totalDaVenda - descontoGlobal, 0);
    const pago = pagamentos.reduce((soma, p) => soma + p.valor, 0);
    const diferenca = pago - totalComDesconto;

    const label = document.getElementById('restante-pagamento');

    if (diferenca < -0.009) {
        label.innerText = 'Falta: R$ ' + Math.abs(diferenca).toFixed(2).replace('.', ',');
        label.className = 'text-lg font-bold text-red-600';
    } else if (diferenca > 0.009) {
        label.innerText = 'Troco: R$ ' + diferenca.toFixed(2).replace('.', ',');
        label.className = 'text-lg font-bold text-[#10284A]';
    } else {
        label.innerText = 'R$ 0,00';
        label.className = 'text-lg font-bold text-emerald-600';
    }

    return diferenca;
}

function adicionarPagamento() {
    const forma = formaSelecionada;
    const valor = parseFloat(document.getElementById('novo-valor-pagamento').value);

    if (forma === 'credito_loja') {
        abrirModalCreditoLoja(valor);
        return;
    }

    if (forma === 'convenio') {
        abrirModalConvenio(valor);
        return;
    }

    if (!valor || valor <= 0) {
        alert('Informe um valor válido.');
        return;
    }

    pagamentos.push({ forma_pagamento: forma, valor });
    document.getElementById('novo-valor-pagamento').value = '';
    renderizarPagamentos();

    const diferenca = atualizarRestante();

    if (diferenca >= -0.009) {
        document.getElementById('btn-finalizar').focus();
    } else {
        document.getElementById('novo-valor-pagamento').focus();
    }
}

// ===================== CRÉDITO EM LOJA (troca / haver do cliente) =====================
const URL_CREDITOS_LOJA = @json(route('vendas.creditos-loja'));
let valorPedidoCreditoLoja = null;
let creditosLojaAtuais = [];

function faltaPagar() {
    const total = Math.max(totalDaVenda - descontoGlobal, 0);
    return Math.max(0, Math.round((total - pagamentos.reduce((s, p) => s + p.valor, 0)) * 100) / 100);
}

function abrirModalCreditoLoja(valor) {
    if (faltaPagar() <= 0) {
        alert('A venda já está paga.');
        return;
    }
    valorPedidoCreditoLoja = valor > 0 ? valor : null;
    document.getElementById('credito-loja-codigo').value = '';
    document.getElementById('credito-loja-subtitulo').textContent = clienteSelecionadoId
        ? 'Créditos do consumidor identificado — ou digite o código de um vale-troca'
        : 'Digite o código do vale-troca (consumidor não identificado)';
    const modal = document.getElementById('modal-credito-loja');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    buscarCreditosLoja();
    setTimeout(() => document.getElementById('credito-loja-codigo').focus(), 100);
}

function fecharModalCreditoLoja() {
    const modal = document.getElementById('modal-credito-loja');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('novo-valor-pagamento').focus();
}

async function buscarCreditosLoja() {
    const codigo = document.getElementById('credito-loja-codigo').value.trim().toUpperCase();
    const lista = document.getElementById('credito-loja-lista');
    const vazio = document.getElementById('credito-loja-vazio');
    lista.replaceChildren();
    vazio.classList.add('hidden');

    if (!codigo && !clienteSelecionadoId) {
        vazio.textContent = 'Identifique o consumidor (F4) ou digite o código do vale-troca.';
        vazio.classList.remove('hidden');
        return;
    }

    const params = new URLSearchParams(codigo ? { codigo } : { cliente_id: clienteSelecionadoId });
    let dados = [];
    try {
        const resp = await fetch(`${URL_CREDITOS_LOJA}?${params}`, { headers: { 'Accept': 'application/json' } });
        dados = await resp.json();
    } catch (e) {
        dados = [];
    }

    // O que já foi lançado nesta venda sai do saldo mostrado
    creditosLojaAtuais = dados.map(c => ({
        ...c,
        saldo: Math.round((c.saldo - pagamentos.filter(p => p.credito_loja_id === c.id).reduce((s, p) => s + p.valor, 0)) * 100) / 100,
    })).filter(c => c.saldo > 0);

    if (creditosLojaAtuais.length === 0) {
        vazio.textContent = codigo
            ? 'Vale-troca não encontrado ou sem saldo neste caixa. Use "Atualizar Caixa" se ele foi gerado agora.'
            : 'O consumidor não tem crédito em loja.';
        vazio.classList.remove('hidden');
        return;
    }

    creditosLojaAtuais.forEach((c, i) => {
        const li = document.createElement('li');
        li.className = 'flex justify-between items-center px-3 py-2.5 hover:bg-blue-50 cursor-pointer text-sm';
        const rotulo = document.createElement('span');
        rotulo.textContent = c.codigo + (c.cliente ? ' — ' + c.cliente : ' — vale-troca');
        const saldo = document.createElement('strong');
        saldo.textContent = 'R$ ' + c.saldo.toFixed(2).replace('.', ',');
        li.append(rotulo, saldo);
        li.addEventListener('click', () => usarCreditoLoja(i));
        lista.appendChild(li);
    });
}

function usarCreditoLoja(indice) {
    const c = creditosLojaAtuais[indice];
    if (!c) return;

    if (c.cliente_id && Number(c.cliente_id) !== Number(clienteSelecionadoId)) {
        alert('Este crédito é de ' + c.cliente + '. Identifique esse consumidor (F4) antes de usar o crédito.');
        return;
    }

    // Usa o valor digitado, limitado ao saldo do crédito e ao que falta pagar (crédito não dá troco)
    const valor = Math.min(valorPedidoCreditoLoja ?? Infinity, c.saldo, faltaPagar());
    if (valor <= 0) return;

    pagamentos.push({ forma_pagamento: 'credito_loja', valor: Math.round(valor * 100) / 100, credito_loja_id: c.id, codigo: c.codigo });
    document.getElementById('novo-valor-pagamento').value = '';
    fecharModalCreditoLoja();
    renderizarPagamentos();
    if (atualizarRestante() >= -0.009) document.getElementById('btn-finalizar').focus();
}

// ===================== CONVÊNIO (venda fiado no limite de crédito do cliente) =====================
const URL_CONVENIO = @json(route('vendas.convenio'));
let valorPedidoConvenio = null;
let convenioAtual = null;
let clienteConvenioPendente = null; // consumidor escolhido dentro do modal: {id, label}
let timeoutClienteConvenio;

const moedaBr = v => 'R$ ' + Number(v).toFixed(2).replace('.', ',');

function abrirModalConvenio(valor) {
    if (faltaPagar() <= 0) {
        alert('A venda já está paga.');
        return;
    }
    valorPedidoConvenio = valor > 0 ? valor : null;
    clienteConvenioPendente = null;

    const modal = document.getElementById('modal-convenio');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Sem consumidor identificado: o modal pede o consumidor antes de mostrar o limite
    if (clienteSelecionadoId && clienteSelecionadoId > 0) {
        carregarConvenio(clienteSelecionadoId, document.getElementById('resumo-cliente-label').innerText);
    } else {
        mostrarPassoClienteConvenio();
    }
}

function fecharModalConvenio() {
    const modal = document.getElementById('modal-convenio');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('novo-valor-pagamento').focus();
}

function mostrarPassoClienteConvenio() {
    document.getElementById('convenio-passo-limite').classList.add('hidden');
    document.getElementById('convenio-passo-cliente').classList.remove('hidden');
    document.getElementById('convenio-cliente-busca').value = '';
    buscarClienteConvenio();
    setTimeout(() => document.getElementById('convenio-cliente-busca').focus(), 100);
}

function trocarClienteConvenio() {
    mostrarPassoClienteConvenio();
}

const navegacaoClientesConvenio = listaNavegavel('convenio-cliente-busca', 'convenio-cliente-lista');

function buscarClienteConvenio() {
    clearTimeout(timeoutClienteConvenio);
    timeoutClienteConvenio = setTimeout(async () => {
        const termo = document.getElementById('convenio-cliente-busca').value;
        let itens = [];
        try {
            const resp = await fetch(`{{ route('clientes.buscar') }}?q=${encodeURIComponent(termo)}`);
            itens = await resp.json();
        } catch (e) {
            itens = [];
        }

        const lista = document.getElementById('convenio-cliente-lista');
        lista.replaceChildren();
        document.getElementById('convenio-cliente-vazio').classList.toggle('hidden', itens.length > 0);

        itens.forEach(c => lista.appendChild(linhaCliente(c, () => {
            clienteConvenioPendente = { id: c.id, label: c.label };
            carregarConvenio(c.id, c.label);
        })));
        navegacaoClientesConvenio.definir(itens, c => {
            clienteConvenioPendente = { id: c.id, label: c.label };
            carregarConvenio(c.id, c.label);
        });
    }, 200);
}

async function carregarConvenio(clienteId, rotulo) {
    document.getElementById('convenio-passo-cliente').classList.add('hidden');
    document.getElementById('convenio-passo-limite').classList.remove('hidden');
    document.getElementById('convenio-cliente-nome').textContent = rotulo || ('Cliente #' + clienteId);
    document.getElementById('convenio-erro').classList.add('hidden');

    const semLimite = document.getElementById('convenio-sem-limite');
    const comLimite = document.getElementById('convenio-com-limite');
    semLimite.classList.add('hidden');
    comLimite.classList.add('hidden');
    convenioAtual = null;

    if (clienteId < 0) {
        semLimite.textContent = 'Cliente cadastrado agora neste caixa: ainda não tem limite de convênio. Conceda o limite no painel e use "Atualizar Caixa".';
        semLimite.classList.remove('hidden');
        return;
    }

    let dados = null;
    try {
        const resp = await fetch(`${URL_CONVENIO}?cliente_id=${encodeURIComponent(clienteId)}`, { headers: { 'Accept': 'application/json' } });
        dados = await resp.json();
    } catch (e) {
        dados = null;
    }

    if (!dados || !dados.tem_limite) {
        semLimite.textContent = 'Este cliente não tem limite de convênio. O limite é concedido no cadastro do cliente (aba "Limite de crédito"); se foi concedido agora, use "Atualizar Caixa".';
        semLimite.classList.remove('hidden');
        return;
    }

    // O que já foi lançado no convênio nesta venda sai do disponível mostrado
    const jaLancado = pagamentos.filter(p => p.forma_pagamento === 'convenio').reduce((s, p) => s + p.valor, 0);
    const disponivel = Math.max(0, Math.round((dados.disponivel - jaLancado) * 100) / 100);
    convenioAtual = { ...dados, disponivel };

    document.getElementById('convenio-limite').textContent = moedaBr(dados.limite);
    document.getElementById('convenio-usado').textContent = moedaBr(dados.usado + jaLancado);
    document.getElementById('convenio-disponivel').textContent = moedaBr(disponivel);
    document.getElementById('convenio-prazo').textContent = `A venda vira uma conta a receber com vencimento em ${dados.dias} dias. Convênio não dá troco.`;
    comLimite.classList.remove('hidden');

    const falta = faltaPagar();
    const sugerido = Math.min(valorPedidoConvenio ?? falta, falta, disponivel);
    const campo = document.getElementById('convenio-valor');
    campo.value = sugerido > 0 ? sugerido.toFixed(2) : '';

    const aviso = document.getElementById('convenio-aviso');
    if (disponivel <= 0) {
        aviso.textContent = 'O limite do cliente está todo usado. Receba por outra forma de pagamento.';
        aviso.classList.remove('hidden');
    } else if (disponivel < falta) {
        aviso.textContent = `O limite disponível (${moedaBr(disponivel)}) é menor que o que falta pagar (${moedaBr(falta)}). Use o limite e receba o restante (${moedaBr(falta - disponivel)}) em outra forma de pagamento.`;
        aviso.classList.remove('hidden');
    } else {
        aviso.classList.add('hidden');
    }

    setTimeout(() => { campo.focus(); campo.select(); }, 100);
}

function confirmarConvenio() {
    if (!convenioAtual) return;
    const erro = document.getElementById('convenio-erro');
    const valor = Math.round((parseFloat(document.getElementById('convenio-valor').value) || 0) * 100) / 100;

    const mensagem = valor <= 0 ? 'Informe o valor a lançar no convênio.'
        : valor - convenioAtual.disponivel > 0.009 ? `O limite disponível é ${moedaBr(convenioAtual.disponivel)}.`
        : valor - faltaPagar() > 0.009 ? `Falta pagar só ${moedaBr(faltaPagar())} (convênio não dá troco).`
        : null;

    if (mensagem) {
        erro.textContent = mensagem;
        erro.classList.remove('hidden');
        return;
    }

    // Consumidor escolhido no modal passa a ser o consumidor da venda (sai na NFC-e)
    if (clienteConvenioPendente) {
        selecionarCliente(clienteConvenioPendente.id, clienteConvenioPendente.label);
        clienteConvenioPendente = null;
    }

    pagamentos.push({ forma_pagamento: 'convenio', valor, cliente_convenio_id: convenioAtual.cliente_id });
    document.getElementById('novo-valor-pagamento').value = '';
    fecharModalConvenio();
    renderizarPagamentos();
    if (atualizarRestante() >= -0.009) document.getElementById('btn-finalizar').focus();
}

// Convênio lançado é do consumidor da venda: trocar ou remover o consumidor tira o convênio lançado
function descartarConvenioDeOutroCliente(novoClienteId) {
    const antes = pagamentos.length;
    pagamentos = pagamentos.filter(p => p.forma_pagamento !== 'convenio' || Number(p.cliente_convenio_id) === Number(novoClienteId));
    if (pagamentos.length !== antes) {
        renderizarPagamentos();
        alert('O pagamento no convênio era do consumidor anterior e foi retirado. Lance o convênio de novo, se for o caso.');
    }
}

function removerPagamento(index) {
    pagamentos.splice(index, 1);
    renderizarPagamentos();
}

function renderizarPagamentos() {
    const nomes = { dinheiro: 'Dinheiro', pix: 'PIX', credito: 'Crédito', debito: 'Débito', credito_loja: 'Crédito em loja', convenio: 'Convênio' };

    document.getElementById('lista-pagamentos').innerHTML = pagamentos.length === 0
        ? '<p class="text-sm text-slate-400 rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-center">Nenhum pagamento lançado</p>'
        : pagamentos.map((p, i) => `
        <div class="flex justify-between items-center bg-slate-50 ring-1 ring-slate-200 rounded-xl px-3 py-2 text-sm">
            <span class="text-slate-700"><strong class="text-slate-800">${nomes[p.forma_pagamento]}</strong>${p.codigo ? ' (' + p.codigo + ')' : ''}</span>
            <span class="flex items-center gap-3">
                <strong class="text-[#10284A]">R$ ${p.valor.toFixed(2).replace('.', ',')}</strong>
                <button onclick="removerPagamento(${i})" class="text-red-500 text-xs hover:underline">Remover</button>
            </span>
        </div>
    `).join('');

    atualizarRestante();
}

// ===================== FINALIZAR (F2): registra a venda, emite a NFC-e e pergunta se imprime =====================
const URL_EMITIR = @json(route('vendas.emitir', '__UUID__'));
const URL_NOVA_VENDA = @json(route('vendas.pdv'));
let vendaFinalizadaUuid = null;
let etapaEmissao = null; // null | 'carregando' | 'pergunta' | 'imprimindo' | 'falha'
let timerDemora = null;

const ICONES_EMISSAO = {
    ok: ['bg-green-100 text-green-600', '<svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'],
    contingencia: ['bg-amber-100 text-amber-600', '<svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>'],
    falha: ['bg-red-100 text-red-600', '<svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'],
};

function mostrarCarregandoEmissao() {
    etapaEmissao = 'carregando';
    const modal = document.getElementById('modal-emissao');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('emissao-carregando').classList.remove('hidden');
    document.getElementById('emissao-resultado').classList.add('hidden');

    const demora = document.getElementById('emissao-demora');
    demora.classList.add('invisible');
    clearTimeout(timerDemora);
    timerDemora = setTimeout(() => demora.classList.remove('invisible'), 5000);
}

function fecharModalEmissao() {
    clearTimeout(timerDemora);
    etapaEmissao = null;
    const modal = document.getElementById('modal-emissao');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function mostrarResultadoEmissao(tipo, titulo, detalhe) {
    clearTimeout(timerDemora);
    document.getElementById('emissao-carregando').classList.add('hidden');
    document.getElementById('emissao-resultado').classList.remove('hidden');

    const [cores, svg] = ICONES_EMISSAO[tipo];
    const icone = document.getElementById('emissao-icone');
    icone.className = 'mx-auto w-16 h-16 rounded-full flex items-center justify-center ' + cores;
    icone.innerHTML = svg;

    document.getElementById('emissao-titulo').textContent = titulo;
    document.getElementById('emissao-detalhe').textContent = detalhe || '';
    document.getElementById('emissao-impressao').classList.add('hidden');

    const falhou = tipo === 'falha';
    document.getElementById('emissao-pergunta').classList.toggle('hidden', falhou);
    document.getElementById('emissao-falha').classList.toggle('hidden', !falhou);
    etapaEmissao = falhou ? 'falha' : 'pergunta';
}

function erroNoPagamento(mensagem) {
    fecharModalEmissao();
    const erroP = document.getElementById('erro-venda');
    erroP.innerText = mensagem;
    erroP.classList.remove('hidden');
}

async function finalizarVenda() {
    if (etapaEmissao || vendaFinalizadaUuid) return; // já está emitindo (F2 repetido)

    const diferenca = atualizarRestante();
    if (diferenca < -0.009) {
        document.getElementById('erro-venda').innerText = 'A soma dos pagamentos é menor que o total da venda.';
        document.getElementById('erro-venda').classList.remove('hidden');
        return;
    }

    document.getElementById('erro-venda').classList.add('hidden');
    mostrarCarregandoEmissao();

    const payload = {
        itens: itensDaVenda.map(i => ({
            produto_id: i.produto_id,
            produto_variante_id: i.produto_variante_id,
            quantidade: i.quantidade,
            desconto: i.desconto,
        })),
        pagamentos: pagamentos.map(p => ({ forma_pagamento: p.forma_pagamento, valor: p.valor, credito_loja_id: p.credito_loja_id ?? null })),
        desconto_global: descontoGlobal,
        cliente_id: clienteSelecionadoId,
        cpf_na_nota: cpfNaNotaValor,
    };

    try {
        const resp = await fetch('{{ route("vendas.finalizar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(payload),
        });

        const data = await resp.json().catch(() => ({}));

        if (!resp.ok || !data.venda_uuid) {
            erroNoPagamento(data.erro || data.message || 'Erro ao finalizar venda.');
            return;
        }

        vendaFinalizadaUuid = data.venda_uuid;
    } catch (e) {
        erroNoPagamento('Erro de conexão. Tente novamente.');
        return;
    }

    // A venda já está gravada: daqui em diante não volta mais para a tela de pagamento
    await emitirVendaFinalizada();
}

async function emitirVendaFinalizada() {
    mostrarCarregandoEmissao();

    let resultado;
    try {
        const resp = await fetch(URL_EMITIR.replace('__UUID__', encodeURIComponent(vendaFinalizadaUuid)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
        });
        resultado = await resp.json();
    } catch (e) {
        resultado = { sucesso: false, erro: 'Erro de conexão ao tentar emitir.' };
    }

    if (resultado.sucesso) {
        mostrarResultadoEmissao('ok', 'Venda emitida', resultado.chave ? 'Chave: ' + resultado.chave : '');
    } else if (resultado.contingencia) {
        mostrarResultadoEmissao('contingencia', 'Venda emitida em contingência',
            'Sem comunicação no momento. A NFC-e será transmitida automaticamente quando a conexão voltar.');
    } else {
        mostrarResultadoEmissao('falha', 'A NFC-e não foi emitida',
            (resultado.erro || 'Erro desconhecido.') + ' A venda já está registrada.');
    }
}

async function responderImpressaoVenda(imprimir) {
    if (etapaEmissao !== 'pergunta') return;
    if (!imprimir) {
        irParaNovaVenda();
        return;
    }

    etapaEmissao = 'imprimindo';
    document.getElementById('emissao-pergunta').classList.add('hidden');
    const msg = document.getElementById('emissao-impressao');
    msg.className = 'mt-4 text-sm text-gray-600';
    msg.textContent = 'Imprimindo comprovante...';

    let espera = 800;
    try {
        await imprimirCupomFiscal(vendaFinalizadaUuid); // só segue quando a impressão terminar
        msg.className = 'mt-4 text-sm text-green-700';
        msg.textContent = '✓ Comprovante enviado para a impressora.';
    } catch (e) {
        msg.className = 'mt-4 text-sm text-red-600';
        msg.textContent = 'Não foi possível imprimir: ' + e.message + ' Reimprima pelo menu Impressão (F7).';
        espera = 4000;
    }

    setTimeout(irParaNovaVenda, espera);
}

function irParaNovaVenda() {
    etapaEmissao = 'saindo';
    window.location.href = URL_NOVA_VENDA;
}

// Com o modal aberto, só ele responde ao teclado (Enter/Esc); os atalhos da tela ficam bloqueados
window.addEventListener('keydown', (e) => {
    if (!etapaEmissao) return;
    e.preventDefault();
    e.stopImmediatePropagation();

    if (etapaEmissao === 'pergunta') {
        if (e.key === 'Enter') responderImpressaoVenda(true);
        else if (e.key === 'Escape') responderImpressaoVenda(false);
    } else if (etapaEmissao === 'falha') {
        if (e.key === 'Enter') emitirVendaFinalizada();
        else if (e.key === 'Escape') irParaNovaVenda();
    }
}, true);
renderizarPagamentos(); // já mostra "Nenhum pagamento lançado" e o restante
</script>
@endsection