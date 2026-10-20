@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';

    $situacoes = [
        'abaixo' => ['label' => 'Abaixo do mín.', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'zerado' => ['label' => 'Zerado', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
        'negativo' => ['label' => 'Negativo', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];

    $usaAlteracao = $filtros['tipo_data'] === 'alteracao';
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Itens abaixo do mínimo</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->itens, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Zerados ou negativos</p>
        <p class="text-lg font-semibold text-red-600">{{ number_format($totais->zerados, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Quantidade a repor</p>
        <p class="text-lg font-semibold text-gray-800">{{ $qtd($totais->total_falta) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Custo de reposição</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->custo_reposicao) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. Barras</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Und</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Mínimo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Estoque</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">A repor</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Custo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Custo de reposição</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Marca</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Categoria</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">{{ $usaAlteracao ? 'Últ. alteração' : 'Cadastro' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itens as $item)
                    @php
                        $situacao = $situacoes[$item->situacao];
                        $dataItem = $usaAlteracao ? $item->alterado_em : $item->cadastrado_em;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
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
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->unidade }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $qtd($item->estoque_minimo) }}</td>
                        <td class="px-4 py-3 text-right font-medium whitespace-nowrap {{ (float) $item->estoque <= 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $qtd($item->estoque) }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 font-medium whitespace-nowrap">{{ $qtd($item->falta) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($item->custo) }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ $dinheiro((float) $item->falta * (float) $item->custo) }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $item->marca ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $item->categoria ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($dataItem) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum item abaixo do estoque mínimo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>
