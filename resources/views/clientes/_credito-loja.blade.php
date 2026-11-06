@php
    // Aba "Crédito em loja" do cadastro do cliente: saldo disponível e de onde veio cada crédito (trocas)
    $creditosCliente = \App\Models\CreditoLoja::with([
            'notaFiscal.trocaVenda', 'notaFiscal.trocaNotaFiscal', 'movimentos.notaFiscal',
        ])
        ->where('cliente_id', $cliente->id)
        ->orderByDesc('id')
        ->get();

    // Vendas do caixa (NFC-e) em que o crédito foi usado, para mostrar o número do cupom
    $vendasUso = \App\Models\Venda::whereIn('uuid', $creditosCliente->flatMap->movimentos->pluck('venda_uuid')->filter()->unique())
        ->get(['id', 'uuid', 'numero_nfce', 'serie_nfce'])
        ->keyBy('uuid');

    $saldoDisponivel = (float) $creditosCliente->where('status', 'ativo')->sum('saldo');
    $totalGerado = (float) $creditosCliente->where('status', '!=', 'cancelado')->sum('valor');
    $totalUsado = (float) $creditosCliente->flatMap->movimentos->where('tipo', 'uso')->sum('valor')
                - (float) $creditosCliente->flatMap->movimentos->where('tipo', 'estorno')->sum('valor');

    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $rotulosStatus = [
        'ativo'     => ['Com saldo', 'bg-green-100 text-green-800'],
        'esgotado'  => ['Usado', 'bg-slate-200 text-slate-700'],
        'cancelado' => ['Cancelado', 'bg-red-100 text-red-700'],
    ];
    // Itens da venda original, marcando o que foi devolvido NESTA troca (pela referência nº do item da NF-e de entrada)
    $servicoTroca = app(\App\Services\MontagemNfeTrocaService::class);
    $itensDaVenda = function (?\App\Models\NotaFiscal $entrada) use ($servicoTroca) {
        if (!$entrada || !($entrada->troca_venda_id || $entrada->troca_nota_fiscal_id)) {
            return collect();
        }

        $doc = \App\Services\MontagemNfeTrocaService::carregar(
            $entrada->troca_nota_fiscal_id ? 'nfe' : 'nfce',
            (int) ($entrada->troca_nota_fiscal_id ?: $entrada->troca_venda_id)
        );
        if (!$doc) {
            return collect();
        }

        $devolvidoNaTroca = $entrada->itens()->get(['ref_nitem', 'produto_id', 'produto_variante_id', 'quantidade', 'valor_total'])
            ->groupBy(fn ($i) => $i->ref_nitem . '|' . (int) $i->produto_id . '|' . (int) $i->produto_variante_id)
            ->map(fn ($g) => ['quantidade' => (float) $g->sum('quantidade'), 'valor' => (float) $g->sum('valor_total')]);

        return $servicoTroca->itensComSaldo($doc)->map(function ($l) use ($devolvidoNaTroca) {
            $d = $devolvidoNaTroca[$l['n_item'] . '|' . (int) $l['produto_id'] . '|' . (int) $l['produto_variante_id']] ?? null;
            $l['devolvido_troca'] = $d['quantidade'] ?? 0;
            $l['valor_devolvido'] = $d['valor'] ?? 0;
            $l['valor_vendido'] = round($l['quantidade'] * $l['valor_unitario'] - $l['desconto'], 2);
            return $l;
        });
    };

    $vendaOriginal = function (?\App\Models\NotaFiscal $entrada) {
        if ($entrada?->trocaVenda) {
            return ['rotulo' => "NFC-e nº {$entrada->trocaVenda->numero_nfce} / série {$entrada->trocaVenda->serie_nfce}", 'data' => $entrada->trocaVenda->created_at, 'url' => null];
        }
        if ($entrada?->trocaNotaFiscal) {
            return ['rotulo' => "NF-e nº {$entrada->trocaNotaFiscal->numero} / série {$entrada->trocaNotaFiscal->serie}", 'data' => $entrada->trocaNotaFiscal->emitida_em, 'url' => route('notasfiscais.show', $entrada->trocaNotaFiscal)];
        }
        return null;
    };
@endphp

