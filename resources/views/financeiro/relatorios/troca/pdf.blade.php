@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $detalhada = $filtros['visao'] === 'detalhada';
    $rotulosSituacao = ['emitida' => 'Autorizada', 'rascunho' => 'Rascunho', 'cancelada' => 'Cancelada'];
    $classesSituacao = ['emitida' => 'verde', 'rascunho' => 'ambar', 'cancelada' => 'vermelho'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Trocas</title>
    <style>
        @page { margin: 28px 24px 40px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .sub { color: #6b7280; margin-bottom: 8px; }
        .filtros { margin-bottom: 8px; color: #374151; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px; padding: 2px 6px; margin: 0 4px 3px 0; }

        .totais { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 8px -6px; }
        .totais td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; width: 20%; }
        .totais .rotulo { font-size: 7px; text-transform: uppercase; color: #6b7280; }
        .totais .valor { font-size: 11px; font-weight: bold; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #10284A; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; }
        tr.conta td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.conta-resumida td { border-top: 1px solid #e5e7eb; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .ambar { color: #b45309; }
        .verde { color: #15803d; }
        .vermelho { color: #dc2626; }
        .azul { color: #10284A; }
        .cinza { color: #6b7280; font-weight: normal; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Trocas</h1>
    <div class="sub">Financeiro &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Trocas</div><div class="valor">{{ number_format($totais->quantidade, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor devolvido</div><div class="valor">{{ $dinheiro($totais->valor) }}</div></td>
            <td><div class="rotulo">Crédito gerado</div><div class="valor azul">{{ $dinheiro($totais->credito_gerado) }}</div></td>
            <td><div class="rotulo">Crédito usado</div><div class="valor verde">{{ $dinheiro($totais->credito_usado) }}</div></td>
            <td><div class="rotulo">Crédito em aberto</div><div class="valor ambar">{{ $dinheiro($totais->credito_aberto) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Data</th>
                <th>NF-e de entrada</th>
                <th>Situação</th>
                <th>Venda original</th>
                <th>Cliente</th>
                <th>CFOP</th>
                <th class="dir">Valor devolvido</th>
                <th>Vale-troca</th>
                <th class="dir">Saldo do crédito</th>
                <th>Saída do item novo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($trocas as $troca)
                @php
                    $o = $troca->rel_original;
                    $credito = $troca->rel_credito;
                @endphp

                <tr class="{{ $detalhada ? 'conta' : 'conta-resumida' }}">
                    <td>{{ $data($troca->emitida_em ?? $troca->created_at, 'd/m/Y H:i') }}</td>
                    <td>{{ $troca->numero ? "NF-e nº {$troca->numero}" : "Rascunho #{$troca->id}" }}</td>
                    <td class="{{ $classesSituacao[$troca->status] ?? '' }}">{{ $rotulosSituacao[$troca->status] ?? $troca->status }}</td>
                    <td>{{ $o['tipo'] }} nº {{ $o['numero'] }} / {{ $o['serie'] }} <span class="cinza">{{ $data($o['data']) }}</span></td>
                    <td>{{ $troca->cliente->nome ?? '—' }}</td>
                    <td>{{ $troca->cfopSaida->codigo ?? '—' }}</td>
                    <td class="dir">{{ $dinheiro($troca->valor_total) }}</td>
                    <td>{{ $credito->codigo ?? '—' }}</td>
                    <td class="dir">{{ $credito ? $dinheiro($credito->saldo) : '—' }}</td>
                    <td>{{ $troca->rel_saidas->implode(', ') ?: '—' }}</td>
                </tr>

                @if ($detalhada)
                    @if ($troca->itens->isEmpty())
                        <tr class="item"><td colspan="10" class="cinza">Troca sem itens.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td>Item da venda</td>
                            <td colspan="4">Produto devolvido</td>
                            <td class="dir">Qtd.</td>
                            <td class="dir">Valor unit.</td>
                            <td class="dir">Valor</td>
                            <td colspan="2"></td>
                        </tr>
                        @foreach ($troca->itens as $item)
                            <tr class="item">
                                <td>{{ $item->ref_nitem ?: '—' }}</td>
                                <td colspan="4">{{ $item->descricao ?: ($item->produto->nome ?? '—') }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }} {{ $item->produto->unidade_comercial ?? '' }}</td>
                                <td class="dir">{{ $dinheiro($item->valor_unitario) }}</td>
                                <td class="dir">{{ $dinheiro($item->valor_total) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="10" class="cinza" style="text-align:center; padding: 16px;">Nenhuma troca encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
