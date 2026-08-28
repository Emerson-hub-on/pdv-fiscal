@extends('layouts.app')

@section('titulo', 'Pagamento')

@section('conteudo')
<style>
    #nav-principal { display: none; }
</style>
<div class="max-w-md mx-auto pt-32">
    <div class="fixed top-0 left-0 right-0 z-50
            bg-linear-to-r from-slate-800 via-slate-900 to-slate-900
            shadow-lg">
        <div class="flex justify-between items-center px-6 py-4">
            <div>
                <h1 class="text-xl font-bold text-white tracking-tight">Pagamento</h1>
                <p class="text-xs text-slate-400 mt-0.5">Finalize a venda ou volte para ajustar os itens</p>
            </div>
            <div class="flex gap-2">
                <button onclick="abrirModalCliente()"
                        class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                    <span id="btn-cliente-label">Adicionar consumidor</span> <span class="opacity-60 text-xs">F4</span>
                </button>
                <button onclick="abrirModalDescontoGlobal()"
                        class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                    Desconto Geral <span class="opacity-60 text-xs">F5</span>
                </button>
                <button onclick="abrirModalConfirmarVoltar()"
                        class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                    ← Voltar aos itens
                </button>
            </div>
        </div>
    </div>
    <div class="bg-white rounded shadow p-4">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Total a pagar</p>
        <p id="total-pagamento-exibido" class="text-4xl font-bold text-slate-900 mb-5">
            R$ {{ number_format($total, 2, ',', '.') }}
        </p>

        <div class="bg-gray-50 rounded-lg p-3 text-sm mb-5 space-y-1.5">
            <p class="flex justify-between">Desconto por item
                <strong class="text-green-600">R$ {{ number_format($desconto_item, 2, ',', '.') }}</strong>
            </p>
            <p class="flex justify-between">Desconto global
                <strong id="desconto-global-exibido" class="text-green-600">R$ 0,00</strong>
            </p>
            <p class="flex justify-between border-t border-gray-200 pt-1.5 mt-1.5">Total de desconto
                <strong id="desconto-total-exibido" class="text-green-700">R$ {{ number_format($desconto_item, 2, ',', '.') }}</strong>
            </p>
        </div>

        <div id="resumo-cliente" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm mb-4 flex justify-between items-center">
            <span id="resumo-cliente-label" class="text-blue-800"></span>
            <button type="button" onclick="removerClienteSelecionado()" class="text-xs text-blue-600 hover:underline">Remover</button>
        </div>

        <label class="block text-sm font-medium mb-1">Pagamentos</label>
        <div id="lista-pagamentos" class="space-y-2 mb-2"></div>

        <div class="flex gap-2 mb-2">
            <input type="number" step="0.01" id="novo-valor-pagamento" placeholder="Valor" autofocus class="w-24 border rounded px-2 py-1 text-sm">

            <div class="relative flex-1">
                <button type="button" id="btn-forma-pagamento" onclick="toggleDropdownFormaPagamento()"
                        class="w-full border rounded px-2 py-1 text-sm text-left bg-white">
                    Dinheiro
                </button>
                <div id="dropdown-forma-pagamento" class="absolute bg-white border rounded shadow w-full mt-1 hidden z-10">
                    <div class="p-2 cursor-pointer text-sm" data-forma="dinheiro" data-index="0" onclick="selecionarFormaPagamento('dinheiro')">Dinheiro</div>
                    <div class="p-2 cursor-pointer text-sm" data-forma="pix" data-index="1" onclick="selecionarFormaPagamento('pix')">PIX</div>
                    <div class="p-2 cursor-pointer text-sm" data-forma="credito" data-index="2" onclick="selecionarFormaPagamento('credito')">Cartão de Crédito</div>
                    <div class="p-2 cursor-pointer text-sm" data-forma="debito" data-index="3" onclick="selecionarFormaPagamento('debito')">Cartão de Débito</div>
                </div>
            </div>
        </div>

        <p class="text-sm mb-4">
            Restante a pagar: <strong id="restante-pagamento">R$ 0,00</strong>
        </p>

        <button id="btn-finalizar" onclick="finalizarVenda()"
                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3 rounded font-bold text-lg disabled:opacity-50">
            Aperte F2 Para Finalizar
        </button>
        <p id="erro-venda" class="text-red-600 text-sm mt-2 hidden"></p>
    </div>


