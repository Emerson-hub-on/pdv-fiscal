@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $situacoes = [
        'ok' => 'OK',
        'abaixo' => 'Abaixo do mín.',
        'zerado' => 'Zerado',
        'negativo' => 'Negativo',
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Inventário de Produtos</title>
    {{-- O dompdf não executa Tailwind: o PDF precisa de CSS próprio. --}}
    <style>
        @page { margin: 26px 22px 38px 22px; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }

        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { font-size: 8px; color: #6b7280; margin-bottom: 8px; }

        table.resumo { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.resumo td { width: 25%; border: 1px solid #e5e7eb; padding: 5px 8px; }
        .resumo-rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .resumo-valor { font-size: 11px; font-weight: bold; }

        table.dados { width: 100%; border-collapse: collapse; }
        table.dados thead { display: table-header-group; }
        table.dados th {
            background: #374151; color: #fffbeb; text-align: left;
            padding: 5px 4px; font-size: 7px; text-transform: uppercase;
        }
        table.dados td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.dados tr { page-break-inside: avoid; }
        table.dados tr.zebra td { background: #f9fafb; }

        .dir { text-align: right; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .forte { font-weight: bold; }
        .cinza { color: #6b7280; }
        .vermelho { color: #dc2626; }
        .ambar { color: #b45309; }
        .verde { color: #15803d; }

        table.dados tr.total td { border-top: 2px solid #374151; border-bottom: none; font-weight: bold; padding-top: 5px; }
        .vazio { text-align: center; padding: 18px; color: #6b7280; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: right; font-size: 7px; color: #9ca3af; }
        .rodape:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape"></div>

    <h1>Inventário de Produtos</h1>
    <div class="sub">
        Gerado em {{ $geradoEm->format('d/m/Y H:i') }}
        &nbsp;•&nbsp; {{ implode('  •  ', $filtrosAplicados) }}
    </div>

    <table class="resumo">
        <tr>
            <td>
                <div class="resumo-rotulo">Itens</div>
                <div class="resumo-valor">{{ number_format($totais->itens, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Quantidade em estoque</div>
                <div class="resumo-valor">{{ $qtd($totais->quantidade) }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Total a custo</div>
                <div class="resumo-valor">{{ $dinheiro($totais->total_custo) }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Total a preço de venda</div>
                <div class="resumo-valor">{{ $dinheiro($totais->total_venda) }}</div>
            </td>
        </tr>
    </table>

    <table class="dados">
        <colgroup>
            <col style="width: 5%">  {{-- Código --}}
            <col style="width: 9%">  {{-- Cód. Barras --}}
            <col style="width: 20%"> {{-- Nome --}}
            <col style="width: 3%">  {{-- Und --}}
            <col style="width: 9%">  {{-- Categoria --}}
            <col style="width: 7%">  {{-- Custo --}}
            <col style="width: 7%">  {{-- Preço --}}
            <col style="width: 6%">  {{-- Estoque --}}
            <col style="width: 5%">  {{-- Mínimo --}}
            <col style="width: 8%">  {{-- Situação --}}
            <col style="width: 5%">  {{-- Marg. % --}}
            <col style="width: 8%">  {{-- Total Custo --}}
            <col style="width: 8%">  {{-- Total Venda --}}
        </colgroup>
        <thead>
            <tr>
                <th>Código</th>
                <th>Cód. Barras</th>
                <th>Nome</th>
                <th>Und</th>
                <th>Categoria</th>
                <th class="dir">Custo</th>
                <th class="dir">Preço</th>
                <th class="dir">Estoque</th>
                <th class="dir">Mínimo</th>
                <th>Situação</th>
                <th class="dir">Marg. %</th>
                <th class="dir">Total Custo</th>
                <th class="dir">Total Venda</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($itens as $item)
                @php
                    $estoquePositivo = max((float) $item->estoque, 0);
                    $corSituacao = match ($item->situacao) {
                        'ok' => 'verde',
                        'abaixo' => 'ambar',
                        default => 'vermelho',
                    };
                @endphp
                <tr class="{{ $loop->even ? 'zebra' : '' }}">
                    <td class="mono">{{ $item->codigo_interno }}</td>
                    <td class="mono">{{ $item->codigo_barras ?: '—' }}</td>
                    <td class="forte">
                        {{ $item->descricao }}
                        @if ($item->variacao)
                            <span class="cinza">({{ $item->variacao }})</span>
                        @endif
                        @unless ($item->ativo)
                            <span class="vermelho">Inativo</span>
                        @endunless
                    </td>
                    <td>{{ $item->unidade }}</td>
                    <td>{{ $item->categoria ?: '—' }}</td>
                    <td class="dir">{{ $dinheiro($item->custo) }}</td>
                    <td class="dir">{{ $dinheiro($item->venda) }}</td>
                    <td class="dir forte">{{ $qtd($item->estoque) }}</td>
                    <td class="dir">{{ $item->estoque_minimo > 0 ? $qtd($item->estoque_minimo) : '—' }}</td>
                    <td class="forte {{ $corSituacao }}">{{ $situacoes[$item->situacao] }}</td>
                    <td class="dir {{ $item->margem !== null && $item->margem < 0 ? 'vermelho' : '' }}">
                        {{ $item->margem !== null ? number_format($item->margem, 2, ',', '.') . '%' : '—' }}
                    </td>
                    <td class="dir">{{ $dinheiro($estoquePositivo * (float) $item->custo) }}</td>
                    <td class="dir">{{ $dinheiro($estoquePositivo * (float) $item->venda) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="vazio">Nenhum item encontrado.</td>
                </tr>
            @endforelse
            @if ($itens->isNotEmpty())
                <tr class="total">
                    <td colspan="7">Totais</td>
                    <td class="dir">{{ $qtd($totais->quantidade) }}</td>
                    <td colspan="3"></td>
                    <td class="dir">{{ $dinheiro($totais->total_custo) }}</td>
                    <td class="dir">{{ $dinheiro($totais->total_venda) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
