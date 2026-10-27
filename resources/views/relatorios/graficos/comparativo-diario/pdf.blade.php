@php
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';
    $sinal = fn ($v) => $v === null ? '' : ((float) $v > 0 ? '+' : '');
    $cor = fn ($v) => $v === null ? '' : ((float) $v < 0 ? 'vermelho' : ((float) $v > 0 ? 'verde' : ''));
    $comparar = $filtros['comparar'] !== 'nao';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comparativo diário de vendas</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        h2 { font-size: 10px; margin: 10px 0 4px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 20%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }
        .totais .detalhe { font-size: 7px; color: #6b7280; }

        .grafico { text-align: center; margin: 4px 0 8px 0; }
        .grafico svg { width: 100%; height: auto; }
        .legenda { font-size: 7px; color: #6b7280; text-align: center; margin-bottom: 8px; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .cinza { color: #6b7280; font-weight: normal; }
        .verde { color: #047857; }
        .vermelho { color: #dc2626; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Comparativo diário de vendas</h1>
    <div class="sub">Relatórios &middot; Gráficos &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Total no período</div><div class="valor">R$ {{ $moeda($totais->valor_liquido) }}</div><div class="detalhe">{{ $totais->dias }} dia(s) ({{ $totais->dias_com_venda }} com venda) &middot; {{ number_format($totais->documentos, 0, ',', '.') }} documentos</div></td>
            <td><div class="rotulo">Média diária</div><div class="valor">R$ {{ $moeda($totais->media_diaria) }}</div></td>
            <td><div class="rotulo">Melhor dia</div><div class="valor">{{ $totais->melhor_dia ?: '—' }}</div><div class="detalhe">{{ $totais->melhor_dia ? 'R$ ' . $moeda($totais->melhor_valor) : '' }}</div></td>
            <td><div class="rotulo">Último dia vs anterior</div><div class="valor {{ $cor($totais->ultimo_variacao) }}">{{ $sinal($totais->ultimo_variacao) }}{{ $perc($totais->ultimo_variacao) }}</div><div class="detalhe">{{ $totais->ultimo_rotulo }}{{ $totais->ultimo_parcial ? ' (em andamento)' : '' }}</div></td>
            <td><div class="rotulo">Ticket médio</div><div class="valor">R$ {{ $moeda($totais->ticket_medio) }}</div></td>
        </tr>
    </table>

    <h2>Evolução diária — {{ $grafico['metrica'] }}</h2>
    <div class="grafico">
        @include('relatorios.graficos.comparativo-mensal._grafico', ['grafico' => $grafico])
    </div>
    <div class="legenda">
        Verde: {{ $grafico['metrica'] }} &middot; verde escuro: melhor dia{{ $grafico['comparar'] ? ' · cinza: ' . mb_strtolower($comparacao_titulo) : '' }} &middot; barra clara: dia em andamento
    </div>

    <table class="lista">
        <thead>
            <tr>
                <th>Dia</th>
                <th class="dir">Docs</th>
                <th class="dir">Itens</th>
                <th class="dir">Valor bruto</th>
                <th class="dir">Desc.</th>
                <th class="dir">Acrésc.</th>
                <th class="dir">Valor líquido</th>
                <th class="dir">Ticket médio</th>
                <th class="dir">Var. R$</th>
                <th class="dir">Var. %</th>
                @if ($comparar)
                    <th class="dir">{{ $comparacao_titulo }}</th>
                    <th class="dir">Var. % comp.</th>
                @endif
                <th class="dir">% do total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dias as $m)
                <tr>
                    <td>{{ $m['rotulo'] }}{{ $m['parcial'] ? ' (parcial)' : '' }}</td>
                    <td class="dir">{{ number_format($m['documentos'], 0, ',', '.') }}</td>
                    <td class="dir">{{ number_format($m['itens'], 0, ',', '.') }}</td>
                    <td class="dir">{{ $moeda($m['valor_bruto']) }}</td>
                    <td class="dir">{{ $moeda($m['desconto']) }}</td>
                    <td class="dir">{{ $moeda($m['acrescimo']) }}</td>
                    <td class="dir">{{ $moeda($m['valor_liquido']) }}</td>
                    <td class="dir">{{ $moeda($m['ticket_medio']) }}</td>
                    <td class="dir {{ $cor($m['variacao_valor']) }}">{{ $sinal($m['variacao_valor']) }}{{ $moeda($m['variacao_valor']) }}</td>
                    <td class="dir {{ $cor($m['variacao_percentual']) }}">{{ $sinal($m['variacao_percentual']) }}{{ $perc($m['variacao_percentual']) }}</td>
                    @if ($comparar)
                        <td class="dir">{{ $moeda($m['comparacao']['valor_liquido'] ?? 0) }}</td>
                        <td class="dir {{ $cor($m['variacao_comparacao_percentual'] ?? null) }}">{{ $sinal($m['variacao_comparacao_percentual'] ?? null) }}{{ $perc($m['variacao_comparacao_percentual'] ?? null) }}</td>
                    @endif
                    <td class="dir">{{ $perc($m['participacao']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="dir">{{ number_format($totais->documentos, 0, ',', '.') }}</td>
                <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                <td class="dir">{{ $moeda($totais->valor_bruto) }}</td>
                <td class="dir">{{ $moeda($totais->desconto) }}</td>
                <td class="dir">{{ $moeda($totais->acrescimo) }}</td>
                <td class="dir">{{ $moeda($totais->valor_liquido) }}</td>
                <td class="dir">{{ $moeda($totais->ticket_medio) }}</td>
                <td></td>
                <td></td>
                @if ($comparar)
                    <td class="dir">{{ $moeda($totais->comparacao ?? 0) }}</td>
                    <td class="dir">{{ $sinal($totais->variacao_comparacao) }}{{ $perc($totais->variacao_comparacao) }}</td>
                @endif
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="cinza" style="margin-top: 8px; font-size: 7px;">
        NFC-e e NF-e de saída emitidas, pela data de emissão. Valor bruto inclui os acréscimos (frete e outras despesas); valor líquido = valor bruto − descontos.
    </div>
</body>
</html>
