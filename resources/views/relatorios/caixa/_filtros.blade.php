{{-- Filtros comuns dos relatórios de caixa. Espera $filtros, $pdvs, $operadores e $rota; $comStatus mostra o filtro de situação. --}}
@php
    $campo = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
@endphp

<form method="GET" action="{{ route($rota) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <div class="grid grid-cols-2 {{ ($comStatus ?? false) ? 'md:grid-cols-6' : 'md:grid-cols-5' }} gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $rotuloData ?? 'De' }}</label>
            <input type="date" name="de" value="{{ $filtros['de'] }}" class="{{ $campo }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Até</label>
            <input type="date" name="ate" value="{{ $filtros['ate'] }}" class="{{ $campo }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">PDV</label>
            <select name="pdv_id" class="{{ $campo }} bg-white">
                <option value="">Todos</option>
                @foreach ($pdvs as $pdv)
                    <option value="{{ $pdv->id }}" @selected((string) $filtros['pdv_id'] === (string) $pdv->id)>{{ $pdv->nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Operador</label>
            <select name="operador_id" class="{{ $campo }} bg-white">
                <option value="">Todos</option>
                @foreach ($operadores as $op)
                    <option value="{{ $op->id }}" @selected((string) $filtros['operador_id'] === (string) $op->id)>
                        {{ $op->codigo ? str_pad($op->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $op->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @if ($comStatus ?? false)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                <select name="status" class="{{ $campo }} bg-white">
                    <option value="">Todas</option>
                    <option value="aberto" @selected($filtros['status'] === 'aberto')>Aberto</option>
                    <option value="fechado" @selected($filtros['status'] === 'fechado')>Fechado</option>
                </select>
            </div>
        @endif
        <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition cursor-pointer">
                Filtrar
            </button>
            <a href="{{ route($rota) }}"
               class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                Limpar
            </a>
        </div>
    </div>
</form>
