@php
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $detalhada = $filtros['visao'] === 'detalhada';
    $rotulos = array_map(fn ($m) => $m->rotulo, $resumo->modalidades);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Forma de Pagamento</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        h2 { font-size: 10px; margin: 12px 0 4px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 33%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }
        .totais .detalhe { font-size: 7px; color: #6b7280; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .cinza { color: #6b7280; }
        .ambar { color: #b45309; font-weight: bold; }
        .verde { color: #047857; font-weight: bold; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Forma de Pagamento</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td>
                <div class="rotulo">Total</div>
                <div class="valor">R$ {{ $moeda($resumo->total) }}</div>
                <div class="detalhe">{{ number_format($resumo->documentos, 0, ',', '.') }} documento(s) &middot; NFC-e R$ {{ $moeda($resumo->nfce) }} &middot; NF-e R$ {{ $moeda($resumo->nfe) }}</div>
            </td>
            <td>
                <div class="rotulo">À vista (recebido na venda)</div>
                <div class="valor verde">R$ {{ $moeda($resumo->a_vista) }}</div>
                <div class="detalhe">{{ $perc(100 - $resumo->percentual_a_prazo) }} do total</div>
            </td>
            <td>
                <div class="rotulo">A prazo (a receber)</div>
                <div class="valor ambar">R$ {{ $moeda($resumo->a_prazo) }}</div>
                <div class="detalhe">{{ $perc($resumo->percentual_a_prazo) }} do total</div>
            </td>
        </tr>
    </table>

    @unless ($detalhada)
    <h2>Faturamento por modalidade</h2>
    <table class="lista">
        <thead>
            <tr>
                <th>Modalidade</th>
                <th class="dir">Pagamentos</th>
                <th class="dir">NFC-e</th>
                <th class="dir">NF-e</th>
                <th class="dir">À vista</th>
                <th class="dir">A prazo</th>
                <th class="dir">Total</th>
                <th class="dir">% do total</th>
                <th class="dir">Valor médio</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resumo->modalidades as $m)
                <tr>
                    <td>{{ $m->rotulo }}</td>
                    <td class="dir">{{ number_format($m->pagamentos, 0, ',', '.') }}</td>
                    <td class="dir">{{ $moeda($m->nfce) }}</td>
                    <td class="dir">{{ $moeda($m->nfe) }}</td>
                    <td class="dir">{{ $moeda($m->a_vista) }}</td>
                    <td class="dir">{{ $moeda($m->a_prazo) }}</td>
                    <td class="dir">{{ $moeda($m->total) }}</td>
                    <td class="dir">{{ $perc($m->percentual) }}</td>
                    <td class="dir">{{ $moeda($m->ticket_medio) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="dir">{{ number_format($resumo->pagamentos, 0, ',', '.') }}</td>
                <td class="dir">{{ $moeda($resumo->nfce) }}</td>
                <td class="dir">{{ $moeda($resumo->nfe) }}</td>
                <td class="dir">{{ $moeda($resumo->a_vista) }}</td>
                <td class="dir">{{ $moeda($resumo->a_prazo) }}</td>
                <td class="dir">{{ $moeda($resumo->total) }}</td>
                <td class="dir">{{ $resumo->total > 0 ? '100,00%' : '—' }}</td>
                <td class="dir">{{ $moeda($resumo->pagamentos > 0 ? $resumo->total / $resumo->pagamentos : 0) }}</td>
            </tr>
        </tbody>
    </table>

    @endunless

    @if ($detalhada)
        <h2>Pagamentos</h2>
        <table class="lista">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Nº doc.</th>
                    <th>Forma de pagamento</th>
                    <th>Modalidade</th>
                    <th>Prazo</th>
                    <th class="dir">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lista as $p)
                    <tr>
                        <td>{{ $data($p->data) }}</td>
                        <td>{{ $p->tipo }}</td>
                        <td>{{ $p->numero ?? '—' }}{{ ($p->serie !== null && $p->serie !== '') ? '/' . $p->serie : '' }}</td>
                        <td>{{ $p->forma }}</td>
                        <td>{{ $rotulos[$p->modalidade] ?? $p->modalidade }}</td>
                        <td class="{{ $p->a_prazo ? 'ambar' : '' }}">{{ $p->a_prazo ? 'A prazo' : 'À vista' }}</td>
                        <td class="dir">{{ $moeda($p->valor) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="cinza" style="text-align:center; padding: 16px;">Nenhum pagamento encontrado.</td>
                    </tr>
                @endforelse

                @if ($lista->count() > 0)
                    <tr class="total">
                        <td colspan="6">Total das linhas listadas</td>
                        <td class="dir">{{ $moeda($lista->sum('valor')) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <div class="cinza" style="margin-top: 8px; font-size: 7px;">
        NFC-e: cada forma de pagamento da venda, com o troco descontado do dinheiro. NF-e: forma de pagamento da nota pelo valor total da nota (inclui IPI e frete).
        “A prazo” segue o indicador da forma de pagamento cadastrada.
    </div>
</body>
</html>