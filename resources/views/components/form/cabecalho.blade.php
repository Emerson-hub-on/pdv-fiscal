@props([
    'titulo',
    'rotulo' => null,        // linha pequena acima do título (ex.: "Financeiro · Contas a pagar")
    'voltar' => null,        // URL do botão voltar
    'voltarTitulo' => 'Voltar',
    'selo' => null,          // ex.: "Rascunho", "Pedido nº 12"
    'solto' => false,        // true: faixa independente acima do conteúdo (telas cujo formulário tem vários cartões)
])
{{-- Faixa azul-marinho no topo do cartão principal do formulário. O slot recebe ações à direita. --}}
<div class="flex items-center justify-between gap-4 bg-[#10284A] px-6 py-4 {{ $solto ? 'rounded-xl shadow-sm mb-6' : 'rounded-t-xl' }}">
    <div class="flex items-center gap-3 min-w-0">
        @if ($voltar)
            <a href="{{ $voltar }}" title="{{ $voltarTitulo }}"
               class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-slate-300 hover:bg-amber-50/10 hover:text-amber-50 transition">
                <x-form.icone nome="voltar" tamanho="w-5 h-5" />
            </a>
        @endif
        <div class="min-w-0">
            @if ($rotulo)
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $rotulo }}</p>
            @endif
            <h1 class="text-lg font-semibold text-amber-50 leading-tight truncate">{{ $titulo }}</h1>
        </div>
        @if ($selo)
            <span class="shrink-0 rounded-full bg-amber-50/10 ring-1 ring-amber-50/25 text-amber-50 px-2.5 py-0.5 text-xs font-medium">
                {{ $selo }}
            </span>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex items-center gap-2 shrink-0">{{ $slot }}</div>
    @endif
</div>
