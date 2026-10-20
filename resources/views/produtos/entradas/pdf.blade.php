@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';

    $unitario = function ($v) {
        $s = number_format((float) $v, 4, ',', '.');

        return 'R$ ' . (str_ends_with($s, '00') ? substr($s, 0, -2) : $s);
    };

    $status = [
        'finalizada' => ['label' => 'Finalizada', 'cor' => 'verde'],
        'rascunho' => ['label' => 'Rascunho', 'cor' => 'ambar'],
        'cancelada' => ['label' => 'Cancelada', 'cor' => 'vermelho'],
    ];

    $porProduto = $filtros['visao'] === 'produto';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Entrada de Produtos</title>
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
        table.dados tr.total td { border-top: 2px solid #374151; border-bottom: none; font-weight: bold; padding-top: 5px; }

        .dir { text-align: right; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .forte { font-weight: bold; }
        .cinza { color: #6b7280; }
        .vermelho { color: #dc2626; }
        .ambar { color: #b45309; }
        .verde { color: #15803d; }
        .vazio { text-align: center; padding: 18px; color: #6b7280; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: right; font-size: 7px; color: #9ca3af; }
        .rodape:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape"></div>

    <h1>Entrada de Produtos</h1>
    <div class="sub">
        Gerado em {{ now()->format('d/m/Y H:i') }}
        &nbsp;•&nbsp; {{ implode('  •  ', $resumoFiltros) }}
    </div>

    <table class="resumo">
        <tr>
            <td>
                <div class="resumo-rotulo">Notas de entrada</div>
                <div class="resumo-valor">{{ number_format($totais->notas, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Produtos com entrada</div>
                <div class="resumo-valor">{{ number_format($totais->produtos, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Quantidade recebida</div>
                <div class="resumo-valor">{{ $qtd($totais->quantidade) }}</div>
            </td>
            <td>
                <div class="resumo-rotulo">Valor movimentado</div>
                <div class="resumo-valor">{{ $dinheiro($totais->valor) }}</div>
            </td>
        </tr>
    </table>

    <table class="dados">
        @if ($porProduto)
            <colgroup>
                <col style="width: 6%">  {{-- Código --}}
                <col style="width: 10%"> {{-- Cód. Barras --}}
                <col style="width: 20%"> {{-- Nome --}}
                <col style="width: 4%">  {{-- Und --}}
                <col style="width: 5%">  {{-- Notas --}}
                <col style="width: 6%">  {{-- Fornecedores --}}
                <col style="width: 8%">  {{-- Qtd recebida --}}
                <col style="width: 10%"> {{-- Valor movimentado --}}
                <col style="width: 8%">  {{-- Custo médio --}}
                <col style="width: 7%">  {{-- Estoque atual --}}
                <col style="width: 8%">  {{-- Primeira entrada --}}
                <col style="width: 8%">  {{-- Última entrada --}}
            </colgroup>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cód. Barras</th>
                    <th>Nome</th>
                    <th>Und</th>
                    <th class="dir">Notas</th>
                    <th class="dir">Fornec.</th>
                    <th class="dir">Qtd recebida</th>
                    <th class="dir">Valor movimentado</th>
                    <th class="dir">Custo médio un.</th>
                    <th class="dir">Estoque atual</th>
                    <th>1ª entrada</th>
                    <th>Última entrada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($itens as $item)
                    <tr class="{{ $loop->even ? 'zebra' : '' }}">
                        <td class="mono">{{ $item->codigo_interno ?: '—' }}</td>
                        <td class="mono">{{ $item->codigo_barras ?: '—' }}</td>
                        <td class="forte">
                            {{ $item->descricao }}
                            @if ($item->variacao)
                                <span class="cinza">({{ $item->variacao }})</span>
                            @endif
                        </td>
                        <td>{{ $item->unidade }}</td>
                        <td class="dir">{{ number_format($item->notas, 0, ',', '.') }}</td>
                        <td class="dir">{{ number_format($item->fornecedores, 0, ',', '.') }}</td>
                        <td class="dir forte">{{ $qtd($item->qtd_recebida) }}</td>
                        <td class="dir">{{ $dinheiro($item->valor_movimentado) }}</td>
                        <td class="dir">{{ $item->custo_medio !== null ? $unitario($item->custo_medio) : '—' }}</td>
                        <td class="dir {{ (float) $item->estoque_atual <= 0 ? 'vermelho' : '' }}">{{ $qtd($item->estoque_atual) }}</td>
                        <td>{{ $data($item->primeira_entrada) }}</td>
                        <td>{{ $data($item->ultima_entrada) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="vazio">Nenhum produto com nota de entrada para os filtros selecionados.</td>
                    </tr>
                @endforelse
                @if ($itens->isNotEmpty())
                    <tr class="total">
                        <td colspan="6">Totais</td>
                        <td class="dir">{{ $qtd($totais->quantidade) }}</td>
                        <td class="dir">{{ $dinheiro($totais->valor) }}</td>
                        <td colspan="4"></td>
                    </tr>
                @endif
            </tbody>
        @else
            <colgroup>
                <col style="width: 5%">  {{-- Código --}}
                <col style="width: 21%"> {{-- Nome --}}
                <col style="width: 6%">  {{-- Nota --}}
                <col style="width: 15%"> {{-- Fornecedor --}}
                <col style="width: 7%">  {{-- Entrada --}}
                <col style="width: 3%">  {{-- Und --}}
                <col style="width: 5%">  {{-- Qtd nota --}}
                <col style="width: 4%">  {{-- Qtd/cx --}}
                <col style="width: 6%">  {{-- Qtd recebida --}}
                <col style="width: 7%">  {{-- Vl. unit. --}}
                <col style="width: 6%">  {{-- Desconto --}}
                <col style="width: 8%">  {{-- Total --}}
                <col style="width: 7%">  {{-- Status --}}
            </colgroup>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Nota</th>
                    <th>Fornecedor</th>
                    <th>Entrada</th>
                    <th>Und</th>
                    <th class="dir">Qtd nota</th>
                    <th class="dir">Qtd/cx</th>
                    <th class="dir">Qtd receb.</th>
                    <th class="dir">Vl. unit.</th>
                    <th class="dir">Desconto</th>
                    <th class="dir">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($itens as $item)
                    @php $situacao = $status[$item->status] ?? ['label' => $item->status, 'cor' => 'cinza']; @endphp
                    <tr class="{{ $loop->even ? 'zebra' : '' }}">
                        <td class="mono">{{ $item->codigo_interno ?: '—' }}</td>
                        <td class="forte">
                            {{ $item->descricao }}
                            @if ($item->variacao)
                                <span class="cinza">({{ $item->variacao }})</span>
                            @endif
                            @unless ($item->produto_id)
                                <span class="ambar">Sem vínculo</span>
                            @endunless
                        </td>
                        <td>
                            {{ $item->numero }}@if ($item->serie !== null && $item->serie !== '')<span class="cinza"> / {{ $item->serie }}</span>@endif
                        </td>
                        <td>{{ $item->fornecedor ?: '—' }}</td>
                        <td>{{ $data($item->data_entrada) }}</td>
                        <td>{{ $item->unidade ?: '—' }}</td>
                        <td class="dir">{{ $qtd($item->quantidade) }}</td>
                        <td class="dir">{{ $item->qtd_por_caixa }}</td>
                        <td class="dir forte">{{ $qtd($item->quantidade_estoque) }}</td>
                        <td class="dir">{{ $unitario($item->valor_unitario) }}</td>
                        <td class="dir">{{ $dinheiro($item->valor_desconto) }}</td>
                        <td class="dir">{{ $dinheiro($item->valor_total) }}</td>
                        <td class="forte {{ $situacao['cor'] }}">{{ $situacao['label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="vazio">Nenhuma entrada de nota encontrada para os filtros selecionados.</td>
                    </tr>
                @endforelse
                @if ($itens->isNotEmpty())
                    <tr class="total">
                        <td colspan="8">Totais</td>
                        <td class="dir">{{ $qtd($totais->quantidade) }}</td>
                        <td></td>
                        <td class="dir">{{ $dinheiro($totais->desconto) }}</td>
                        <td class="dir">{{ $dinheiro($totais->valor) }}</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        @endif
    </table>
</body>
</html>
