@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $detalhada = $filtros['visao'] === 'detalhada';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Vendas por Vendedor</title>
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
        .totais .detalhe { font-size: 7px; color: #6b7280; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista thead { display: table-header-group; }
        table.lista th { background: #374151; color: #fffbeb; font-size: 7px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { padding: 4px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
        table.lista tr.total td { background: #f3f4f6; border-top: 1px solid #d1d5db; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .dir { text-align: right !important; }
        .cinza { color: #6b7280; font-weight: normal; }
        .vermelho { color: #dc2626; }

        .rodape { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
        .rodape .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
    <div class="rodape">
        <span class="pagina"></span> &middot; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h1>Relatório de Vendas por Vendedor</h1>
    <div class="sub">Relatórios &middot; Vendas &middot; gerado em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>

    <div class="filtros">
        @foreach ($resumoFiltros as $linha)
            <span>{{ $linha }}</span>
        @endforeach
    </div>

    <table class="totais">
        <tr>
            <td><div class="rotulo">Venda (líquida)</div><div class="valor">R$ {{ $moeda($totais->valor_liquido) }}</div><div class="detalhe">Bruto R$ {{ $moeda($totais->valor_bruto) }}</div></td>
            <td><div class="rotulo">Documentos</div><div class="valor">{{ number_format($totais->documentos, 0, ',', '.') }}</div><div class="detalhe">{{ number_format($totais->itens, 0, ',', '.') }} itens</div></td>
            <td><div class="rotulo">Ticket médio</div><div class="valor">R$ {{ $moeda($totais->ticket_medio) }}</div></td>
            <td><div class="rotulo">Descontos</div><div class="valor vermelho">R$ {{ $moeda($totais->desconto) }}</div><div class="detalhe">{{ $perc($totais->desconto_percentual) }} do bruto</div></td>
            <td><div class="rotulo">Vendedores</div><div class="valor">{{ number_format($totais->vendedores, 0, ',', '.') }}</div><div class="detalhe">Maior venda: {{ $totais->melhor_vendedor ?: '—' }}</div></td>
        </tr>
    </table>

    <div class="cinza" style="margin-bottom: 6px;">
        Valor bruto inclui os acréscimos (frete e outras despesas); valor líquido = valor bruto − descontos. Usuários inativos continuam aparecendo nas vendas que fizeram.
    </div>

    @unless ($detalhada)
        <table class="lista">
            <thead>
                <tr>
                    <th>Cód.</th>
                    <th>Vendedor</th>
                    <th>Perfil</th>
                    <th class="dir">Docs</th>
                    <th class="dir">Itens</th>
                    <th class="dir">Valor bruto</th>
                    <th class="dir">Desc.</th>
                    <th class="dir">Acrésc.</th>
                    <th class="dir">Valor líquido</th>
                    <th class="dir">Ticket médio</th>
                    <th class="dir">% do total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $v)
                    @php
                        $perfil = collect([$v->is_admin ? 'Administrador' : null, $v->perfil])->filter()->implode(', ');
                        $participacao = $totais->valor_liquido > 0 ? (float) $v->valor_liquido / (float) $totais->valor_liquido * 100 : 0;
                    @endphp
                    <tr>
                        <td class="cinza">{{ $v->codigo_vendedor ?? '—' }}</td>
                        <td>{{ $v->vendedor }}{{ ($v->operador_id !== null && ! $v->vendedor_ativo) ? ' (inativo)' : '' }}</td>
                        <td>{{ $perfil !== '' ? $perfil : '—' }}</td>
                        <td class="dir">{{ number_format($v->documentos, 0, ',', '.') }}</td>
                        <td class="dir">{{ number_format($v->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($v->valor_bruto) }}</td>
                        <td class="dir">{{ $moeda($v->desconto) }}</td>
                        <td class="dir">{{ $moeda($v->acrescimo) }}</td>
                        <td class="dir">{{ $moeda($v->valor_liquido) }}</td>
                        <td class="dir">{{ $moeda($v->ticket_medio) }}</td>
                        <td class="dir">{{ $perc($participacao) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda emitida encontrada.</td>
                    </tr>
                @endforelse

                @if ($registros->count() > 0)
                    <tr class="total">
                        <td colspan="3">Total do filtro</td>
                        <td class="dir">{{ number_format($totais->documentos, 0, ',', '.') }}</td>
                        <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($totais->valor_bruto) }}</td>
                        <td class="dir">{{ $moeda($totais->desconto) }}</td>
                        <td class="dir">{{ $moeda($totais->acrescimo) }}</td>
                        <td class="dir">{{ $moeda($totais->valor_liquido) }}</td>
                        <td class="dir">{{ $moeda($totais->ticket_medio) }}</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    @else
        <table class="lista">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Nº doc.</th>
                    <th>Vendedor</th>
                    <th class="dir">Itens</th>
                    <th class="dir">Valor bruto</th>
                    <th class="dir">Desc.</th>
                    <th class="dir">Acrésc.</th>
                    <th class="dir">Valor líquido</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $d)
                    <tr>
                        <td>{{ $data($d->data) }}</td>
                        <td>{{ $d->tipo }}</td>
                        <td>{{ $d->numero ?? '—' }}{{ ($d->serie !== null && $d->serie !== '') ? '/' . $d->serie : '' }}</td>
                        <td>{{ $d->vendedor }}{{ $d->codigo_vendedor !== null ? ' (cód. ' . $d->codigo_vendedor . ')' : '' }}</td>
                        <td class="dir">{{ number_format($d->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($d->valor_bruto) }}</td>
                        <td class="dir">{{ $moeda($d->desconto) }}</td>
                        <td class="dir">{{ $moeda($d->acrescimo) }}</td>
                        <td class="dir">{{ $moeda($d->valor_liquido) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="cinza" style="text-align:center; padding: 16px;">Nenhuma venda emitida encontrada.</td>
                    </tr>
                @endforelse

                @if ($registros->count() > 0)
                    <tr class="total">
                        <td colspan="4">Total do filtro</td>
                        <td class="dir">{{ number_format($totais->itens, 0, ',', '.') }}</td>
                        <td class="dir">{{ $moeda($totais->valor_bruto) }}</td>
                        <td class="dir">{{ $moeda($totais->desconto) }}</td>
                        <td class="dir">{{ $moeda($totais->acrescimo) }}</td>
                        <td class="dir">{{ $moeda($totais->valor_liquido) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endunless
</body>
</html>
