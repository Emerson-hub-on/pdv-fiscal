@php
    // Ícone de cada categoria: por palavra no nome; o resto usa garfo e faca
    $iconeCategoria = function (?string $nome): string {
        $n = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $nome));

        return match (true) {
            str_contains($n, 'bebida') || str_contains($n, 'suco') || str_contains($n, 'drink') || str_contains($n, 'refri') => 'bebida',
            str_contains($n, 'sobremesa') || str_contains($n, 'doce') => 'sobremesa',
            str_contains($n, 'lanche') || str_contains($n, 'hamburg') || str_contains($n, 'burger') => 'lanche',
            str_contains($n, 'pizza') => 'pizza',
            default => 'prato',
        };
    };
    $caminhosIcone = [
        'prato'     => 'M7 3v8m-3-8v5a3 3 0 0 0 6 0V3M7 11v10M17 21V3c-2 1.5-3 4-3 7v4h3',
        'bebida'    => 'M6 3h12l-1.5 17a1 1 0 0 1-1 .9H8.5a1 1 0 0 1-1-.9L6 3zm.5 5h11',
        'sobremesa' => 'M12 3a5 5 0 0 1 5 5v1H7V8a5 5 0 0 1 5-5zM6 9h12l-2 12H8L6 9z',
        'lanche'    => 'M4 11a8 6 0 0 1 16 0H4zm-1 3h18M5 17h14a2 2 0 0 1-2 3H7a2 2 0 0 1-2-3z',
        'pizza'     => 'M12 3 3 20h18L12 3zm0 6v.01M10 14v.01M14 15v.01',
    ];
    $iconeMesa = 'M4 9h16M6 9v10m12-10v10M3 9l2-4h14l2 4';
    $iniciais = collect(explode(' ', trim($garcom->name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');

    // Itens do cardápio para o JavaScript (janela do item e comanda). O preço aqui é só para mostrar:
    // ao finalizar, o servidor recalcula com o preço do cadastro.
    $itensTela = $itens->mapWithKeys(fn ($item) => [$item->id => [
        'id'         => $item->id,
        'nome'       => $item->nomeExibido(),
        'descricao'  => $item->descricaoExibida(),
        'preco'      => (float) $item->produto->preco_venda,
        'imagem'     => $item->urlImagem('garcom.cardapio.imagem'),
        'semEstoque' => $item->estoqueAtual() <= 0,
    ]]);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>Garçom - Cyberly Sistema</title>
    @include('partials.favicon')
    @include('partials.escape-html')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="max-w-3xl mx-auto min-h-screen flex flex-col">

    {{-- ===== Topo: mesa selecionada (ou o aviso para escolher), garçom no ícone, busca e abas ===== --}}
    <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-100 px-4 pt-4 pb-3 shadow-sm">
        <div class="flex items-center gap-3">
            {{-- Mesa selecionada (preenchido pelo JavaScript) --}}
            <div id="topo-mesa" class="min-w-0 flex-1"></div>

            {{-- Garçom: os dados aparecem ao tocar no ícone --}}
            <div class="relative shrink-0" id="perfil">
                <button type="button" onclick="alternarPerfil()" aria-label="Operador" aria-haspopup="true"
                        class="relative w-12 h-12 rounded-full bg-[#10284A] text-amber-50 flex items-center justify-center shadow-sm">
                    @if ($iniciais !== '')
                        <span class="text-base font-bold uppercase">{{ $iniciais }}</span>
                    @else
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/></svg>
                    @endif
                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-white" title="Conectado"></span>
                </button>

                <div id="painel-perfil" class="hidden absolute right-0 top-14 z-30 w-64 bg-white rounded-2xl shadow-xl ring-1 ring-slate-200 p-4">
                    <p class="text-xs font-medium text-slate-400">Operador / Garçom</p>
                    <p class="font-bold text-slate-900 truncate">{{ $garcom->name }}</p>
                    <p class="text-xs text-slate-400">Código {{ $garcom->codigo }}</p>
                    <form method="POST" action="{{ route('auth.logout') }}" class="mt-4" onsubmit="return confirm('Sair da tela do garçom?')">
                        @csrf
                        <input type="hidden" name="contexto" value="garcom">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9M12 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/></svg>
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Busca: na aba Cardápio procura prato/bebida/código; na aba Mesas, a mesa --}}
        <div class="relative mt-4">
            <svg class="w-5 h-5 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0z"/></svg>
            <input type="search" id="busca" autocomplete="off" enterkeyhint="search"
                   placeholder="Buscar prato, bebida, código..."
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-3 text-base text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
        </div>

        {{-- Abas --}}
        <div class="mt-3 grid grid-cols-2 gap-1 bg-slate-100 rounded-2xl p-1" role="tablist">
            <button type="button" role="tab" data-aba="cardapio" onclick="trocarAba('cardapio')"
                    class="aba flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminhosIcone['prato'] }}"/></svg>
                Cardápio
            </button>
            <button type="button" role="tab" data-aba="mesas" onclick="trocarAba('mesas')"
                    class="aba flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 4.5-9 4.5-9-4.5L12 3zm-9 9 9 4.5 9-4.5M3 16.5 12 21l9-4.5"/></svg>
                Mesas
            </button>
        </div>
    </header>

    <main id="conteudo" class="flex-1 px-4 pt-4 pb-8">

        {{-- ===== Aba Cardápio ===== --}}
        <section id="painel-cardapio" data-painel="cardapio">
            @if ($itens->isEmpty())
                <div class="text-center py-16 px-6">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-[#10284A]/10 text-[#10284A] flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminhosIcone['prato'] }}"/></svg>
                    </div>
                    <p class="font-semibold text-slate-700">O cardápio está vazio</p>
                    <p class="text-sm text-slate-400 mt-1">Os itens são cadastrados no painel, em Comandas → Cardápio → Menu.</p>
                </div>
            @else
                {{-- Categorias (rolagem lateral no celular) --}}
                <div class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 mb-4" id="categorias">
                    <button type="button" data-categoria="" onclick="escolherCategoria('')"
                            class="categoria shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 4.5-9 4.5-9-4.5L12 3zm-9 9 9 4.5 9-4.5M3 16.5 12 21l9-4.5"/></svg>
                        Todos
                    </button>
                    @foreach ($categorias as $categoria)
                        <button type="button" data-categoria="{{ $categoria->id }}" onclick="escolherCategoria('{{ $categoria->id }}')"
                                class="categoria shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminhosIcone[$iconeCategoria($categoria->nome)] }}"/></svg>
                            {{ $categoria->nome }}
                        </button>
                    @endforeach
                    @if ($temSemCategoria && $categorias->isNotEmpty())
                        <button type="button" data-categoria="sem" onclick="escolherCategoria('sem')"
                                class="categoria shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                            Outros
                        </button>
                    @endif
                </div>

                {{-- Itens: tocar abre a janela para escolher a quantidade e a observação --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="lista-itens">
                    @foreach ($itens as $item)
                        @php
                            $descricao = $item->descricaoExibida();
                            $semEstoque = $item->estoqueAtual() <= 0;
                            // texto da busca: nome, descrição, código, categoria (sem acento, minúsculo)
                            $textoBusca = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(implode(' ', array_filter([
                                $item->nomeExibido(), $descricao, $item->codigoExibido(), $item->produto->codigo_interno, $item->categoria?->nome,
                            ]))));
                        @endphp
                        <button type="button" data-item data-categoria="{{ $item->cardapio_categoria_id ?? 'sem' }}" data-busca="{{ $textoBusca }}"
                                onclick="abrirItem({{ $item->id }})"
                                class="text-left flex gap-3 bg-white rounded-2xl shadow-sm ring-1 ring-slate-100 p-3 active:scale-[0.98] transition {{ $semEstoque ? 'opacity-60' : '' }}">
                            <div class="shrink-0 w-24 h-24 rounded-xl overflow-hidden bg-slate-100">
                                @if ($item->imagem)
                                    <img src="{{ $item->urlImagem('garcom.cardapio.imagem') }}" alt="{{ $item->nomeExibido() }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-slate-300">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminhosIcone['prato'] }}"/></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 flex flex-col">
                                <h3 class="font-bold text-slate-900 leading-snug">{{ $item->nomeExibido() }}</h3>
                                @if ($descricao)
                                    <p class="text-sm text-slate-400 leading-snug mt-1 line-clamp-2">{{ $descricao }}</p>
                                @endif
                                <div class="mt-auto pt-2 flex items-end justify-between gap-2">
                                    <p class="text-lg font-bold text-[#10284A] whitespace-nowrap">R$ {{ number_format((float) $item->produto->preco_venda, 2, ',', '.') }}</p>
                                    @if ($semEstoque)
                                        <span class="rounded-full bg-red-50 text-red-600 px-2 py-0.5 text-xs font-semibold whitespace-nowrap">Sem estoque</span>
                                    @endif
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div id="cardapio-vazio" class="hidden text-center py-12 text-sm text-slate-400">
                    Nenhum item encontrado.
                </div>
            @endif
        </section>

        {{-- ===== Aba Mesas: tocar numa mesa seleciona e leva para o cardápio ===== --}}
        <section id="painel-mesas" data-painel="mesas" class="hidden">
            {{-- Filtro por situação (com a quantidade de cada uma) --}}
            <div class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 mb-4" id="filtros-mesa">
                <button type="button" data-filtro-mesa="" onclick="filtrarStatusMesa('')"
                        class="filtro-mesa shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                    Todas <span data-conta="" class="text-xs opacity-70"></span>
                </button>
                @foreach (\App\Models\Mesa::STATUS as $chave => $s)
                    <button type="button" data-filtro-mesa="{{ $chave }}" onclick="filtrarStatusMesa('{{ $chave }}')"
                            class="filtro-mesa shrink-0 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                        <span class="w-2 h-2 rounded-full {{ $s['ponto'] }}"></span>
                        {{ $s['rotulo'] }} <span data-conta="{{ $chave }}" class="text-xs opacity-70"></span>
                    </button>
                @endforeach
            </div>

            {{-- Tabs das mesas (montadas pelo JavaScript e atualizadas a cada 15 s) --}}
            <div id="lista-mesas" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>

            <div id="mesas-vazio" class="hidden text-center py-16 px-6">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconeMesa }}"/></svg>
                </div>
                <p class="font-semibold text-slate-700" id="mesas-vazio-titulo">Nenhuma mesa cadastrada</p>
                <p class="text-sm text-slate-400 mt-1" id="mesas-vazio-texto">As mesas são cadastradas no painel, em Comandas → Mesas → Lista de Mesas.</p>
            </div>
        </section>
    </main>
