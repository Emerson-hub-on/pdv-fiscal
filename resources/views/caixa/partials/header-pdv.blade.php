{{-- Cabeçalho exclusivo da tela do PDV. Usa $caixa, herdado da view que o inclui. --}}
<div class="bg-linear-to-r from-slate-800 via-slate-900 to-slate-900
            shadow-lg shrink-0">
    <div class="flex justify-between items-center px-6 py-4 border-b border-white/10">
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
        <div class="flex gap-2">
            <button id="btn-sincronizar" onclick="sincronizarAgora()"
                    class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 text-sm font-medium px-4 py-2 cursor-pointer transition flex items-center gap-1.5">
                Atualizar Caixa
            </button>
            <a href="{{ route('caixa.fechar-form') }}"
               class="bg-purple-500/20 hover:bg-purple-500/30 text-slate-300 text-sm font-medium px-4 py-2 transition flex items-center gap-1.5">
                Fechar Caixa
            </a>

        </div>
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
        <button onclick="abrirModalReimpressao()"
                class="bg-sky-500/20 hover:bg-sky-500/30 text-slate-300 cursor-pointer text-xs font-semibold px-3 py-1.5 transition">
            Reimprimir Cupom <span class="opacity-60">F7</span>
        </button>
    </div>
</div>
