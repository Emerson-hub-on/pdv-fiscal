@php
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd   = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    $titulo = $c['numero'] . '/' . $c['serie'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NFC-e {{ $titulo }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Única regra fora do Tailwind: @page não existe como utilitário. Página de 80 mm de largura e altura do conteúdo. --}}
    <style>
        @media print { @page { size: 80mm auto; margin: 0; } }
    </style>
</head>
<body class="bg-gray-200 print:bg-white text-black">

<div class="print:hidden flex flex-col items-center gap-2 py-4">
    <div class="flex gap-3">
        <button type="button" onclick="window.print()"
                class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir cupom fiscal</button>
        <button type="button" onclick="window.close()"
                class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Fechar</button>
    </div>
    @if ($c['contingencia'])
        <p class="text-xs text-amber-700 text-center max-w-xs">NFC-e em contingência: o cupom sai em 2 vias (consumidor e estabelecimento).</p>
    @endif
</div>

@foreach ($c['vias'] as $via)
<main class="w-[80mm] mx-auto bg-white shadow print:shadow-none px-[4mm] py-[4mm] print:py-[2mm] mb-4 print:mb-0 font-mono text-[11px] leading-tight {{ $loop->last ? '' : 'print:break-after-page' }}">

    {{-- Emitente --}}
    <header class="text-center">
        <p class="text-[13px] font-bold uppercase break-words">{{ $c['emitente']['fantasia'] ?: $c['emitente']['nome'] }}</p>
        @if ($c['emitente']['fantasia'])
            <p class="break-words">{{ $c['emitente']['nome'] }}</p>
        @endif
        <p>CNPJ: {{ $c['emitente']['cnpj'] }}@if ($c['emitente']['ie']) &nbsp;IE: {{ $c['emitente']['ie'] }}@endif</p>
        <p class="break-words">{{ $c['emitente']['endereco'] }}</p>
        <p class="break-words">{{ $c['emitente']['bairro_cidade'] }}</p>
    </header>

    <hr class="border-t border-dashed border-black my-1.5">

    <section class="text-center">
        <p class="font-bold">DANFE NFC-e - Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</p>
        <p>Não permite aproveitamento de crédito de ICMS</p>
        @if ($c['homologacao'])
            <p class="font-bold mt-1">EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO - SEM VALOR FISCAL</p>
        @endif
        @if ($c['cancelada'])
            <p class="font-bold mt-1">*** NFC-e CANCELADA ***</p>
        @endif
        @if ($c['contingencia'])
            <p class="font-bold mt-1">EMITIDA EM CONTINGÊNCIA</p>
            <p class="font-bold">Pendente de autorização</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Itens --}}
    <section>
        <p class="font-bold">ITEM CÓDIGO DESCRIÇÃO</p>
        <div class="flex justify-between font-bold">
            <span>QTD UN x VL UNIT</span>
            <span>VL TOTAL (R$)</span>
        </div>

        <hr class="border-t border-dashed border-black my-1">

        @foreach ($c['itens'] as $item)
            <div class="mb-1">
                <p class="break-words">
                    {{ str_pad((string) $item['seq'], 3, '0', STR_PAD_LEFT) }}
                    {{ $item['codigo'] }}
                    {{ $item['descricao'] }}
                </p>
                <div class="flex justify-between">
                    <span>{{ $qtd($item['quantidade']) }} {{ $item['unidade'] }} x {{ $moeda($item['valor_unitario']) }}</span>
                    <span>{{ $moeda($item['valor_total']) }}</span>
                </div>
                @if ($item['desconto'] > 0)
                    <div class="flex justify-between">
                        <span>  Desconto</span>
                        <span>-{{ $moeda($item['desconto']) }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Totais e pagamento --}}
    <section>
        <div class="flex justify-between">
            <span>QTD. TOTAL DE ITENS</span>
            <span>{{ $c['qtd_itens'] }}</span>
        </div>
        @if ($c['desconto'] > 0)
            <div class="flex justify-between">
                <span>VALOR TOTAL R$</span>
                <span>{{ $moeda($c['total_produtos']) }}</span>
            </div>
            <div class="flex justify-between">
                <span>DESCONTO R$</span>
                <span>-{{ $moeda($c['desconto']) }}</span>
            </div>
        @endif
        <div class="flex justify-between text-[14px] font-bold mt-0.5">
            <span>VALOR A PAGAR R$</span>
            <span>{{ $moeda($c['total']) }}</span>
        </div>

        <p class="font-bold mt-1.5">FORMA DE PAGAMENTO &nbsp;&nbsp;&nbsp;&nbsp; VALOR PAGO R$</p>
        @foreach ($c['pagamentos'] as $pg)
            <div class="flex justify-between">
                <span class="break-words">{{ $pg['forma'] }}</span>
                <span>{{ $moeda($pg['valor']) }}</span>
            </div>
        @endforeach
        @if ($c['troco'] > 0)
            <div class="flex justify-between">
                <span>Troco</span>
                <span>{{ $moeda($c['troco']) }}</span>
            </div>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Identificação da nota --}}
    <section class="text-center">
        <p class="font-bold">NFC-e nº {{ $c['numero'] }} Série {{ $c['serie'] }}</p>
        <p>Emissão: {{ $c['emissao']->format('d/m/Y H:i:s') }}</p>
        <p class="font-bold">{{ $via }}</p>

        @if ($c['url_consulta'])
            <p class="mt-1">Consulte pela Chave de Acesso em</p>
            <p class="break-all">{{ $c['url_consulta'] }}</p>
        @endif

        <p class="mt-1 font-bold">CHAVE DE ACESSO</p>
        <p class="break-words">{{ $c['chave_formatada'] }}</p>
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Consumidor --}}
    <section class="text-center">
        @if ($c['consumidor']['documento'])
            <p class="font-bold">CONSUMIDOR</p>
            <p>CPF/CNPJ: {{ $c['consumidor']['documento'] }}</p>
            @if ($c['consumidor']['nome'])
                <p class="break-words">{{ $c['consumidor']['nome'] }}</p>
            @endif
        @else
            <p class="font-bold">CONSUMIDOR NÃO IDENTIFICADO</p>
        @endif
    </section>

    {{-- Protocolo (autorizada) --}}
    @if ($c['protocolo'])
        <section class="text-center mt-1">
            <p>Protocolo de autorização: {{ $c['protocolo'] }}</p>
            @if ($c['data_autorizacao'])
                <p>Data de autorização: {{ $c['data_autorizacao']->format('d/m/Y H:i:s') }}</p>
            @endif
        </section>
    @endif

    {{-- QR Code --}}
    @if ($c['qrcode'])
        <div class="qr-nfce w-[38mm] h-[38mm] mx-auto my-2" data-qr="{{ $c['qrcode'] }}"></div>
    @else
        <p class="text-center font-bold my-2">QR Code indisponível neste XML</p>
    @endif

    {{-- Tributos (Lei 12.741/2012) --}}
    @if ($c['tributos'] !== null)
        <p class="text-center">Tributos Totais Incidentes (Lei Federal 12.741/2012): R$ {{ $moeda($c['tributos']) }}</p>
    @endif

    @if ($c['info_complementar'])
        <p class="text-center mt-1 break-words whitespace-pre-line">{{ $c['info_complementar'] }}</p>
    @endif

    {{-- Folga no fim para o corte da guilhotina --}}
    <div class="h-[12mm]"></div>
</main>
@endforeach

{{-- Biblioteca de QR Code local (funciona sem internet): copie para public/js/qrcode.js --}}
<script src="{{ asset('js/qrcode.js') }}"></script>
<script>
document.querySelectorAll('.qr-nfce').forEach((el) => {
    const texto = el.dataset.qr;
    if (!texto || typeof qrcode === 'undefined') return;

    const qr = qrcode(0, 'M'); // tipo automático, correção de erro M (padrão da NFC-e)
    qr.addData(texto);
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });

    const svg = el.querySelector('svg');
    if (svg) svg.setAttribute('class', 'w-full h-full');
});

@if (request()->boolean('auto'))
// Aberto dentro do iframe do PDV: imprime e avisa a tela de origem quando terminar (ou cancelar)
window.addEventListener('afterprint', () => {
    if (window.parent !== window) {
        window.parent.postMessage({ tipo: 'cupom-fiscal-impresso' }, window.location.origin);
    }
});
window.addEventListener('load', () => setTimeout(() => window.print(), 200));
@endif
</script>

</body>
</html>