</div>

{{-- ===== Barra da comanda (aparece quando a comanda da mesa tem itens) ===== --}}
<div id="barra-comanda" class="hidden fixed inset-x-0 bottom-0 z-30 px-4 pb-4 pt-2 pointer-events-none">
    <button type="button" onclick="abrirComanda()"
            class="pointer-events-auto max-w-3xl mx-auto w-full flex items-center gap-3 rounded-2xl bg-[#10284A] text-amber-50 px-4 py-3 shadow-2xl active:scale-[0.99] transition">
        <span class="relative shrink-0">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4"/></svg>
            <span id="barra-quantidade" class="absolute -top-2 -left-2 min-w-5 h-5 px-1 rounded-full bg-amber-50 text-[#10284A] text-[11px] font-bold flex items-center justify-center"></span>
        </span>
        <span class="min-w-0 flex-1 text-left">
            <span class="block text-xs text-slate-300 truncate" id="barra-mesa"></span>
            <span class="block font-bold">Ver Comanda</span>
        </span>
        <span class="shrink-0 font-bold text-lg" id="barra-total"></span>
        <svg class="w-5 h-5 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    </button>
</div>

{{-- ===== Janela do item: quantidade, observação e "Adicionar à comanda" ===== --}}
<div id="modal-item" class="fixed inset-0 z-40 bg-black/50 hidden items-end sm:items-center justify-center"
     onclick="if (event.target === this) fecharItem()">
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
        <div id="item-foto" class="relative aspect-video bg-slate-100 rounded-t-3xl overflow-hidden"></div>
        <div class="p-5 pb-8 sm:pb-5">
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-bold text-slate-900 text-xl leading-snug" id="item-nome"></h2>
                <p class="shrink-0 font-bold text-[#10284A] text-lg" id="item-preco"></p>
            </div>
            <p class="text-sm text-slate-500 mt-1" id="item-descricao"></p>
            <p class="hidden mt-2 rounded-xl bg-red-50 text-red-600 px-3 py-2 text-sm font-medium" id="item-sem-estoque">Sem estoque no sistema: confirme na cozinha antes de adicionar.</p>

            <p class="mt-5 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Quantidade</p>
            <div class="flex items-center gap-4">
                <button type="button" onclick="mudarQuantidadeItem(-1)" aria-label="Diminuir"
                        class="w-12 h-12 rounded-2xl ring-1 ring-slate-200 text-2xl font-bold text-slate-700 active:bg-slate-100">−</button>
                <span class="w-12 text-center text-2xl font-bold text-slate-900" id="item-quantidade">1</span>
                <button type="button" onclick="mudarQuantidadeItem(1)" aria-label="Aumentar"
                        class="w-12 h-12 rounded-2xl bg-[#10284A] text-amber-50 text-2xl font-bold active:opacity-80">+</button>
            </div>

            <label for="item-observacao" class="block mt-5 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Observação <span class="font-normal normal-case tracking-normal text-slate-400">(opcional)</span></label>
            <textarea id="item-observacao" rows="2" maxlength="200" placeholder="Ex.: sem cebola, carne ao ponto, gelo e limão..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-base text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition"></textarea>

            <div class="mt-5 flex gap-3">
                <button type="button" onclick="fecharItem()"
                        class="rounded-2xl px-5 py-3.5 text-base font-semibold text-slate-600 ring-1 ring-slate-200 active:bg-slate-100">Cancelar</button>
                <button type="button" onclick="adicionarItem()" id="item-adicionar"
                        class="flex-1 rounded-2xl bg-[#10284A] text-amber-50 px-4 py-3.5 text-base font-bold active:opacity-90"></button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Janela da comanda: confere, muda quantidades e finaliza o pedido ===== --}}
