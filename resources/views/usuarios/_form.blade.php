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
            <input type="text" name="name" id="campo-nome" maxlength="100" required autocomplete="off"
                   value="{{ old('name', $usuario->name ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            <p class="text-xs text-gray-500 mt-1">
                Login gerado: <span id="login-gerado" class="font-mono font-medium text-gray-700">—</span>
            </p>
            @unless ($usuario)
                <p class="text-xs text-gray-400 mt-1">
                    Se esta pessoa já estiver cadastrada em outro perfil, informe o mesmo nome: o acesso será adicionado e a senha atual mantida.
                </p>
            @endunless
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Senha @unless ($usuario) <span class="text-red-500">*</span> @endunless
            </label>
            <input type="password" name="password" minlength="4" autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            @if ($usuario)
                <p class="text-xs text-gray-400 mt-1">Deixe em branco para manter a senha atual.</p>
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

<script>
    // Mesma regra do User::normalizarUsername()
    function normalizarUsername(valor) {
        valor = valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        valor = valor.replace(/\s+/g, '.').replace(/[^a-z0-9._-]/g, '');
        return valor.replace(/^\.+|\.+$/g, '');
    }

    const campoNome = document.getElementById('campo-nome');
    const loginGerado = document.getElementById('login-gerado');

    function atualizarLogin() {
        loginGerado.innerText = normalizarUsername(campoNome.value) || '—';
    }

    campoNome.addEventListener('input', atualizarLogin);
    atualizarLogin();
</script>