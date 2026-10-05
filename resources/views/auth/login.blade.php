<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ $modo === 'admin' ? 'Administrador' : 'Operador' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow w-full max-w-sm">
        <h1 class="text-xl font-bold mb-6 text-center">
            Login {{ $modo === 'admin' ? 'Administrador' : 'Operador' }}
        </h1>

        @if ($errors->any())
            <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-2 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('auth.login.submit') }}" method="POST">
            @csrf
            <input type="hidden" name="modo" value="{{ $modo }}">

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="col-span-1">
                    <label for="campo-codigo" class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" id="campo-codigo" name="codigo" inputmode="numeric" pattern="[0-9]*"
                        required autofocus autocomplete="off" value="{{ old('codigo') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuário</label>
                    <input type="text" id="campo-usuario" readonly tabindex="-1" placeholder="—"
                        class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5 text-sm text-gray-800 outline-none">
                </div>
            </div>

            @error('codigo')
                <p class="text-red-600 text-sm -mt-2 mb-3">{{ $message }}</p>
            @enderror

            <div class="mb-6">
                <label for="campo-senha" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                <input type="password" id="campo-senha" name="password" required autocomplete="current-password"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold">
                Entrar
            </button>
        </form>

        <a href="{{ route('auth.escolha') }}" class="block text-center text-sm text-gray-500 mt-4 hover:underline">
            ← Voltar
        </a>
    </div>

<script>
    const modoLogin = @json($modo);
    const campoCodigo = document.getElementById('campo-codigo');
    const campoUsuario = document.getElementById('campo-usuario');
    const campoSenha = document.getElementById('campo-senha');

    let timeoutUsuario;
    let ultimaBusca = 0;

    function definirUsuario(texto, erro = false) {
        campoUsuario.value = texto;
        campoUsuario.classList.toggle('text-red-600', erro);
        campoUsuario.classList.toggle('text-gray-800', !erro);
    }

    async function buscarUsuario() {
        const codigo = campoCodigo.value.trim();
        const busca = ++ultimaBusca;

        if (!/^\d+$/.test(codigo)) {
            definirUsuario('');
            return;
        }

        try {
            const url = `{{ route('auth.usuario') }}?modo=${modoLogin}&codigo=${encodeURIComponent(codigo)}`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const dados = await resp.json();

            if (busca !== ultimaBusca) return; // chegou uma resposta mais nova

            if (dados.nome) {
                definirUsuario(dados.nome);
            } else {
                definirUsuario('Código não encontrado', true);
            }
        } catch (e) {
            if (busca === ultimaBusca) definirUsuario('');
        }
    }

    campoCodigo.addEventListener('input', () => {
        clearTimeout(timeoutUsuario);
        timeoutUsuario = setTimeout(buscarUsuario, 250);
    });

    // Enter no código vai para a senha, em vez de enviar o formulário
    campoCodigo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            campoSenha.focus();
        }
    });

    if (campoCodigo.value) buscarUsuario(); // código vindo de uma tentativa anterior
</script>
</body>
</html>