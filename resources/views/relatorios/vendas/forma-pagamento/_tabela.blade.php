@php
    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $perc = fn ($v) => number_format((float) $v, 2, ',', '.') . '%';
    $data = fn ($v, $formato = 'd/m/Y H:i') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    $tipoClasse = [
        'NFC-e' => 'bg-emerald-50 text-emerald-700',
        'NF-e'  => 'bg-blue-50 text-blue-700',
    ];

    $modalidadeClasse = [
        'dinheiro' => 'bg-emerald-50 text-emerald-700',
        'credito'  => 'bg-blue-50 text-blue-700',
        'debito'   => 'bg-sky-50 text-sky-700',
        'pix'      => 'bg-teal-50 text-teal-700',
        'boleto'   => 'bg-amber-50 text-amber-700',
        'outros'   => 'bg-gray-100 text-gray-600',
    ];
@endphp

{{-- Cards por modalidade --}}
<div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-4">
    @foreach ($resumo->modalidades as $m)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $m->rotulo }}</p>
            <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($m->total) }}</p>
            <p class="text-xs text-gray-400">{{ $perc($m->percentual) }} · {{ number_format($m->pagamentos, 0, ',', '.') }} {{ $m->pagamentos == 1 ? 'pagamento' : 'pagamentos' }}</p>
        </div>
    @endforeach

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
        <p class="text-lg font-semibold text-gray-800">R$ {{ $moeda($resumo->total) }}</p>
        <p class="text-xs text-gray-400">{{ number_format($resumo->documentos, 0, ',', '.') }} {{ $resumo->documentos == 1 ? 'documento' : 'documentos' }}</p>
    </div>
</div>

{{-- À vista x a prazo --}}
<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">À vista (recebido na venda)</p>
        <p class="text-lg font-semibold text-emerald-700">R$ {{ $moeda($resumo->a_vista) }}</p>
        <p class="text-xs text-gray-400">{{ $perc(100 - $resumo->percentual_a_prazo) }} do total</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">A prazo (a receber)</p>
        <p class="text-lg font-semibold text-amber-600">R$ {{ $moeda($resumo->a_prazo) }}</p>
        <p class="text-xs text-gray-400">{{ $perc($resumo->percentual_a_prazo) }} do total</p>
    </div>
</div>

@if ($filtros['visao'] === 'resumida')
{{-- Resumo por modalidade (visão padrão) --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Modalidade</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Pagamentos</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">NFC-e</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">NF-e</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">À vista</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">A prazo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">% do total</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor médio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($resumo->modalidades as $m)
                    <tr class="hover:bg-gray-200 transition {{ $m->pagamentos === 0 ? 'text-gray-400' : '' }}">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $modalidadeClasse[$m->chave] }}">{{ $m->rotulo }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format($m->pagamentos, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m->nfce) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m->nfe) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m->a_vista) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m->a_prazo) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($m->total) }}</td>
                        <td class="px-4 py-3 text-right">{{ $perc($m->percentual) }}</td>
                        <td class="px-4 py-3 text-right">{{ $moeda($m->ticket_medio) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200 text-sm font-semibold text-gray-800">
                <tr>
                    <td class="px-4 py-3">Total</td>
                    <td class="px-4 py-3 text-right">{{ number_format($resumo->pagamentos, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->nfce) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->nfe) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->a_vista) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->a_prazo) }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->total) }}</td>
                    <td class="px-4 py-3 text-right">{{ $resumo->total > 0 ? '100,00%' : '—' }}</td>
                    <td class="px-4 py-3 text-right">{{ $moeda($resumo->pagamentos > 0 ? $resumo->total / $resumo->pagamentos : 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@else
{{-- Lista de pagamentos (visão detalhada, no lugar do resumo) --}}
@if ($filtros['modalidade'] !== 'todas')
    <p class="mb-2 text-sm text-gray-500">Modalidade: <span class="font-medium text-gray-700">{{ $modalidades[$filtros['modalidade']] }}</span></p>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nº doc.</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Forma de pagamento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Modalidade</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Prazo</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($lista as $p)
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 text-gray-600">{{ $data($p->data) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoClasse[$p->tipo] ?? 'bg-gray-100 text-gray-700' }}">{{ $p->tipo }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $p->numero ?? '—' }}@if ($p->serie !== null && $p->serie !== '')<span class="text-xs font-normal text-gray-400"> / {{ $p->serie }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 max-w-xs truncate" title="{{ $p->forma }}">{{ $p->forma }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $modalidadeClasse[$p->modalidade] ?? 'bg-gray-100 text-gray-600' }}">{{ $modalidades[$p->modalidade] ?? $p->modalidade }}</span>
                        </td>
                        <td class="px-4 py-3 {{ $p->a_prazo ? 'text-amber-600 font-medium' : 'text-gray-600' }}">{{ $p->a_prazo ? 'A prazo' : 'À vista' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800">{{ $moeda($p->valor) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum pagamento encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $lista->links() }}
</div>
@endif

<p class="mt-3 text-xs text-gray-400">
    NFC-e: cada forma de pagamento da venda, com o troco descontado do dinheiro. NF-e: forma de pagamento da nota pelo valor total da nota (inclui IPI e frete),
    por isso o total pode diferir do valor líquido dos relatórios por produto. “A prazo” segue o indicador da forma de pagamento cadastrada; ainda não há vencimento nem baixa de parcelas.
    Os cards e o resumo sempre consideram todo o período; na visão detalhada, a modalidade e o nº do documento filtram só a lista.
</p>