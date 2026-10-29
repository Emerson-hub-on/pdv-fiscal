@extends('layouts.app')

@section('titulo', 'Última numeração de MDF-e')

@section('conteudo')
    @php
        $campo = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $serieValor  = old('serie', $serieAtiva->serie ?? 1);
        $numeroValor = old('numero_atual', $serieAtiva->numero_atual ?? 0);
    @endphp

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Última numeração de MDF-e</h1>

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

    <div class="grid grid-cols-3 gap-6">
        {{-- ===================== FORMULÁRIO ===================== --}}
        <form method="POST" action="{{ route('mdfes.numeracao.salvar') }}" class="col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            @csrf
            @method('PUT')

            <p class="text-sm text-gray-600 mb-5">
                Se a empresa já emitia MDF-e em outro sistema, informe a série e o <span class="font-medium">último número emitido</span> nela.
                O próximo manifesto sai com o número seguinte. Quem nunca emitiu MDF-e deixa o último número em 0.
            </p>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Série <span class="text-red-500">*</span></label>
                    <input type="number" name="serie" id="serie" min="0" max="999" required value="{{ $serieValor }}" class="{{ $campo }}">
                    <p class="text-xs text-gray-400 mt-1">De 0 a 999. Use a mesma série do sistema anterior para continuar a sequência.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Último número emitido <span class="text-red-500">*</span></label>
                    <input type="number" name="numero_atual" id="numero_atual" min="0" max="999999998" required value="{{ $numeroValor }}" class="{{ $campo }}">
                    <p class="text-xs text-gray-400 mt-1">
                        Próximo MDF-e: <span id="proximo-numero" class="font-semibold text-gray-700">nº {{ number_format((int) $numeroValor + 1, 0, ',', '.') }}</span>
                    </p>
                </div>
            </div>

            <div class="mt-5 p-4 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 space-y-1">
                <p>Confira o último número na SEFAZ ou no sistema anterior antes de salvar. Número repetido é rejeitado pela SEFAZ (duplicidade de MDF-e).</p>
                <p>Se houver números pulados no sistema anterior, eles precisam ser inutilizados por lá; o sistema não deixa voltar para um número abaixo de um MDF-e que já existe aqui na mesma série.</p>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition"
                        onclick="return confirm('Salvar a numeração? O próximo MDF-e será emitido com o número mostrado.')">
                    Salvar numeração
                </button>
                <a href="{{ route('mdfes.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Voltar
                </a>
            </div>
        </form>

        {{-- ===================== SITUAÇÃO ATUAL ===================== --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Em uso agora</p>
                @if ($serieAtiva)
                    <p class="text-sm text-gray-600">Série <span class="font-semibold text-gray-800">{{ $serieAtiva->serie }}</span></p>
                    <p class="text-sm text-gray-600">Último número <span class="font-semibold text-gray-800">{{ number_format($serieAtiva->numero_atual, 0, ',', '.') }}</span></p>
                    <p class="text-sm text-gray-600">Próximo MDF-e <span class="font-semibold text-green-700">nº {{ number_format($serieAtiva->numero_atual + 1, 0, ',', '.') }}</span></p>
                @else
                    <p class="text-sm text-gray-400">Nenhuma série ativa. Salve a numeração para poder emitir.</p>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">MDF-e emitidos por este sistema</p>
                @forelse ($usados as $u)
                    <p class="text-sm text-gray-600">
                        Série <span class="font-medium text-gray-800">{{ $u->serie }}</span>:
                        {{ $u->total }} manifesto(s), maior nº <span class="font-medium text-gray-800">{{ number_format($u->maior, 0, ',', '.') }}</span>
                    </p>
                @empty
                    <p class="text-sm text-gray-400">Nenhum MDF-e numerado ainda.</p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        // Mostra o próximo número enquanto digita
        document.getElementById('numero_atual').addEventListener('input', (e) => {
            const ultimo = parseInt(e.target.value, 10);
            document.getElementById('proximo-numero').textContent = Number.isNaN(ultimo)
                ? '—'
                : 'nº ' + (ultimo + 1).toLocaleString('pt-BR');
        });
    </script>
@endsection