<div id="modal-comanda" class="fixed inset-0 z-40 bg-black/50 hidden items-end sm:items-center justify-center"
     onclick="if (event.target === this) fecharComanda()">
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] flex flex-col">
        <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-slate-400">Comanda</p>
                <h2 class="font-bold text-slate-900 text-lg truncate" id="comanda-mesa"></h2>
            </div>
            <button type="button" onclick="fecharComanda()" aria-label="Fechar"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-3" id="comanda-itens"></div>

        <div class="px-5 pt-3 pb-8 sm:pb-5 border-t border-slate-100">
            <label for="comanda-cliente" class="block mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Nome do cliente <span class="font-normal normal-case tracking-normal text-slate-400">(opcional)</span></label>
            <input type="text" id="comanda-cliente" maxlength="60" autocomplete="off" placeholder="Ex.: João"
                   class="w-full mb-3 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-base text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm font-semibold text-slate-500">Total do pedido</span>
                <span class="text-2xl font-bold text-[#10284A]" id="comanda-total"></span>
            </div>
            <p id="comanda-erro" class="hidden mb-3 rounded-xl bg-red-50 text-red-600 px-3 py-2 text-sm"></p>
            <button type="button" onclick="fecharComanda()"
                    class="w-full rounded-2xl px-4 py-3.5 text-base font-semibold text-[#10284A] ring-1 ring-[#10284A]/30 active:bg-slate-50">
                + Adicionar mais itens
            </button>
            <button type="button" onclick="finalizarPedido()" id="comanda-finalizar"
                    class="mt-3 w-full rounded-2xl bg-[#10284A] text-amber-50 px-4 py-4 text-base font-bold active:opacity-90 disabled:opacity-60">
                Finalizar pedido
            </button>
        </div>
    </div>
</div>

{{-- ===== Janela da situação da mesa: pedidos com o tempo de espera de cada um (a situação muda sozinha pelos pedidos) ===== --}}
<div id="modal-mesa" class="fixed inset-0 z-40 bg-black/50 hidden items-end sm:items-center justify-center"
     onclick="if (event.target === this) fecharSituacao()">
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 pb-8 sm:pb-5 max-h-[92vh] overflow-y-auto">
        <div class="mx-auto w-10 h-1 rounded-full bg-slate-200 mb-4 sm:hidden"></div>
        <div class="flex items-center gap-4">
            <div class="shrink-0 w-16 h-16 rounded-2xl bg-[#10284A] text-amber-50 flex flex-col items-center justify-center">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-300">Mesa</span>
                <span class="text-2xl font-bold leading-none" id="mesa-numero"></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 text-lg leading-snug truncate" id="mesa-nome"></p>
                <p class="text-sm text-slate-400" id="mesa-lugares"></p>
            </div>
            <button type="button" onclick="fecharSituacao()" aria-label="Fechar"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
        </div>

        <div id="mesa-espera" class="mt-5 rounded-2xl px-4 py-3"></div>
        <div id="mesa-pedidos" class="mt-3 flex flex-col gap-2"></div>

        <button type="button" id="mesa-fechar-conta" onclick="fecharSituacao(); fecharContaMesa()"
                class="hidden mt-4 w-full rounded-2xl bg-[#10284A] text-amber-50 px-4 py-3.5 text-base font-bold active:opacity-90 disabled:bg-slate-200 disabled:text-slate-500 disabled:text-sm disabled:font-semibold"></button>
        <p id="mesa-erro" class="hidden mt-3 text-sm text-red-600"></p>
    </div>
</div>

{{-- ===== Itens de um pedido (abre por cima da janela da mesa) ===== --}}
<div id="modal-pedido" class="fixed inset-0 z-50 bg-black/50 hidden items-end sm:items-center justify-center"
     onclick="if (event.target === this) fecharPedido()">
    <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[88vh] flex flex-col">
        <div class="px-5 pt-5 pb-3 border-b border-slate-100">
            <div class="mx-auto w-10 h-1 rounded-full bg-slate-200 mb-4 sm:hidden"></div>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-slate-900 text-lg" id="pedido-titulo"></h2>
                    <p class="text-sm text-slate-400 truncate" id="pedido-sub"></p>
                </div>
                <button type="button" onclick="fecharPedido()" aria-label="Fechar"
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
            </div>
            <div class="mt-2 flex items-center justify-between gap-3">
                <span id="pedido-selo"></span>
                <span id="pedido-cliente" class="text-sm text-slate-500 truncate"></span>
            </div>
        </div>
        <div id="pedido-itens" class="flex-1 overflow-y-auto px-5 divide-y divide-slate-100"></div>
        <div class="px-5 pt-3 pb-8 sm:pb-5 border-t border-slate-100">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold text-slate-600">Total do pedido</span>
                <span class="text-xl font-bold text-[#10284A]" id="pedido-total"></span>
            </div>
            <button type="button" onclick="fecharPedido()"
                    class="w-full rounded-2xl ring-1 ring-slate-200 px-4 py-3.5 text-base font-semibold text-slate-700 active:bg-slate-100">← Voltar para a mesa</button>
        </div>
    </div>
</div>

{{-- Aviso de pedido pronto (fica até o garçom tocar) --}}
<div id="aviso-pronto" class="hidden fixed inset-x-0 top-3 z-50 px-4 max-w-3xl mx-auto flex flex-col gap-2"></div>

@include('comandas._fechar-conta', ['formas' => $formas])

