@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $detalhada = $filtros['visao'] === 'detalhada';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Margem de Lucro</title>
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
        .verde { color: #047857; }
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

    <h1>Relatório de Margem de Lucro</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Venda (líquida)</div><div class="valor">R$ {{ $moeda($totais->valor_venda) }}</div></td>
            <td><div class="rotulo">Custo</div><div class="valor">R$ {{ $moeda($totais->custo) }}</div></td>
            <td><div class="rotulo">Carga tributária</div><div class="valor">R$ {{ $moeda($totais->carga) }}</div></td>
            <td><div class="rotulo">Lucro</div><div class="valor {{ $totais->lucro < 0 ? 'vermelho' : 'verde' }}">R$ {{ $moeda($totais->lucro) }}</div></td>
            <td><div class="rotulo">Margem</div><div class="valor {{ $totais->lucro < 0 ? 'vermelho' : 'verde' }}">{{ $perc($totais->margem) }}</div></td>
        </tr>
    </table>

    <div class="cinza" style="margin-bottom: 6px;">
        Venda = valor líquido (produtos + acréscimos − descontos). Carga tributária = ICMS + PIS + COFINS{{ $totais->incluiu_ibs_cbs ? ' + IBS + CBS' : '' }}
        destacados na nota (o IPI não entra). Lucro = venda − custo − carga tributária; margem = lucro ÷ venda.
    </div>

    @if ($totais->itens_sem_dados > 0)
        <div class="cinza" style="margin-bottom: 6px;">
            {{ number_format($totais->itens_sem_dados, 0, ',', '.') }} item(ns) sem custo ou sem impostos gravados ficam fora dos valores acima e aparecem com “—”.
        </div>
    @endif

    <table class="lista">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Nº doc.</th>
                <th class="dir">Itens</th>
                <th class="dir">Venda</th>
                <th class="dir">Custo</th>
                <th class="dir">Carga trib.</th>
                <th class="dir">Lucro</th>
                <th class="dir">Margem</th>
                <th>Vendedor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documentos as $doc)
                @php $completo = (bool) $doc->completo; @endphp
                <tr class="{{ $detalhada ? 'venda' : '' }}">
                    <td>{{ $data($doc->data) }}</td>
                    <td>{{ $doc->tipo }}</td>
                    <td>{{ $doc->numero ?? '—' }}{{ ($doc->serie !== null && $doc->serie !== '') ? '/' . $doc->serie : '' }}</td>
                    <td class="dir">{{ $doc->qtd_itens }}</td>
                    <td class="dir">{{ $moeda($doc->valor_venda) }}</td>
                    <td class="dir">{{ $completo ? $moeda($doc->custo) : '—' }}</td>
                    <td class="dir">{{ $completo ? $moeda($doc->carga) : '—' }}</td>
                    <td class="dir {{ $completo ? ((float) $doc->lucro < 0 ? 'vermelho' : 'verde') : '' }}">{{ $completo ? $moeda($doc->lucro) : '—' }}</td>
                    <td class="dir {{ $completo ? ((float) $doc->lucro < 0 ? 'vermelho' : 'verde') : '' }}">{{ $completo ? $perc($doc->margem) : '—' }}</td>
                    <td>{{ $doc->vendedor ?: '—' }}</td>
                </tr>

                @if ($detalhada)
                    @php $itensDoc = $itensPorDocumento[$doc->tipo . '-' . $doc->documento_id] ?? collect(); @endphp
                    @if ($itensDoc->isNotEmpty())
                        <tr class="itens-cab">
                            <td>Código</td>
                            <td colspan="2">Descrição</td>
                            <td class="dir">Qtd</td>
                            <td class="dir">Venda</td>
                            <td class="dir">Custo</td>
                            <td class="dir">Carga trib.</td>
                            <td class="dir">Lucro</td>
                            <td class="dir">Margem</td>
                            <td class="dir">Custo unit.</td>
                        </tr>
                        @foreach ($itensDoc as $item)
                            @php $itemCompleto = (bool) $item->completo; @endphp
                            <tr class="item">
                                <td class="mono">{{ $item->codigo ?: '—' }}</td>
                                <td colspan="2">{{ $item->descricao ?: '—' }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                                <td class="dir">{{ $moeda($item->valor_venda) }}</td>
                                <td class="dir">{{ $item->tem_custo ? $moeda($item->custo) : '—' }}</td>
                                <td class="dir">{{ $item->tem_impostos ? $moeda($item->carga) : '—' }}</td>
                                <td class="dir {{ $itemCompleto ? ((float) $item->lucro < 0 ? 'vermelho' : 'verde') : '' }}">{{ $itemCompleto ? $moeda($item->lucro) : '—' }}</td>
                                <td class="dir {{ $itemCompleto ? ((float) $item->lucro < 0 ? 'vermelho' : 'verde') : '' }}">{{ $itemCompleto ? $perc($item->margem) : '—' }}</td>
                                <td class="dir">{{ $item->custo_unitario !== null ? number_format((float) $item->custo_unitario, 4, ',', '.') : '—' }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="10" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda emitida encontrada.</td>
                </tr>
            @endforelse

            @if ($documentos->count() > 0)
                <tr class="total">
                    <td colspan="3">Total do filtro</td>
                    <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                    <td class="dir">{{ $moeda($totais->valor_venda) }}</td>
                    <td class="dir">{{ $moeda($totais->custo) }}</td>
                    <td class="dir">{{ $moeda($totais->carga) }}</td>
                    <td class="dir">{{ $moeda($totais->lucro) }}</td>
                    <td class="dir">{{ $perc($totais->margem) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
