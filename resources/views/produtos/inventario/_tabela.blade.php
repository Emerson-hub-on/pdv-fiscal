@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $situacoes = [
        'ok' => ['label' => 'OK', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500'],
        'abaixo' => ['label' => 'Abaixo do mín.', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'zerado' => ['label' => 'Zerado', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
        'negativo' => ['label' => 'Negativo', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Itens</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->itens, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Quantidade em estoque</p>
        <p class="text-lg font-semibold text-gray-800">{{ $qtd($totais->quantidade) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total a custo</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->total_custo) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total a preço de venda</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->total_venda) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. Barras</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Und</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Categoria</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Estoque</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Mínimo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Vl. Unit.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Vl. Custo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Marg. %</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total Custo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itens as $item)
                    @php
                        $situacao = $situacoes[$item->situacao];
                        $totalCusto = max((float) $item->estoque, 0) * (float) $item->custo;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->unidade }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 max-w-xs">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('produtos.edit', $item->produto_id) }}"
                                   class="truncate hover:text-blue-600 hover:underline">{{ $item->descricao }}</a>
                                @if ($item->variacao)
                                    <span class="shrink-0 text-xs font-normal text-gray-500 whitespace-nowrap">({{ $item->variacao }})</span>
                                @endif
                                @unless ($item->ativo)
                                    <span class="shrink-0 text-xs font-normal text-red-600">Inativo</span>
                                @endunless
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->categoria ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 font-medium whitespace-nowrap">{{ $qtd($item->estoque) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $item->estoque_minimo > 0 ? $qtd($item->estoque_minimo) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($item->venda) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($item->custo) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap {{ $item->margem !== null && $item->margem < 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $item->margem !== null ? number_format($item->margem, 2, ',', '.') . '%' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ $dinheiro($totalCusto) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum item encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>