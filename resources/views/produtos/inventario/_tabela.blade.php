@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

    $situacoes = [
        'ok' => ['label' => 'OK', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500'],
        'abaixo' => ['label' => 'Abaixo do mín.', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'zerado' => ['label' => 'Zerado', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
        'negativo' => ['label' => 'Negativo', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];

    // Em que largura de tela cada coluna aparece. Sempre visíveis: Código, Descrição, Estoque e Situação.
    //   md (768px)    -> Und, Vl. Unit.
    //   lg (1024px)   -> Cód. Barras, Mínimo
    //   xl (1280px)   -> Vl. Custo, Marg. %
    //   2xl (1536px)  -> Categoria, Total Custo
    //   1900px        -> Marca, NCM, Total Venda
    $col = [
        'und' => 'hidden md:table-cell',
        'unit' => 'hidden md:table-cell',
        'barras' => 'hidden lg:table-cell',
        'minimo' => 'hidden lg:table-cell',
        'custo' => 'hidden xl:table-cell',
        'margem' => 'hidden xl:table-cell',
        'categoria' => 'hidden 2xl:table-cell',
        'total_custo' => 'hidden 2xl:table-cell',
        'marca' => 'hidden min-[1900px]:table-cell',
        'ncm' => 'hidden min-[1900px]:table-cell',
        'total_venda' => 'hidden min-[1900px]:table-cell',
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
                    <th class="px-4 py-3 text-left font-medium text-amber-50 {{ $col['barras'] }}">Cód. Barras</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50 {{ $col['und'] }}">Und</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50 {{ $col['marca'] }}">Marca</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50 {{ $col['categoria'] }}">Categoria</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50 {{ $col['ncm'] }}">NCM</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Estoque</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['minimo'] }}">Mínimo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['unit'] }}">Vl. Unit.</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['custo'] }}">Vl. Custo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['margem'] }}">Marg. %</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['total_custo'] }}">Total Custo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50 {{ $col['total_venda'] }}">Total Venda</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itens as $item)
                    @php
                        $situacao = $situacoes[$item->situacao];
                        $estoquePositivo = max((float) $item->estoque, 0);
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs {{ $col['barras'] }}">{{ $item->codigo_barras ?: '—' }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 {{ $col['und'] }}">{{ $item->unidade }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 max-w-xs xl:max-w-sm 2xl:max-w-md">
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
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap {{ $col['marca'] }}">{{ $item->marca ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap {{ $col['categoria'] }}">{{ $item->categoria ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs {{ $col['ncm'] }}">{{ $item->ncm ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 font-medium whitespace-nowrap">{{ $qtd($item->estoque) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap {{ $col['minimo'] }}">{{ $item->estoque_minimo > 0 ? $qtd($item->estoque_minimo) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap {{ $col['unit'] }}">{{ $dinheiro($item->venda) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap {{ $col['custo'] }}">{{ $dinheiro($item->custo) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap {{ $col['margem'] }} {{ $item->margem !== null && $item->margem < 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $item->margem !== null ? number_format($item->margem, 2, ',', '.') . '%' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap {{ $col['total_custo'] }}">{{ $dinheiro($estoquePositivo * (float) $item->custo) }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap {{ $col['total_venda'] }}">{{ $dinheiro($estoquePositivo * (float) $item->venda) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum item encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>