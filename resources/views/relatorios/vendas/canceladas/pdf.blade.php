@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $detalhada = $filtros['visao'] === 'detalhada';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Vendas Canceladas</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; font-size: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; font-size: 8px; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 25%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #10284A; color: #fffbeb; font-size: 6.5px; text-transform: uppercase; padding: 5px 3px; text-align: left; }
        table.lista td { padding: 3px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        tr.venda td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.itens-cab td { padding: 3px 3px 1px 14px; font-size: 6px; text-transform: uppercase; color: #6b7280; border-bottom: none; }
        tr.item td { padding: 2px 3px 2px 14px; font-size: 6.5px; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.5px; }
        .vermelho { color: #dc2626; }
        .cinza { color: #6b7280; font-weight: normal; }
        .motivo { max-width: 220px; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Vendas Canceladas</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Vendas canceladas</div><div class="valor">{{ number_format($totais->documentos, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor cancelado</div><div class="valor vermelho">R$ {{ $moeda($totais->valor) }}</div></td>
            <td><div class="rotulo">NFC-e ({{ $totais->qtd_nfce }})</div><div class="valor">R$ {{ $moeda($totais->valor_nfce) }}</div></td>
            <td><div class="rotulo">NF-e ({{ $totais->qtd_nfe }})</div><div class="valor">R$ {{ $moeda($totais->valor_nfe) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Cancelada em</th>
                <th>Tipo</th>
                <th>Nº doc.</th>
                <th>Venda em</th>
                <th>Cliente</th>
                <th class="dir">Valor</th>
                <th>Motivo</th>
                <th>Cancelado por</th>
                <th>Liberado por</th>
                <th>Vendedor</th>
                <th class="dir">Itens</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documentos as $doc)
                <tr class="{{ $detalhada ? 'venda' : '' }}">
                    <td>{{ $data($doc->cancelado_em) }}</td>
                    <td>{{ $doc->tipo }}</td>
                    <td>{{ $doc->numero ?? '—' }}{{ ($doc->serie !== null && $doc->serie !== '') ? '/' . $doc->serie : '' }}</td>
                    <td>{{ $data($doc->emitida_em) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($doc->cliente ?: 'Consumidor', 40) }}</td>
                    <td class="dir vermelho">{{ $moeda($doc->valor) }}</td>
                    <td class="motivo">{{ $doc->motivo ?: '—' }}</td>
                    <td>{{ $doc->cancelado_por ?: '—' }}</td>
                    <td>{{ $doc->autorizado_por === 'Operador liberado' ? 'Próprio operador (liberado)' : ($doc->autorizado_por ?: '—') }}</td>
                    <td>{{ $doc->vendedor ?: '—' }}</td>
                    <td class="dir">{{ $doc->qtd_itens }}</td>
                </tr>

                @if ($detalhada)
                    @php $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect(); @endphp
                    @if ($itensDoc->isNotEmpty())
                        <tr class="itens-cab">
                            <td colspan="2">Código</td>
                            <td colspan="5">Descrição</td>
                            <td class="dir">Qtd</td>
                            <td class="dir">Valor unit.</td>
                            <td class="dir">Desc.</td>
                            <td class="dir">Total</td>
                        </tr>
                        @foreach ($itensDoc as $item)
                            <tr class="item">
                                <td colspan="2" class="mono">{{ $item->codigo ?: '—' }}</td>
                                <td colspan="5">{{ $item->descricao ?: '—' }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                                <td class="dir">{{ $moeda($item->valor_unitario) }}</td>
                                <td class="dir">{{ $moeda($item->desconto) }}</td>
                                <td class="dir">{{ $moeda($item->valor_total) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr><td colspan="11" style="text-align:center; padding: 12px; color:#9ca3af;">Nenhuma venda cancelada encontrada no filtro.</td></tr>
            @endforelse
            @if ($documentos->count() > 0)
                <tr class="total">
                    <td colspan="5">Total do filtro</td>
                    <td class="dir vermelho">{{ $moeda($totais->valor) }}</td>
                    <td colspan="4"></td>
                    <td class="dir">{{ $totais->itens }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
