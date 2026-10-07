@extends('layouts.app')
@section('titulo', 'PDVs')
@section('conteudo')

@if ($errors->has('pdv'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $errors->first('pdv') }}</div>
@endif

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">PDVs</h1>
        <a href="{{ route('pdvs.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            + Novo PDV
        </a>
    </div>

    <table class="w-full bg-white rounded shadow overflow-visible">
        <thead class="bg-gray-700 text-amber-50 text-left h-10 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>  
                <th class="px-2 py-3 text-left font-medium text-amber-50">Nome</th>
                <th class="px-2 py-3 text-left font-medium text-amber-50">Série</th>
                <th class="px-2 py-3 text-left font-medium text-amber-50">Número atual</th>
                <th class="px-2 py-3 text-left font-medium text-amber-50">Status</th>
                <th class="px-2 py-3 text-right font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pdvs as $pdv)
                <tr class="border-b">
                    <td class="p-3">{{ $pdv->nome }}</td>
                    <td class="p-3">{{ $pdv->serie_nfce }}</td>
                    <td class="p-3">{{ $pdv->numero_atual_nfce }}</td>
                    <td class="p-3">
                        <span class="{{ $pdv->ativo ? 'text-green-600' : 'text-red-500' }}">
                            {{ $pdv->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right relative">
                        <button type="button" onclick="toggleAcoesLinha({{ $pdv->id }})"
                                class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                            ⋮
                        </button>

                        <div id="dropdown-acoes-{{ $pdv->id }}"
                            class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-44 z-50 text-left">

                            <a href="{{ route('pdvs.edit', $pdv) }}"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                            {{-- AJUSTE: use o href que o link "Emissão de NFC-e" já tem hoje --}}
                            <a href="{{ route('pdvs.emissao', $pdv) }}"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Emissão de NFC-e</a>

                            <div class="border-t border-gray-100 my-1"></div>

                            {{-- AJUSTE: copie o action e o @method do formulário atual de Inativar/Reativar --}}
                            <form method="POST" action="{{ route('pdvs.toggle-ativo', $pdv) }}"
                                onsubmit="return confirm('{{ $pdv->ativo ? 'Inativar' : 'Reativar' }} este PDV?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="w-full text-left px-4 py-2 text-sm {{ $pdv->ativo ? 'text-red-600 hover:bg-red-50' : 'text-green-700 hover:bg-green-50' }}">
                                    {{ $pdv->ativo ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('scripts')
<script>
function toggleAcoesLinha(id) {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(el => {
        if (el.id !== `dropdown-acoes-${id}`) el.classList.add('hidden');
    });
    document.getElementById(`dropdown-acoes-${id}`).classList.toggle('hidden');
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(dropdown => {
        const container = dropdown.closest('td');
        if (!dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>
@endsection