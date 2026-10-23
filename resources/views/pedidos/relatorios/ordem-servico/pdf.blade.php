@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    $detalhada = $filtros['visao'] === 'detalhada';
    $classesStatus = [
        'aberta' => 'cinza', 'em_analise' => 'azul', 'aguardando_aprovacao' => 'ambar', 'aguardando_peca' => 'ambar',
        'em_andamento' => 'azul', 'concluida' => 'verde', 'entregue' => 'verde', 'cancelada' => 'vermelho',
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Ordens de Serviço</title>
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
        tr.os td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.os-resumida td { border-top: 1px solid #e5e7eb; }
        tr.texto td { padding: 2px 4px 2px 14px; font-size: 7.5px; color: #374151; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
        .ambar { color: #b45309; }
        .azul { color: #1d4ed8; }
        .verde { color: #15803d; }
        .vermelho { color: #dc2626; }
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

    <h1>Relatório de Ordens de Serviço</h1>
    <div class="sub">Pedidos &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Em andamento</div><div class="valor azul">{{ number_format($totais->em_andamento, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Atrasadas</div><div class="valor vermelho">{{ number_format($totais->atrasadas, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Concluídas e entregues</div><div class="valor verde">{{ number_format($totais->concluidas, 0, ',', '.') }}</div></td>
            <td><div class="rotulo">Valor das OS (sem as {{ number_format($totais->canceladas, 0, ',', '.') }} canceladas)</div><div class="valor">{{ $dinheiro($totais->valor_ordens) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>OS</th>
                <th>Abertura</th>
                <th>Previsão</th>
                <th>Cliente</th>
                <th>Equipamento</th>
                <th>Técnico</th>
                <th>Prior.</th>
                <th>Status</th>
                <th class="dir">Peças</th>
                <th class="dir">Mão de obra</th>
                <th class="dir">Desconto</th>
                <th class="dir">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ordens as $os)
                @php
                    $cancelada = $os->status === 'cancelada';
                    $atrasada = $os->atrasada();
                    $diasAtraso = $atrasada ? (int) $os->previsao_entrega->diffInDays(today()) : 0;
                    $maquina = trim(($os->marca ?? '') . ' ' . ($os->modelo ?? ''));
                @endphp

                <tr class="{{ $detalhada ? 'os' : 'os-resumida' }}">
                    <td>{{ $os->numeroExibicao() }}</td>
                    <td>{{ $data($os->data_abertura) }}</td>
                    <td class="{{ $atrasada ? 'vermelho' : '' }}">
                        {{ $data($os->previsao_entrega) }}
                        @if ($atrasada)<br><span class="cinza">{{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }} de atraso</span>@endif
                    </td>
                    <td>{{ $os->nomeCliente() ?? '—' }}@if ($os->clienteAvulso()) <span class="cinza">(avulso)</span>@endif</td>
                    <td>{{ $os->equipamento ?: '—' }}@if ($maquina !== '') <span class="cinza">{{ $maquina }}</span>@endif</td>
                    <td>{{ $os->tecnico->name ?? '—' }}</td>
                    <td class="{{ $os->prioridade === 'urgente' ? 'vermelho' : '' }}">{{ $os->prioridade === 'urgente' ? 'Urgente' : 'Normal' }}</td>
                    <td class="{{ $classesStatus[$os->status] ?? '' }}">{{ $os->statusTexto() }}</td>
                    <td class="dir">{{ $dinheiro($os->valorPecas()) }}</td>
                    <td class="dir">{{ $dinheiro($os->valor_servico) }}</td>
                    <td class="dir">{{ $dinheiro($os->desconto) }}</td>
                    <td class="dir {{ $cancelada ? 'vermelho risco' : '' }}">{{ $dinheiro($os->valor_total) }}</td>
                </tr>

                @if ($detalhada)
                    @foreach ([
                        'Defeito relatado' => $os->defeito_relatado,
                        'Laudo técnico' => $os->laudo_tecnico,
                        'Serviço executado' => $os->servico_executado,
                    ] as $rotulo => $texto)
                        @if (trim((string) $texto) !== '')
                            <tr class="texto"><td colspan="12"><strong>{{ $rotulo }}:</strong> {{ $texto }}</td></tr>
                        @endif
                    @endforeach

                    @if ($os->itens->isEmpty())
                        <tr class="item"><td colspan="12" class="cinza">Nenhuma peça lançada nesta OS.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td>#</td>
                            <td>Cód. barras</td>
                            <td colspan="4">Peça</td>
                            <td>Und</td>
                            <td class="dir">Quantidade</td>
                            <td class="dir">Valor unit.</td>
                            <td></td>
                            <td class="dir" colspan="2">Total</td>
                        </tr>
                        @foreach ($os->itens as $indice => $item)
                            <tr class="item {{ $cancelada ? 'cinza' : '' }}">
                                <td>{{ $indice + 1 }}</td>
                                <td class="mono">{{ $item->codigo_barras ?: '—' }}</td>
                                <td colspan="4">{{ $item->descricao }}</td>
                                <td>{{ $item->unidade }}</td>
                                <td class="dir">{{ $qtd($item->quantidade) }}</td>
                                <td class="dir">{{ $dinheiro($item->valor_unitario) }}</td>
                                <td></td>
                                <td class="dir" colspan="2">{{ $dinheiro($item->total()) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="12" class="cinza" style="text-align:center; padding: 16px;">Nenhuma ordem de serviço encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