<div class="flex flex-col gap-5">
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-[#10284A] px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Disponível para compras</p>
            <p class="text-2xl font-bold text-amber-50 mt-1">{{ $moeda($saldoDisponivel) }}</p>
            <p class="text-xs text-slate-300 mt-0.5">Pode ser usado no caixa ("Crédito em loja") ou na NF-e de venda.</p>
        </div>
        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total gerado em trocas</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ $moeda($totalGerado) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Já usado</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ $moeda(max(0, $totalUsado)) }}</p>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-slate-800 mb-2">Vendas devolvidas que geraram crédito</h3>
        <div class="rounded-xl ring-1 ring-slate-200 overflow-hidden">
            <table class="w-full text-sm text-slate-700">
                <thead class="bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-3 py-2.5">Vale</th>
                        <th class="text-left px-3 py-2.5">Venda devolvida</th>
                        <th class="text-left px-3 py-2.5">Troca (entrada)</th>
                        <th class="text-right px-3 py-2.5">Crédito</th>
                        <th class="text-right px-3 py-2.5">Saldo</th>
                        <th class="text-left px-3 py-2.5">Situação</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($creditosCliente as $credito)
                        @php
                            [$rotulo, $cor] = $rotulosStatus[$credito->status] ?? [$credito->status, 'bg-slate-100 text-slate-700'];
                            $original = $vendaOriginal($credito->notaFiscal);
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2.5 font-mono font-semibold">{{ $credito->codigo }}</td>
                            <td class="px-3 py-2.5">
                                @if ($original)
                                    @if ($original['url'])
                                        <a href="{{ $original['url'] }}" class="text-[#10284A] hover:underline">{{ $original['rotulo'] }}</a>
                                    @else
                                        {{ $original['rotulo'] }}
                                    @endif
                                    <span class="block text-xs text-slate-400">venda de {{ $original['data']?->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($credito->notaFiscal)
                                    <a href="{{ route('notasfiscais.show', $credito->notaFiscal) }}" class="text-[#10284A] hover:underline">NF-e nº {{ $credito->notaFiscal->numero }}</a>
                                @endif
                                <span class="block text-xs text-slate-400">{{ $credito->created_at->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right">{{ $moeda($credito->valor) }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-[#10284A]">{{ $moeda($credito->saldo) }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <button type="button" onclick="document.getElementById('cred-mov-{{ $credito->id }}').classList.toggle('hidden')"
                                        class="text-xs text-[#10284A] hover:underline">Histórico</button>
                            </td>
                        </tr>
                        <tr id="cred-mov-{{ $credito->id }}" class="hidden bg-slate-50">
                            <td colspan="7" class="px-5 py-3">
                                @php $itensVenda = $itensDaVenda($credito->notaFiscal); @endphp
                                @if ($itensVenda->isNotEmpty())
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                                        Itens da venda {{ $original['rotulo'] ?? '' }}
                                    </p>
                                    <div class="rounded-lg ring-1 ring-slate-200 overflow-hidden bg-white mb-4">
                                        <table class="w-full text-xs">
                                            <thead class="bg-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                                                <tr>
                                                    <th class="text-left px-3 py-1.5 w-12">Item</th>
                                                    <th class="text-left px-3 py-1.5">Descrição</th>
                                                    <th class="text-right px-3 py-1.5">Vendido</th>
                                                    <th class="text-right px-3 py-1.5">Valor</th>
                                                    <th class="text-right px-3 py-1.5">Devolvido nesta troca</th>
                                                    <th class="text-right px-3 py-1.5">Valor devolvido</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($itensVenda as $iv)
                                                    @php $foiDevolvido = $iv['devolvido_troca'] > 0; @endphp
                                                    <tr class="{{ $foiDevolvido ? 'bg-amber-50' : '' }}">
                                                        <td class="px-3 py-1.5 font-mono text-slate-500">{{ $iv['n_item'] }}</td>
                                                        <td class="px-3 py-1.5">
                                                            {{ $iv['descricao'] }}
                                                            @if ($foiDevolvido)
                                                                <span class="ml-1 rounded-full bg-amber-200 text-amber-900 px-1.5 py-0.5 text-[10px] font-semibold">
                                                                    {{ $iv['devolvido_troca'] + 0.0005 >= $iv['quantidade'] ? 'Devolvido' : 'Devolvido em parte' }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-1.5 text-right">{{ $qtd($iv['quantidade']) }} {{ $iv['produto']?->unidade_comercial }}</td>
                                                        <td class="px-3 py-1.5 text-right">{{ $moeda($iv['valor_vendido']) }}</td>
                                                        <td class="px-3 py-1.5 text-right font-semibold {{ $foiDevolvido ? 'text-amber-800' : 'text-slate-300' }}">
                                                            {{ $foiDevolvido ? $qtd($iv['devolvido_troca']) : '—' }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-right {{ $foiDevolvido ? 'font-semibold text-amber-800' : 'text-slate-300' }}">
                                                            {{ $foiDevolvido ? $moeda($iv['valor_devolvido']) : '—' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Movimentação do crédito</p>
                                <table class="w-full text-xs">
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach ($credito->movimentos as $mov)
                                            @php $vendaCaixa = $mov->venda_uuid ? ($vendasUso[$mov->venda_uuid] ?? null) : null; @endphp
                                            <tr>
                                                <td class="py-1.5 w-32 text-slate-500">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="py-1.5 w-28 font-medium">{{ \App\Models\CreditoLojaMovimento::ROTULOS[$mov->tipo] ?? $mov->tipo }}</td>
                                                <td class="py-1.5">
                                                    @if ($mov->tipo !== 'credito' && $mov->notaFiscal)
                                                        <a href="{{ route('notasfiscais.show', $mov->notaFiscal) }}" class="text-[#10284A] hover:underline">NF-e nº {{ $mov->notaFiscal->numero }}</a>
                                                    @elseif ($vendaCaixa)
                                                        Caixa · NFC-e nº {{ $vendaCaixa->numero_nfce }} / série {{ $vendaCaixa->serie_nfce }}
                                                    @else
                                                        {{ $mov->descricao }}
                                                    @endif
                                                </td>
                                                <td class="py-1.5 w-28 text-right font-semibold {{ in_array($mov->tipo, ['uso', 'cancelamento'], true) ? 'text-red-700' : 'text-green-700' }}">
                                                    {{ in_array($mov->tipo, ['uso', 'cancelamento'], true) ? '−' : '+' }} {{ $moeda($mov->valor) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-400">
                                Este cliente ainda não tem crédito em loja. O crédito é gerado quando uma troca dele é registrada
                                (Faturamento → Montagem NF-e → NF-e de Troca) e a NF-e de entrada é autorizada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
