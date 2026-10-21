@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $diasAtraso = function ($parcela) {
        if (! $parcela->vencimento) {
            return 0;
        }

        return max((int) $parcela->vencimento->diffInDays($parcela->isPaga() ? $parcela->data_pagamento : today(), false), 0);
    };

    $detalhada = $filtros['visao'] === 'detalhada';
    $rotulosSituacao = ['paga' => 'Paga', 'vencida' => 'Vencida', 'aberta' => 'Em aberto'];
    $classesSituacao = ['paga' => 'verde', 'vencida' => 'vermelho', 'aberta' => 'ambar'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Contas a Pagar</title>
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
        tr.conta td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr.conta-resumida td { border-top: 1px solid #e5e7eb; }
        tr.itens-cab td { padding: 3px 4px 1px 14px; font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        tr.item td { padding: 2px 4px 2px 14px; font-size: 7.5px; border-bottom: 1px solid #f3f4f6; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .ambar { color: #b45309; }
        .verde { color: #15803d; }
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

    <h1>Relatório de Contas a Pagar</h1>
    <div class="sub">Financeiro &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Em aberto</div><div class="valor">{{ $dinheiro($totais->aberto) }}</div></td>
            <td><div class="rotulo">Vencido</div><div class="valor vermelho">{{ $dinheiro($totais->vencido) }}</div></td>
            <td><div class="rotulo">A vencer</div><div class="valor ambar">{{ $dinheiro($totais->a_vencer) }}</div></td>
            <td><div class="rotulo">Pago</div><div class="valor verde">{{ $dinheiro($totais->pago) }}</div></td>
        </tr>
    </table>

    <table class="lista">
        <thead>
            <tr>
                <th>Documento</th>
                <th>Data</th>
                <th>Fornecedor</th>
                <th>Tipo</th>
                <th>Descrição</th>
                <th>Origem</th>
                <th>Situação</th>
                <th class="dir">Valor total</th>
                <th class="dir">Pago</th>
                <th class="dir">Em aberto</th>
                <th>Próx. venc.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($contas as $conta)
                @php
                    $sit = $conta->situacao();
                    $proxima = $conta->proximaParcela();
                    $pago = (float) $conta->parcelas->filter(fn ($p) => $p->isPaga())->sum(fn ($p) => $p->valor_pago ?? $p->valor);
                @endphp

                <tr class="{{ $detalhada ? 'conta' : 'conta-resumida' }}">
                    <td>{{ $conta->numeroExibicao() }}</td>
                    <td>{{ $data($conta->data_conta) }}</td>
                    <td>{{ $conta->fornecedor->nome_exibicao ?? '—' }}</td>
                    <td>{{ $conta->tipo->descricao ?? '—' }}</td>
                    <td>{{ $conta->descricao }}</td>
                    <td>{{ $conta->entrada_nota_id ? 'Entrada de nota' : 'Manual' }}</td>
                    <td class="{{ $classesSituacao[$sit] }}">{{ $rotulosSituacao[$sit] }}</td>
                    <td class="dir">{{ $dinheiro($conta->valor_total) }}</td>
                    <td class="dir">{{ $dinheiro($pago) }}</td>
                    <td class="dir">{{ $dinheiro($conta->saldoAberto()) }}</td>
                    <td class="{{ $proxima && $proxima->isVencida() ? 'vermelho' : '' }}">{{ $proxima ? $data($proxima->vencimento) : '—' }}</td>
                </tr>

                @if ($detalhada)
                    @if ($conta->parcelas->isEmpty())
                        <tr class="item"><td colspan="11" class="cinza">Conta sem parcelas.</td></tr>
                    @else
                        <tr class="itens-cab">
                            <td>Parcela</td>
                            <td>Vencimento</td>
                            <td class="dir" colspan="2">Valor</td>
                            <td>Situação</td>
                            <td>Pago em</td>
                            <td class="dir">Valor pago</td>
                            <td colspan="2">Forma de pagamento</td>
                            <td class="dir">Atraso</td>
                            <td></td>
                        </tr>
                        @foreach ($conta->parcelas->sortBy('numero') as $parcela)
                            @php $atraso = $diasAtraso($parcela); @endphp
                            <tr class="item">
                                <td>{{ $parcela->numero }}/{{ $conta->parcelas->count() }}</td>
                                <td class="{{ $parcela->isVencida() ? 'vermelho' : '' }}">{{ $data($parcela->vencimento) }}</td>
                                <td class="dir" colspan="2">{{ $dinheiro($parcela->valor) }}</td>
                                <td class="{{ $parcela->isPaga() ? 'verde' : ($parcela->isVencida() ? 'vermelho' : 'ambar') }}">
                                    {{ $parcela->isPaga() ? 'Paga' : ($parcela->isVencida() ? 'Vencida' : 'A vencer') }}
                                </td>
                                <td>{{ $parcela->isPaga() ? $data($parcela->data_pagamento) : '—' }}</td>
                                <td class="dir">{{ $parcela->isPaga() ? $dinheiro($parcela->valor_pago ?? $parcela->valor) : '—' }}</td>
                                <td colspan="2">{{ $parcela->isPaga() ? ($parcela->formaPagamento->descricao ?? '—') : '—' }}</td>
                                <td class="dir {{ $atraso > 0 ? 'vermelho' : 'cinza' }}">{{ $atraso > 0 ? $atraso . ($atraso === 1 ? ' dia' : ' dias') : '—' }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @endif
            @empty
                <tr>
                    <td colspan="11" class="cinza" style="text-align:center; padding: 16px;">Nenhuma conta encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
