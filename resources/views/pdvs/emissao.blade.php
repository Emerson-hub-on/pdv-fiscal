@extends('layouts.app')
@section('titulo', 'Emissão de NFC-e - ' . $pdv->nome)

@section('breadcrumb')
    <span>Cadastros</span>
    <span class="text-gray-300">›</span>
    <span>PDVs</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Emissão de NFC-e</span>
@endsection

@section('conteudo')
    <h1 class="text-2xl font-bold mb-1">Emissão de NFC-e: {{ $pdv->nome }}</h1>
    <p class="text-sm text-gray-500 mb-6">Define quem numera e emite as NFC-e deste PDV. Por padrão é o caixa.</p>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 max-w-xl">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-xl mb-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-gray-700">Emissor atual</span>
            @if ($pdv->emissao_local)
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">Caixa</span>
            @else
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">Servidor (emergência)</span>
            @endif
        </div>

        <dl class="text-sm space-y-1 text-gray-600">
            <div class="flex justify-between"><dt>Série</dt><dd class="font-mono text-gray-800">{{ $pdv->serie_nfce }}</dd></div>
            <div class="flex justify-between"><dt>Último número conhecido pelo servidor</dt><dd class="font-mono text-gray-800">{{ $pdv->numero_atual_nfce }}</dd></div>
            <div class="flex justify-between"><dt>Vendas sem NFC-e no servidor</dt><dd class="font-mono text-gray-800">{{ $naoEmitidas }}</dd></div>
        </dl>

        @if (!$pdv->emissao_local && $pdv->emissor_alterado_em)
            <p class="text-xs text-gray-500 mt-4">
                Desde {{ $pdv->emissor_alterado_em->format('d/m/Y H:i') }}
                @if ($alteradoPor) por {{ $alteradoPor }} @endif
                @if ($pdv->emissor_motivo) &mdash; {{ $pdv->emissor_motivo }} @endif
            </p>
        @endif
    </div>

    @if ($pdv->emissao_local)
        <form action="{{ route('pdvs.emissao.servidor', $pdv) }}" method="POST"
              class="bg-white rounded-xl shadow-sm border border-red-200 p-6 max-w-xl space-y-4">
            @csrf
            <div>
                <h2 class="font-semibold text-red-700">Passar a emissão para o servidor</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Use só quando o banco do caixa (SQLite) estiver corrompido ou perdido, até o técnico restaurar ou trocar o banco.
                    Enquanto estiver assim, o caixa não emite NFC-e por conta própria.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-red-500">*</span></label>
                <textarea name="motivo" rows="2" maxlength="200" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none transition">{{ old('motivo') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Último número de NFC-e que você sabe que foi emitido (opcional)</label>
                <input type="number" name="ultimo_numero" min="0" value="{{ old('ultimo_numero') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none transition">
                <p class="text-xs text-gray-400 mt-1">
                    Se o caixa emitiu notas sem conexão e o banco se perdeu antes de avisar o servidor, o servidor não sabe esse número.
                    Consulte o portal da SEFAZ ou o último cupom impresso. Em branco, vale o do servidor ({{ $pdv->numero_atual_nfce }}).
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite o nome do PDV para confirmar: <strong>{{ $pdv->nome }}</strong></label>
                <input type="text" name="confirmacao" autocomplete="off" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none transition">
            </div>

            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Passar para o servidor
            </button>
        </form>
    @else
        <form action="{{ route('pdvs.emissao.caixa', $pdv) }}" method="POST"
              class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-xl space-y-3"
              onsubmit="return confirm('Devolver a emissão deste PDV ao caixa? O banco do caixa já deve estar restaurado ou trocado e sincronizado.')">
            @csrf
            <h2 class="font-semibold text-gray-800">Devolver a emissão ao caixa</h2>
            <p class="text-xs text-gray-500">
                Faça isto depois que o técnico restaurar ou trocar o banco do caixa e ele sincronizar com o servidor.
                O caixa continua do número {{ $pdv->numero_atual_nfce + 1 }}.
            </p>

            @if ($naoEmitidas > 0)
                <p class="text-xs text-amber-700">Há {{ $naoEmitidas }} venda(s) sem NFC-e no servidor: emita-as (F1) antes de devolver.</p>
            @endif

            <button type="submit" @disabled($naoEmitidas > 0)
                    class="bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Devolver ao caixa
            </button>
        </form>
    @endif
@endsection