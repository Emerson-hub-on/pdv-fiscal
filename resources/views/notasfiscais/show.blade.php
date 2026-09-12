@extends('layouts.app')

@section('titulo', 'Nota Fiscal #' . $notaFiscal->id)

@section('conteudo')

@include('notasfiscais._recalculo_flash')

<div class="flex flex-col gap-6 max-w-4xl">

    <div class="bg-white rounded-lg shadow p-6 flex justify-between items-start">
        <div>
            <h1 class="text-lg font-semibold">Nota Fiscal {{ $notaFiscal->numero ? '#' . $notaFiscal->numero : '(rascunho)' }}</h1>
            <p class="text-sm text-gray-500">Cliente: {{ $notaFiscal->cliente->nome }}</p>
            <p class="text-sm text-gray-500">Status:
                <span class="font-medium">{{ ucfirst($notaFiscal->status) }}</span>
            </p>
        </div>

        <div class="relative">
            <button type="button" onclick="toggleAcoesNota()"
                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50 flex items-center gap-2">
                Ações
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="dropdown-acoes-nota" class="hidden absolute right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-48 z-50 text-left">
                <a href="{{ route('notasfiscais.previsualizar', $notaFiscal) }}" target="_blank"
                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Pré-visualizar PDF</a>

                @if ($notaFiscal->status === 'rascunho')
                    <a href="{{ route('notasfiscais.edit', $notaFiscal) }}"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                    <form method="POST" action="{{ route('notasfiscais.recalcular', $notaFiscal) }}"
                          onsubmit="return confirm('Isso vai atualizar NCM, CEST, tributação, PIS/COFINS e IPI de cada item com base no cadastro atual dos produtos. Quantidade, valor e desconto não serão alterados. Confirma?')">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recalcular</button>
                    </form>

                    <div class="border-t border-gray-100 my-1"></div>

                    <form method="POST" action="{{ route('notasfiscais.emitir', $notaFiscal) }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 font-medium hover:bg-green-50">Emitir NF-e</button>
                    </form>
                @elseif ($notaFiscal->status === 'emitida')
                    <a href="{{ route('notasfiscais.xml', $notaFiscal) }}"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Baixar XML</a>

                    <div class="border-t border-gray-100 my-1"></div>

                    <a href="{{ route('notasfiscais.cancelar-form', $notaFiscal) }}"
                       class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar</a>
                @endif
            </div>
        </div>
    </div>

    @error('emissao')
        <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-2">Produto</th>
                    <th class="text-left px-4 py-2">CFOP</th>
                    <th class="text-right px-4 py-2">Qtd</th>
                    <th class="text-right px-4 py-2">Unit.</th>
                    <th class="text-right px-4 py-2">Desconto</th>
                    <th class="text-right px-4 py-2">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($notaFiscal->itens as $item)
                    <tr>
                        <td class="px-4 py-2">{{ $item->produto->nome }}</td>
                        <td class="px-4 py-2">{{ $item->cfop }}</td>
                        <td class="px-4 py-2 text-right">{{ $item->quantidade }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_desconto, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="5" class="px-4 py-2 text-right">Total</td>
                    <td class="px-4 py-2 text-right">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleAcoesNota() {
    document.getElementById('dropdown-acoes-nota').classList.toggle('hidden');
}
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('dropdown-acoes-nota');
    const container = dropdown?.closest('.relative');
    if (dropdown && !dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
@endsection