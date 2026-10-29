@php
    $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? ''));
    $cnpjFormatado = strlen($cnpj) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj)
        : $cnpj;
    $n = fn ($v) => number_format((float) $v, 2, ',', '.');
    $numeroCaixa = $caixa->id_central
        ? str_pad($caixa->id_central, 6, '0', STR_PAD_LEFT)
        : 'L' . str_pad($caixa->id, 5, '0', STR_PAD_LEFT);
    $formas = \App\Services\RelatorioFechamentoService::FORMAS;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de fechamento - Caixa {{ $numeroCaixa }}</title>
    @vite(['resources/css/app.css'])
    {{-- @page não tem equivalente em classe do Tailwind: define a bobina de 80 mm sem margem --}}
    <style>@page { size: 80mm auto; margin: 0; }</style>
</head>
<body class="bg-white text-black">
<div class="w-[72mm] mx-auto px-[2mm] py-[3mm] font-mono text-[11px] leading-snug">

    {{-- Empresa --}}
    <div class="text-center">
        <p class="font-bold uppercase text-[12px]">{{ $empresa->razao_social ?? 'EMPRESA' }}</p>
        @if (!empty($empresa?->nome_fantasia) && $empresa->nome_fantasia !== $empresa->razao_social)
            <p class="uppercase">{{ $empresa->nome_fantasia }}</p>
        @endif
        @if ($cnpjFormatado)
            <p>CNPJ {{ $cnpjFormatado }}</p>
        @endif
    </div>

    <div class="border-t border-dashed border-black my-2"></div>

    <p class="text-center font-bold text-[13px]">RELATÓRIO DE FECHAMENTO DE CAIXA</p>
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
        <div class="flex justify-between gap-2"><span>Computador</span><span class="text-right">{{ $maquina }}</span></div>
        <div class="flex justify-between gap-2">
            <span>Operador</span>
            <span class="text-right">{{ !empty($operador?->codigo) ? str_pad($operador->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $operador->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between gap-2"><span>Abertura</span><span>{{ $caixa->data_abertura->format('d/m/Y H:i:s') }}</span></div>
        <div class="flex justify-between gap-2"><span>Fechamento</span><span>{{ $caixa->data_fechamento?->format('d/m/Y H:i:s') ?? '-' }}</span></div>
    </div>

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Vendas --}}
    <p class="font-bold">VENDAS</p>
    <div class="flex gap-1 font-bold mt-1">
        <span class="w-[13mm]">NFC-e</span>
        <span class="w-[9mm]">Hora</span>
        <span class="flex-1">Pagto</span>
        <span class="w-[17mm] text-right">Total</span>
    </div>
    @forelse ($r['vendas'] as $v)
        <div class="flex gap-1">
            <span class="w-[13mm]">{{ $v['numero'] }}</span>
            <span class="w-[9mm]">{{ $v['hora'] }}</span>
            <span class="flex-1 truncate">{{ $v['situacao'] ? '[' . $v['situacao'] . '] ' : '' }}{{ $v['pagamento'] }}</span>
            <span class="w-[17mm] text-right {{ $v['situacao'] === 'CANC' ? 'line-through' : '' }}">{{ $n($v['total']) }}</span>
        </div>
    @empty
        <p class="mt-1">Nenhuma venda neste caixa.</p>
    @endforelse
    @if ($r['vendas'])
        <p class="text-[10px] mt-1">[CANC] cancelada · [CONT] contingência · [PEND] sem NFC-e</p>
    @endif

    <div class="border-t border-dashed border-black my-2"></div>

    <div class="space-y-0.5">
        <div class="flex justify-between"><span>Qtd. vendas</span><span>{{ $r['qtd_validas'] }}</span></div>
        <div class="flex justify-between"><span>Total bruto</span><span>{{ $n($r['total_bruto']) }}</span></div>
        <div class="flex justify-between"><span>(-) Descontos</span><span>{{ $n($r['total_descontos']) }}</span></div>
        <div class="flex justify-between font-bold"><span>TOTAL VENDIDO</span><span>{{ $n($r['total_liquido']) }}</span></div>
        <div class="flex justify-between"><span>Canceladas ({{ $r['qtd_canceladas'] }})</span><span>{{ $n($r['total_cancelado']) }}</span></div>
    </div>

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Formas de pagamento --}}
    <p class="font-bold">POR FORMA DE PAGAMENTO</p>
    @forelse ($r['formas'] as $chave => $valor)
        <div class="flex justify-between"><span>{{ $formas[$chave] ?? $chave }}</span><span>{{ $n($valor) }}</span></div>
    @empty
        <p>-</p>
    @endforelse
    @if ($r['troco'] > 0)
        <div class="flex justify-between"><span>Troco devolvido</span><span>{{ $n($r['troco']) }}</span></div>
    @endif

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Cancelamentos --}}
    <p class="font-bold">CANCELAMENTOS DE NFC-e</p>
    @forelse ($r['cancelamentos'] as $c)
        <div class="mt-1">
            <div class="flex justify-between"><span>{{ $c['numero'] }} {{ $c['hora'] }}</span><span>{{ $n($c['valor']) }}</span></div>
            @if ($c['motivo'])
                <p class="pl-2 break-words">Motivo: {{ $c['motivo'] }}</p>
            @endif
            <p class="pl-2">Liberado por: {{ $c['liberado_por'] }}</p>
        </div>
    @empty
        <p>Nenhum cancelamento.</p>
    @endforelse

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Descontos --}}
    <p class="font-bold">DESCONTOS</p>
    @forelse ($r['descontos'] as $d)
        <div class="mt-1">
            <div class="flex justify-between"><span>{{ $d['numero'] }} {{ $d['hora'] }}</span><span>{{ $n($d['valor']) }}</span></div>
            <p class="pl-2 truncate">{{ $d['descricao'] }}</p>
            <p class="pl-2">Liberado por: {{ $d['liberado_por'] }}</p>
        </div>
    @empty
        <p>Nenhum desconto.</p>
    @endforelse
    @if ($r['descontos'])
        <div class="flex justify-between font-bold mt-1"><span>Total de descontos</span><span>{{ $n($r['total_descontos']) }}</span></div>
    @endif

    {{-- Outras liberações --}}
    @if ($r['liberacoes'])
        <div class="border-t border-dashed border-black my-2"></div>
        <p class="font-bold">OUTRAS LIBERAÇÕES DO SUPERVISOR</p>
        @foreach ($r['liberacoes'] as $l)
            <div class="mt-1">
                <p>{{ $l['hora'] }} {{ $l['acao'] }}</p>
                <p class="pl-2">Liberado por: {{ $l['liberado_por'] }}</p>
            </div>
        @endforeach
    @endif

    <div class="border-t border-dashed border-black my-2"></div>

    {{-- Gaveta --}}
    <p class="font-bold">CONFERÊNCIA DA GAVETA (DINHEIRO)</p>
    <div class="space-y-0.5 mt-1">
        <div class="flex justify-between"><span>Fundo de troco</span><span>{{ $n($caixa->valor_abertura) }}</span></div>
        <div class="flex justify-between"><span>(+) Vendas em dinheiro</span><span>{{ $n($r['formas']['dinheiro'] ?? 0) }}</span></div>
        <div class="flex justify-between"><span>(-) Troco devolvido</span><span>{{ $n($r['troco']) }}</span></div>
        <div class="flex justify-between font-bold"><span>Esperado na gaveta</span><span>{{ $n($r['dinheiro_esperado']) }}</span></div>
        <div class="flex justify-between"><span>Informado pelo operador</span><span>{{ $r['informado'] !== null ? $n($r['informado']) : '-' }}</span></div>
        @if ($r['diferenca'] !== null)
            <div class="flex justify-between font-bold text-[12px]">
                <span>{{ abs($r['diferenca']) < 0.01 ? 'SEM DIFERENÇA' : ($r['diferenca'] > 0 ? 'SOBRA' : 'FALTA') }}</span>
                <span>{{ $n($r['diferenca']) }}</span>
            </div>
        @endif
    </div>

    @if ($caixa->observacao)
        <p class="mt-2 break-words">Obs.: {{ $caixa->observacao }}</p>
    @endif

    <div class="border-t border-dashed border-black my-2"></div>

    <p>Situação: {{ $caixa->sync_pendente ? 'aguardando envio ao servidor' : 'enviado ao servidor' }}</p>

    <div class="mt-10 border-t border-black"></div>
    <p class="text-center">{{ $operador->name ?? 'Operador' }}</p>
    <p class="text-center text-[10px]">Assinatura do operador</p>

    <div class="mt-10 border-t border-black"></div>
    <p class="text-center text-[10px]">Assinatura do responsável</p>

    <div class="border-t border-dashed border-black my-2"></div>

    <p class="text-center text-[10px]">Impresso em {{ now()->format('d/m/Y H:i:s') }}</p>
    <p class="text-center text-[10px]">Cyberly Sistema</p>

    {{-- Espaço para o corte da bobina --}}
    <div class="h-8"></div>
</div>

@if ($auto)
    <script>
        window.addEventListener('load', () => {
            window.focus();
            window.print();
            try {
                window.parent?.relatorioImpresso?.();
            } catch (e) {}
        });
    </script>
@endif
</body>
</html>