<!-- Modal Adicionar Consumidor -->
<div id="modal-cliente" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Adicionar Consumidor</h2>
            <button type="button" onclick="fecharModalCliente()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-3">
            <input type="text" id="cliente-busca" placeholder="Buscar por nome ou CPF/CNPJ..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarCliente()">
            <button type="button" onclick="abrirFormNovoCliente()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo
            </button>
            <button type="button" onclick="abrirFormCpfNaNota()"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-3 py-2 rounded whitespace-nowrap">
                CPF na nota
            </button>
        </div>

        <div id="form-cpf-na-nota" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">CPF do consumidor</label>
            <input type="text" id="cpf-na-nota-valor" placeholder="000.000.000-00" maxlength="14"
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <p class="text-xs text-gray-400 mb-2">Só o CPF vai na nota fiscal — não cria cadastro de cliente.</p>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormCpfNaNota()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="confirmarCpfNaNota()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Confirmar</button>
            </div>
        </div>

        <div id="form-novo-cliente" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
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
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <input type="text" id="novo-cliente-documento" placeholder="CPF"
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <p class="text-xs text-gray-400 mb-2">Cadastro rápido — complete o endereço depois na tela de Clientes, se precisar.</p>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoCliente()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoCliente()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar e selecionar</button>
            </div>
        </div>

        <ul id="cliente-lista" class="divide-y divide-gray-100"></ul>
        <p id="cliente-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhum cliente encontrado.</p>
    </div>
</div>

<!-- Modal de autorização do supervisor -->
<div id="modal-autorizacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Autorização do Supervisor</h2>
            <button onclick="fecharModalAutorizacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500 mb-4" id="autorizacao-descricao"></p>
        <label class="block text-sm font-medium mb-1">Usuário</label>
        <input type="text" id="autorizacao-usuario" class="w-full border rounded px-3 py-2 mb-3">
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
                <span class="text-sm font-semibold text-gray-700">Por Valor</span>
            </button>
            <button onclick="escolherTipoDesconto('porcentagem')"
                    class="flex flex-col items-center justify-center gap-2 border-2 border-gray-200 hover:border-purple-500 hover:bg-purple-50 rounded-xl p-6 transition">
                <span class="text-3xl">%</span>
                <span class="text-sm font-semibold text-gray-700">Por Porcentagem</span>
            </button>
        </div>
        <p class="text-center text-xs text-gray-400 mt-4">Ou pressione <strong>1</strong> para valor · <strong>2</strong> para porcentagem</p>
    </div>
</div>

<!-- Modal desconto global -->
<div id="modal-desconto-global" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
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


<!-- Modal de confirmação de volta -->
<div id="modal-confirmar-voltar" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Voltar aos itens?</h2>
        </div>
        <p class="text-sm text-gray-500 mb-6">Os pagamentos lançados serão perdidos. Deseja realmente voltar?</p>
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
        <p class="text-center text-xs text-gray-400 mt-4">Pressione <strong>1</strong> para confirmar · <strong>2</strong> para cancelar</p>
    </div>
</div>

</div>
@endsection

@section('scripts')
<script>
const itensDaVenda = @json($itens);
const totalDaVenda = {{ $total }};
let pagamentos = [];
let descontoGlobal = 0;
let tipoDescontoPendente = null;
let formaSelecionada = 'dinheiro';
let indiceFormaDestacada = 0;
let tipoDescontoEscolhido = null;
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
];

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

