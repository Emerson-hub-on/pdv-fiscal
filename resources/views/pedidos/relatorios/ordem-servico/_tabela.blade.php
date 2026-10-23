@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';

    $classesStatus = [
        'aberta'               => ['classe' => 'bg-slate-100 text-slate-700', 'ponto' => 'bg-slate-500', 'texto' => 'text-slate-700'],
        'em_analise'           => ['classe' => 'bg-indigo-50 text-indigo-700', 'ponto' => 'bg-indigo-500', 'texto' => 'text-indigo-700'],
        'aguardando_aprovacao' => ['classe' => 'bg-amber-50 text-amber-700', 'ponto' => 'bg-amber-500', 'texto' => 'text-amber-700'],
        'aguardando_peca'      => ['classe' => 'bg-orange-50 text-orange-700', 'ponto' => 'bg-orange-500', 'texto' => 'text-orange-700'],
        'em_andamento'         => ['classe' => 'bg-blue-50 text-blue-700', 'ponto' => 'bg-blue-500', 'texto' => 'text-blue-700'],
        'concluida'            => ['classe' => 'bg-green-50 text-green-700', 'ponto' => 'bg-green-500', 'texto' => 'text-green-700'],
        'entregue'             => ['classe' => 'bg-emerald-50 text-emerald-700', 'ponto' => 'bg-emerald-500', 'texto' => 'text-emerald-700'],
        'cancelada'            => ['classe' => 'bg-red-50 text-red-600', 'ponto' => 'bg-red-500', 'texto' => 'text-red-600'],
    ];
    $padraoStatus = ['classe' => 'bg-gray-100 text-gray-700', 'ponto' => 'bg-gray-400', 'texto' => 'text-gray-700'];

    $colunas = 13;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Em andamento</p>
        <p class="text-lg font-semibold text-blue-700">{{ number_format($totais->em_andamento, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Atrasadas</p>
        <p class="text-lg font-semibold text-red-600">{{ number_format($totais->atrasadas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Concluídas e entregues</p>
        <p class="text-lg font-semibold text-green-700">{{ number_format($totais->concluidas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor das OS</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_ordens) }}</p>
        <p class="text-xs text-gray-400">sem as canceladas ({{ number_format($totais->canceladas, 0, ',', '.') }})</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">OS</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Abertura</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Previsão</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Cliente</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Equipamento</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Técnico</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Prioridade</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Peças</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Mão de obra</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Desconto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Detalhes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($ordens as $os)
                    @php
                        $cancelada = $os->status === 'cancelada';
                        $cores = $classesStatus[$os->status] ?? $padraoStatus;
                        $atrasada = $os->atrasada();
                        $diasAtraso = $atrasada ? (int) $os->previsao_entrega->diffInDays(today()) : 0;
                        $cliente = $os->nomeCliente();
                        $maquina = trim(($os->marca ?? '') . ' ' . ($os->modelo ?? ''));
                        $pecas = $os->valorPecas();
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $os->numeroExibicao() }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($os->data_abertura) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap {{ $atrasada ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $data($os->previsao_entrega) }}
                            @if ($atrasada)
                                <span class="text-xs font-normal">({{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }} de atraso)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $cliente }}">
                            {{ $cliente ?: '—' }}
                            @if ($os->clienteAvulso())
                                <span class="text-xs text-gray-400">(avulso)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $os->equipamento }} {{ $maquina }}">
                            {{ $os->equipamento ?: '—' }}
                            @if ($maquina !== '')
                                <span class="text-xs text-gray-500">{{ $maquina }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $os->tecnico->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($os->prioridade === 'urgente')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Urgente</span>
                            @else
                                <span class="text-gray-500">Normal</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $cores['classe'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $cores['ponto'] }}"></span>
                                {{ $os->statusTexto() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($pecas) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($os->valor_servico) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($os->desconto) }}</td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $cancelada ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($os->valor_total) }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $os->id }}"
                                    data-titulo="Ordem de serviço {{ $os->numeroExibicao() }}"
                                    data-subtitulo="{{ $cliente ?? '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver detalhes
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none" title="Peças">{{ $os->itens->count() }}</span>
                            </button>

                            {{-- Conteúdo do modal desta OS: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-nota-{{ $os->id }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Cliente</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $cliente }}">{{ $cliente ?: '—' }}{{ $os->clienteAvulso() ? ' (avulso)' : '' }}</p>
                                        <p class="text-xs text-gray-500">{{ $os->telefoneCliente() ?: '' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $os->documentoCliente() ?: '' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Equipamento</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $os->equipamento }}">{{ $os->equipamento ?: '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ $maquina }}</p>
                                        @if ($os->numero_serie)
                                            <p class="text-xs text-gray-500 font-mono">Série: {{ $os->numero_serie }}</p>
                                        @endif
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium {{ $cores['texto'] }}">{{ $os->statusTexto() }}</p>
                                        <p class="text-xs text-gray-500">Técnico: {{ $os->tecnico->name ?? '—' }}</p>
                                        <p class="text-xs {{ $os->prioridade === 'urgente' ? 'text-red-600 font-medium' : 'text-gray-500' }}">Prioridade: {{ $os->prioridade === 'urgente' ? 'Urgente' : 'Normal' }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Datas</p>
                                        <p class="text-xs text-gray-600">Abertura: {{ $data($os->data_abertura) }}</p>
                                        <p class="text-xs {{ $atrasada ? 'text-red-600 font-medium' : 'text-gray-600' }}">Previsão: {{ $data($os->previsao_entrega) }}{{ $atrasada ? ' (' . $diasAtraso . ($diasAtraso === 1 ? ' dia' : ' dias') . ' de atraso)' : '' }}</p>
                                        <p class="text-xs text-gray-600">Concluída: {{ $data($os->concluida_em, 'd/m/Y H:i') }}</p>
                                        <p class="text-xs text-gray-600">Entregue: {{ $data($os->entregue_em, 'd/m/Y H:i') }}</p>
                                    </div>
                                </div>

                                @php
                                    $textos = array_filter([
                                        'Defeito relatado'  => $os->defeito_relatado,
                                        'Condição de entrada' => $os->condicao_entrada,
                                        'Acessórios'        => $os->acessorios,
                                        'Laudo técnico'     => $os->laudo_tecnico,
                                        'Serviço executado' => $os->servico_executado,
                                        'Observações'       => $os->observacoes,
                                    ], fn ($v) => trim((string) $v) !== '');
                                @endphp

                                @if ($textos || $os->garantia_dias)
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 text-left">
                                        @foreach ($textos as $rotulo => $texto)
                                            <div class="border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500 mb-0.5">{{ $rotulo }}</p>
                                                <p class="text-sm text-gray-700 whitespace-pre-line break-words">{{ $texto }}</p>
                                            </div>
                                        @endforeach
                                        @if ($os->garantia_dias)
                                            <div class="border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500 mb-0.5">Garantia</p>
                                                <p class="text-sm text-gray-700">{{ $os->garantia_dias }} {{ (int) $os->garantia_dias === 1 ? 'dia' : 'dias' }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1.5 text-left">Peças utilizadas</p>
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Cód. barras</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Und</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Quantidade</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor unit.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($os->itens as $indice => $item)
                                                    <tr class="{{ $cancelada ? 'text-gray-400' : 'text-gray-700' }}">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">{{ $item->descricao }}</td>
                                                        <td class="px-3 py-2.5">{{ $item->unidade }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $dinheiro($item->valor_unitario) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $dinheiro($item->total()) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Nenhuma peça lançada nesta OS.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Peças: <strong class="text-gray-800">{{ $dinheiro($pecas) }}</strong></span>
                                    <span>Mão de obra: <strong class="text-gray-800">{{ $dinheiro($os->valor_servico) }}</strong></span>
                                    <span>Desconto: <strong class="text-gray-800">− {{ $dinheiro($os->desconto) }}</strong></span>
                                    <span>Total: <strong class="{{ $cancelada ? 'text-red-600' : 'text-gray-800' }}">{{ $dinheiro($os->valor_total) }}</strong></span>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma ordem de serviço encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $ordens->links() }}
</div>
