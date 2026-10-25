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
    <title>Relatório de Regime/Alíquota e Tributação</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; font-size: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; font-size: 8px; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 16.6%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 10px; font-weight: bold; }
        .totais .detalhe { font-size: 6.5px; color: #6b7280; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 6.5px; text-transform: uppercase; padding: 5px 3px; text-align: left; }
        table.lista td { padding: 3px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.5px; }
        .cinza { color: #6b7280; font-weight: normal; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Regime/Alíquota e Tributação</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Venda (líquida)</div><div class="valor">R$ {{ $moeda($totais->valor_venda) }}</div><div class="detalhe">{{ number_format($totais->documentos, 0, ',', '.') }} doc. &middot; {{ number_format($totais->itens, 0, ',', '.') }} itens</div></td>
            <td><div class="rotulo">ICMS</div><div class="valor">R$ {{ $moeda($totais->icms) }}</div><div class="detalhe">Base R$ {{ $moeda($totais->bc_icms) }}</div></td>
            <td><div class="rotulo">PIS + COFINS</div><div class="valor">R$ {{ $moeda($totais->pis + $totais->cofins) }}</div><div class="detalhe">PIS {{ $moeda($totais->pis) }} &middot; COFINS {{ $moeda($totais->cofins) }}</div></td>
            <td><div class="rotulo">IPI</div><div class="valor">R$ {{ $moeda($totais->ipi) }}</div></td>
            <td><div class="rotulo">IBS + CBS</div><div class="valor">R$ {{ $moeda($totais->ibs + $totais->cbs) }}</div><div class="detalhe">IBS {{ $moeda($totais->ibs) }} &middot; CBS {{ $moeda($totais->cbs) }}</div></td>
            <td><div class="rotulo">Total de impostos</div><div class="valor">R$ {{ $moeda($totais->total_impostos) }}</div><div class="detalhe">{{ $perc($totais->carga_percentual) }} da venda</div></td>
        </tr>
    </table>

    <div class="cinza" style="margin-bottom: 4px;">
        Simples Nacional (CSOSN): R$ {{ $moeda($totais->venda_simples) }} ({{ $perc($totais->percentual_simples) }} da venda) &middot;
        Regime normal (CST): R$ {{ $moeda($totais->venda_normal) }} ({{ $perc($totais->percentual_normal) }} da venda).
        Regime pelo código gravado no item: CSOSN (3 dígitos) = Simples Nacional; CST (2 dígitos) = regime normal.
    </div>

    @if ($totais->itens_sem_impostos > 0)
        <div class="cinza" style="margin-bottom: 6px;">
            {{ number_format($totais->itens_sem_impostos, 0, ',', '.') }} item(ns) de notas emitidas antes do registro dos impostos ficam fora do relatório.
        </div>
    @endif

    @unless ($detalhada)
        <table class="lista">
            <thead>
                <tr>
                    <th>Regime</th>
                    <th>CST/CSOSN</th>
                    <th>CFOP</th>
                    <th class="dir">Alíq. ICMS</th>
                    <th class="dir">Itens</th>
                    <th class="dir">Venda</th>
                    <th class="dir">BC ICMS</th>
                    <th class="dir">ICMS</th>
                    <th class="dir">PIS</th>
                    <th class="dir">COFINS</th>
                    <th class="dir">IPI</th>
                    <th class="dir">IBS</th>
                    <th class="dir">CBS</th>
                    <th class="dir">Total impostos</th>
                    <th class="dir">% s/ venda</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $g)
                    <tr>
                        <td>{{ $regimes[$g->regime] ?? '—' }}</td>
                        <td>{{ $g->cst ?: '—' }} <span class="cinza">{{ $descricoes[$g->cst] ?? '' }}</span></td>
                        <td class="mono">{{ $g->cfop ?: '—' }}</td>
                        <td class="dir">{{ $perc($g->aliquota_icms) }}</td>
                        <td class="dir">{{ number_format($g->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($g->valor_venda) }}</td>
                        <td class="dir">{{ $moeda($g->bc_icms) }}</td>
                        <td class="dir">{{ $moeda($g->icms) }}</td>
                        <td class="dir">{{ $moeda($g->pis) }}</td>
                        <td class="dir">{{ $moeda($g->cofins) }}</td>
                        <td class="dir">{{ $moeda($g->ipi) }}</td>
                        <td class="dir">{{ $moeda($g->ibs) }}</td>
                        <td class="dir">{{ $moeda($g->cbs) }}</td>
                        <td class="dir">{{ $moeda($g->total_impostos) }}</td>
                        <td class="dir">{{ $perc($g->carga_percentual) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda com tributação registrada no período.</td>
                    </tr>
                @endforelse

                @if ($registros->count() > 0)
                    <tr class="total">
                        <td colspan="4">Total do filtro</td>
                        <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($totais->valor_venda) }}</td>
                        <td class="dir">{{ $moeda($totais->bc_icms) }}</td>
                        <td class="dir">{{ $moeda($totais->icms) }}</td>
                        <td class="dir">{{ $moeda($totais->pis) }}</td>
                        <td class="dir">{{ $moeda($totais->cofins) }}</td>
                        <td class="dir">{{ $moeda($totais->ipi) }}</td>
                        <td class="dir">{{ $moeda($totais->ibs) }}</td>
                        <td class="dir">{{ $moeda($totais->cbs) }}</td>
                        <td class="dir">{{ $moeda($totais->total_impostos) }}</td>
                        <td class="dir">{{ $perc($totais->carga_percentual) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @else
        <table class="lista">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Nº doc.</th>
                    <th>Cód.</th>
                    <th>Descrição</th>
                    <th>Regime</th>
                    <th>CST/CSOSN</th>
                    <th>CFOP</th>
                    <th class="dir">Venda</th>
                    <th class="dir">BC ICMS</th>
                    <th class="dir">Alíq. ICMS</th>
                    <th class="dir">ICMS</th>
                    <th class="dir">PIS</th>
                    <th class="dir">COFINS</th>
                    <th class="dir">IPI</th>
                    <th class="dir">IBS</th>
                    <th class="dir">CBS</th>
                    <th class="dir">Total impostos</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $i)
                    <tr>
                        <td>{{ $data($i->data) }}</td>
                        <td>{{ $i->tipo }}</td>
                        <td>{{ $i->numero ?? '—' }}{{ ($i->serie !== null && $i->serie !== '') ? '/' . $i->serie : '' }}</td>
                        <td class="mono">{{ $i->codigo ?: '—' }}</td>
                        <td>{{ $i->descricao ?: '—' }}</td>
                        <td>{{ $regimes[$i->regime] ?? '—' }}</td>
                        <td>{{ $i->cst ?: '—' }}</td>
                        <td class="mono">{{ $i->cfop ?: '—' }}</td>
                        <td class="dir">{{ $moeda($i->valor_venda) }}</td>
                        <td class="dir">{{ $moeda($i->bc_icms) }}</td>
                        <td class="dir">{{ $perc($i->aliquota_icms) }}</td>
                        <td class="dir">{{ $moeda($i->icms) }}</td>
                        <td class="dir">{{ $moeda($i->pis) }}</td>
                        <td class="dir">{{ $moeda($i->cofins) }}</td>
                        <td class="dir">{{ $moeda($i->ipi) }}</td>
                        <td class="dir">{{ $moeda($i->ibs) }}</td>
                        <td class="dir">{{ $moeda($i->cbs) }}</td>
                        <td class="dir">{{ $moeda($i->total_impostos) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="18" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda com tributação registrada no período.</td>
                    </tr>
                @endforelse

                @if ($registros->count() > 0)
                    <tr class="total">
                        <td colspan="8">Total do filtro</td>
                        <td class="dir">{{ $moeda($totais->valor_venda) }}</td>
                        <td class="dir">{{ $moeda($totais->bc_icms) }}</td>
                        <td></td>
                        <td class="dir">{{ $moeda($totais->icms) }}</td>
                        <td class="dir">{{ $moeda($totais->pis) }}</td>
                        <td class="dir">{{ $moeda($totais->cofins) }}</td>
                        <td class="dir">{{ $moeda($totais->ipi) }}</td>
                        <td class="dir">{{ $moeda($totais->ibs) }}</td>
                        <td class="dir">{{ $moeda($totais->cbs) }}</td>
                        <td class="dir">{{ $moeda($totais->total_impostos) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endunless
</body>
</html>
