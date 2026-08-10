@extends('layouts.app')

@section('titulo', 'Pagamento')

@section('conteudo')
    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 bg-white rounded shadow p-4">
            <a href="{{ route('vendas.pdv') }}" class="text-sm text-gray-500 hover:underline mb-4 inline-block">← Voltar aos itens</a>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 border-b">
                        <th class="py-2">Produto</th>
                        <th class="py-2">Qtd</th>
                        <th class="py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itens as $item)
                        <tr class="border-b">
                            <td class="py-2">{{ $item['nome'] }}</td>
                            <td class="py-2">{{ $item['quantidade'] }}</td>
                            <td class="py-2 text-right">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded shadow p-4 h-fit">
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
                <select id="nova-forma-pagamento" class="flex-1 border rounded px-2 py-1 text-sm">
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="credito">Cartão de Crédito</option>
                    <option value="debito">Cartão de Débito</option>
                </select>
                <input type="number" step="0.01" id="novo-valor-pagamento" placeholder="Valor" class="w-24 border rounded px-2 py-1 text-sm">
                <button type="button" onclick="adicionarPagamento()" class="bg-gray-200 hover:bg-gray-300 px-3 rounded text-sm">+</button>
            </div>

            <p class="text-sm mb-4">
                Restante a pagar: <strong id="restante-pagamento">R$ 0,00</strong>
            </p>

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
const itensDaVenda = @json($itens);
const totalDaVenda = {{ $total }};
let pagamentos = [];

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
    const forma = document.getElementById('nova-forma-pagamento').value;
    const valor = parseFloat(document.getElementById('novo-valor-pagamento').value);

    if (!valor || valor <= 0) {
        alert('Informe um valor válido.');
        return;
    }

    pagamentos.push({ forma_pagamento: forma, valor });
    document.getElementById('novo-valor-pagamento').value = '';
    renderizarPagamentos();
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
</script>
@endsection