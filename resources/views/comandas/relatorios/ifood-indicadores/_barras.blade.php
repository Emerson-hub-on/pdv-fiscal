{{-- Gráfico de barras verticais em SVG (uma série). Recebe:
       $barras: [['rotulo', 'valor', 'tooltip', 'destaque' => bool, 'apagado' => bool], ...]
       $vazio: texto quando não há valor; $altura (opcional, padrão 220); $cadaRotulo: mostra 1 rótulo a cada N barras. --}}
@php
    $altura = $altura ?? 220;
    $n = max(1, count($barras));
    $x0 = 36; $topo = 12; $base = $altura - 26;
    // O viewBox tem a largura parecida com a da tela ($larguraAlvo), para o texto não esticar nem encolher
    $larguraAlvo = $larguraAlvo ?? 960;
    $passo = max(20, ($larguraAlvo - $x0 - 8) / $n); // largura de cada coluna
    $largura = $x0 + $n * $passo + 8;
    $maior = max(array_column($barras, 'valor') ?: [0]);

    // Escala "redonda" para o eixo: 1, 2, 5 × 10^k
    $alvo = max(1, $maior) / 4;
    $pot = 10 ** floor(log10($alvo));
    $intervalo = collect([1, 2, 5, 10])->map(fn ($m) => $m * $pot)->first(fn ($v) => $v >= $alvo);
    $intervalo = max(1, $intervalo);
    $teto = max($intervalo, ceil(max(1, $maior) / $intervalo) * $intervalo);
    $y = fn ($v) => $base - ($v / $teto) * ($base - $topo);
    $cadaRotulo = $cadaRotulo ?? ($n > 16 ? 2 : 1);
    $larguraBarra = min(48, $passo * 0.7);
    $folga = ($passo - $larguraBarra) / 2;
@endphp

<svg viewBox="0 0 {{ $largura }} {{ $altura }}" class="w-full h-auto" role="img" aria-label="{{ $titulo ?? 'Gráfico' }}" xmlns="http://www.w3.org/2000/svg">
    @for ($v = 0; $v <= $teto; $v += $intervalo)
        <line x1="{{ $x0 }}" x2="{{ $largura - 4 }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="{{ $v == 0 ? 'stroke-gray-300' : 'stroke-gray-100' }}" stroke-width="1" />
        <text x="{{ $x0 - 6 }}" y="{{ $y($v) + 3 }}" text-anchor="end" font-size="10" class="fill-gray-400">{{ number_format($v, 0, ',', '.') }}</text>
    @endfor

    @foreach ($barras as $i => $b)
        @php
            $bx = $x0 + $i * $passo + $folga;
            $by = $y($b['valor']);
            $bh = max(0, $base - $by);
        @endphp
        <g class="group">
            {{-- Área de toque maior que a barra --}}
            <rect x="{{ $x0 + $i * $passo }}" y="{{ $topo }}" width="{{ $passo }}" height="{{ $base - $topo }}" class="fill-transparent group-hover:fill-gray-50" />
            @if ($bh > 0)
                <path d="M{{ $bx }},{{ $base }} V{{ $by + min(4, $bh) }} Q{{ $bx }},{{ $by }} {{ $bx + min(4, $bh) }},{{ $by }} H{{ $bx + $larguraBarra - min(4, $bh) }} Q{{ $bx + $larguraBarra }},{{ $by }} {{ $bx + $larguraBarra }},{{ $by + min(4, $bh) }} V{{ $base }} Z"
                      class="{{ !empty($b['destaque']) ? 'fill-emerald-600' : 'fill-emerald-400' }} {{ !empty($b['apagado']) ? 'opacity-40' : '' }} group-hover:fill-emerald-700" />
            @endif
            @if ($i % $cadaRotulo === 0)
                <text x="{{ $bx + $larguraBarra / 2 }}" y="{{ $base + 15 }}" text-anchor="middle" font-size="10" class="fill-gray-500">{{ $b['rotulo'] }}</text>
            @endif
            <title>{{ $b['tooltip'] }}</title>
        </g>
    @endforeach

    @if ($maior <= 0)
        <text x="{{ $largura / 2 }}" y="{{ ($topo + $base) / 2 }}" text-anchor="middle" font-size="13" class="fill-gray-400">{{ $vazio ?? 'Sem pedidos no período' }}</text>
    @endif
</svg>
