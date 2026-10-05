@php $usuario = $usuario ?? null; @endphp

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-xl">
    <div class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="name" maxlength="100" required autocomplete="off"
                   value="{{ old('name', $usuario->name ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código de acesso</label>
            @if ($usuario)
                <input type="text" value="{{ $usuario->codigo }}" disabled
                       class="w-full border border-gray-200 bg-gray-50 text-gray-500 font-mono rounded-lg px-3 py-2.5 text-sm">
                <p class="text-xs text-gray-400 mt-1">Usado no login junto com a senha, em todos os tipos de acesso desta pessoa. Não pode ser alterado.</p>
            @else
                <p class="text-xs text-gray-500">
                    Gerado automaticamente ao salvar. É o código que a pessoa digita no login, junto com a senha.
                </p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Senha @unless ($usuario) <span class="text-red-500">*</span> @endunless
            </label>
            <input type="password" name="password" minlength="4" autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            @if ($usuario)
                <p class="text-xs text-gray-400 mt-1">Deixe em branco para manter a senha atual. A senha vale para todos os tipos de acesso desta pessoa.</p>
            @endif
        </div>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar
    </button>
    <a href="{{ route('usuarios.index', $perfil) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>