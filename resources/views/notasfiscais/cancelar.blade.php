@extends('layouts.app')

@section('titulo', 'Cancelar Nota Fiscal #' . $notaFiscal->id)

@section('conteudo')
<div class="max-w-2xl flex flex-col gap-6">

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-2 mb-4">
            <a href="{{ route('notasfiscais.show', $notaFiscal) }}" title="Voltar para a nota"
               class="text-gray-500 hover:text-gray-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-lg font-semibold">Cancelar Nota Fiscal</h1>
        </div>

        <div class="text-sm text-gray-600 mb-4">
            <p><strong>Número:</strong> {{ $notaFiscal->numero }} — Série {{ $notaFiscal->serie }}</p>
            <p><strong>Cliente:</strong> {{ $notaFiscal->cliente->nome }}</p>
            <p><strong>Chave de acesso:</strong> <span class="font-mono text-xs">{{ $notaFiscal->chave_acesso }}</span></p>
            <p><strong>Valor total:</strong> R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</p>
            
            @if ($notaFiscal->status === 'cancelada')
                <hr class="my-2 border-gray-200">
                <p class="text-sm text-gray-500"><strong>Protocolo de cancelamento:</strong> {{ $notaFiscal->protocolo_cancelamento }}</p>
                <p class="text-sm text-gray-500"><strong>Cancelada em:</strong> {{ $notaFiscal->cancelado_em->format('d/m/Y H:i') }}</p>
            @endif
        </div>

        @if ($notaFiscal->status === 'cancelada')
            {{-- Exibe uma mensagem de sucesso caso já esteja cancelada e esconde o formulário --}}
            <div class="bg-green-50 text-green-800 border border-green-200 rounded-lg px-4 py-3 text-sm mb-4">
                Esta Nota Fiscal já foi cancelada com sucesso no sistema e homologada na SEFAZ.
            </div>
            <div class="flex gap-2 mt-4">
                <a href="{{ route('notasfiscais.show', $notaFiscal) }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50">
                    Voltar para a Nota
                </a>
            </div>
        @else
            {{-- Se não estiver cancelada, exibe o fluxo normal de cancelamento --}}
            <div class="bg-red-50 text-red-800 border border-red-200 rounded-lg px-4 py-3 text-sm mb-4">
                Esta ação é irreversível perante a SEFAZ. O cancelamento só é permitido dentro do prazo legal
                (geralmente 24h após a emissão) e estorna o estoque dos itens, se o CFOP da nota tiver
                "movimenta estoque" ativado.
            </div>

            @error('cancelamento')
                <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3 mb-4">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('notasfiscais.cancelar', $notaFiscal) }}"
                  onsubmit="return confirm('Confirma o cancelamento desta NF-e? Esta ação é irreversível.')">
                @csrf

                <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa (mínimo 15 caracteres)</label>
                <textarea name="motivo_cancelamento" rows="3" required minlength="15" maxlength="255"
                          placeholder="Ex: Erro no cadastro do cliente, nota emitida em duplicidade..."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-1">{{ old('motivo_cancelamento') }}</textarea>
                @error('motivo_cancelamento') <p class="text-red-600 text-xs mb-3">{{ $message }}</p> @enderror

                <div class="flex gap-2 mt-4">
                    <a href="{{ route('notasfiscais.show', $notaFiscal) }}"
                       class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50">
                        Voltar
                    </a>
                    <button type="submit"
                            class="bg-red-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-700">
                        Confirmar Cancelamento
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
