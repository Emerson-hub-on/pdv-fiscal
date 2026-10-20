@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';

    // Valor unitário de NF-e pode ter 4 casas: só mostra além dos centavos quando houver.
    $unitario = function ($v) {
        $s = number_format((float) $v, 4, ',', '.');

        return 'R$ ' . (str_ends_with($s, '00') ? substr($s, 0, -2) : $s);
    };

    $status = [
        'finalizada' => ['label' => 'Finalizada', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500'],
        'rascunho' => ['label' => 'Rascunho', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
        'cancelada' => ['label' => 'Cancelada', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
    ];

    $porProduto = $filtros['visao'] === 'produto';
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Notas de entrada</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->notas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Produtos com entrada</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->produtos, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Quantidade recebida</p>
        <p class="text-lg font-semibold text-gray-800">{{ $qtd($totais->quantidade) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor movimentado</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            @if ($porProduto)
                {{-- ===== Visão consolidada: uma linha por produto/variação ===== --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. Barras</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Und</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Notas</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Fornecedores</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd recebida</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor movimentado</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Custo médio un.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Estoque atual</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Primeira entrada</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Última entrada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($itens as $item)
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
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
                            <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ number_format($item->notas, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ number_format($item->fornecedores, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 font-medium whitespace-nowrap">{{ $qtd($item->qtd_recebida) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ $dinheiro($item->valor_movimentado) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $item->custo_medio !== null ? $unitario($item->custo_medio) : '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap {{ (float) $item->estoque_atual <= 0 ? 'text-red-600' : 'text-gray-600' }}">{{ $qtd($item->estoque_atual) }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($item->primeira_entrada) }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($item->ultima_entrada) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum produto com nota de entrada para os filtros selecionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            @else
                {{-- ===== Visão detalhada: uma linha por item de nota ===== --}}
                <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. Barras</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Und</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Nota</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Fornecedor</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Entrada</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Emissão</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd nota</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd/cx</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd recebida</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Vl. unit.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Desconto</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Custo un.</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Operação</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($itens as $item)
                        @php $situacao = $status[$item->status] ?? ['label' => $item->status, 'classe' => 'bg-gray-100 text-gray-600', 'ponto' => 'bg-gray-400']; @endphp
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_interno ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 max-w-xs xl:max-w-sm 2xl:max-w-md">
                                <div class="flex items-center gap-1">
                                    @if ($item->produto_id)
                                        <a href="{{ route('produtos.edit', $item->produto_id) }}"
                                           class="truncate hover:text-blue-600 hover:underline">{{ $item->descricao }}</a>
                                    @else
                                        <span class="truncate" title="{{ $item->descricao }}">{{ $item->descricao }}</span>
                                        <span class="shrink-0 text-xs font-normal text-amber-600">Sem vínculo</span>
                                    @endif
                                    @if ($item->variacao)
                                        <span class="shrink-0 text-xs font-normal text-gray-500 whitespace-nowrap">({{ $item->variacao }})</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $item->unidade ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-800 whitespace-nowrap" title="Chave: {{ $item->chave_acesso ?: 'não informada' }}">
                                {{ $item->numero }}@if ($item->serie !== null && $item->serie !== '')<span class="text-gray-400"> / {{ $item->serie }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-xs">
                                <div class="truncate" title="{{ $item->fornecedor }}">{{ $item->fornecedor ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($item->data_entrada) }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($item->data_emissao) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $qtd($item->quantidade) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $item->qtd_por_caixa }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 font-medium whitespace-nowrap">{{ $qtd($item->quantidade_estoque) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $unitario($item->valor_unitario) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($item->valor_desconto) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 whitespace-nowrap">{{ $dinheiro($item->valor_total) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $item->custo_unitario !== null ? $unitario($item->custo_unitario) : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $item->operacao ?: '—' }}
                                @unless ($item->movimenta_estoque)
                                    <span class="text-xs text-amber-600">não movimenta estoque</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                    {{ $situacao['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma entrada de nota encontrada para os filtros selecionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            @endif
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $itens->links() }}
</div>
