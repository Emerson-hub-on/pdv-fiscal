{{-- Símbolo da marca Cyberly (opção "Sinal"): um "C" de circuito com o ponto de conexão saindo pela abertura.
     Uso: @include('partials.logo-simbolo', ['tamanho' => 32, 'fundo' => 'escuro'])
     fundo: 'escuro' (detalhes em creme, para fundos escuros) | 'claro' (detalhes em azul-escuro, para fundos claros).
     O mesmo desenho está em public/favicon.svg. --}}
@php
    $tamanho = $tamanho ?? 32;
    $detalhe = ($fundo ?? 'escuro') === 'claro' ? '#0F172A' : '#FFFBEB';
    $destaque = $destaque ?? '#8B5CF6';
@endphp
<svg viewBox="0 0 64 64" width="{{ $tamanho }}" height="{{ $tamanho }}" class="shrink-0 {{ $classe ?? '' }}"
     @if (!empty($rotulo)) role="img" aria-label="{{ $rotulo }}" @else aria-hidden="true" @endif>
    <path d="M44.7 19.3 A18 18 0 1 0 44.7 44.7" fill="none" stroke="{{ $destaque }}" stroke-width="6" stroke-linecap="round"/>
    <path d="M30 32 H42" stroke="{{ $detalhe }}" stroke-width="4" stroke-linecap="round"/>
    <circle cx="30" cy="32" r="5" fill="{{ $detalhe }}"/>
    <circle cx="50" cy="32" r="4" fill="none" stroke="{{ $detalhe }}" stroke-width="3"/>
</svg>
