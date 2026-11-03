{{-- Perfil do usuário logado (canto direito da linha do caminho da tela): clique abre o menu com "Sair" --}}
@auth('web')
    @php
        $usuarioLogado = auth('web')->user();
        $iniciais = collect(preg_split('/\s+/', trim((string) $usuarioLogado->name)))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
    @endphp
    <div class="relative shrink-0" id="perfil-usuario">
        <button type="button" onclick="alternarMenuPerfil(event)" aria-haspopup="true" aria-expanded="false" id="perfil-botao"
                class="flex flex-col items-center gap-0.5 rounded-lg px-1.5 py-0.5 hover:bg-gray-200/70 transition cursor-pointer">
            <span class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-semibold">{{ $iniciais }}</span>
            <span class="text-[11px] leading-tight font-medium text-gray-600 max-w-32 truncate">{{ $usuarioLogado->name }}</span>
        </button>

        <div id="perfil-menu" class="hidden absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
            <div class="px-4 py-2 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ $usuarioLogado->name }}</p>
                <p class="text-xs text-gray-500">Código {{ $usuarioLogado->codigo }}{{ $usuarioLogado->isAdmin() ? ' · Administrador' : '' }}</p>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <input type="hidden" name="contexto" value="admin">
                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition text-left">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Sair
                </button>
            </form>
        </div>
    </div>
    <script>
        function alternarMenuPerfil(e) {
            e.stopPropagation();
            const menu = document.getElementById('perfil-menu');
            const aberto = menu.classList.toggle('hidden') === false;
            document.getElementById('perfil-botao').setAttribute('aria-expanded', aberto);
        }
        // fecha ao clicar fora ou com Esc
        document.addEventListener('click', (e) => {
            if (!document.getElementById('perfil-usuario')?.contains(e.target)) document.getElementById('perfil-menu')?.classList.add('hidden');
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') document.getElementById('perfil-menu')?.classList.add('hidden');
        });
    </script>
@endauth
