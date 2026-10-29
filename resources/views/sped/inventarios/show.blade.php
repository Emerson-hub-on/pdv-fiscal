@extends('layouts.app')

@section('titulo', 'Inventário do SPED')

@section('conteudo')
    @php
        $editavel = $inventario->isRascunho();
        $input = 'w-28 border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $fmtQtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    @endphp

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-800">Inventário de {{ $inventario->data->format('d/m/Y') }}</h1>
            @if ($editavel)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Rascunho
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Fechado em {{ $inventario->fechado_em?->format('d/m/Y H:i') }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('inventarios.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">Voltar</a>

            @if ($editavel)
                <form action="{{ route('inventarios.recalcular', $inventario) }}" method="POST"
                      onsubmit="return confirm('Recalcular a partir do histórico? As quantidades e custos editados serão descartados.')">
                    @csrf
                    <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">
                        Recalcular
                    </button>
                </form>
                <form action="{{ route('inventarios.fechar', $inventario) }}" method="POST"
                      onsubmit="return confirm('Fechar o inventário? Depois de fechado ele não pode ser editado (só reaberto).')">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                        Fechar inventário
                    </button>
                </form>
            @else
                <form action="{{ route('inventarios.reabrir', $inventario) }}" method="POST"
                      onsubmit="return confirm('Reabrir o inventário para edição?')">
                    @csrf
                    <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">
                        Reabrir
                    </button>
                </form>
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

    @if ($antesDoHistorico)
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            A data do inventário é anterior ao início do histórico de estoque ({{ $inicioHistorico->format('d/m/Y') }}).
            O que mudou no estoque entre essas datas não foi registrado, então as quantidades calculadas podem estar erradas:
            confira com a contagem física ou com o inventário do sistema anterior e corrija as linhas.
        </div>
    @endif

    @if ($negativos > 0)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ $negativos }} produto(s) ficaram com quantidade negativa na data. O inventário só pode ser fechado depois de corrigir essas linhas.
        </div>
    @endif

    <div class="grid grid-cols-3 gap-4 mb-6 text-sm">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-400">Motivo</p>
            <p class="text-gray-800 font-medium">{{ $inventario->motivo }} - {{ \App\Models\Inventario::MOTIVOS[$inventario->motivo] ?? '' }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-400">Produtos</p>
            <p class="text-gray-800 font-medium">{{ $inventario->itens->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-400">Valor total do inventário</p>
            <p id="total-inventario" class="text-gray-800 font-medium">R$ {{ number_format($inventario->valor_total, 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
        <input type="text" id="filtro-itens" placeholder="Filtrar por código ou nome do produto..." autocomplete="off"
               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm w-80 focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        @if ($editavel)
            <p class="text-xs text-gray-400">Alterou a quantidade ou o custo? A linha é salva sozinha ao sair do campo.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="max-h-[60vh] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 font-medium text-xs uppercase tracking-wide sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Produto</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Un</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Qtd calculada</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Quantidade</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Custo unitário</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($inventario->itens as $item)
                        <tr data-linha data-busca="{{ mb_strtolower($item->produto?->codigo_interno . ' ' . $item->produto?->nome) }}"
                            class="hover:bg-gray-50 transition {{ $item->quantidade < 0 ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $item->produto?->codigo_interno }}</td>
                            <td class="px-4 py-2 text-gray-800">{{ $item->produto?->nome }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $item->unidade }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $fmtQtd($item->quantidade_calculada) }}</td>
                            @if ($editavel)
                                <td class="px-4 py-2 text-right">
                                    <input type="number" step="0.001" min="0" value="{{ $fmtQtd($item->quantidade) }}" data-campo="quantidade"
                                           data-url="{{ route('inventarios.itens.atualizar', [$inventario, $item]) }}" class="{{ $input }}">
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" step="0.0001" min="0" value="{{ rtrim(rtrim(number_format((float) $item->custo_unitario, 6, '.', ''), '0'), '.') ?: '0' }}"
                                           data-campo="custo_unitario" class="{{ $input }}">
                                </td>
                            @else
                                <td class="px-4 py-2 text-right text-gray-800">{{ $fmtQtd($item->quantidade) }}</td>
                                <td class="px-4 py-2 text-right text-gray-800">R$ {{ number_format($item->custo_unitario, 4, ',', '.') }}</td>
                            @endif
                            <td class="px-4 py-2 text-right text-gray-800" data-valor>R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum produto com estoque nesta data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const brl = (n) => Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        document.getElementById('filtro-itens').addEventListener('input', (e) => {
            const termo = e.target.value.trim().toLowerCase();
            document.querySelectorAll('[data-linha]').forEach((tr) => {
                tr.classList.toggle('hidden', termo !== '' && !tr.dataset.busca.includes(termo));
            });
        });

        @if ($editavel)
        async function salvarLinha(tr) {
            const qtd = tr.querySelector('[data-campo="quantidade"]');
            const custo = tr.querySelector('[data-campo="custo_unitario"]');

            const resp = await fetch(qtd.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({ quantidade: qtd.value || 0, custo_unitario: custo.value || 0 }),
            });
            const json = await resp.json().catch(() => ({}));

            if (!resp.ok) {
                const msg = json.errors ? Object.values(json.errors)[0][0] : (json.message || 'Não foi possível salvar a linha.');
                alert(msg);
                return;
            }

            tr.querySelector('[data-valor]').textContent = brl(json.valor_total);
            document.getElementById('total-inventario').textContent = brl(json.total_inventario);
            tr.classList.toggle('bg-red-50', Number(qtd.value) < 0);
        }

        document.querySelectorAll('[data-campo]').forEach((input) => {
            input.dataset.original = input.value;
            input.addEventListener('change', () => salvarLinha(input.closest('tr')));
            input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } });
        });
        @endif
    </script>
@endsection
