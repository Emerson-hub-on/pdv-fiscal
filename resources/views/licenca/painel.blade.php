@extends('layouts.app')

@section('titulo', 'Licença')

@php
    $cores = match ($situacao['estado']) {
        'valida'     => 'bg-green-100 text-green-800',
        'vencendo', 'tolerancia' => 'bg-amber-100 text-amber-800',
        default      => 'bg-red-100 text-red-700',
    };
    $rotulo = match ($situacao['estado']) {
        'valida'     => 'Em dia',
        'vencendo'   => 'Vence em breve',
        'tolerancia' => 'Vencida (tolerância)',
        default      => 'Bloqueada',
    };
@endphp

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex justify-between items-center p-4 border-b border-gray-100">
        <h1 class="text-lg font-semibold">Licença do sistema</h1>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $cores }}">{{ $rotulo }}</span>
    </div>

    <div class="p-4 grid sm:grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-gray-500">Vencimento</p>
            <p class="text-2xl font-semibold">{{ $situacao['valida_ate']?->format('d/m/Y') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Bloqueia em (após {{ \App\Services\LicencaService::TOLERANCIA_DIAS }} dias de tolerância)</p>
            <p class="text-2xl font-semibold">{{ $situacao['bloqueia_em']?->format('d/m/Y') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">CNPJ da licença</p>
            <p class="text-2xl font-semibold font-mono">{{ $situacao['cnpj'] ?? '—' }}</p>
        </div>
    </div>

    <p class="px-4 pb-4 text-sm text-gray-600">{{ $situacao['mensagem'] }}</p>

    <div class="p-4 border-t border-gray-100">
        <button type="button" id="btn-nova-licenca"
                onclick="document.getElementById('form-licenca').classList.remove('hidden'); this.classList.add('hidden'); document.getElementById('codigo').focus();"
                class="rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-amber-50 hover:bg-gray-700 {{ $errors->has('codigo') ? 'hidden' : '' }}">
            Registrar nova licença
        </button>

        <form method="POST" action="{{ route('licenca.registrar') }}" id="form-licenca"
              class="{{ $errors->has('codigo') ? '' : 'hidden' }}">
            @csrf
            <label for="codigo" class="block text-sm font-medium text-gray-700">Código da nova licença</label>
            <textarea id="codigo" name="codigo" rows="4" required spellcheck="false"
                      class="mt-1 w-full rounded border border-gray-300 px-3 py-2 font-mono text-xs focus:border-gray-500 focus:outline-none"
                      placeholder="CYB1....">{{ old('codigo') }}</textarea>
            @error('codigo')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="mt-3 flex gap-2">
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-amber-50 hover:bg-gray-700">
                    Registrar
                </button>
                <button type="button"
                        onclick="document.getElementById('form-licenca').classList.add('hidden'); document.getElementById('btn-nova-licenca').classList.remove('hidden');"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
