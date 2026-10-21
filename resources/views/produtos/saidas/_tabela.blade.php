@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $tipos = \App\Models\MovimentacaoEstoque::TIPOS;

    // Cor do selo por tipo
    $classesTipo = [
        'nfe_saida'        => 'bg-blue-50 text-blue-700',
        'nfe_entrada'      => 'bg-green-50 text-green-700',
        'nfe_cancelamento' => 'bg-gray-100 text-gray-700',
        'venda'            => 'bg-indigo-50 text-indigo-700',
        'venda_cancelada'  => 'bg-gray-100 text-gray-700',
        'ajuste_manual'    => 'bg-amber-50 text-amber-700',
        'estoque_inicial'  => 'bg-green-50 text-green-700',
        'entrada_compra'   => 'bg-green-50 text-green-700',
        'transferencia'    => 'bg-purple-50 text-purple-700',
        'entrada_estornada' => 'bg-gray-100 text-gray-700',
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Movimentações</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->movimentacoes, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Quantidade que saiu</p>
        <p class="text-lg font-semibold text-red-600">{{ $qtd($totais->qtd_saidas) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Custo das saídas</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->custo_saidas) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Quantidade que entrou</p>
        <p class="text-lg font-semibold text-green-700">{{ $qtd($totais->qtd_entradas) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data/hora</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Und</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Quantidade</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Saldo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Custo total</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Documento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Operador</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Observação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itens as $item)
                    @php
                        $saida = (float) $item->quantidade < 0;
                        $custoTotal = $item->custo_unitario !== null ? abs((float) $item->quantidade) * (float) $item->custo_unitario : null;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $classesTipo[$item->tipo] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $tipos[$item->tipo] ?? $item->tipo }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 max-w-xs xl:max-w-sm 2xl:max-w-md">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('produtos.edit', $item->produto_id) }}"
                                   class="truncate hover:text-blue-600 hover:underline">{{ $item->descricao }}</a>
                                @if ($item->variacao)
                                    <span class="shrink-0 text-xs font-normal text-gray-500 whitespace-nowrap">({{ $item->variacao }})</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->unidade }}</td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $saida ? 'text-red-600' : 'text-green-700' }}">
                            {{ $saida ? '−' : '+' }}{{ $qtd(abs((float) $item->quantidade)) }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">
                            {{ $qtd($item->saldo_anterior) }} → <span class="font-medium text-gray-800">{{ $qtd($item->saldo_posterior) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ $custoTotal !== null ? $dinheiro($custoTotal) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            @if ($item->documento_tipo === 'nota_fiscal' && $item->documento_id)
                                <a href="{{ route('notasfiscais.show', $item->documento_id) }}"
                                   class="hover:text-blue-600 hover:underline">{{ $item->documento_rotulo ?: 'Nota fiscal' }}</a>
                            @else
                                {{ $item->documento_rotulo ?: '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $item->operador ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate" title="{{ $item->observacao }}">{{ $item->observacao ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma movimentação encontrada no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>