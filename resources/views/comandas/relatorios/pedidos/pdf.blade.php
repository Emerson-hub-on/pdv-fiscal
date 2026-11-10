@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    $detalhada = $filtros['visao'] === 'detalhada';
    $classesStatus = ['concluido' => 'verde', 'andamento' => 'ambar', 'cancelado' => 'vermelho'];
    $origens = ['mesa' => 'Mesa', 'ifood' => 'iFood'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Pedidos do Restaurante</title>
    {{-- PDF (DomPDF): o Tailwind não é carregado aqui, por isso o CSS fica no próprio arquivo, como nos outros relatórios --}}
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 25%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; }
        tr.pedido td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.pedido-resumido td { border-top: 1px solid #e5e7eb; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .vermelho { color: #dc2626; }
        .verde { color: #15803d; }
        .ambar { color: #b45309; }
        .cinza { color: #6b7280; font-weight: normal; }
        .risco { text-decoration: line-through; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Pedidos do Restaurante</h1>
    <div class="sub">Comandas &middot; mesas e iFood &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Vendido ({{ $totais->concluidos }} concluídos)</div><div class="valor verde">{{ $dinheiro($totais->valor_vendido) }}</div></td>
            <td><div class="rotulo">Mesas ({{ $totais->mesas }} contas pagas)</div><div class="valor">{{ $dinheiro($totais->valor_mesas) }}</div></td>
            <td><div class="rotulo">iFood ({{ $totais->ifood }} pedidos, só itens)</div><div class="valor">{{ $dinheiro($totais->valor_ifood) }}</div></td>
            <td><div class="rotulo">Cancelados ({{ $dinheiro($totais->valor_cancelado) }})</div><div class="valor vermelho">{{ number_format($totais->cancelados, 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Origem</th>
                <th>Pedido</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Garçom / Entrega</th>
                <th>Pagamento</th>
                <th>Status</th>
                <th>NFC-e</th>
                <th class="dir">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                @php $cancelado = $pedido['status'] === 'cancelado'; @endphp

                <tr class="{{ $detalhada ? 'pedido' : 'pedido-resumido' }}">
                    <td>{{ $origens[$pedido['origem']] }}</td>
                    <td>{{ $pedido['pedido'] }}{{ $pedido['detalhe'] ? ' · ' . $pedido['detalhe'] : '' }}</td>
                    <td>{{ $data($pedido['momento']) }}</td>
                    <td>{{ $pedido['cliente'] ?: '—' }}</td>
                    <td>{{ $pedido['atendimento'] ?: '—' }}</td>
                    <td>{{ $pedido['pagamento'] ?: '—' }}</td>
                    <td class="{{ $classesStatus[$pedido['status']] ?? '' }}">{{ $pedido['status_rotulo'] }}</td>
                    <td>{{ $pedido['nfce'] }}</td>
                    <td class="dir {{ $cancelado ? 'vermelho risco' : '' }}">{{ $dinheiro($pedido['total']) }}</td>
                </tr>

                @if ($detalhada)
                    @if ($pedido['observacao'])
                        <tr class="item"><td colspan="9" class="cinza">Observação: {{ $pedido['observacao'] }}</td></tr>
                    @endif

                    @if (! $pedido['itens'])
                        <tr class="item"><td colspan="9" class="cinza">Pedido sem itens.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td colspan="3">Descrição</td>
                            <td colspan="3">Observação</td>
                            <td class="dir">Quantidade</td>
                            <td class="dir">Valor unit.</td>
                            <td class="dir">Total</td>
                        </tr>
                        @foreach ($pedido['itens'] as $item)
                            <tr class="item {{ $cancelado ? 'cinza' : '' }}">
                                <td colspan="3">{{ $item['nome'] }}</td>
                                <td colspan="3" class="cinza">{{ $item['observacao'] ?: '—' }}</td>
                                <td class="dir">{{ $item['quantidade'] }}</td>
                                <td class="dir">{{ $dinheiro($item['preco_unitario']) }}</td>
                                <td class="dir">{{ $dinheiro($item['subtotal']) }}</td>
                            </tr>
                        @endforeach
                        @if ($pedido['origem'] === 'ifood' && ($pedido['taxa_entrega'] > 0 || $pedido['descontos'] > 0))
                            <tr class="item"><td colspan="9" class="cinza">Taxa de entrega {{ $dinheiro($pedido['taxa_entrega']) }} &middot; Descontos {{ $dinheiro($pedido['descontos']) }} (fora do total)</td></tr>
                        @endif
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="9" class="cinza" style="text-align:center; padding: 16px;">Nenhum pedido encontrado no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
