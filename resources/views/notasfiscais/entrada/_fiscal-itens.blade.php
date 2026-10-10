@php
    $normal = \App\Models\Empresa::atual()->crt == 3;
    $rotuloCst = $normal ? 'CST' : 'CSOSN';
@endphp

@if ($entrada->exists && $entrada->itens->isNotEmpty())
<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Classificação fiscal dos itens</h3>
            <p class="text-xs text-gray-500">
                {{ $entrada->operacao?->descricao ?? 'Operação não definida' }} · entrada em {{ $rotuloCst }}
            </p>
        </div>
        @if ($entrada->isRascunho())
            <p class="text-xs text-gray-400">Atualiza ao salvar o rascunho</p>
        @endif
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="text-left px-6 py-2">Produto</th>
                <th class="text-left px-4 py-2">CFOP (origem → entrada)</th>
                <th class="text-left px-4 py-2">{{ $rotuloCst }} (origem → entrada)</th>
                <th class="text-left px-4 py-2">Crédito de ICMS</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($entrada->itens as $item)
                @php
                    $origem = $item->origem_mercadoria;
                    $tipoOrigem = $item->csosn_origem ? 'CSOSN' : 'CST';
                    $cstOrigem = $item->csosn_origem ?: $item->cst_origem;
                    $semDadosXml = ! $cstOrigem;

                    // no regime normal o CST é exibido com a origem na frente (ex.: 000)
                    $cstOrigemExib = ($tipoOrigem === 'CST' && $cstOrigem !== null && $origem !== null) ? $origem . $cstOrigem : $cstOrigem;
                    $cstEntrada = $item->cst_csosn_entrada;
                    $cstEntradaExib = ($normal && $cstEntrada !== null && $origem !== null) ? $origem . $cstEntrada : $cstEntrada;
                @endphp
                <tr>
                    <td class="px-6 py-3 text-gray-800">{{ $item->produto?->nome ?? $item->descricao }}</td>

                    <td class="px-4 py-3">
                        <span class="text-xs text-gray-400 font-mono">{{ $item->cfop_origem ?? '—' }}</span>
                        <span class="text-gray-300">→</span>
                        @if ($item->cfopEntrada)
                            <span class="inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono font-semibold"
                                  title="{{ $item->cfopEntrada->descricao }}">{{ $item->cfopEntrada->codigo }}</span>
                        @else
                            <span class="inline-block px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-medium">Pendente</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        @if ($semDadosXml)
                            <span class="text-xs text-gray-400">Sem dados do XML</span>
                        @else
                            <span class="text-xs text-gray-400 font-mono">{{ $cstOrigemExib }}</span>
                            <span class="text-gray-300">→</span>
                            @if ($cstEntrada)
                                <span class="inline-block px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-mono font-semibold">{{ $cstEntradaExib }}</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-medium">Pendente</span>
                            @endif
                        @endif
                    </td>

                    <td class="px-4 py-3 text-gray-600">
                        {{ $item->gera_credito === null ? '—' : ($item->gera_credito ? 'Sim' : 'Não') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif