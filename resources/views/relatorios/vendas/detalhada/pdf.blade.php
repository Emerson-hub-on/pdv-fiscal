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
    <title>Relatório de Vendas Detalhada</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; font-size: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; font-size: 8px; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 20%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 6.5px; text-transform: uppercase; padding: 5px 3px; text-align: left; }
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

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Vendas Detalhada</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Documentos</div><div class="valor">{{ number_format($totais->documentos, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor bruto</div><div class="valor">R$ {{ $moeda($totais->valor_bruto) }}</div></td>
            <td><div class="rotulo">Descontos</div><div class="valor vermelho">R$ {{ $moeda($totais->desconto) }}</div></td>
            <td><div class="rotulo">Valor líquido</div><div class="valor">R$ {{ $moeda($totais->valor_liquido) }}</div></td>
            <td><div class="rotulo">Impostos destacados</div><div class="valor">R$ {{ $moeda($totais->impostos) }}</div></td>
        </tr>
    </table>

    @if ($totais->sem_impostos > 0)
        <div class="cinza" style="margin-bottom: 6px;">
            {{ number_format($totais->sem_impostos, 0, ',', '.') }} item(ns) de notas emitidas antes do registro dos impostos aparecem com “—” e não entram nos totais de impostos.
        </div>
    @endif

    <table class="lista">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Nº doc.</th>
                <th class="dir">Itens</th>
                <th class="dir">Bruto</th>
                <th class="dir">Desc.</th>
                <th class="dir">Acrésc.</th>
                <th class="dir">Líquido</th>
                <th class="dir">ICMS</th>
                <th class="dir">PIS</th>
                <th class="dir">COFINS</th>
                <th class="dir">IPI</th>
                <th class="dir">IBS</th>
                <th class="dir">CBS</th>
                <th>Vendedor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documentos as $doc)
                @php $imposto = fn ($v) => $doc->tem_impostos ? $moeda($v) : '—'; @endphp
                <tr class="{{ $detalhada ? 'venda' : '' }}">
                    <td>{{ $data($doc->data) }}</td>
                    <td>{{ $doc->tipo }}</td>
                    <td>{{ $doc->numero ?? '—' }}{{ ($doc->serie !== null && $doc->serie !== '') ? '/' . $doc->serie : '' }}</td>
                    <td class="dir">{{ $doc->qtd_itens }}</td>
                    <td class="dir">{{ $moeda($doc->valor_bruto) }}</td>
                    <td class="dir {{ (float) $doc->desconto > 0 ? 'vermelho' : '' }}">{{ $moeda($doc->desconto) }}</td>
                    <td class="dir">{{ $moeda($doc->acrescimo) }}</td>
                    <td class="dir">{{ $moeda($doc->valor_liquido) }}</td>
                    <td class="dir">{{ $imposto($doc->icms) }}</td>
                    <td class="dir">{{ $imposto($doc->pis) }}</td>
                    <td class="dir">{{ $imposto($doc->cofins) }}</td>
                    <td class="dir">{{ $imposto($doc->ipi) }}</td>
                    <td class="dir">{{ $imposto($doc->ibs) }}</td>
                    <td class="dir">{{ $imposto($doc->cbs) }}</td>
                    <td>{{ $doc->vendedor ?: '—' }}</td>
                </tr>

                @if ($detalhada)
                    @php $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect(); @endphp
                    @if ($itensDoc->isNotEmpty())
                        <tr class="itens-cab">
                            <td>Código</td>
                            <td colspan="3">Descrição</td>
                            <td class="dir">Valor bruto</td>
                            <td class="dir">Desc.</td>
                            <td class="dir">Acrésc.</td>
                            <td class="dir">Líquido</td>
                            <td class="dir">ICMS</td>
                            <td class="dir">PIS</td>
                            <td class="dir">COFINS</td>
                            <td class="dir">IPI</td>
                            <td class="dir">IBS</td>
                            <td class="dir">CBS</td>
                            <td class="dir">Qtd</td>
                        </tr>
                        @foreach ($itensDoc as $item)
                            @php $impostoItem = fn ($v) => $item->tem_impostos ? $moeda($v) : '—'; @endphp
                            <tr class="item">
                                <td class="mono">{{ $item->codigo ?: '—' }}</td>
                                <td colspan="3">{{ $item->descricao ?: '—' }}</td>
                                <td class="dir">{{ $moeda($item->valor_bruto) }}</td>
                                <td class="dir">{{ $moeda($item->desconto) }}</td>
                                <td class="dir">{{ $moeda($item->acrescimo) }}</td>
                                <td class="dir">{{ $moeda($item->valor_liquido) }}</td>
                                <td class="dir">{{ $impostoItem($item->icms) }}</td>
                                <td class="dir">{{ $impostoItem($item->pis) }}</td>
                                <td class="dir">{{ $impostoItem($item->cofins) }}</td>
                                <td class="dir">{{ $impostoItem($item->ipi) }}</td>
                                <td class="dir">{{ $impostoItem($item->ibs) }}</td>
                                <td class="dir">{{ $impostoItem($item->cbs) }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="15" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda emitida encontrada.</td>
                </tr>
            @endforelse

            @if ($documentos->count() > 0)
                <tr class="total">
                    <td colspan="3">Total do filtro</td>
                    <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                    <td class="dir">{{ $moeda($totais->valor_bruto) }}</td>
                    <td class="dir">{{ $moeda($totais->desconto) }}</td>
                    <td class="dir">{{ $moeda($totais->acrescimo) }}</td>
                    <td class="dir">{{ $moeda($totais->valor_liquido) }}</td>
                    <td class="dir">{{ $moeda($totais->icms) }}</td>
                    <td class="dir">{{ $moeda($totais->pis) }}</td>
                    <td class="dir">{{ $moeda($totais->cofins) }}</td>
                    <td class="dir">{{ $moeda($totais->ipi) }}</td>
                    <td class="dir">{{ $moeda($totais->ibs) }}</td>
                    <td class="dir">{{ $moeda($totais->cbs) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>