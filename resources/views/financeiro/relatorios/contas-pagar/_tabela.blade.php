@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    // Em aberto e vencida: dias desde o vencimento. Paga: dias que atrasou até pagar (0 = em dia)
    $diasAtraso = function ($parcela) {
        if (! $parcela->vencimento) {
            return 0;
        }

        return max((int) $parcela->vencimento->diffInDays($parcela->isPaga() ? $parcela->data_pagamento : today(), false), 0);
    };

    $situacoesConta = [
        'paga'    => ['label' => 'Paga', 'classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500'],
        'vencida' => ['label' => 'Vencida', 'classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500'],
        'aberta'  => ['label' => 'Em aberto', 'classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500'],
    ];

    $colunas = 13;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Em aberto</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->aberto) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Vencido</p>
        <p class="text-lg font-semibold text-red-600">{{ $dinheiro($totais->vencido) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">A vencer</p>
        <p class="text-lg font-semibold text-amber-600">{{ $dinheiro($totais->a_vencer) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Pago</p>
        <p class="text-lg font-semibold text-green-700">{{ $dinheiro($totais->pago) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Documento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Fornecedor</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Origem</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor total</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Pago</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Em aberto</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Próx. vencimento</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Parcelas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($contas as $conta)
                    @php
                        $situacao = $situacoesConta[$conta->situacao()];
                        $proxima = $conta->proximaParcela();
                        $pago = (float) $conta->parcelas->filter(fn ($p) => $p->isPaga())->sum(fn ($p) => $p->valor_pago ?? $p->valor);
                        $saldo = $conta->saldoAberto();
                        $fornecedor = $conta->fornecedor->nome_exibicao ?? null;
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <a href="{{ route('contas-pagar.edit', $conta) }}" class="hover:text-blue-600 hover:underline">{{ $conta->numeroExibicao() }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($conta->data_conta) }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $fornecedor }}">{{ $fornecedor ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $conta->fornecedor->documento_formatado ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $conta->tipo->descricao ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $conta->descricao }}">{{ $conta->descricao }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $conta->entrada_nota_id ? 'Entrada de nota' : 'Manual' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $situacao['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $situacao['ponto'] }}"></span>
                                {{ $situacao['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800 whitespace-nowrap">{{ $dinheiro($conta->valor_total) }}</td>
                        <td class="px-4 py-3 text-right text-green-700 whitespace-nowrap">{{ $dinheiro($pago) }}</td>
                        <td class="px-4 py-3 text-right font-medium whitespace-nowrap {{ $saldo > 0 ? 'text-gray-800' : 'text-gray-400' }}">{{ $dinheiro($saldo) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap {{ $proxima && $proxima->isVencida() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $proxima ? $data($proxima->vencimento) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $conta->id }}"
                                    data-titulo="{{ $conta->numeroExibicao() }} — {{ $conta->descricao }}"
                                    data-subtitulo="{{ $fornecedor ?? '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver parcelas
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $conta->parcelas->count() }}</span>
                            </button>

                            {{-- Conteúdo do modal desta conta: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-nota-{{ $conta->id }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Fornecedor</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $fornecedor }}">{{ $fornecedor ?: '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $conta->fornecedor->documento_formatado ?? '' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Conta</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $conta->tipo->descricao ?? '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ $data($conta->data_conta) }} · {{ $conta->entrada_nota_id ? 'Entrada de nota' : 'Manual' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium {{ $conta->situacao() === 'paga' ? 'text-green-700' : ($conta->situacao() === 'vencida' ? 'text-red-600' : 'text-amber-600') }}">{{ $situacao['label'] }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Em aberto</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ $dinheiro($saldo) }}</p>
                                        <p class="text-xs text-gray-500">de {{ $dinheiro($conta->valor_total) }}</p>
                                    </div>
                                </div>

                                @if ($conta->observacao)
                                    <p class="mb-4 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-left">
                                        Observação: {{ $conta->observacao }}
                                    </p>
                                @endif

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Parcela</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Vencimento</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Situação</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Pago em</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor pago</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Forma de pagamento</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Atraso</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($conta->parcelas->sortBy('numero') as $parcela)
                                                    @php $atraso = $diasAtraso($parcela); @endphp
                                                    <tr class="text-gray-700">
                                                        <td class="px-3 py-2.5 text-gray-500">{{ $parcela->numero }}/{{ $conta->parcelas->count() }}</td>
                                                        <td class="px-3 py-2.5 {{ $parcela->isVencida() ? 'text-red-600 font-medium' : '' }}">{{ $data($parcela->vencimento) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $dinheiro($parcela->valor) }}</td>
                                                        <td class="px-3 py-2.5">
                                                            @if ($parcela->isPaga())
                                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">Paga</span>
                                                            @elseif ($parcela->isVencida())
                                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Vencida</span>
                                                            @else
                                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">A vencer</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2.5">{{ $parcela->isPaga() ? $data($parcela->data_pagamento) : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $parcela->isPaga() ? $dinheiro($parcela->valor_pago ?? $parcela->valor) : '—' }}</td>
                                                        <td class="px-3 py-2.5">{{ $parcela->isPaga() ? ($parcela->formaPagamento->descricao ?? '—') : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right {{ $atraso > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $atraso > 0 ? $atraso . ' ' . ($atraso === 1 ? 'dia' : 'dias') : '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">Conta sem parcelas.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Valor total: <strong class="text-gray-800">{{ $dinheiro($conta->valor_total) }}</strong></span>
                                    <span>Pago: <strong class="text-green-700">{{ $dinheiro($pago) }}</strong></span>
                                    <span>Em aberto: <strong class="text-gray-800">{{ $dinheiro($saldo) }}</strong></span>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma conta encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $contas->links() }}
</div>
