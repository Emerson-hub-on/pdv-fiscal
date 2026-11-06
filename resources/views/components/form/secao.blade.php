@props(['titulo', 'icone' => 'documento'])
{{-- Título de seção dos formulários: ícone azul-marinho + título + descrição (slot). Slot "acoes" vai à direita. --}}
<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 mb-4']) }}>
    <div class="flex items-start gap-3 min-w-0">
        <span class="shrink-0 w-8 h-8 rounded-lg bg-[#10284A] text-amber-50 flex items-center justify-center">
            <x-form.icone :nome="$icone" />
        </span>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-800 leading-tight">{{ $titulo }}</h2>
            @if ($slot->isNotEmpty())
                <p class="text-xs text-slate-500 mt-0.5">{{ $slot }}</p>
            @endif
        </div>
    </div>
    @isset($acoes)
        <div class="shrink-0">{{ $acoes }}</div>
    @endisset
</div>
