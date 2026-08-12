@extends('layouts.app')

@section('titulo', 'Pagamento')

@section('conteudo')
<div class="max-w-md mx-auto">
    <a href="{{ route('vendas.pdv') }}" class="text-sm text-gray-500 hover:underline mb-4 inline-block">← Voltar aos itens</a>

    <div class="bg-white rounded shadow p-4">
        <p class="text-sm text-gray-500 mb-1">Total a pagar</p>
        <p class="text-3xl font-bold mb-4">R$ {{ number_format($total, 2, ',', '.') }}</p>

        <div class="text-sm mb-4 space-y-1">
            <p>Desconto por item: <strong class="text-green-600">R$ {{ number_format($desconto_item, 2, ',', '.') }}</strong></p>
            <p>Desconto global: <strong class="text-green-600">R$ {{ number_format($desconto_global, 2, ',', '.') }}</strong></p>
            <p>Desconto aplicado: <strong class="text-green-700">R$ {{ number_format($desconto_item + $desconto_global, 2, ',', '.') }}</strong></p>
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

            <button type="button" onclick="adicionarPagamento()" class="bg-gray-200 hover:bg-gray-300 px-3 rounded text-sm">+</button>
        </div>

        <p class="text-sm mb-4">
            Restante a pagar: <strong id="restante-pagamento">R$ 0,00</strong>
        </p>

        <button id="btn-finalizar" onclick="finalizarVenda()"
                class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold text-lg disabled:opacity-50">
            Aperte F2 Para Finalizar
        </button>

        <p id="erro-venda" class="text-red-600 text-sm mt-2 hidden"></p>
    </div>
</div>
@endsection

@section('scripts')
<script>
const itensDaVenda = @json($itens);
const totalDaVenda = {{ $total }};
let pagamentos = [];


const formasPagamento = [
    { valor: 'dinheiro', label: 'Dinheiro' },
    { valor: 'pix', label: 'PIX' },
    { valor: 'credito', label: 'Cartão de Crédito' },
    { valor: 'debito', label: 'Cartão de Débito' },
];

let formaSelecionada = 'dinheiro';
let indiceFormaDestacada = 0;

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
        window.location.href = '{{ route("vendas.pdv") }}';
    }
    if (e.key === 'F2') {
        e.preventDefault();
        finalizarVenda();
    }
});


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

document.getElementById('nova-forma-pagamento').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        adicionarPagamento();
        document.getElementById('novo-valor-pagamento').focus();
    }
});


function atualizarRestante() {
    const pago = pagamentos.reduce((soma, p) => soma + p.valor, 0);
    const diferenca = pago - totalDaVenda;

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