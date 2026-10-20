@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $tipos = \App\Models\MovimentacaoEstoque::TIPOS;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Saída de Produtos</title>
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
        table.lista td { padding: 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.lista tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .vermelho { color: #dc2626; font-weight: bold; }
        .verde { color: #15803d; font-weight: bold; }
        .cinza { color: #6b7280; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Saída de Produtos</h1>
    <div class="sub">Histórico de movimentações de estoque &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Movimentações</div><div class="valor">{{ number_format($totais->movimentacoes, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Quantidade que saiu</div><div class="valor">{{ $qtd($totais->qtd_saidas) }}</div></td>
            <td><div class="rotulo">Custo das saídas</div><div class="valor">{{ $dinheiro($totais->custo_saidas) }}</div></td>
            <td><div class="rotulo">Quantidade que entrou</div><div class="valor">{{ $qtd($totais->qtd_entradas) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Data/hora</th>
                <th>Tipo</th>
                <th>Código</th>
                <th>Descrição</th>
                <th>Und</th>
                <th class="dir">Quantidade</th>
                <th class="dir">Saldo</th>
                <th class="dir">Custo total</th>
                <th>Documento</th>
                <th>Operador</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($itens as $item)
                @php
                    $saida = (float) $item->quantidade < 0;
                    $custoTotal = $item->custo_unitario !== null ? abs((float) $item->quantidade) * (float) $item->custo_unitario : null;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $tipos[$item->tipo] ?? $item->tipo }}</td>
                    <td class="mono">{{ $item->codigo_interno }}</td>
                    <td>
                        {{ $item->descricao }}
                        @if ($item->variacao)<span class="cinza">({{ $item->variacao }})</span>@endif
                    </td>
                    <td>{{ $item->unidade }}</td>
                    <td class="dir {{ $saida ? 'vermelho' : 'verde' }}">{{ $saida ? '−' : '+' }}{{ $qtd(abs((float) $item->quantidade)) }}</td>
                    <td class="dir">{{ $qtd($item->saldo_anterior) }} &rarr; {{ $qtd($item->saldo_posterior) }}</td>
                    <td class="dir">{{ $custoTotal !== null ? $dinheiro($custoTotal) : '—' }}</td>
                    <td>{{ $item->documento_rotulo ?: '—' }}</td>
                    <td>{{ $item->operador ?: '—' }}</td>
                    <td class="cinza">{{ $item->observacao ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="cinza" style="text-align:center; padding: 16px;">Nenhuma movimentação encontrada no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
