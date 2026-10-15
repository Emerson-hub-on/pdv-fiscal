@php
    $cnpjNum = preg_replace('/\D/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpjNum) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjNum)
        : $cnpjNum;

    $enderecoEmpresa = collect([
        trim(($empresa->logradouro ?? '') . ', ' . ($empresa->numero ?? ''), ', '),
        $empresa->bairro,
        $empresa->municipio ? $empresa->municipio . '/' . $empresa->uf : null,
    ])->filter()->implode(' — ');

    $formaPagamento = $pedido->formaPagamento;
    $totalPedido = 'R$ ' . number_format($pedido->valor_total, 2, ',', '.');

    $statusTexto = ['finalizado' => 'Finalizado', 'faturado' => 'Faturado', 'cancelado' => 'Cancelado'][$pedido->status] ?? $pedido->status;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido de Venda {{ $pedido->numeroExibicao() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 print:bg-white text-gray-900">

<div class="print:hidden flex justify-center gap-3 py-4">
    <button type="button" onclick="window.print()"
            class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir / salvar PDF</button>
    <a href="{{ route('pedidos-venda.index') }}"
       class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</a>
</div>

<main class="max-w-4xl mx-auto bg-white shadow print:shadow-none p-8 mb-8 print:mb-0">

    <header class="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Pedido de Venda</h1>
            <p class="text-sm text-gray-500">Nº {{ $pedido->numeroExibicao() }} — {{ $statusTexto }}</p>
        </div>
        <dl class="text-sm text-right">
            <div><dt class="inline text-gray-500">Data do pedido:</dt> <dd class="inline font-medium">{{ $pedido->data_pedido->format('d/m/Y') }}</dd></div>
            @if ($pedido->previsao_entrega)
                <div><dt class="inline text-gray-500">Previsão de entrega:</dt> <dd class="inline font-medium">{{ $pedido->previsao_entrega->format('d/m/Y') }}</dd></div>
            @endif
        </dl>
    </header>

    <section class="grid grid-cols-2 gap-6 mb-6">
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Empresa</h2>
            <p class="text-sm font-medium">{{ $empresa->razao_social ?: '—' }}</p>
            <p class="text-sm text-gray-600">
                CNPJ {{ $cnpjFmt ?: '—' }}@if ($empresa->ie) · IE {{ $empresa->ie }}@endif
            </p>
            @if ($enderecoEmpresa)
                <p class="text-sm text-gray-600">{{ $enderecoEmpresa }}</p>
            @endif
        </div>

        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Cliente</h2>
            @if ($pedido->cliente)
                <p class="text-sm font-medium">{{ $pedido->cliente->nome }}</p>
                <p class="text-sm text-gray-600">{{ $pedido->cliente->cpf_cnpj_formatado }}</p>
            @else
                <p class="text-sm text-gray-500">Não informado</p>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-2 gap-6 mb-6">
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Operador</h2>
            <p class="text-sm font-medium">
                {{ $pedido->operador?->name ?? '—' }}
                @if ($pedido->operador?->codigo)
                    <span class="font-normal text-gray-500">— Cód. {{ $pedido->operador->codigo }}</span>
                @endif
            </p>
        </div>
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Forma de pagamento</h2>
            <p class="text-sm font-medium">{{ $formaPagamento?->descricao ?? 'Não informada' }}</p>
        </div>
    </section>

    <section class="mb-6">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-2">Itens</h2>
        <table class="w-full text-sm border border-gray-300">
            <thead class="bg-gray-100 text-xs uppercase">
                <tr>
                    <th class="text-left px-2 py-2 w-10">#</th>
                    <th class="text-left px-2 py-2 w-36">Cód. barras</th>
                    <th class="text-left px-2 py-2">Descrição</th>
                    <th class="text-left px-2 py-2 w-14">Un.</th>
                    <th class="text-right px-2 py-2 w-20">Qtd.</th>
                    <th class="text-right px-2 py-2 w-24">Vlr. unit.</th>
                    <th class="text-right px-2 py-2 w-28">Total</th>
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
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ number_format($item->total(), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50">
                    <td colspan="6" class="px-2 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total do pedido</td>
                    <td class="px-2 py-2 text-right font-semibold whitespace-nowrap">{{ $totalPedido }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    @if ($pedido->descricao)
        <section>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Descrição</h2>
            <p class="text-sm whitespace-pre-line">{{ $pedido->descricao }}</p>
        </section>
    @endif
</main>

</body>
</html>
