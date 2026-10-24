@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $cor = ['A' => 'verde', 'B' => 'ambar', 'C' => 'cinza'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Curva ABC</title>
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
        .totais .detalhe { font-size: 7px; color: #6b7280; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .centro { text-align: center !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .verde { color: #047857; font-weight: bold; }
        .ambar { color: #b45309; font-weight: bold; }
        .cinza { color: #6b7280; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Curva ABC</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            @foreach (['A' => 'Classe A — maior faturamento', 'B' => 'Classe B — intermediário', 'C' => 'Classe C — menor expressão'] as $letra => $titulo)
                @php $c = $resumo->classes[$letra]; @endphp
                <td>
                    <div class="rotulo">{{ $titulo }}</div>
                    <div class="valor {{ $cor[$letra] }}">R$ {{ $moeda($c->faturamento) }}</div>
                    <div class="detalhe">{{ $perc($c->percentual_faturamento) }} do faturamento &middot; {{ number_format($c->produtos, 0, ',', '.') }} produto(s) ({{ $perc($c->percentual_produtos) }})</div>
                </td>
            @endforeach
            <td>
                <div class="rotulo">Total do período</div>
                <div class="valor">R$ {{ $moeda($resumo->faturamento) }}</div>
                <div class="detalhe">{{ number_format($resumo->produtos, 0, ',', '.') }} produto(s) vendido(s)</div>
            </td>
        </tr>
    </table>

    <div class="cinza" style="margin-bottom: 6px;">
        Faturamento = valor líquido vendido (produtos + acréscimos − descontos) de NFC-e e NF-e emitidas. A classe vale para o ranking do período inteiro.
    </div>

    <table class="lista">
        <thead>
            <tr>
                <th>#</th>
                <th class="centro">Classe</th>
                <th>Código</th>
                <th>Produto</th>
                <th class="dir">Qtd vendida</th>
                <th class="dir">Nº de vendas</th>
                <th class="dir">Faturamento</th>
                <th class="dir">% do total</th>
                <th class="dir">% acumulado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($produtos as $p)
                <tr>
                    <td class="cinza">{{ $p->posicao }}</td>
                    <td class="centro {{ $cor[$p->classe] }}">{{ $p->classe }}</td>
                    <td class="mono">{{ $p->codigo ?: '—' }}</td>
                    <td>{{ $p->nome ?: '—' }}</td>
                    <td class="dir">{{ $qtd($p->quantidade) }}</td>
                    <td class="dir">{{ number_format($p->vendas, 0, ',', '.') }}</td>
                    <td class="dir">{{ $moeda($p->faturamento) }}</td>
                    <td class="dir">{{ $perc($p->percentual) }}</td>
                    <td class="dir">{{ $perc($p->percentual_acumulado) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="cinza" style="text-align:center; padding: 16px;">Nenhum produto vendido no período.</td>
                </tr>
            @endforelse

            @if ($produtos->count() > 0)
                <tr class="total">
                    <td colspan="4">Total {{ $filtros['classe'] !== 'todas' || $filtros['busca'] !== '' ? 'das linhas listadas' : 'do período' }}</td>
                    <td class="dir">{{ $qtd($produtos->sum('quantidade')) }}</td>
                    <td></td>
                    <td class="dir">{{ $moeda($produtos->sum('faturamento')) }}</td>
                    <td class="dir">{{ $perc($produtos->sum('percentual')) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
