@extends('layouts.app')

@section('titulo', 'Nota Fiscal #' . $notaFiscal->id)

@section('conteudo')
<div class="flex flex-col gap-6 max-w-4xl">

    <div class="bg-white rounded-lg shadow p-6 flex justify-between items-start">
        <div>
            <h1 class="text-lg font-semibold">Nota Fiscal {{ $notaFiscal->numero ? '#' . $notaFiscal->numero : '(rascunho)' }}</h1>
            <p class="text-sm text-gray-500">Cliente: {{ $notaFiscal->cliente->nome }}</p>
            <p class="text-sm text-gray-500">Status:
                <span class="font-medium">{{ ucfirst($notaFiscal->status) }}</span>
            </p>
        </div>

        @if ($notaFiscal->status === 'rascunho')
            <form method="POST" action="{{ route('notasfiscais.emitir', $notaFiscal) }}">
                @csrf
                <button type="submit"
                        class="bg-green-700 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-green-800">
                    Emitir NF-e
                </button>
            </form>
        @elseif ($notaFiscal->status === 'emitida')
            <div class="flex gap-2">
                <a href="{{ route('notasfiscais.xml', $notaFiscal) }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">XML</a>
                <a href="{{ route('notasfiscais.cancelar-form', $notaFiscal) }}"
                   class="border border-red-300 text-red-700 rounded-lg px-4 py-2 text-sm hover:bg-red-50">Cancelar</a>
            </div>
        @endif
    </div>

    @error('emissao')
        <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3">{{ $message }}</div>
    @enderror

    @if ($notaFiscal->status === 'rascunho')
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold mb-3">Adicionar item</h2>
            <div id="busca-produto" class="flex gap-2 mb-4">
                <input type="text" id="termo-produto" placeholder="Código de barras ou nome"
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div id="resultado-busca" class="hidden border border-gray-200 rounded-lg mb-4"></div>

            <form id="form-item" method="POST" action="{{ route('notasfiscais.itens.adicionar', $notaFiscal) }}"
                  class="hidden grid grid-cols-4 gap-3 items-end">
                @csrf
                <input type="hidden" name="produto_id" id="item-produto-id">
                <div class="col-span-4 text-sm font-medium" id="item-produto-nome"></div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Quantidade</label>
                    <input type="number" step="0.001" name="quantidade" value="1" required
                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Valor unitário</label>
                    <input type="number" step="0.0001" name="valor_unitario" id="item-valor-unitario" required
                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">CFOP</label>
                    <input type="text" name="cfop" maxlength="4" placeholder="5102" required
                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <button type="submit"
                        class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700">
                    Adicionar
                </button>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-2">Produto</th>
                    <th class="text-right px-4 py-2">Qtd</th>
                    <th class="text-right px-4 py-2">Unit.</th>
                    <th class="text-right px-4 py-2">Total</th>
                    @if ($notaFiscal->status === 'rascunho') <th></th> @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($notaFiscal->itens as $item)
                    <tr>
                        <td class="px-4 py-2">{{ $item->produto->nome }}</td>
                        <td class="px-4 py-2 text-right">{{ $item->quantidade }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                        @if ($notaFiscal->status === 'rascunho')
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('notasfiscais.itens.remover', [$notaFiscal, $item]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 text-xs hover:underline">remover</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="3" class="px-4 py-2 text-right">Total</td>
                    <td class="px-4 py-2 text-right">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('termo-produto')?.addEventListener('input', async function (e) {
    const termo = e.target.value.trim();
    const box = document.getElementById('resultado-busca');
    if (termo.length < 2) { box.classList.add('hidden'); return; }

    const resp = await fetch(`{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();

    box.innerHTML = produtos.map(p => `
        <div class="px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm border-b border-gray-100"
             onclick='selecionarProduto(${JSON.stringify(p)})'>
            ${p.nome} — R$ ${Number(p.preco_venda).toFixed(2)}
        </div>
    `).join('');
    box.classList.remove('hidden');
});

function selecionarProduto(produto) {
    document.getElementById('item-produto-id').value = produto.id;
    document.getElementById('item-produto-nome').innerText = produto.nome;
    document.getElementById('item-valor-unitario').value = produto.preco_venda;
    document.getElementById('resultado-busca').classList.add('hidden');
    document.getElementById('termo-produto').value = '';
    document.getElementById('form-item').classList.remove('hidden');
}
</script>
@endsection