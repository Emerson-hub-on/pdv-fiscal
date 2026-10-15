<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido de Compra {{ $pedido->numeroExibicao() }}</title>
    {{-- Cole aqui a MESMA linha que o layouts.app usa para carregar o Tailwind (@vite(...) ou o link do CSS compilado) --}}
</head>
<body class="bg-gray-100 print:bg-white text-gray-900">

<div class="print:hidden flex justify-center gap-3 py-4">
    <button type="button" onclick="window.print()"
            class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir / salvar PDF</button>
    <a href="{{ route('pedidos-compra.index') }}"
       class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</a>
</div>

<main class="max-w-4xl mx-auto bg-white shadow print:shadow-none p-8 mb-8 print:mb-0">

    <header class="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Pedido de Compra</h1>
            <p class="text-sm text-gray-500">Nº {{ $pedido->numeroExibicao() }}</p>
        </div>
        <dl class="text-sm text-right">
            <div><dt class="inline text-gray-500">Data do pedido:</dt> <dd class="inline font-medium">{{ $pedido->data_pedido->format('d/m/Y') }}</dd></div>
            @if ($pedido->previsao_entrega)
                <div><dt class="inline text-gray-500">Previsão de entrega:</dt> <dd class="inline font-medium">{{ $pedido->previsao_entrega->format('d/m/Y') }}</dd></div>
            @endif
        </dl>
    </header>

    <section class="mb-6">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-2">Fornecedor</h2>
        <p class="text-sm font-medium">
            {{ $pedido->fornecedor?->nome_exibicao ?? '—' }}
            @if ($pedido->fornecedor?->documento_formatado)
                <span class="font-normal text-gray-500">— {{ $pedido->fornecedor->documento_formatado }}</span>
            @endif
        </p>
    </section>

    <section class="mb-6 border border-gray-300 rounded-lg p-4">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-3">Dados para emissão da nota fiscal (destinatário)</h2>
        @include('financeiro.pedidos-compra._dados-empresa', ['empresa' => $empresa])
        <p class="text-xs text-gray-500 mt-3">Informe o nº do pedido ({{ $pedido->numeroExibicao() }}) nos dados adicionais da NF-e.</p>
    </section>

    <section class="mb-6">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-2">Itens</h2>
        <table class="w-full text-sm border border-gray-300">
            <thead class="bg-gray-100 text-xs uppercase">
                <tr>
                    <th class="text-left px-2 py-2 w-10">#</th>
                    <th class="text-left px-2 py-2 w-40">Cód. barras</th>
                    <th class="text-left px-2 py-2">Descrição</th>
                    <th class="text-left px-2 py-2 w-16">Un.</th>
                    <th class="text-right px-2 py-2 w-28">Quantidade</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($pedido->itens as $item)
                    <tr>
                        <td class="px-2 py-1.5 text-gray-500">{{ $loop->iteration }}</td>
                        <td class="px-2 py-1.5 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                        <td class="px-2 py-1.5">{{ $item->descricao }}</td>
                        <td class="px-2 py-1.5">{{ $item->unidade }}</td>
                        <td class="px-2 py-1.5 text-right">{{ rtrim(rtrim(number_format($item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if ($pedido->observacao)
        <section>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Observação</h2>
            <p class="text-sm whitespace-pre-line">{{ $pedido->observacao }}</p>
        </section>
    @endif
</main>

</body>
</html>
