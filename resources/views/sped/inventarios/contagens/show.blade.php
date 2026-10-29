@extends('layouts.app')

@section('titulo', 'Contagem física')

@section('conteudo')
    @php
        $rascunho = $contagem->isRascunho();
        $fmtQtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
        $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $card = 'bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4';

        // Valor do inventário de cada item = quantidade × custo
        $valorSistema = fn ($i) => round((float) $i->saldo_sistema * (float) $i->custo_unitario, 2);
        $valorContado = fn ($i) => round((float) $i->quantidade_contada * (float) $i->custo_unitario, 2);
        $totalSistema = $contagem->itens->sum($valorSistema);
        $totalContado = $contagem->itens->sum($valorContado);
    @endphp

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-800">Contagem física de {{ $contagem->data->format('d/m/Y') }}</h1>
            @if ($rascunho)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Aguardando aplicação
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                    Aplicada em {{ $contagem->aplicada_em?->format('d/m/Y H:i') }} por {{ $contagem->aplicador?->name ?? '—' }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('inventarios.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">Voltar</a>

            @if ($rascunho)
                <form action="{{ route('contagens.destroy', $contagem) }}" method="POST"
                      onsubmit="return confirm('Excluir esta contagem? Nada foi alterado no estoque.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-white border border-red-300 text-red-600 hover:bg-red-50 px-4 py-2.5 rounded-lg text-sm font-medium transition">
                        Excluir
                    </button>
                </form>
                <form action="{{ route('contagens.aplicar', $contagem) }}" method="POST"
                      onsubmit="return confirm('Aplicar a contagem? Cada diferença vira um ajuste de estoque em {{ $contagem->data->format('d/m/Y') }}, registrado no histórico. Isso não pode ser desfeito por aqui.')">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                        Aplicar ajustes no estoque
                    </button>
                </form>
            @else
                <a href="{{ route('inventarios.index', ['data' => $contagem->data->format('Y-m-d')]) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                    Calcular inventário de {{ $contagem->data->format('d/m/Y') }}
                </a>
            @endif
        </div>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('sucesso') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($rascunho)
        <div class="mb-4 p-4 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-800 space-y-1">
            <p>Nada foi alterado no estoque ainda. Confira as diferenças; as grandes costumam ser erro de contagem, de unidade (caixa × unidade) ou produto cadastrado em duplicidade.</p>
            <p>O saldo do sistema é recalculado no momento de aplicar, então vendas registradas depois da importação já entram na conta.</p>
            <p>Faltas confirmadas (perda, quebra, vencimento) devem ser documentadas com o contador: normalmente exigem NF-e de baixa e estorno dos créditos de ICMS e PIS/COFINS.</p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Valor dos itens contados no sistema</p>
            <p class="text-gray-800 font-semibold text-lg">{{ $brl($totalSistema) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">saldo do sistema × custo</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Valor da contagem</p>
            <p class="text-gray-800 font-semibold text-lg">{{ $brl($totalContado) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">quantidade contada × custo</p>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-4 mb-6 text-sm">
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Itens contados</p>
            <p class="text-gray-800 font-medium">{{ $totais['contados'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $totais['diferencas'] }} com diferença</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Sobras ({{ $totais['itensSobra'] }} itens)</p>
            <p class="text-green-700 font-medium">{{ $brl($totais['sobras']) }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Faltas ({{ $totais['itensFalta'] }} itens)</p>
            <p class="text-red-700 font-medium">{{ $brl($totais['faltas']) }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs text-gray-400">Efeito no valor do estoque</p>
            @php $liquido = $totais['sobras'] + $totais['faltas']; @endphp
            <p class="font-medium {{ $liquido < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $brl($liquido) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">pelo preço de custo atual</p>
        </div>
    </div>

    <div class="flex items-center gap-4 mb-4">
        <input type="text" id="filtro-itens" placeholder="Filtrar por código ou descrição..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-80 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
            <input type="checkbox" id="so-diferencas" checked class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
            Mostrar só itens com diferença
        </label>
        @if ($contagem->arquivo_nome)
            <p class="text-xs text-gray-400 ml-auto">Arquivo: {{ $contagem->arquivo_nome }} · importado por {{ $contagem->usuario?->name ?? '—' }} em {{ $contagem->created_at->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="max-h-[60vh] overflow-y-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-gray-700 font-medium text-xs uppercase tracking-wide sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Descrição</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Un</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Custo unit.</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">{{ $rascunho ? 'Saldo no sistema' : 'Saldo antes' }}</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor no sistema</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Contado</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor contado</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Diferença</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor da diferença</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($contagem->itens as $item)
                        @php $dif = (float) $item->diferenca; @endphp
                        <tr data-linha data-diferenca="{{ $dif != 0 ? 1 : 0 }}"
                            data-busca="{{ mb_strtolower($item->produto?->codigo_interno . ' ' . $item->descricao) }}"
                            class="hover:bg-gray-50 transition {{ $dif != 0 ? '' : 'hidden' }}">
                            <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $item->produto?->codigo_interno }}</td>
                            {{-- Descrição longa: uma linha só, com "..." no fim (o nome inteiro aparece ao passar o mouse) --}}
                            <td class="px-4 py-2 text-gray-800">
                                <div class="max-w-[18rem] truncate" title="{{ $item->descricao }}">{{ $item->descricao }}</div>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $item->unidade }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ $brl($item->custo_unitario) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $fmtQtd($item->saldo_sistema) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $brl($valorSistema($item)) }}</td>
                            <td class="px-4 py-2 text-right text-gray-800 font-medium">{{ $fmtQtd($item->quantidade_contada) }}</td>
                            <td class="px-4 py-2 text-right text-gray-800 font-medium">{{ $brl($valorContado($item)) }}</td>
                            <td class="px-4 py-2 text-right font-medium {{ $dif > 0 ? 'text-green-700' : ($dif < 0 ? 'text-red-700' : 'text-gray-400') }}">
                                {{ $dif > 0 ? '+' : '' }}{{ $fmtQtd($dif) }}
                            </td>
                            <td class="px-4 py-2 text-right {{ $dif > 0 ? 'text-green-700' : ($dif < 0 ? 'text-red-700' : 'text-gray-400') }}">
                                {{ $brl($item->valor_diferenca) }}
                            </td>
                        </tr>
                    @endforeach
                    <tr id="sem-linhas" class="{{ $totais['diferencas'] ? 'hidden' : '' }}">
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum item para mostrar com o filtro atual.</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 font-medium sticky bottom-0 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-right text-gray-500">Total dos itens contados</td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ $brl($totalSistema) }}</td>
                        <td></td>
                        <td class="px-4 py-2 text-right text-gray-800">{{ $brl($totalContado) }}</td>
                        <td></td>
                        <td class="px-4 py-2 text-right {{ $totalContado - $totalSistema < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $brl($totalContado - $totalSistema) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <script>
        const filtro = document.getElementById('filtro-itens');
        const soDiferencas = document.getElementById('so-diferencas');

        function filtrar() {
            const termo = filtro.value.trim().toLowerCase();
            let visiveis = 0;

            document.querySelectorAll('[data-linha]').forEach((tr) => {
                const mostrar = (termo === '' || tr.dataset.busca.includes(termo))
                    && (!soDiferencas.checked || tr.dataset.diferenca === '1');
                tr.classList.toggle('hidden', !mostrar);
                mostrar && visiveis++;
            });

            document.getElementById('sem-linhas').classList.toggle('hidden', visiveis > 0);
        }

        filtro.addEventListener('input', filtrar);
        soDiferencas.addEventListener('change', filtrar);
    </script>
@endsection