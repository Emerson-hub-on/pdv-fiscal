@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $unitario = fn ($v) => 'R$ ' . number_format((float) $v, abs(round((float) $v, 2) - (float) $v) < 0.00001 ? 2 : 4, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $documento = function ($v) {
        $d = preg_replace('/\D/', '', (string) $v);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => $v ?: '—',
        };
    };

    $detalhada = $filtros['visao'] === 'detalhada';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Notas de Saída</title>
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
        tr.nota td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.nota-resumida td { border-top: 1px solid #e5e7eb; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .vermelho { color: #dc2626; }
        .verde { color: #15803d; }
        .cinza { color: #6b7280; font-weight: normal; }
        .risco { text-decoration: line-through; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Notas de Saída</h1>
    <div class="sub">Faturamento &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Notas emitidas</div><div class="valor">{{ number_format($totais->emitidas, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor emitido</div><div class="valor">{{ $dinheiro($totais->valor_emitido) }}</div></td>
            <td><div class="rotulo">Notas canceladas</div><div class="valor vermelho">{{ number_format($totais->canceladas, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor cancelado</div><div class="valor vermelho">{{ $dinheiro($totais->valor_cancelado) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Nota</th>
                <th>Emissão</th>
                <th>Cliente</th>
                <th>CPF/CNPJ</th>
                <th>CFOP</th>
                <th>Natureza</th>
                <th>Status</th>
                <th class="dir">Produtos</th>
                <th class="dir">Desconto</th>
                <th class="dir">Frete</th>
                <th class="dir">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notas as $nota)
                @php $cancelada = $nota->status === 'cancelada'; @endphp

                <tr class="{{ $detalhada ? 'nota' : 'nota-resumida' }}">
                    <td>{{ $nota->numero }} <span class="cinza">/ série {{ $nota->serie }}</span>@if ($nota->tipo_operacao === 'entrada') <span class="cinza">(entrada)</span>@endif</td>
                    <td>{{ $data($nota->emitida_em, 'd/m/Y H:i') }}</td>
                    <td>{{ $nota->cliente->nome ?? '—' }}</td>
                    <td class="mono">{{ $documento($nota->cliente->cpf_cnpj ?? null) }}</td>
                    <td class="mono">{{ $nota->cfopSaida->codigo ?? '—' }}</td>
                    <td>{{ $nota->natureza_operacao ?: '—' }}</td>
                    <td class="{{ $cancelada ? 'vermelho' : 'verde' }}">
                        {{ $cancelada ? 'Cancelada' : 'Emitida' }}
                        @if ($cancelada && $nota->cancelado_em)<br><span class="cinza">{{ $data($nota->cancelado_em, 'd/m/Y H:i') }}</span>@endif
                    </td>
                    <td class="dir">{{ $dinheiro($nota->valor_produtos) }}</td>
                    <td class="dir">{{ $dinheiro($nota->valor_desconto) }}</td>
                    <td class="dir">{{ $dinheiro($nota->valor_frete) }}</td>
                    <td class="dir {{ $cancelada ? 'vermelho risco' : '' }}">{{ $dinheiro($nota->valor_total) }}</td>
                </tr>

                @if ($detalhada)
                    @if ($nota->itens->isEmpty())
                        <tr class="item"><td colspan="11" class="cinza">Nota sem itens.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td>Código</td>
                            <td colspan="3">Descrição</td>
                            <td>NCM</td>
                            <td>Und</td>
                            <td class="dir">Quantidade</td>
                            <td class="dir">Valor unit.</td>
                            <td class="dir">Desconto</td>
                            <td></td>
                            <td class="dir">Total</td>
                        </tr>
                        @foreach ($nota->itens as $item)
                            <tr class="item {{ $cancelada ? 'cinza' : '' }}">
                                <td class="mono">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                <td colspan="3">
                                    {{ $item->descricao ?: ($item->produto->nome ?? '—') }}
                                    @if ($item->variante)<span class="cinza">({{ $item->variante->rotulo() }})</span>@endif
                                </td>
                                <td class="mono">{{ $item->ncm->codigo ?? '—' }}</td>
                                <td>{{ $item->produto->unidade_comercial ?? '' }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                                <td class="dir">{{ $unitario($item->valor_unitario) }}</td>
                                <td class="dir">{{ $dinheiro($item->valor_desconto) }}</td>
                                <td></td>
                                <td class="dir">{{ $dinheiro($item->valor_total) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="11" class="cinza" style="text-align:center; padding: 16px;">Nenhuma nota encontrada no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
