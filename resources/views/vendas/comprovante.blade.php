@extends('layouts.app')

@section('titulo', 'Comprovante de Venda')

@section('conteudo')
    <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-xl font-bold mb-4">Comprovante de Venda</h1>

        @if ($status === 'aguardando_sincronizacao')
            <div class="bg-yellow-100 text-yellow-800 border border-yellow-300 rounded px-4 py-3 mb-4 text-sm">
                Esta venda ainda está aguardando conexão com o servidor pra poder ser emitida. Ela já foi registrada localmente e não será perdida.
            </div>
        @endif

        @if (session('sucesso'))
            <div class="bg-green-100 text-green-800 border border-green-300 rounded px-4 py-3 mb-4 text-sm">
                {{ session('sucesso') }}
            </div>
        @endif

        @if (session('erro_fiscal'))
            <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-3 mb-4 text-sm">
                <strong>Erro na emissão:</strong> {{ session('erro_fiscal') }}
            </div>
        @endif

        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2">Produto</th>
                    <th class="py-2">Qtd</th>
                    <th class="py-2">Preço</th>
                    <th class="py-2 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($itens as $item)
                    <tr class="border-b">
                        <td class="py-2">{{ $item->produto->nome }}</td>
                        <td class="py-2">{{ $item->quantidade }}</td>
                        <td class="py-2">R$ {{ number_format($item->subtotal / $item->quantidade, 2, ',', '.') }}</td>
                        <td class="py-2 text-right">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($pagamentos->count() > 0)
            @php
                $nomesPagamento = [
                    'dinheiro' => 'Dinheiro',
                    'pix' => 'PIX',
                    'credito' => 'Cartão de Crédito',
                    'debito' => 'Cartão de Débito',
                ];
            @endphp

            <div class="bg-gray-50 border rounded p-3 mb-6 text-sm">
                <p class="font-medium text-gray-700 mb-2">Formas de pagamento</p>
                @foreach ($pagamentos as $pagamento)
                    <div class="flex justify-between text-gray-600">
                        <span>{{ $nomesPagamento[$pagamento->forma_pagamento] ?? $pagamento->forma_pagamento }}</span>
                        <span>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</span>
                    </div>
                @endforeach

                @if ($troco > 0.009)
                    <div class="flex justify-between text-blue-600 font-semibold border-t mt-2 pt-2">
                        <span>Troco</span>
                        <span>R$ {{ number_format($troco, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @endif


        <p class="text-right font-bold text-lg mb-6">Total: R$ {{ number_format($total, 2, ',', '.') }}</p>

        @if ($status === 'emitida')
            <p class="text-green-700 text-sm mb-2">✓ Cupom fiscal emitido</p>
            <p class="text-xs text-gray-500 break-all">Chave: {{ $chave_nfe }}</p>
        @elseif ($status !== 'aguardando_sincronizacao')
            <button id="btn-emitir" onclick="emitirCupom()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                Emitir Cupom Fiscal
            </button>
            <p id="msg-emissao" class="text-sm mt-3 text-center hidden"></p>
        @endif

        <a href="{{ route('vendas.pdv') }}" class="block text-center text-sm text-gray-500 mt-4 hover:underline">
            ← Nova venda
        </a>
    </div>
@endsection

@section('scripts')
<script>
async function emitirCupom() {
    const btn = document.getElementById('btn-emitir');
    const msg = document.getElementById('msg-emissao');

    btn.disabled = true;
    btn.innerText = 'Processando...';
    msg.classList.add('hidden');

    try {
        const resp = await fetch('{{ route("vendas.emitir", request()->route("uuid")) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
        });

        const resultado = await resp.json();

        if (resultado.sucesso) {
            msg.className = 'text-sm mt-3 text-center text-green-700';
            msg.innerText = resultado.ja_emitida
                ? 'Esta venda já havia sido emitida.'
                : '✓ Cupom fiscal emitido com sucesso!';
        } else if (resultado.contingencia) {
            msg.className = 'text-sm mt-3 text-center text-orange-600';
            msg.innerText = 'Sem conexão no momento. Venda será emitida em contingência assim que possível.';
        } else {
            msg.className = 'text-sm mt-3 text-center text-red-600';
            msg.innerText = resultado.erro;
        }

        msg.classList.remove('hidden');
    } catch (e) {
        msg.className = 'text-sm mt-3 text-center text-red-600';
        msg.innerText = 'Erro de conexão ao tentar emitir.';
        msg.classList.remove('hidden');
    }

    // Volta pra tela de nova venda automaticamente, dando tempo do operador ler a mensagem
    setTimeout(() => {
        window.location.href = '{{ route("vendas.pdv") }}';
    }, 1500);
}
</script>
@endsection