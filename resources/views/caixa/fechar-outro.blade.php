@extends('layouts.caixa')

@section('titulo', 'Fechar caixa de outro operador')

@php
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $campo = 'w-full border rounded px-3 py-2';
@endphp

@section('conteudo')
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10 mb-10">
        <h1 class="text-xl font-bold">Fechar caixa de outro operador</h1>
        <p class="text-xs text-gray-500 mt-1 mb-5">
            Exige o código e a senha de um supervisor ou administrador. O fechamento fica registrado em nome de quem autorizou.
        </p>

        <dl class="text-sm divide-y divide-gray-100 border border-gray-200 rounded mb-5">
            <div class="flex justify-between gap-3 px-3 py-2">
                <dt class="text-gray-600">PDV</dt>
                <dd class="font-medium text-right">{{ $caixa->pdv->nome ?? 'PDV #' . $caixa->pdv_id }}</dd>
            </div>
            <div class="flex justify-between gap-3 px-3 py-2">
                <dt class="text-gray-600">Operador</dt>
                <dd class="font-medium text-right">
                    {{ !empty($operador?->codigo) ? str_pad($operador->codigo, 4, '0', STR_PAD_LEFT) . ' - ' : '' }}{{ $operador->name ?? 'Operador #' . $caixa->operador_id }}
                </dd>
            </div>
            <div class="flex justify-between gap-3 px-3 py-2">
                <dt class="text-gray-600">Aberto em</dt>
                <dd class="font-medium">{{ $caixa->data_abertura?->format('d/m/Y H:i') }}</dd>
            </div>
            <div class="flex justify-between gap-3 px-3 py-2">
                <dt class="text-gray-600">Valor de abertura</dt>
                <dd class="font-medium">{{ $moeda($caixa->valor_abertura) }}</dd>
            </div>
            <div class="flex justify-between gap-3 px-3 py-2">
                <dt class="text-gray-600">Total vendido</dt>
                <dd class="font-medium">{{ $moeda($totalVendido) }}</dd>
            </div>
            <div class="flex justify-between gap-3 px-3 py-2 text-blue-700">
                <dt>Valor esperado em caixa</dt>
                <dd class="font-bold">{{ $moeda($valorEsperado) }}</dd>
            </div>
        </dl>

        <form action="{{ route('caixa.fechar-outro', $caixa->uuid) }}" method="POST" autocomplete="off">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Código do supervisor</label>
                    <input type="number" min="1" name="supervisor_codigo" id="supervisor-codigo" required autofocus
                           value="{{ old('supervisor_codigo') }}" class="{{ $campo }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Senha</label>
                    <input type="password" name="supervisor_senha" required autocomplete="new-password" class="{{ $campo }}">
                </div>
            </div>
            <p id="supervisor-nome" class="text-xs mt-1 min-h-4 text-gray-500"></p>
            @error('supervisor_codigo')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
            @error('supervisor_senha')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <label class="block text-sm font-medium mb-1 mt-3">Valor contado no caixa</label>
            <input type="number" step="0.01" min="0" name="valor_fechamento_informado" required
                   value="{{ old('valor_fechamento_informado') }}" class="{{ $campo }}">
            @error('valor_fechamento_informado')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <label class="block text-sm font-medium mb-1 mt-3">Motivo</label>
            <textarea name="observacao" rows="2" required maxlength="500" class="{{ $campo }}"
                      placeholder="Ex.: operador saiu sem fechar o caixa">{{ old('observacao') }}</textarea>
            @error('observacao')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <div class="flex gap-2 mt-5">
                <a href="{{ route('caixa.abrir-form') }}"
                   class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded font-semibold">
                    Voltar
                </a>
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold cursor-pointer">
                    Fechar caixa
                </button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
    // Mostra o nome do supervisor assim que o código é digitado (consulta só o banco do caixa)
    (function () {
        const campo = document.getElementById('supervisor-codigo');
        const nome = document.getElementById('supervisor-nome');
        const url = @json(route('supervisor.usuario'));
        let espera;

        async function buscar() {
            const codigo = campo.value.trim();
            nome.textContent = '';
            if (!codigo) return;

            try {
                const resposta = await fetch(url + '?codigo=' + encodeURIComponent(codigo), { headers: { Accept: 'application/json' } });
                if (!resposta.ok) return;
                const dados = await resposta.json();
                nome.textContent = dados.nome ?? dados.aviso ?? '';
                nome.className = 'text-xs mt-1 min-h-4 ' + (dados.nome ? 'text-green-700 font-medium' : 'text-red-600');
            } catch (e) { /* sem resposta: o servidor confere ao enviar */ }
        }

        campo.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(buscar, 300); });
        if (campo.value) buscar();
    })();
</script>
@endsection
