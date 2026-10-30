@php
    $doc = fn ($v) => preg_replace('/\D/', '', (string) $v);
    $cnpj = $doc($empresa->cnpj ?? '');
    $cnpjFormatado = strlen($cnpj) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj)
        : $cnpj;
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $numeroCaixa = $caixa->id_central
        ? str_pad($caixa->id_central, 6, '0', STR_PAD_LEFT)
        : 'L' . str_pad($caixa->id, 5, '0', STR_PAD_LEFT);
    $endereco = $empresa
        ? trim($empresa->logradouro . ', ' . $empresa->numero . ($empresa->complemento ? ' - ' . $empresa->complemento : ''))
        : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de abertura - Caixa {{ $numeroCaixa }}</title>
    @vite(['resources/css/app.css'])
    {{-- @page não tem equivalente em classe do Tailwind: bobina de 80 mm sem margem.
         A altura é ajustada pelo script no fim da página para o relatório sair numa folha só (um único corte). --}}
    <style id="pagina-bobina">@page { size: 80mm 297mm; margin: 0; }</style>
</head>
<body class="bg-white text-black">
<div id="bobina" class="w-[72mm] mx-auto px-[2mm] py-[3mm] font-mono text-[13px] leading-snug">

    {{-- Empresa --}}
    <div class="text-center">
        <p class="font-bold uppercase text-[14px]">{{ $empresa->razao_social ?? 'EMPRESA' }}</p>
        @if (!empty($empresa?->nome_fantasia) && $empresa->nome_fantasia !== $empresa->razao_social)
            <p class="uppercase">{{ $empresa->nome_fantasia }}</p>
        @endif
        @if ($cnpjFormatado)
            <p>
                CNPJ {{ $cnpjFormatado }}
                @if (!empty($empresa->ie))
                    &nbsp;IE {{ $empresa->ie }}
                @endif
            </p>
        @endif
        @if ($endereco)
            <p>{{ $endereco }}</p>
            <p>{{ $empresa->bairro }} - {{ $empresa->municipio }}/{{ $empresa->uf }}</p>
        @endif
    </div>

    <div class="border-t border-dashed border-black my-2"></div>

    <p class="text-center font-bold text-[15px]">RELATÓRIO DE ABERTURA DE CAIXA</p>
    @if ($reimpressao)
        <p class="text-center font-bold mt-1">*** REIMPRESSÃO ***</p>
    @endif
    @if ((int) ($empresa->ambiente ?? 1) === 2)
        <p class="text-center mt-1">AMBIENTE DE HOMOLOGAÇÃO</p>
    @endif

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Identificação --}}
    <div class="space-y-0.5">
        <div class="flex justify-between gap-2"><span>Caixa nº</span><span class="font-bold">{{ $numeroCaixa }}</span></div>
        <div class="flex justify-between gap-2"><span>PDV</span><span class="text-right">{{ $caixa->pdv->nome ?? '-' }}</span></div>
        <div class="flex justify-between gap-2"><span>Série NFC-e</span><span>{{ $caixa->pdv->serie_nfce ?? '-' }}</span></div>
        <div class="flex justify-between gap-2"><span>Computador</span><span class="text-right">{{ $maquina }}</span></div>
        <div class="flex justify-between gap-2">
            <span>Operador</span>
            <span class="text-right">{{ !empty($operador?->codigo) ? str_pad($operador->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $operador->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between gap-2"><span>Data</span><span>{{ $caixa->data_abertura->format('d/m/Y') }}</span></div>
        <div class="flex justify-between gap-2"><span>Hora</span><span>{{ $caixa->data_abertura->format('H:i:s') }}</span></div>
    </div>

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Valor --}}
    <div class="flex justify-between gap-2 font-bold text-[15px]">
        <span>FUNDO DE TROCO</span>
        <span>{{ $moeda($caixa->valor_abertura) }}</span>
    </div>
    <p class="text-[12px]">Valor em dinheiro na gaveta na abertura</p>

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Situação --}}
    <p>
        Situação:
        @if ($caixa->sync_pendente)
            aguardando envio ao servidor
        @else
            enviado ao servidor
        @endif
    </p>
    <p class="text-[12px] break-all">ID {{ $caixa->uuid }}</p>

    {{-- Conferência --}}
    <p class="mt-3">Valor conferido: R$ ______________</p>
    <p class="mt-2">Observação: _____________________</p>
    <p class="mt-1">________________________________</p>

    {{-- Assinaturas e rodapé nunca se separam (nem quebram de página no meio) --}}
    <div class="break-inside-avoid">
        <div class="mt-10 border-t border-black"></div>
        <p class="text-center">{{ $operador->name ?? 'Operador' }}</p>
        <p class="text-center text-[12px]">Assinatura do operador</p>

        <div class="mt-10 border-t border-black"></div>
        <p class="text-center text-[12px]">Assinatura do responsável</p>

        <div class="border-t border-dashed border-black my-2"></div>

        <p class="text-center text-[12px]">Impresso em {{ now()->format('d/m/Y H:i:s') }}</p>
        <p class="text-center text-[12px]">Cyberly Sistema</p>

        {{-- Espaço para o corte da bobina --}}
        <div class="h-8"></div>
    </div>
</div>

<script>
    // Altura da folha = altura do relatório: o driver da POS imprime tudo e corta uma vez só, no fim
    function ajustarBobina() {
        const alturaMm = Math.ceil(document.getElementById('bobina').scrollHeight * 25.4 / 96) + 5;
        document.getElementById('pagina-bobina').textContent =
            '@page { size: 80mm ' + alturaMm + 'mm; margin: 0; }';
    }

    window.addEventListener('beforeprint', ajustarBobina);
    window.addEventListener('load', () => {
        ajustarBobina();
        @if ($auto)
            window.focus();
            window.print();
        @endif
    });
</script>
</body>
</html>