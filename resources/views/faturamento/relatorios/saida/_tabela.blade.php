@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    // Valor unitário pode ter até 4 casas: mostra 4 só quando precisa
    $unitario = fn ($v) => 'R$ ' . number_format((float) $v, abs(round((float) $v, 2) - (float) $v) < 0.00001 ? 2 : 4, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $documento = function ($v) {
        $d = preg_replace('/\D/', '', (string) $v);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => $v ?: '—',
        };
    };

    $detalhada = $filtros['visao'] === 'detalhada';
    $colunas = 11;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Notas emitidas</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->emitidas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor emitido</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_emitido) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Notas canceladas</p>
        <p class="text-lg font-semibold text-red-600">{{ number_format($totais->canceladas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor cancelado</p>
        <p class="text-lg font-semibold text-red-600">{{ $dinheiro($totais->valor_cancelado) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nota</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Emissão</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CFOP</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Natureza</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Produtos</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Desconto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Frete</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                </tr>
            </thead>

            @forelse ($notas as $nota)
                @php $cancelada = $nota->status === 'cancelada'; @endphp

                <tbody class="border-t border-gray-200">
                    <tr class="bg-gray-50 hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <a href="{{ route('notasfiscais.show', $nota) }}" class="hover:text-blue-600 hover:underline">
                                {{ $nota->numero }}<span class="text-xs font-normal text-gray-500"> / série {{ $nota->serie }}</span>
                            </a>
                            @if ($nota->tipo_operacao === 'entrada')
                                <span class="ml-1 text-xs font-normal text-purple-600">Entrada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($nota->emitida_em, 'd/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate">{{ $nota->cliente->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $documento($nota->cliente->cpf_cnpj ?? null) }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $nota->cfopSaida->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $nota->natureza_operacao }}">{{ $nota->natureza_operacao ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($cancelada)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-red-50 text-red-600"
                                      title="Cancelada em {{ $data($nota->cancelado_em, 'd/m/Y H:i') }}{{ $nota->motivo_cancelamento ? ' — ' . $nota->motivo_cancelamento : '' }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Cancelada
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-green-50 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Emitida
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($nota->valor_produtos) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($nota->valor_desconto) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($nota->valor_frete) }}</td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $cancelada ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($nota->valor_total) }}</td>
                    </tr>

                    @if ($detalhada)
                        <tr>
                            <td colspan="{{ $colunas }}" class="px-4 pb-4 pt-2">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-gray-500 uppercase tracking-wide">
                                            <th class="py-1.5 pr-3 text-left font-medium">Código</th>
                                            <th class="py-1.5 pr-3 text-left font-medium">Descrição</th>
                                            <th class="py-1.5 pr-3 text-left font-medium">NCM</th>
                                            <th class="py-1.5 pr-3 text-left font-medium">Und</th>
                                            <th class="py-1.5 pr-3 text-right font-medium">Quantidade</th>
                                            <th class="py-1.5 pr-3 text-right font-medium">Valor unit.</th>
                                            <th class="py-1.5 pr-3 text-right font-medium">Desconto</th>
                                            <th class="py-1.5 text-right font-medium">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse ($nota->itens as $item)
                                            <tr class="{{ $cancelada ? 'text-gray-400' : 'text-gray-700' }}">
                                                <td class="py-1.5 pr-3 font-mono">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                                <td class="py-1.5 pr-3">
                                                    {{ $item->descricao ?: ($item->produto->nome ?? '—') }}
                                                    @if ($item->variante)
                                                        <span class="text-gray-500">({{ $item->variante->rotulo() }})</span>
                                                    @endif
                                                </td>
                                                <td class="py-1.5 pr-3 font-mono">{{ $item->ncm->codigo ?? '—' }}</td>
                                                <td class="py-1.5 pr-3">{{ $item->produto->unidade_comercial ?? '' }}</td>
                                                <td class="py-1.5 pr-3 text-right">{{ $qtd($item->quantidade) }}</td>
                                                <td class="py-1.5 pr-3 text-right">{{ $unitario($item->valor_unitario) }}</td>
                                                <td class="py-1.5 pr-3 text-right">{{ $dinheiro($item->valor_desconto) }}</td>
                                                <td class="py-1.5 text-right font-medium">{{ $dinheiro($item->valor_total) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="py-2 text-gray-400">Nota sem itens.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma nota encontrada no período.</td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $notas->links() }}
</div>
