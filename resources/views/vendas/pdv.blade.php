@extends('layouts.app')

@section('titulo', 'PDV - Venda')

@section('conteudo')
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-2xl font-bold">Nova Venda</h1>
        <p class="text-sm text-gray-500">
            {{ $caixa->pdv->nome }} — Série {{ $caixa->pdv->serie_nfce }} — Próxima NFC-e: nº {{ $caixa->pdv->numero_atual_nfce + 1 }}
        </p>
    </div>
    <div class="flex gap-4 items-center">
        <button onclick="abrirModalContingencias()" class="text-orange-600 hover:underline text-sm">
            Contingências (F1)
        </button>
        <button onclick="abrirModalInutilizacao()" class="text-red-600 hover:underline text-sm">
            Inutilizar NFC-e (F2)
        </button>
        <a href="{{ route('caixa.fechar-form') }}" class="text-red-600 hover:underline text-sm">Fechar Caixa</a>
    </div>
</div>

<!-- Modal de contingencias -->
<div id="modal-contingencias" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-2xl max-h-[80vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Vendas em Contingência</h2>
            <button onclick="fecharModalContingencias()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

    <div class="flex justify-between items-center mb-3">
        <label class="text-sm flex items-center gap-2">
            <input type="checkbox" id="selecionar-todas" onchange="toggleTodas(this.checked)">
            Selecionar todas
        </label>
        <div class="flex items-center gap-3">
            <span id="progresso-emissao" class="text-sm text-gray-500 hidden"></span>
            <button id="btn-emitir-selecionadas" onclick="emitirSelecionadas()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold">
                Emitir selecionadas
            </button>
        </div>
    </div>

        <div id="lista-contingencias" class="space-y-2">
            <p class="text-gray-400 text-sm">Carregando...</p>
        </div>
    </div>
</div>

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

        <button onclick="confirmarInutilizacao()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
            Inutilizar
        </button>
    </div>
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
        window.location.href = `/pdv/venda/${data.venda_uuid}/comprovante`;
    } catch (e) {
        erroP.innerText = 'Erro de conexão. Tente novamente.';
        erroP.classList.remove('hidden');
        btn.disabled = false;
    }
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'F1') {
        e.preventDefault();
        abrirModalContingencias();
    }
});

function abrirModalContingencias() {
    document.getElementById('modal-contingencias').classList.remove('hidden');
    document.getElementById('modal-contingencias').classList.add('flex');
    carregarContingencias();
}

function fecharModalContingencias() {
    document.getElementById('modal-contingencias').classList.add('hidden');
    document.getElementById('modal-contingencias').classList.remove('flex');
}

async function carregarContingencias() {
    const resp = await fetch('{{ route("contingencias.listar") }}');
    const vendas = await resp.json();

    const container = document.getElementById('lista-contingencias');

    if (vendas.length === 0) {
        container.innerHTML = '<p class="text-gray-400 text-sm">Nenhuma venda em contingência.</p>';
        return;
    }

    container.innerHTML = vendas.map(v => `
        <div class="border rounded">
            <div class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50" onclick="toggleExpandir(${v.id})">
                <div class="flex items-center gap-3">
                    <input type="checkbox" class="check-contingencia" value="${v.id}" onclick="event.stopPropagation()">
                    <div>
                        <p class="text-sm font-medium">
                            NFC-e nº ${v.numero_nfce ?? '-'} (série ${v.serie_nfce ?? '-'}) — R$ ${Number(v.total).toFixed(2)}
                        </p>
                        <p class="text-xs text-gray-400">${v.criada_em}</p>
                        ${v.chave_nfe ? `<p class="text-xs text-gray-400 break-all">Chave: ${v.chave_nfe}</p>` : ''}
                    </div>
                </div>
                <span class="text-gray-400 text-xs">▼</span>
            </div>
            <div id="detalhe-${v.id}" class="hidden border-t bg-gray-50 p-3 text-sm">
                <p class="text-red-600 mb-2"><strong>Motivo:</strong> ${v.motivo ?? 'Não informado'}</p>
                <p class="text-gray-600"><strong>Itens:</strong> ${v.itens.join(', ')}</p>
            </div>
        </div>
    `).join('');
}

function toggleExpandir(id) {
    document.getElementById(`detalhe-${id}`).classList.toggle('hidden');
}

function toggleTodas(marcado) {
    document.querySelectorAll('.check-contingencia').forEach(cb => cb.checked = marcado);
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

    let sucesso = 0;
    let falha = 0;

    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        progresso.innerText = `${i + 1} / ${ids.length} processando...`;

        try {
            const resp = await fetch(`/contingencias/${id}/reenviar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
            });

            const resultado = await resp.json();

            if (resultado.sucesso) {
                sucesso++;
                marcarLinhaEmitida(id);
            } else {
                falha++;
                atualizarMotivoLinha(id, resultado.erro);
            }
        } catch (e) {
            falha++;
            atualizarMotivoLinha(id, 'Erro de conexão ao tentar emitir.');
        }

        progresso.innerText = `${i + 1} / ${ids.length} — ${sucesso} emitida(s), ${falha} pendente(s)`;
    }

    btn.disabled = false;

    setTimeout(() => {
        progresso.classList.add('hidden');
        carregarContingencias(); // atualiza a lista, removendo as que foram emitidas
    }, 1500);
}

function marcarLinhaEmitida(id) {
    const linha = document.querySelector(`.check-contingencia[value="${id}"]`)?.closest('.border');
    if (linha) {
        linha.classList.add('opacity-40');
        linha.querySelector('.check-contingencia').disabled = true;
        const status = document.createElement('span');
        status.className = 'text-green-600 text-xs ml-2';
        status.innerText = '✓ Emitida';
        linha.querySelector('.flex.items-center.gap-3')?.appendChild(status);
    }
}

function atualizarMotivoLinha(id, motivo) {
    const detalhe = document.getElementById(`detalhe-${id}`);
    if (detalhe) {
        detalhe.querySelector('p').innerHTML = `<strong>Motivo:</strong> ${motivo}`;
        detalhe.classList.remove('hidden'); // expande automaticamente pra mostrar o novo motivo
    }
}



document.addEventListener('keydown', (e) => {
    if (e.key === 'F1') {
        e.preventDefault();
        abrirModalContingencias();
    }
    if (e.key === 'F2') {
        e.preventDefault();
        abrirModalInutilizacao();
    }
});

function abrirModalInutilizacao() {
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

    erroP.classList.add('hidden');

    if (!numeroInicial || !numeroFinal || justificativa.length < 15) {
        erroP.innerText = 'Preencha os números e uma justificativa com pelo menos 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm(`Confirma a inutilização da numeração ${numeroInicial} a ${numeroFinal}? Esta ação não pode ser desfeita.`)) {
        return;
    }

    const resp = await fetch('{{ route("inutilizacao.executar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ numero_inicial: numeroInicial, numero_final: numeroFinal, justificativa }),
    });

    const resultado = await resp.json();

    if (resultado.sucesso) {
        alert('Numeração inutilizada com sucesso. Protocolo: ' + resultado.protocolo);
        fecharModalInutilizacao();
    } else {
        erroP.innerText = resultado.erro;
        erroP.classList.remove('hidden');
    }
}

</script>
@endsection