function buscarCliente() {
    clearTimeout(timeoutCliente);
    timeoutCliente = setTimeout(async () => {
        const termo = document.getElementById('cliente-busca').value;

        const resp = await fetch(`{{ route('clientes.buscar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('cliente-lista');
        const vazio = document.getElementById('cliente-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        lista.innerHTML = items.map(c => `
            <li class="py-2 px-1 hover:bg-blue-50 rounded text-sm cursor-pointer"
                onclick="selecionarCliente(${c.id}, '${c.label.replace(/'/g, "\\'")}')">
                ${c.label}
            </li>
        `).join('');
    }, 200);
}

function selecionarCliente(id, label) {
    clienteSelecionadoId = id;
    cpfNaNotaValor = null; // mutuamente exclusivo
    document.getElementById('btn-cliente-label').innerText = 'Consumidor identificado';
    document.getElementById('resumo-cliente').classList.remove('hidden');
    document.getElementById('resumo-cliente-label').innerText = label;
    fecharModalCliente();
}

function removerClienteSelecionado() {
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
        el.classList.toggle('bg-blue-100', i === indiceFormaDestacada);
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
        const modalAberto = ['modal-autorizacao', 'modal-desconto-global', 'modal-tipo-desconto', 'modal-confirmar-voltar', 'modal-cliente']
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
    ['modal-autorizacao', 'modal-desconto-global', 'modal-tipo-desconto', 'modal-confirmar-voltar', 'modal-cliente'].forEach(id => {
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
    document.getElementById('autorizacao-descricao').innerText = 'Autorização necessária para aplicar desconto geral.';
    document.getElementById('autorizacao-usuario').value = '';
    document.getElementById('autorizacao-senha').value = '';
    document.getElementById('autorizacao-erro').classList.add('hidden');
    document.getElementById('modal-autorizacao').classList.remove('hidden');
    document.getElementById('modal-autorizacao').classList.add('flex');
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
            body: JSON.stringify({ username: usuario, password: senha }),
        });
        const resultado = await resp.json();
        if (!resultado.autorizado) {
            erroP.innerText = 'Usuário ou senha do supervisor inválidos.';
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

    document.getElementById('modal-tipo-desconto').classList.remove('hidden');
    document.getElementById('modal-tipo-desconto').classList.add('flex');

    _handlerTipoDesconto = function (e) {
        if (e.key === '1') { e.preventDefault(); escolherTipoDesconto('valor'); }
        if (e.key === '2') { e.preventDefault(); escolherTipoDesconto('porcentagem'); }
    };
    document.addEventListener('keydown', _handlerTipoDesconto);
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
        label.className = 'text-red-600';
    } else if (diferenca > 0.009) {
        label.innerText = 'Troco: R$ ' + diferenca.toFixed(2).replace('.', ',');
        label.className = 'text-blue-600 font-semibold';
    } else {
        label.innerText = 'R$ 0,00';
        label.className = 'text-green-600';
    }

    return diferenca;
}

function adicionarPagamento() {
    const forma = formaSelecionada;
    const valor = parseFloat(document.getElementById('novo-valor-pagamento').value);

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

function removerPagamento(index) {
    pagamentos.splice(index, 1);
    renderizarPagamentos();
}

function renderizarPagamentos() {
    const nomes = { dinheiro: 'Dinheiro', pix: 'PIX', credito: 'Crédito', debito: 'Débito' };

    document.getElementById('lista-pagamentos').innerHTML = pagamentos.map((p, i) => `
        <div class="flex justify-between items-center bg-gray-50 border rounded px-2 py-1 text-sm">
            <span>${nomes[p.forma_pagamento]} — R$ ${p.valor.toFixed(2)}</span>
            <button onclick="removerPagamento(${i})" class="text-red-500 text-xs hover:underline">Remover</button>
        </div>
    `).join('');

    atualizarRestante();
}

async function finalizarVenda() {
    const diferenca = atualizarRestante();
    if (diferenca < -0.009) {
        document.getElementById('erro-venda').innerText = 'A soma dos pagamentos é menor que o total da venda.';
        document.getElementById('erro-venda').classList.remove('hidden');
        return;
    }

    const btn = document.getElementById('btn-finalizar');
    const erroP = document.getElementById('erro-venda');

    btn.disabled = true;
    btn.innerText = 'Processando...';
    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
    btn.classList.add('bg-blue-600');
    erroP.classList.add('hidden');

    const payload = {
        itens: itensDaVenda.map(i => ({
            produto_id: i.produto_id,
            produto_variante_id: i.produto_variante_id,
            quantidade: i.quantidade,
            desconto: i.desconto,
        })),
        pagamentos: pagamentos,
        desconto_global: descontoGlobal,
        cliente_id: clienteSelecionadoId,
        cpf_na_nota: cpfNaNotaValor,
    };

    try {
        const resp = await fetch('{{ route("vendas.finalizar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(payload),
        });

        const data = await resp.json();

        if (!resp.ok) {
            erroP.innerText = data.erro || 'Erro ao finalizar venda.';
            erroP.classList.remove('hidden');
            btn.disabled = false;
            btn.innerText = 'Finalizar Venda';
            btn.classList.remove('bg-blue-600');
            btn.classList.add('bg-green-600', 'hover:bg-green-700');
            return;
        }

        window.location.href = `/pdv/venda/${data.venda_uuid}/comprovante`;
    } catch (e) {
        erroP.innerText = 'Erro de conexão. Tente novamente.';
        erroP.classList.remove('hidden');
        btn.disabled = false;
        btn.innerText = 'Finalizar Venda';
        btn.classList.remove('bg-blue-600');
        btn.classList.add('bg-green-600', 'hover:bg-green-700');
    }
}
atualizarRestante();
</script>
@endsection