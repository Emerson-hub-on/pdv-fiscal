{{-- Cabeçalho exclusivo da tela do PDV. Usa $caixa, herdado da view que o inclui. --}}
<div class="bg-linear-to-r from-slate-800 via-slate-900 to-slate-900
            shadow-lg shrink-0">
    <div class="flex justify-between items-center px-6 py-4 border-b border-white/10">
        <div class="flex items-center gap-4">
        @include('partials.logo-simbolo', ['tamanho' => 40, 'rotulo' => 'Cyberly Sistema'])
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">{{ $caixa->pdv->nome }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Série {{ $caixa->pdv->serie_nfce }} · Próxima NFC-e nº {{ $caixa->pdv->proximoNumeroNfce() }}
            </p>
            @unless ($caixa->pdv->emissao_local)
                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-400/20 text-amber-300">
                    Emissão pelo servidor (modo de emergência)
                </span>
            @endunless
        </div>
        </div>
        @include('caixa.partials.perfil-operador')
    </div>

    <div class="flex flex-wrap gap-2 px-6 py-3 bg-black/20">
        <button onclick="abrirModalContingencias()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
            ⚠️ Contingências <span class="opacity-60">F1</span>
        </button>
        <button onclick="abrirModalInutilizacao()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
            Inutilizar <span class="opacity-60">F2</span>
        </button>
        <div class="relative">
            <button onclick="toggleDropdownCancelamento()" id="btn-cancelamento-main"
                    class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
                Cancelamento <span class="opacity-60">F3</span> ▾
            </button>
            <!-- Adicionado id no container do menu -->
            <div id="dropdown-cancelamento" class="absolute hidden bg-slate-200 rounded-lg shadow-xl mt-2 w-52 overflow-hidden z-50 border border-white/10">
                <button onclick="fecharDropdownCancelamento(); solicitarCancelamentoNfce();"
                        class="opcao-dropdown w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition" data-index="0">
                    Cancelar NFC-e
                </button>
                <button onclick="fecharDropdownCancelamento(); abrirModalCancelarItem();"
                        class="opcao-dropdown w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition border-t border-white/10" data-index="1">
                    Cancelar Item
                </button>
                <button onclick="fecharDropdownCancelamento(); abrirModalLimparPdv();"
                        class="opcao-dropdown w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition border-t border-white/10" data-index="2">
                    Cancelar Cupom
                </button>
            </div>
        </div>

        <button onclick="abrirModalDescontoItem()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
            Desconto Item <span class="opacity-60">F4</span>
        </button>
        <button onclick="abrirModalPedidos()"
                class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
            Puxar Pedido <span class="opacity-60">F6</span>
        </button>
        <div class="relative">
            <button onclick="toggleDropdownImpressao()" id="btn-impressao-main"
                    class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
                Impressão <span class="opacity-60">F7</span> ▾
            </button>
            <div id="dropdown-impressao" class="absolute hidden bg-slate-200 rounded-lg shadow-xl mt-2 w-56 overflow-hidden z-50 border border-white/10">
                <button onclick="fecharDropdownImpressao(); abrirModalReimpressao();"
                        class="opcao-menu-caixa w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition">
                    Reimprimir Cupom
                </button>
                <button onclick="fecharDropdownImpressao(); imprimirRelatorioAbertura(true);"
                        class="opcao-menu-caixa w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition border-t border-white/10">
                    Relatório de Abertura
                </button>
            </div>
        </div>
        <div class="relative">
            <button type="button" onclick="toggleDropdownOpcoes()"
                    class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
                Opções <span class="opacity-60">F8</span> ▾
            </button>
            <div id="dropdown-opcoes" class="absolute hidden bg-slate-200 rounded-lg shadow-xl mt-2 w-52 overflow-hidden z-50 border border-white/10">
                {{-- id usado por sincronizarAgora() para mostrar "Sincronizando..." --}}
                <button id="btn-sincronizar" onclick="fecharDropdownOpcoes(); sincronizarAgora();"
                        class="opcao-menu-caixa w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition">
                    Atualizar Caixa
                </button>
                <a href="{{ route('caixa.fechar-form') }}" onclick="fecharDropdownOpcoes()"
                   class="opcao-menu-caixa block w-full text-left px-4 py-2.5 text-sm text-slate-800 hover:bg-slate-700 hover:text-white transition border-t border-white/10">
                    Fechar Caixa
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Relatório de abertura: a página de impressão carrega num iframe fora da tela e chama a impressão sozinha.
     (display:none faz alguns navegadores imprimirem em branco, por isso w-0/h-0 em vez de hidden) --}}
