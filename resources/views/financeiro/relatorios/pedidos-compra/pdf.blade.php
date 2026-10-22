@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $atraso = function ($pedido) {
        if (! in_array($pedido->status, ['aberto', 'enviado'], true) || ! $pedido->previsao_entrega) {
            return 0;
        }

        return max((int) $pedido->previsao_entrega->diffInDays(today(), false), 0);
    };

    $detalhada = $filtros['visao'] === 'detalhada';
    $rotulosStatus = ['aberto' => 'Aberto', 'enviado' => 'Enviado', 'cancelado' => 'Cancelado'];
    $classesStatus = ['aberto' => 'ambar', 'enviado' => 'azul', 'cancelado' => 'vermelho'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Pedidos de Compra</title>
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
        table.lista td { padding: 4px; vertical-align: top; }
        tr.pedido td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.pedido-resumido td { border-top: 1px solid #e5e7eb; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .ambar { color: #b45309; }
        .azul { color: #1d4ed8; }
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

    <h1>Relatório de Pedidos de Compra</h1>
    <div class="sub">Financeiro &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Abertos</div><div class="valor ambar">{{ number_format($totais->abertos, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Enviados</div><div class="valor azul">{{ number_format($totais->enviados, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Atrasados</div><div class="valor vermelho">{{ number_format($totais->atrasados, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Cancelados</div><div class="valor">{{ number_format($totais->cancelados, 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Data</th>
                <th>Previsão de entrega</th>
                <th colspan="2">Fornecedor</th>
                <th>CPF/CNPJ</th>
                <th>Status</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                @php $diasAtraso = $atraso($pedido); @endphp

                <tr class="{{ $detalhada ? 'pedido' : 'pedido-resumido' }}">
                    <td>{{ $pedido->numeroExibicao() }}</td>
                    <td>{{ $data($pedido->data_pedido) }}</td>
                    <td class="{{ $diasAtraso > 0 ? 'vermelho' : '' }}">
                        {{ $data($pedido->previsao_entrega) }}
                        @if ($diasAtraso > 0)<span class="cinza">({{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }} de atraso)</span>@endif
                    </td>
                    <td colspan="2">{{ $pedido->fornecedor->nome_exibicao ?? '—' }}</td>
                    <td class="mono">{{ $pedido->fornecedor->documento_formatado ?? '—' }}</td>
                    <td class="{{ $classesStatus[$pedido->status] ?? '' }}">{{ $rotulosStatus[$pedido->status] ?? ucfirst($pedido->status) }}</td>
                    <td class="cinza">{{ $pedido->observacao ?: '—' }}</td>
                </tr>

                @if ($detalhada)
                    @if ($pedido->itens->isEmpty())
                        <tr class="item"><td colspan="8" class="cinza">Pedido sem itens.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td>Código</td>
                            <td>Cód. barras</td>
                            <td colspan="3">Descrição</td>
                            <td>Und</td>
                            <td class="dir">Quantidade</td>
                            <td class="dir">Estoque atual</td>
                        </tr>
                        @foreach ($pedido->itens as $item)
                            @php $estoqueAtual = $item->variante?->estoque ?? $item->produto?->estoque; @endphp
                            <tr class="item">
                                <td class="mono">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                <td class="mono">{{ $item->codigo_barras ?: '—' }}</td>
                                <td colspan="3">{{ $item->descricao }}</td>
                                <td>{{ $item->unidade }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                                <td class="dir">{{ $estoqueAtual !== null ? $qtd($estoqueAtual) : '—' }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="8" class="cinza" style="text-align:center; padding: 16px;">Nenhum pedido encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
