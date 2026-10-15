@php
    $cnpjNum = preg_replace('/\D/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpjNum) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjNum)
        : $cnpjNum;

    $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;

    $enderecoLinha1 = trim(($empresa->logradouro ?? '') . ', ' . ($empresa->numero ?? '') . ($empresa->complemento ? ' ' . $empresa->complemento : ''), ', ');
    $enderecoLinha2 = collect([
        $empresa->bairro,
        $empresa->municipio ? $empresa->municipio . '/' . $empresa->uf : null,
    ])->filter()->implode(' - ');

    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd   = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $totalItens = $pedido->itens->count();
    $cancelado  = $pedido->status === 'cancelado';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupom - Pedido {{ $pedido->numeroExibicao() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Única regra fora do Tailwind: @page não existe como utilitário. Faz a página sair com 80 mm de largura e altura do conteúdo. --}}
    <style>
        @media print { @page { size: 80mm auto; margin: 0; } }
    </style>
</head>
<body class="bg-gray-200 print:bg-white text-black">

<div class="print:hidden flex flex-col items-center gap-2 py-4">
    <div class="flex gap-3">
        <button type="button" onclick="window.print()"
                class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir cupom (80 mm)</button>
        <a href="{{ route('pedidos-venda.index') }}"
           class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</a>
    </div>
    <p class="text-xs text-gray-500 text-center max-w-xs">
        Na janela de impressão: escolha a POS-80, papel 80 mm, margens "Nenhuma", escala 100% e desmarque "Cabeçalhos e rodapés".
    </p>
</div>

{{-- Papel: 80 mm de largura, com folga lateral de 4 mm --}}
<main class="w-[80mm] mx-auto bg-white shadow print:shadow-none px-[4mm] py-[4mm] print:py-[2mm] font-mono text-[11px] leading-tight">

    {{-- Empresa --}}
    <header class="text-center">
        <p class="text-[13px] font-bold uppercase break-words">{{ $nomeEmpresa }}</p>
        @if ($empresa->nome_fantasia && $empresa->razao_social)
            <p class="break-words">{{ $empresa->razao_social }}</p>
        @endif
        <p>CNPJ: {{ $cnpjFmt ?: '—' }}@if ($empresa->ie) &nbsp;IE: {{ $empresa->ie }}@endif</p>
        @if ($enderecoLinha1) <p class="break-words">{{ $enderecoLinha1 }}</p> @endif
        @if ($enderecoLinha2) <p class="break-words">{{ $enderecoLinha2 }}</p> @endif
    </header>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Identificação do documento --}}
    <section class="text-center">
        <p class="text-[12px] font-bold">PEDIDO DE VENDA Nº {{ $pedido->numeroExibicao() }}</p>
        <p class="font-bold">*** NÃO É DOCUMENTO FISCAL ***</p>
        @if ($cancelado)
            <p class="font-bold">*** PEDIDO CANCELADO ***</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    <section>
        <p>Data do pedido: {{ $pedido->data_pedido->format('d/m/Y') }}</p>
        @if ($pedido->previsao_entrega)
            <p>Previsão de entrega: {{ $pedido->previsao_entrega->format('d/m/Y') }}</p>
        @endif
        <p class="break-words">Operador: {{ $pedido->operador?->name ?? '—' }}</p>
        <p class="break-words">Cliente: {{ $pedido->cliente?->nome ?? 'CONSUMIDOR' }}</p>
        @if ($pedido->cliente?->cpf_cnpj_formatado)
            <p>CPF/CNPJ: {{ $pedido->cliente->cpf_cnpj_formatado }}</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Itens (formato de cupom: código/descrição e, na linha de baixo, qtd x valor e total) --}}
    <section>
        <div class="flex justify-between font-bold">
            <span>ITEM CÓDIGO DESCRIÇÃO</span>
        </div>
        <div class="flex justify-between font-bold">
            <span>QTD UN x VL UNIT</span>
            <span>VL ITEM (R$)</span>
        </div>

        <hr class="border-t border-dashed border-black my-1">

        @foreach ($pedido->itens as $item)
            <div class="mb-1">
                <p class="break-words">
                    {{ str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT) }}
                    {{ $item->codigo_barras ?: '—' }}
                    {{ $item->descricao }}
                </p>
                <div class="flex justify-between">
                    <span>{{ $qtd($item->quantidade) }} {{ $item->unidade }} x {{ $moeda($item->valor_unitario) }}</span>
                    <span>{{ $moeda($item->total()) }}</span>
                </div>
            </div>
        @endforeach
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Totais --}}
    <section>
        <div class="flex justify-between">
            <span>QTD. TOTAL DE ITENS</span>
            <span>{{ $totalItens }}</span>
        </div>
        <div class="flex justify-between text-[14px] font-bold mt-0.5">
            <span>VALOR TOTAL R$</span>
            <span>{{ $moeda($pedido->valor_total) }}</span>
        </div>
        @if ($pedido->formaPagamento)
            <div class="flex justify-between mt-0.5">
                <span>FORMA DE PAGAMENTO</span>
                <span class="text-right break-words">{{ $pedido->formaPagamento->descricao }}</span>
            </div>
        @endif
    </section>

    @if ($pedido->descricao)
        <hr class="border-t border-dashed border-black my-1.5">
        <section>
            <p class="font-bold">OBSERVAÇÕES</p>
            <p class="whitespace-pre-line break-words">{{ $pedido->descricao }}</p>
        </section>
    @endif

    <hr class="border-t border-dashed border-black my-1.5">

    <footer class="text-center">
        <p>Emitido em {{ now()->format('d/m/Y H:i:s') }}</p>
        <p class="font-bold mt-1">Documento sem valor fiscal.</p>
        <p>Obrigado pela preferência!</p>
    </footer>

    {{-- Folga no fim para o corte da guilhotina não pegar a última linha --}}
    <div class="h-[12mm]"></div>
</main>

@if (request()->boolean('auto'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

</body>
</html>