<iframe id="frame-relatorio-abertura" title="Relatório de abertura"
        class="fixed bottom-0 right-0 w-0 h-0 border-0 opacity-0 pointer-events-none"></iframe>
<script>
    function imprimirRelatorioAbertura(reimpressao) {
        const url = new URL(@json(route('caixa.relatorio-abertura')), window.location.origin);
        url.searchParams.set('auto', '1');
        if (reimpressao) {
            url.searchParams.set('reimpressao', '1');
        }
        url.searchParams.set('t', Date.now()); // sempre carrega de novo (sem cache)

        document.getElementById('frame-relatorio-abertura').src = url.toString();
    }

    // Menus do cabeçalho: "Impressão" (F7) e "Opções" (F8). Mesmo comportamento do menu de Cancelamento:
    // abre com a 1ª opção marcada, ↑ ↓ trocam, Enter escolhe, Esc ou clique fora fecha. Só um menu aberto por vez.
    const MENUS_CAIXA = ['dropdown-impressao', 'dropdown-opcoes'];
    const indiceMenuCaixa = {};

    function menuCaixaAberto() {
        return MENUS_CAIXA.find(id => !document.getElementById(id)?.classList.contains('hidden')) ?? null;
    }

    function fecharMenuCaixa(id) {
        document.getElementById(id)?.classList.add('hidden');
        indiceMenuCaixa[id] = -1;
    }

    function marcarOpcaoMenuCaixa(id) {
        document.querySelectorAll(`#${id} .opcao-menu-caixa`).forEach((opcao, i) => {
            const ativa = i === indiceMenuCaixa[id];
            opcao.classList.toggle('bg-slate-700', ativa);
            opcao.classList.toggle('text-white', ativa);
            if (ativa) opcao.focus();
        });
    }

    function alternarMenuCaixa(id) {
        const menu = document.getElementById(id);
        const abrir = menu.classList.contains('hidden');

        MENUS_CAIXA.forEach(fecharMenuCaixa);
        if (typeof fecharDropdownCancelamento === 'function') fecharDropdownCancelamento();
        if (typeof fecharMenuOperador === 'function') fecharMenuOperador();
        if (!abrir) return;

        menu.classList.remove('hidden');
        indiceMenuCaixa[id] = 0;
        marcarOpcaoMenuCaixa(id);
    }

    // nomes usados pela tela do PDV (atalhos F7/F8 e "fechar tudo")
    function toggleDropdownImpressao() { alternarMenuCaixa('dropdown-impressao'); }
    function fecharDropdownImpressao() { fecharMenuCaixa('dropdown-impressao'); }
    function toggleDropdownOpcoes() { alternarMenuCaixa('dropdown-opcoes'); }
    function fecharDropdownOpcoes() { fecharMenuCaixa('dropdown-opcoes'); }

    document.addEventListener('keydown', (e) => {
        const id = menuCaixaAberto();
        if (!id) return;

        const opcoes = document.querySelectorAll(`#${id} .opcao-menu-caixa`);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const passo = e.key === 'ArrowDown' ? 1 : -1;
            indiceMenuCaixa[id] = (indiceMenuCaixa[id] + passo + opcoes.length) % opcoes.length;
            marcarOpcaoMenuCaixa(id);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            opcoes[indiceMenuCaixa[id]]?.click();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            fecharMenuCaixa(id);
        }
    });

    // Mouse sobre uma opção: ela passa a ser a marcada (senão a marcada pelo teclado continuaria acesa)
    document.addEventListener('mouseover', (e) => {
        const opcao = e.target.closest?.('.opcao-menu-caixa');
        const id = opcao?.parentElement?.id;
        if (!opcao || !MENUS_CAIXA.includes(id)) return;

        const indice = [...document.querySelectorAll(`#${id} .opcao-menu-caixa`)].indexOf(opcao);
        if (indice !== indiceMenuCaixa[id]) {
            indiceMenuCaixa[id] = indice;
            marcarOpcaoMenuCaixa(id);
        }
    });

    document.addEventListener('click', (e) => {
        MENUS_CAIXA.forEach(id => {
            const menu = document.getElementById(id);
            if (menu && !menu.classList.contains('hidden') && !menu.closest('.relative').contains(e.target)) fecharMenuCaixa(id);
        });
    });

    @if (session('imprimir_abertura'))
        window.addEventListener('load', () => imprimirRelatorioAbertura(false));
    @endif
</script>