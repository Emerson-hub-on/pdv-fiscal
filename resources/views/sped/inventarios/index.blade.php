@extends('layouts.app')

@section('titulo', 'Inventário do SPED')

@section('conteudo')
    @php
        $campo = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
    @endphp

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Inventário do SPED</h1>

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

    <form method="POST" action="{{ route('inventarios.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        @csrf
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Novo inventário</h2>
        <p class="text-xs text-gray-400 mb-4">
            O sistema calcula o estoque de cada produto no fim do dia escolhido, a partir do histórico de movimentações.
            Depois você confere, ajusta a contagem se precisar e fecha o inventário.
            @if ($inicioHistorico)
                O histórico de estoque começa em <span class="font-medium text-gray-600">{{ $inicioHistorico->format('d/m/Y') }}</span>.
            @endif
        </p>

        <div class="grid grid-cols-3 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data do inventário <span class="text-red-500">*</span></label>
                <input type="date" name="data" required max="{{ now()->format('Y-m-d') }}" value="{{ old('data', $dataSugerida) }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <select name="motivo" class="{{ $campo }} bg-white">
                    @foreach (\App\Models\Inventario::MOTIVOS as $codigo => $rotulo)
                        <option value="{{ $codigo }}">{{ $codigo }} - {{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                    <input type="hidden" name="incluir_zerados" value="0">
                    <input type="checkbox" name="incluir_zerados" value="1" {{ old('incluir_zerados') ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">Listar também produtos com saldo zero</span>
                </label>
            </div>

            <div class="col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                <input type="text" name="observacao" maxlength="255" value="{{ old('observacao') }}" class="{{ $campo }}">
            </div>
        </div>

        <div class="mt-5">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Calcular inventário
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-700 font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Motivo</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Itens</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Valor</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($inventarios as $inv)
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $inv->data->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $inv->motivo }} - {{ \App\Models\Inventario::MOTIVOS[$inv->motivo] ?? '' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $inv->itens_count }}</td>
                        <td class="px-4 py-3 text-gray-600">R$ {{ number_format($inv->valor_total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($inv->isRascunho())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Rascunho
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Fechado
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('inventarios.show', $inv) }}" class="text-blue-600 hover:text-blue-700 font-medium">Abrir</a>
                                @if ($inv->isRascunho())
                                    <form action="{{ route('inventarios.destroy', $inv) }}" method="POST" onsubmit="return confirm('Excluir este inventário em rascunho?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-700 font-medium">Excluir</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum inventário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $inventarios->links() }}
    </div>
@endsection
