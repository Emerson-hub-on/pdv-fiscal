{{-- Perfil do operador de caixa logado (canto superior direito do cabeçalho do PDV): clique abre o menu com "Sair".
     Sair desloga só o caixa (contexto=caixa); o caixa continua aberto. Se o operador não voltar, um supervisor ou
     administrador fecha pela tela de abertura ("Caixas abertos neste computador"). --}}
@auth('caixa')
    @php
        $operador = auth('caixa')->user();
        $iniciaisOperador = collect(preg_split('/\s+/', trim((string) $operador->name)))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
    @endphp
    <div class="relative shrink-0" id="perfil-operador">
        <button type="button" onclick="alternarMenuOperador(event)" aria-haspopup="true" aria-expanded="false" id="perfil-operador-botao"
                class="flex flex-col items-center gap-0.5 rounded-lg px-2 py-1 hover:bg-white/10 transition cursor-pointer">
            <span class="w-8 h-8 rounded-full bg-white text-[#10284A] flex items-center justify-center text-xs font-semibold ring-1 ring-white/20">{{ $iniciaisOperador }}</span>
            <span class="text-[11px] leading-tight font-medium text-slate-200 max-w-36 truncate">{{ $operador->name }}</span>
        </button>

        <div id="perfil-operador-menu" class="hidden absolute right-0 mt-1 w-56 bg-slate-200 rounded-lg shadow-xl z-50 overflow-hidden border border-white/10">
            <div class="px-4 py-2.5 border-b border-slate-300">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ $operador->name }}</p>
                <p class="text-xs text-slate-500">Operador · Código {{ $operador->codigo }}</p>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <input type="hidden" name="contexto" value="caixa">
                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-700 hover:bg-slate-700 hover:text-white transition text-left">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Sair
                </button>
            </form>
        </div>
    </div>
    <script>
        function alternarMenuOperador(e) {
            e.stopPropagation();
            const menu = document.getElementById('perfil-operador-menu');
            const abrir = menu.classList.contains('hidden');
            // um menu por vez: fecha os do cabeçalho (Cancelamento, Impressão, Opções)
            if (abrir) {
                if (typeof fecharDropdownCancelamento === 'function') fecharDropdownCancelamento();
                if (typeof fecharDropdownImpressao === 'function') { fecharDropdownImpressao(); fecharDropdownOpcoes(); }
            }
            menu.classList.toggle('hidden', !abrir);
            document.getElementById('perfil-operador-botao').setAttribute('aria-expanded', abrir);
        }

        function fecharMenuOperador() {
            document.getElementById('perfil-operador-menu')?.classList.add('hidden');
        }

        document.addEventListener('click', (e) => {
            if (!document.getElementById('perfil-operador')?.contains(e.target)) fecharMenuOperador();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') fecharMenuOperador();
        });
    </script>
@endauth
