@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $situacoes = ['abaixo' => 'Abaixo do mín.', 'zerado' => 'Zerado', 'negativo' => 'Negativo'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Estoque Baixo</title>
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
        .cinza { color: #6b7280; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Estoque Baixo</h1>
    <div class="sub">Itens abaixo do estoque mínimo &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Itens abaixo do mínimo</div><div class="valor">{{ number_format($totais->itens, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Zerados ou negativos</div><div class="valor">{{ number_format($totais->zerados, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Quantidade a repor</div><div class="valor">{{ $qtd($totais->total_falta) }}</div></td>
            <td><div class="rotulo">Custo de reposição</div><div class="valor">{{ $dinheiro($totais->custo_reposicao) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cód. Barras</th>
                <th>Descrição</th>
                <th>Und</th>
                <th class="dir">Mínimo</th>
                <th class="dir">Estoque</th>
                <th class="dir">A repor</th>
                <th class="dir">Custo</th>
                <th class="dir">Custo repos.</th>
                <th>Marca</th>
                <th>Categoria</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($itens as $item)
                <tr>
                    <td class="mono">{{ $item->codigo_interno }}</td>
                    <td class="mono">{{ $item->codigo_barras ?: '—' }}</td>
                    <td>
                        {{ $item->descricao }}
                        @if ($item->variacao)<span class="cinza">({{ $item->variacao }})</span>@endif
                        @unless ($item->ativo)<span class="vermelho">Inativo</span>@endunless
                    </td>
                    <td>{{ $item->unidade }}</td>
                    <td class="dir">{{ $qtd($item->estoque_minimo) }}</td>
                    <td class="dir {{ (float) $item->estoque <= 0 ? 'vermelho' : '' }}">{{ $qtd($item->estoque) }}</td>
                    <td class="dir"><strong>{{ $qtd($item->falta) }}</strong></td>
                    <td class="dir">{{ $dinheiro($item->custo) }}</td>
                    <td class="dir">{{ $dinheiro((float) $item->falta * (float) $item->custo) }}</td>
                    <td>{{ $item->marca ?: '—' }}</td>
                    <td>{{ $item->categoria ?: '—' }}</td>
                    <td class="{{ $item->situacao !== 'abaixo' ? 'vermelho' : '' }}">{{ $situacoes[$item->situacao] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="cinza" style="text-align:center; padding: 16px;">Nenhum item abaixo do estoque mínimo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