{{-- Aviso rápido (mesa selecionada, item adicionado, pedido enviado) --}}
<div id="aviso" class="hidden fixed left-1/2 -translate-x-1/2 top-4 z-50 max-w-sm w-[calc(100%-2rem)] rounded-2xl bg-slate-900 text-white px-4 py-3 text-sm font-medium shadow-2xl text-center"></div>

@include('comandas.mesas._relogio')

<script>
    // ===================== Estado =====================
    const STATUS_MESA = @json(\App\Models\Mesa::STATUS);
    const ITENS = @json($itensTela);
    const URL_MESAS = @json(route('garcom.mesas'));
    const URL_PEDIDO = @json(route('garcom.mesas.pedidos', ['mesa' => '__ID__']));
    const CSRF = @json(csrf_token());
    const ICONE_MESA = @json($iconeMesa);
    const URL_CONTA = @json(route('garcom.mesas.conta', ['mesa' => '__ID__']));
    const URL_FECHAR_CONTA = @json(route('garcom.mesas.conta.fechar', ['mesa' => '__ID__']));
    const MEU_ID = @json($garcom->id);

    let mesas = @json($mesas);
    let mesaSelecionada = null;   // id da mesa em que o garçom está montando o pedido
    let comandas = {};            // mesaId => { uuid, itens: [{ id, quantidade, observacao }] }
    let filtroMesa = '';
    let itemAberto = null;
    let quantidadeItem = 1;

    const campoBusca = document.getElementById('busca');
    const PLACEHOLDER = { cardapio: 'Buscar prato, bebida, código...', mesas: 'Buscar mesa pelo número ou nome...' };
    const termos = { cardapio: '', mesas: '' }; // cada aba guarda a própria busca
    let abaAtual = 'cardapio';
    let categoriaAtual = '';

    const semAcento = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const moeda = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const mesaAtual = () => mesas.find(m => m.id === mesaSelecionada) || null;
    const nomeMesa = (m) => m.nome ? `Mesa ${m.numero} · ${m.nome}` : `Mesa ${m.numero}`;

    // A comanda em montagem fica guardada no aparelho (não se perde se a página recarregar)
    function salvarEstado() {
        try {
            localStorage.setItem('garcom.comandas', JSON.stringify(comandas));
            localStorage.setItem('garcom.mesa', mesaSelecionada ? String(mesaSelecionada) : '');
        } catch (e) {}
    }
    function carregarEstado() {
        try {
            comandas = JSON.parse(localStorage.getItem('garcom.comandas') || '{}') || {};
            const id = Number(localStorage.getItem('garcom.mesa'));
            mesaSelecionada = id && mesas.some(m => m.id === id) ? id : null;
        } catch (e) { comandas = {}; }
        // descarta itens que saíram do cardápio
        Object.values(comandas).forEach(c => { c.itens = (c.itens || []).filter(l => ITENS[l.id]); });
    }

    // uuid do pedido (crypto.randomUUID só existe em https; aqui o servidor é http na rede)
    function novoUuid() {
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    let timeoutAviso;
    function avisar(texto) {
        const aviso = document.getElementById('aviso');
        aviso.textContent = texto;
        aviso.classList.remove('hidden');
        clearTimeout(timeoutAviso);
        timeoutAviso = setTimeout(() => aviso.classList.add('hidden'), 2500);
    }

    function abrirJanela(id) {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        el.classList.add('flex');
    }
    function fecharJanela(id) {
        const el = document.getElementById(id);
        el.classList.add('hidden');
        el.classList.remove('flex');
    }

    // ===================== Topo =====================
    function alternarPerfil(abrir) {
        const painel = document.getElementById('painel-perfil');
        painel.classList.toggle('hidden', abrir === undefined ? !painel.classList.contains('hidden') : !abrir);
    }
    document.addEventListener('click', (e) => {
        if (!document.getElementById('perfil').contains(e.target)) alternarPerfil(false);
    });

    function desenharTopo() {
        const topo = document.getElementById('topo-mesa');
        const m = mesaAtual();

        if (!m) {
            topo.innerHTML = `
                <button type="button" onclick="trocarAba('mesas')" class="flex items-center gap-3 text-left w-full">
                    <span class="shrink-0 w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="${ICONE_MESA}"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold text-slate-900">Nenhuma mesa selecionada</span>
                        <span class="block text-xs text-slate-400">Toque em Mesas e escolha uma para montar o pedido</span>
                    </span>
                </button>`;
            return;
        }

        const s = STATUS_MESA[m.status] ?? STATUS_MESA.disponivel;
        const tempo = m.status !== 'disponivel' && m.status_desde ? ` · <span data-desde="${escHtml(m.status_desde)}"></span>` : '';
        topo.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="shrink-0 w-12 h-12 rounded-2xl bg-[#10284A] text-amber-50 flex flex-col items-center justify-center">
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-300 leading-none">Mesa</span>
                    <span class="text-lg font-bold leading-none mt-0.5">${m.numero}</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-slate-900 truncate">${escHtml(nomeMesa(m))}</span>
                    <button type="button" onclick="abrirSituacao()" class="inline-flex items-center gap-1.5 mt-0.5 px-2 py-0.5 rounded-full text-xs font-semibold ${s.selo}">
                        <span class="w-1.5 h-1.5 rounded-full ${s.ponto}"></span> ${escHtml(s.rotulo)}${tempo}
                    </button>
                </span>
                <span class="shrink-0 flex flex-col gap-1">
                    <button type="button" onclick="trocarAba('mesas')" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-[#10284A] ring-1 ring-[#10284A]/20 active:bg-slate-50">Trocar</button>
                    ${m.conta && !m.conta.em_preparo ? `<button type="button" onclick="fecharContaMesa()" class="rounded-xl px-3 py-1.5 text-xs font-semibold bg-[#10284A] text-amber-50 active:opacity-90">Fechar conta</button>` : ''}
                </span>
            </div>`;
        relogioMesas.atualizar(topo);
    }

    // ===================== Abas, categorias e busca =====================
    function trocarAba(aba) {
        termos[abaAtual] = campoBusca.value;
        abaAtual = aba;

        document.querySelectorAll('.aba').forEach(botao => {
            const ativa = botao.dataset.aba === aba;
            botao.setAttribute('aria-selected', ativa);
            botao.classList.toggle('bg-[#10284A]', ativa);
            botao.classList.toggle('text-amber-50', ativa);
            botao.classList.toggle('shadow-sm', ativa);
            botao.classList.toggle('text-slate-500', !ativa);
        });
        document.querySelectorAll('[data-painel]').forEach(p => p.classList.toggle('hidden', p.dataset.painel !== aba));

        campoBusca.placeholder = PLACEHOLDER[aba];
        campoBusca.value = termos[aba];
        filtrar();
        window.scrollTo({ top: 0 });
    }

    function marcarBotoes(seletor, atributo, valor) {
        document.querySelectorAll(seletor).forEach(botao => {
            const ativa = botao.dataset[atributo] === valor;
            botao.classList.toggle('bg-slate-900', ativa);
            botao.classList.toggle('text-white', ativa);
            botao.classList.toggle('ring-slate-900', ativa);
            botao.classList.toggle('bg-white', !ativa);
            botao.classList.toggle('text-slate-600', !ativa);
            botao.classList.toggle('ring-slate-200', !ativa);
        });
    }

    function escolherCategoria(id) {
        categoriaAtual = String(id);
        marcarBotoes('.categoria', 'categoria', categoriaAtual);
        filtrar();
    }

    function filtrar() {
        if (abaAtual === 'mesas') return desenharMesas();

        const termo = semAcento(campoBusca.value);
        let visiveis = 0;

        document.querySelectorAll('[data-item]').forEach(item => {
            // Buscando: procura em todas as categorias (o garçom não precisa saber onde o item está)
            const naCategoria = termo !== '' || categoriaAtual === '' || item.dataset.categoria === categoriaAtual;
            const mostra = naCategoria && (termo === '' || item.dataset.busca.includes(termo));
            item.classList.toggle('hidden', !mostra);
            if (mostra) visiveis++;
        });

        document.getElementById('cardapio-vazio')?.classList.toggle('hidden', visiveis > 0);
    }
    campoBusca.addEventListener('input', filtrar);

    // ===================== Mesas =====================
    function desenharMesas() {
        const termo = semAcento(abaAtual === 'mesas' ? campoBusca.value : termos.mesas);
        const lista = document.getElementById('lista-mesas');

        document.querySelectorAll('[data-conta]').forEach(el => {
            const chave = el.dataset.conta;
            el.textContent = mesas.filter(m => !chave || m.status === chave).length;
        });

        const visiveis = mesas.filter(m =>
            (!filtroMesa || m.status === filtroMesa)
            && (!termo || String(m.numero) === termo || semAcento(m.titulo).includes(termo)));

        lista.innerHTML = visiveis.map(m => {
            const s = STATUS_MESA[m.status] ?? STATUS_MESA.disponivel;
            const selecionada = m.id === mesaSelecionada;
            const itensComanda = (comandas[m.id]?.itens || []).reduce((t, l) => t + l.quantidade, 0);
            const tempo = m.status !== 'disponivel' && m.status_desde
                ? `<span class="block text-xs font-medium text-slate-500 mt-1" data-desde="${escHtml(m.status_desde)}"></span>` : '';
            return `
                <button type="button" onclick="selecionarMesa(${m.id})"
                        class="relative text-left bg-white rounded-2xl shadow-sm ring-2 ${selecionada ? 'ring-[#10284A]' : s.borda} p-3 active:scale-95 transition">
                    ${selecionada ? '<span class="absolute top-2 right-2 rounded-full bg-[#10284A] text-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase">Selecionada</span>' : ''}
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 w-14 h-14 rounded-xl bg-[#10284A] text-amber-50 flex flex-col items-center justify-center">
                            <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-300">Mesa</span>
                            <span class="text-xl font-bold leading-none">${m.numero}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 text-sm leading-snug truncate">${escHtml(m.nome || 'Mesa ' + m.numero)}</p>
                            ${m.lugares ? `<p class="text-xs text-slate-400">${m.lugares} ${m.lugares === 1 ? 'lugar' : 'lugares'}</p>` : ''}
                        </div>
                    </div>
                    <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${s.selo}">
                        <span class="w-1.5 h-1.5 rounded-full ${s.ponto}"></span> ${escHtml(s.rotulo)}
                    </span>
                    ${tempo}
                    ${m.pedidos.length ? `<span class="block text-xs text-slate-400 mt-0.5">${m.pedidos.length} ${m.pedidos.length === 1 ? 'pedido' : 'pedidos'}</span>` : ''}
                    ${itensComanda ? `<span class="block text-xs font-semibold text-[#10284A] mt-0.5">${itensComanda} ${itensComanda === 1 ? 'item' : 'itens'} na comanda</span>` : ''}
                </button>`;
        }).join('');

        document.getElementById('mesas-vazio').classList.toggle('hidden', visiveis.length > 0);
        document.getElementById('mesas-vazio-titulo').textContent = mesas.length ? 'Nenhuma mesa encontrada' : 'Nenhuma mesa cadastrada';
        document.getElementById('mesas-vazio-texto').classList.toggle('hidden', mesas.length > 0);
        relogioMesas.atualizar(lista);
    }

    function filtrarStatusMesa(status) {
        filtroMesa = status;
        marcarBotoes('.filtro-mesa', 'filtroMesa', status);
        desenharMesas();
    }

    // Selecionar a mesa leva direto para o cardápio, para montar o pedido
    function selecionarMesa(id) {
        mesaSelecionada = id;
        salvarEstado();
        const m = mesaAtual();
        desenharTopo();
        desenharBarra();
        trocarAba('cardapio');
        avisar(`${nomeMesa(m)} selecionada. Escolha os itens do pedido.`);
    }

    // ===================== Janela do item =====================
    function abrirItem(id) {
        if (!mesaAtual()) {
            avisar('Escolha a mesa primeiro.');
            return trocarAba('mesas');
        }

        const item = ITENS[id];
        itemAberto = id;
        quantidadeItem = 1;

        document.getElementById('item-foto').innerHTML = item.imagem
            ? `<img src="${escHtml(item.imagem)}" alt="" class="w-full h-full object-cover">`
            : `<div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-14 h-14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v8m-3-8v5a3 3 0 0 0 6 0V3M7 11v10M17 21V3c-2 1.5-3 4-3 7v4h3"/></svg></div>`;
        document.getElementById('item-nome').textContent = item.nome;
        document.getElementById('item-preco').textContent = moeda(item.preco);
        document.getElementById('item-descricao').textContent = item.descricao || '';
        document.getElementById('item-sem-estoque').classList.toggle('hidden', !item.semEstoque);
        document.getElementById('item-observacao').value = '';
        desenharQuantidadeItem();
        abrirJanela('modal-item');
    }

    function fecharItem() {
        itemAberto = null;
        fecharJanela('modal-item');
    }

    function mudarQuantidadeItem(delta) {
        quantidadeItem = Math.min(999, Math.max(1, quantidadeItem + delta));
        desenharQuantidadeItem();
    }

    function desenharQuantidadeItem() {
        document.getElementById('item-quantidade').textContent = quantidadeItem;
        document.getElementById('item-adicionar').textContent = `Adicionar à comanda · ${moeda(ITENS[itemAberto].preco * quantidadeItem)}`;
    }

    function adicionarItem() {
        const m = mesaAtual();
        if (!m || !itemAberto) return;

        const observacao = document.getElementById('item-observacao').value.trim().slice(0, 200);
        const comanda = comandas[m.id] ??= { uuid: null, itens: [] };
        // mesmo item com a mesma observação: soma na linha que já existe
        const linha = comanda.itens.find(l => l.id === itemAberto && (l.observacao || '') === observacao);
        if (linha) {
            linha.quantidade = Math.min(999, linha.quantidade + quantidadeItem);
        } else {
            comanda.itens.push({ id: itemAberto, quantidade: quantidadeItem, observacao });
        }
        comanda.uuid = null; // a comanda mudou: é um pedido novo

        salvarEstado();
        const nome = ITENS[itemAberto].nome;
        fecharItem();
        desenharBarra();
        avisar(`${quantidadeItem}× ${nome} adicionado à comanda.`);
    }

    // ===================== Barra e janela da comanda =====================
    function comandaAtual() {
        const m = mesaAtual();
        return m ? (comandas[m.id] ?? { uuid: null, itens: [] }) : { uuid: null, itens: [] };
    }

    const totalComanda = (c) => c.itens.reduce((t, l) => t + ITENS[l.id].preco * l.quantidade, 0);

    function desenharBarra() {
        const m = mesaAtual();
        const c = comandaAtual();
        const quantidade = c.itens.reduce((t, l) => t + l.quantidade, 0);
        const mostrar = !!m && quantidade > 0;

        document.getElementById('barra-comanda').classList.toggle('hidden', !mostrar);
        // espaço no fim da página para a barra não cobrir o último item
        document.getElementById('conteudo').classList.toggle('pb-28', mostrar);
        document.getElementById('conteudo').classList.toggle('pb-8', !mostrar);
        if (!mostrar) return;

        document.getElementById('barra-quantidade').textContent = quantidade;
        document.getElementById('barra-mesa').textContent = `Comanda ${nomeMesa(m)}`;
        document.getElementById('barra-total').textContent = moeda(totalComanda(c));
    }

    function abrirComanda() {
        document.getElementById('comanda-erro').classList.add('hidden');
        desenharComanda();
        abrirJanela('modal-comanda');
    }

    function fecharComanda() {
        fecharJanela('modal-comanda');
    }

    function desenharComanda() {
        const m = mesaAtual();
        const c = comandaAtual();
        if (!m) return fecharComanda();

        document.getElementById('comanda-mesa').textContent = nomeMesa(m);
        document.getElementById('comanda-cliente').value = c.cliente || '';
        document.getElementById('comanda-total').textContent = moeda(totalComanda(c));
        document.getElementById('comanda-finalizar').disabled = c.itens.length === 0;

        document.getElementById('comanda-itens').innerHTML = c.itens.length ? c.itens.map((l, i) => {
            const item = ITENS[l.id];
            return `
                <div class="py-3 border-b border-slate-100 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 leading-snug">${escHtml(item.nome)}</p>
                            ${l.observacao ? `<p class="text-sm text-slate-500 mt-0.5">Obs.: ${escHtml(l.observacao)}</p>` : ''}
                            <p class="text-xs text-slate-400 mt-0.5">${escHtml(moeda(item.preco))} cada</p>
                        </div>
                        <p class="shrink-0 font-bold text-slate-900">${escHtml(moeda(item.preco * l.quantidade))}</p>
                    </div>
                    <div class="mt-2 flex items-center gap-3">
                        <button type="button" onclick="mudarQuantidadeLinha(${i}, -1)" aria-label="Diminuir" class="w-9 h-9 rounded-xl ring-1 ring-slate-200 text-lg font-bold text-slate-700 active:bg-slate-100">−</button>
                        <span class="w-8 text-center font-bold text-slate-900">${l.quantidade}</span>
                        <button type="button" onclick="mudarQuantidadeLinha(${i}, 1)" aria-label="Aumentar" class="w-9 h-9 rounded-xl bg-[#10284A] text-amber-50 text-lg font-bold active:opacity-80">+</button>
                        <button type="button" onclick="removerLinha(${i})" class="ml-auto text-sm font-semibold text-red-600 px-2 py-1 rounded-lg active:bg-red-50">Remover</button>
                    </div>
                </div>`;
        }).join('') : '<p class="py-10 text-center text-sm text-slate-400">A comanda está vazia.</p>';
    }

    function mudarQuantidadeLinha(indice, delta) {
        const c = comandaAtual();
        const linha = c.itens[indice];
        if (!linha) return;
        if (linha.quantidade + delta < 1) return removerLinha(indice);
        linha.quantidade = Math.min(999, linha.quantidade + delta);
        c.uuid = null;
        salvarEstado();
        desenharComanda();
        desenharBarra();
    }

    function removerLinha(indice) {
        const c = comandaAtual();
        const linha = c.itens[indice];
        if (!linha || !confirm(`Remover ${ITENS[linha.id].nome} da comanda?`)) return;
        c.itens.splice(indice, 1);
        c.uuid = null;
        salvarEstado();
        desenharComanda();
        desenharBarra();
        if (!c.itens.length) fecharComanda();
    }

    async function finalizarPedido() {
        const m = mesaAtual();
        const c = comandaAtual();
        if (!m || !c.itens.length) return;

        const botao = document.getElementById('comanda-finalizar');
        const erro = document.getElementById('comanda-erro');
        botao.disabled = true;
        botao.textContent = 'Enviando...';
        erro.classList.add('hidden');

        // O mesmo uuid em toda tentativa deste pedido: reenviar não duplica
        c.uuid ??= novoUuid();
        c.cliente = document.getElementById('comanda-cliente').value.trim().slice(0, 60);
        comandas[m.id] = c;
        salvarEstado();

        try {
            const resp = await fetch(URL_PEDIDO.replace('__ID__', m.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    uuid: c.uuid,
                    cliente: c.cliente || null,
                    itens: c.itens.map(l => ({ cardapio_item_id: l.id, quantidade: l.quantidade, observacao: l.observacao || null })),
                }),
            });
            if (resp.redirected) return location.reload(); // sessão expirou: vai para o login
            const dados = await resp.json().catch(() => ({}));
            if (!resp.ok) throw new Error(dados.message || 'Não foi possível enviar o pedido. Tente de novo.');

            // Enviado: limpa a comanda da mesa e volta para as mesas
            delete comandas[m.id];
            mesas = mesas.map(x => x.id === dados.mesa.id ? dados.mesa : x);
            mesaSelecionada = null;
            salvarEstado();
            fecharComanda();
            desenharTopo();
            desenharBarra();
            trocarAba('mesas');
            avisar(`Pedido da ${nomeMesa(dados.mesa)} enviado.`);
        } catch (e) {
            erro.textContent = e.message === 'Failed to fetch' ? 'Sem conexão com o servidor. Confira o Wi-Fi e toque em Finalizar de novo.' : e.message;
            erro.classList.remove('hidden');
        } finally {
            botao.disabled = false;
            botao.textContent = 'Finalizar pedido';
        }
    }

    // ===================== Situação da mesa (tocando no selo do topo) =====================
    function abrirSituacao() {
        if (!mesaAtual()) return;
        document.getElementById('mesa-erro').classList.add('hidden');
        desenharSituacao();
        abrirJanela('modal-mesa');
    }

    function fecharSituacao() {
        fecharPedido();
        fecharJanela('modal-mesa');
    }

    const horaPedido = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
    // Situação de cada pedido: esperando (com o tempo), pronto para buscar, entregue
    const seloPedido = (p) => ({
        preparo:  `<span class="shrink-0 rounded-full bg-amber-50 text-amber-700 px-2.5 py-1 text-xs font-bold">Em preparo <span data-desde="${escHtml(p.enviado_em)}"></span></span>`,
        pronto:   `<span class="shrink-0 rounded-full bg-blue-600 text-white px-2.5 py-1 text-xs font-bold">Pronto! Pode buscar</span>`,
        entregue: `<span class="shrink-0 rounded-full bg-indigo-50 text-indigo-700 px-2.5 py-1 text-xs font-semibold">Entregue ${escHtml(horaPedido(p.entregue_em || p.enviado_em))}</span>`,
    })[p.status] ?? '';

    // ===================== Itens de um pedido (tocando no cartão do pedido na janela da mesa) =====================
    let pedidoAberto = null;

    function abrirPedido(id) {
        pedidoAberto = id;
        if (desenharPedido()) abrirJanela('modal-pedido');
    }

    function fecharPedido() {
        pedidoAberto = null;
        fecharJanela('modal-pedido');
    }

    // Redesenha com os dados atuais (a situação pode mudar enquanto a janela está aberta)
    function desenharPedido() {
        const p = mesaAtual()?.pedidos.find(x => x.id === pedidoAberto);
        if (!p) { fecharPedido(); return false; }

        document.getElementById('pedido-titulo').textContent = `${p.numero}º pedido`;
        document.getElementById('pedido-sub').textContent = `#${p.id} · ${horaPedido(p.enviado_em)}${p.garcom ? ' · ' + p.garcom : ''}`;
        document.getElementById('pedido-selo').innerHTML = seloPedido(p);
        document.getElementById('pedido-cliente').textContent = p.cliente ? 'Cliente: ' + p.cliente : '';
        document.getElementById('pedido-cliente').classList.toggle('hidden', !p.cliente);
        document.getElementById('pedido-itens').innerHTML = p.itens.map(i => `
            <div class="py-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-base text-slate-900"><span class="font-bold">${i.quantidade}×</span> ${escHtml(i.nome)}</p>
                    ${i.preco_unitario != null ? `<p class="text-xs text-slate-400">${escHtml(moeda(i.preco_unitario))} cada</p>` : ''}
                    ${i.observacao ? `<p class="mt-1 text-sm text-amber-700">Obs.: ${escHtml(i.observacao)}</p>` : ''}
                </div>
                ${i.subtotal != null ? `<span class="shrink-0 font-semibold text-slate-800">${escHtml(moeda(i.subtotal))}</span>` : ''}
            </div>`).join('');
        document.getElementById('pedido-total').textContent = moeda(p.total);
        relogioMesas.atualizar(document.getElementById('modal-pedido'));
        return true;
    }

    function desenharSituacao() {
        const m = mesaAtual();
        if (!m) return fecharSituacao();

        const s = STATUS_MESA[m.status] ?? STATUS_MESA.disponivel;
        document.getElementById('mesa-numero').textContent = m.numero;
        document.getElementById('mesa-nome').textContent = m.nome || 'Mesa ' + m.numero;
        document.getElementById('mesa-lugares').textContent = m.lugares ? `${m.lugares} ${m.lugares === 1 ? 'lugar' : 'lugares'}` : '';

        const espera = document.getElementById('mesa-espera');
        espera.className = `mt-5 rounded-2xl px-4 py-3 ${s.selo}`;
        espera.innerHTML = m.status === 'disponivel'
            ? `<p class="font-semibold">${escHtml(s.rotulo)}</p><p class="text-sm opacity-80">Mesa livre para receber clientes.</p>`
            : `<p class="font-semibold">${escHtml(s.rotulo)}</p>
               <p class="text-sm opacity-80"><span data-desde="${escHtml(m.status_desde || '')}"></span>${m.status_por ? ' · ' + escHtml(m.status_por) : ''}</p>`;

        // Cada pedido é um cartão resumido; tocando nele abre a janela com os itens
        document.getElementById('mesa-pedidos').innerHTML = m.pedidos.map(p => {
            const qtd = p.itens.reduce((t, i) => t + Number(i.quantidade), 0);
            return `
            <button type="button" onclick="abrirPedido(${p.id})"
                    class="w-full text-left rounded-2xl ring-1 ${p.status === 'pronto' ? 'ring-2 ring-blue-500' : 'ring-slate-200'} px-4 py-3 bg-white active:bg-slate-50 hover:ring-[#10284A]/40 transition">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-slate-900">${p.numero}º pedido <span class="font-normal text-slate-400 text-sm">· #${p.id} · ${escHtml(horaPedido(p.enviado_em))}</span></p>
                    ${seloPedido(p)}
                </div>
                <div class="mt-1 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-400 min-w-0 truncate">${qtd} ${qtd === 1 ? 'item' : 'itens'} · ${p.cliente ? escHtml(p.cliente) + ' · ' : ''}${p.garcom ? escHtml(p.garcom) + ' · ' : ''}<span class="font-semibold text-slate-600">${escHtml(moeda(p.total))}</span></p>
                    <span class="shrink-0 text-xs font-semibold text-[#10284A]">Ver itens ›</span>
                </div>
            </button>`;
        }).join('');

        const botaoConta = document.getElementById('mesa-fechar-conta');
        botaoConta.classList.toggle('hidden', !m.conta);
        // A conta só fecha quando todos os pedidos da mesa estão prontos (ou entregues)
        if (m.conta) {
            const emPreparo = m.conta.em_preparo || 0;
            botaoConta.disabled = emPreparo > 0;
            botaoConta.textContent = emPreparo
                ? `Fechar conta · aguardando ${emPreparo} ${emPreparo === 1 ? 'pedido ficar pronto' : 'pedidos ficarem prontos'}`
                : `Fechar conta · ${moeda(m.conta.total)}`;
        }

        relogioMesas.atualizar(document.getElementById('modal-mesa'));
    }

    // ===================== Fechar conta (o cliente pagou na mesa) =====================
    function fecharContaMesa() {
        const m = mesaAtual();
        if (!m?.conta) return;
        if (m.conta.em_preparo) {
            avisar('A conta só pode ser fechada quando todos os pedidos estiverem prontos.');
            return;
        }

        const pendentes = (comandas[m.id]?.itens || []).length;
        if (pendentes && !confirm(`A comanda desta mesa tem ${pendentes} ${pendentes === 1 ? 'item que ainda não foi enviado' : 'itens que ainda não foram enviados'} e não entra na conta. Fechar a conta mesmo assim?`)) return;

        FecharConta.abrir({
            urlConta: URL_CONTA.replace('__ID__', m.id),
            urlFechar: URL_FECHAR_CONTA.replace('__ID__', m.id),
            aoFechar: (dados) => {
                mesas = mesas.map(x => x.id === dados.mesa.id ? dados.mesa : x);
                delete comandas[m.id];
                mesaSelecionada = null;
                salvarEstado();
                atualizarTudo();
                trocarAba('mesas');
                avisar(`Conta da ${nomeMesa(dados.mesa)} fechada. Mesa liberada.`);
            },
        });
    }

    // ===================== Aviso de pedido pronto =====================
    // Guarda a situação de cada pedido; quando um pedido deste garçom vira "pronto", avisa (som, vibração e faixa)
    let situacaoPedidos = null;

    function pedidosProntosNovos() {
        const atual = new Map();
        mesas.forEach(m => m.pedidos.forEach(p => atual.set(p.id, { ...p, mesa: m })));

        const novos = situacaoPedidos === null ? [] : [...atual.values()].filter(p =>
            p.status === 'pronto' && situacaoPedidos.get(p.id) !== 'pronto' && (p.garcom_id === MEU_ID || p.garcom_id === null));

        situacaoPedidos = new Map([...atual].map(([id, p]) => [id, p.status]));
        return novos;
    }

    function bipe() {
        try {
            const ctx = bipe.ctx ??= new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.2, 0.4].forEach(atraso => {
                const osc = ctx.createOscillator();
                const vol = ctx.createGain();
                osc.frequency.value = 988;
                vol.gain.setValueAtTime(0.2, ctx.currentTime + atraso);
                vol.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + atraso + 0.15);
                osc.connect(vol).connect(ctx.destination);
                osc.start(ctx.currentTime + atraso);
                osc.stop(ctx.currentTime + atraso + 0.16);
            });
        } catch (e) {}
    }
    // O navegador só libera o som depois de um toque na tela: prepara o áudio no 1º toque
    document.addEventListener('pointerdown', () => {
        try { (bipe.ctx ??= new (window.AudioContext || window.webkitAudioContext)()).resume(); } catch (e) {}
    }, { once: true });

    function avisarProntos(prontos) {
        if (!prontos.length) return;
        const faixa = document.getElementById('aviso-pronto');
        faixa.innerHTML = prontos.map(p => `
            <button type="button" onclick="verPronto(${p.mesa.id})" class="w-full text-left flex items-center gap-3 rounded-2xl bg-blue-600 text-white px-4 py-3 shadow-2xl">
                <svg class="w-7 h-7 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold">Pedido pronto! Pode buscar</span>
                    <span class="block text-sm text-blue-100 truncate">${escHtml(nomeMesa(p.mesa))} · ${p.numero}º pedido (#${p.id})</span>
                </span>
                <span class="text-sm font-semibold">Ver</span>
            </button>`).join('');
        faixa.classList.remove('hidden');
        bipe();
        try { navigator.vibrate?.([300, 150, 300]); } catch (e) {}
    }

    function verPronto(mesaId) {
        document.getElementById('aviso-pronto').classList.add('hidden');
        if (mesaSelecionada !== mesaId) {
            mesaSelecionada = mesaId;
            salvarEstado();
            desenharTopo();
            desenharBarra();
        }
        abrirSituacao();
    }

    // ===================== Atualização =====================
    function atualizarTudo() {
        // mesa inativada/excluída no painel: deixa de estar selecionada
        if (mesaSelecionada && !mesaAtual()) {
            mesaSelecionada = null;
            salvarEstado();
            fecharComanda();
            fecharSituacao();
        }
        desenharTopo();
        desenharMesas();
        desenharBarra();
        if (!document.getElementById('modal-mesa').classList.contains('hidden')) desenharSituacao();
        if (pedidoAberto !== null) desenharPedido();
    }

    // Outros garçons também mudam as mesas: busca a situação de novo a cada 15 s (só com a tela visível)
    async function recarregarMesas() {
        if (document.hidden) return;
        try {
            const resp = await fetch(URL_MESAS, { headers: { Accept: 'application/json' } });
            if (resp.redirected) return location.reload(); // sessão expirou: vai para o login
            if (!resp.ok) return;
            mesas = (await resp.json()).mesas;
            atualizarTudo();
            avisarProntos(pedidosProntosNovos());
        } catch (e) { /* sem conexão: tenta de novo no próximo ciclo */ }
    }
    setInterval(recarregarMesas, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) recarregarMesas(); });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        // Com os itens do pedido abertos, o Esc volta só para a janela da mesa
        if (pedidoAberto !== null) return fecharPedido();
        fecharItem(); fecharComanda(); fecharSituacao(); alternarPerfil(false);
    });

    // ===================== Início =====================
    // Nome do cliente fica guardado com a comanda (mudou: é um pedido novo)
    document.getElementById('comanda-cliente').addEventListener('input', (e) => {
        const m = mesaAtual();
        if (!m || !comandas[m.id]) return;
        comandas[m.id].cliente = e.target.value.slice(0, 60);
        comandas[m.id].uuid = null;
        salvarEstado();
    });

    carregarEstado();
    pedidosProntosNovos(); // marca a situação atual (só avisa o que ficar pronto daqui em diante)
    escolherCategoria('');
    filtrarStatusMesa('');
    desenharTopo();
    desenharBarra();
    // Com mesa escolhida, abre no cardápio; sem mesa, nas mesas
    trocarAba(mesaSelecionada ? 'cardapio' : 'mesas');
</script>
</body>
</html>
