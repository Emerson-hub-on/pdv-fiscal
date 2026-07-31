@extends('layouts.app')

@section('titulo', 'PDV - Venda')

@section('conteudo')
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Nova Venda</h1>
        <a href="{{ route('caixa.fechar-form') }}" class="text-red-600 hover:underline text-sm">Fechar Caixa</a>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 bg-white rounded shadow p-4">
            <div class="relative mb-4">
                <input type="text" id="busca-produto" placeholder="Buscar por nome, código ou código de barras..."
                       class="w-full border rounded px-3 py-2" autofocus>
                <div id="resultados-busca" class="absolute bg-white border rounded shadow w-full mt-1 hidden z-10"></div>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm text-gray-500 border-b">
                        <th class="py-2">Produto</th>
                        <th class="py-2">Qtd</th>
                        <th class="py-2">Preço</th>
                        <th class="py-2">Subtotal</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody id="linhas-carrinho"></tbody>
            </table>

            <p id="carrinho-vazio" class="text-center text-gray-400 py-10">Nenhum item adicionado.</p>
        </div>

        <div class="bg-white rounded shadow p-4 h-fit">
            <p class="text-sm text-gray-500 mb-1">Total</p>
            <p id="total-venda" class="text-3xl font-bold mb-4">R$ 0,00</p>

            <label class="block text-sm font-medium mb-1">Forma de pagamento</label>
            <select id="forma-pagamento" class="w-full border rounded px-3 py-2 mb-4">
                <option value="dinheiro">Dinheiro</option>
                <option value="pix">PIX</option>
                <option value="credito">Cartão de Crédito</option>
                <option value="debito">Cartão de Débito</option>
            </select>

            <button id="btn-finalizar" onclick="finalizarVenda()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold text-lg disabled:opacity-50">
                Finalizar Venda
            </button>

            <p id="erro-venda" class="text-red-600 text-sm mt-2 hidden"></p>
        </div>
    </div>
@endsection

@section('scripts')
<script>
let carrinho = [];

const inputBusca = document.getElementById('busca-produto');
const resultadosDiv = document.getElementById('resultados-busca');

let timeoutBusca;
inputBusca.addEventListener('input', () => {
    clearTimeout(timeoutBusca);
    const termo = inputBusca.value.trim();

    if (termo.length < 2) {
        resultadosDiv.classList.add('hidden');
        return;
    }

    timeoutBusca = setTimeout(() => buscarProduto(termo), 300);
});

async function buscarProduto(termo) {
    const resp = await fetch(`{{ route('vendas.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();

    if (produtos.length === 0) {
        resultadosDiv.innerHTML = '<div class="p-3 text-sm text-gray-400">Nenhum produto encontrado.</div>';
        resultadosDiv.classList.remove('hidden');
        return;
    }

    resultadosDiv.innerHTML = produtos.map(p => {
        if (p.tem_variacao && p.variantes.length > 0) {
            return p.variantes.map(v => `
                <div class="p-3 hover:bg-gray-100 cursor-pointer border-b text-sm"
                     onclick='adicionarAoCarrinho(${JSON.stringify(p)}, ${JSON.stringify(v)})'>
                    ${p.nome} — ${v.cor ?? ''} ${v.tamanho ?? ''} (estoque: ${v.estoque})
                </div>
            `).join('');
        }
        return `
            <div class="p-3 hover:bg-gray-100 cursor-pointer border-b text-sm"
                 onclick='adicionarAoCarrinho(${JSON.stringify(p)}, null)'>
                ${p.nome} — R$ ${Number(p.preco_venda).toFixed(2)} (estoque: ${p.estoque})
            </div>
        `;
    }).join('');

    resultadosDiv.classList.remove('hidden');
}

function adicionarAoCarrinho(produto, variante) {
    const chave = produto.id + '-' + (variante ? variante.id : '0');
    const existente = carrinho.find(i => i.chave === chave);

    if (existente) {
        existente.quantidade++;
    } else {
        carrinho.push({
            chave,
            produto_id: produto.id,
            produto_variante_id: variante ? variante.id : null,
            nome: produto.nome + (variante ? ` — ${variante.cor ?? ''} ${variante.tamanho ?? ''}` : ''),
            preco: parseFloat(produto.preco_venda),
            quantidade: 1,
        });
    }

    inputBusca.value = '';
    resultadosDiv.classList.add('hidden');
    renderizarCarrinho();
}

function alterarQuantidade(chave, delta) {
    const item = carrinho.find(i => i.chave === chave);
    item.quantidade += delta;
    if (item.quantidade <= 0) {
        carrinho = carrinho.filter(i => i.chave !== chave);
    }
    renderizarCarrinho();
}

function removerItem(chave) {
    carrinho = carrinho.filter(i => i.chave !== chave);
    renderizarCarrinho();
}

function renderizarCarrinho() {
    const tbody = document.getElementById('linhas-carrinho');
    const vazio = document.getElementById('carrinho-vazio');

    if (carrinho.length === 0) {
        tbody.innerHTML = '';
        vazio.classList.remove('hidden');
        document.getElementById('total-venda').innerText = 'R$ 0,00';
        return;
    }

    vazio.classList.add('hidden');

    tbody.innerHTML = carrinho.map(item => `
        <tr class="border-b">
            <td class="py-2">${item.nome}</td>
            <td class="py-2">
                <button onclick="alterarQuantidade('${item.chave}', -1)" class="px-2 bg-gray-200 rounded">-</button>
                <span class="mx-2">${item.quantidade}</span>
                <button onclick="alterarQuantidade('${item.chave}', 1)" class="px-2 bg-gray-200 rounded">+</button>
            </td>
            <td class="py-2">R$ ${item.preco.toFixed(2)}</td>
            <td class="py-2">R$ ${(item.preco * item.quantidade).toFixed(2)}</td>
            <td class="py-2">
                <button onclick="removerItem('${item.chave}')" class="text-red-500 text-sm hover:underline">Remover</button>
            </td>
        </tr>
    `).join('');

    const total = carrinho.reduce((soma, i) => soma + (i.preco * i.quantidade), 0);
    document.getElementById('total-venda').innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
}

async function finalizarVenda() {
    if (carrinho.length === 0) return;

    const btn = document.getElementById('btn-finalizar');
    const erroP = document.getElementById('erro-venda');
    btn.disabled = true;
    erroP.classList.add('hidden');

    const payload = {
        itens: carrinho.map(i => ({
            produto_id: i.produto_id,
            produto_variante_id: i.produto_variante_id,
            quantidade: i.quantidade,
        })),
        forma_pagamento: document.getElementById('forma-pagamento').value,
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
            return;
        }

        // Venda gravada com sucesso - redireciona pra emissao fiscal (proximo passo)
        window.location.href = `/pdv/venda/${data.venda_id}/comprovante`;
    } catch (e) {
        erroP.innerText = 'Erro de conexão. Tente novamente.';
        erroP.classList.remove('hidden');
        btn.disabled = false;
    }
}
</script>
@endsection