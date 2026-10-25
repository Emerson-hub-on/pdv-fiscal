{{-- Gráfico de barras verticais em SVG. Cada elemento leva a classe Tailwind (tela) e o atributo equivalente (PDF, sem Tailwind). --}}
@php $g = $grafico; @endphp

<svg viewBox="0 0 {{ $g['largura'] }} {{ $g['altura'] }}" width="{{ $g['largura'] }}" height="{{ $g['altura'] }}"
     class="w-full h-auto" role="img" aria-label="Evolução mensal: {{ $g['metrica'] }}" xmlns="http://www.w3.org/2000/svg">

    {{-- Linhas de grade e valores do eixo vertical --}}
    @foreach ($g['ticks'] as $tick)
        <line x1="{{ $g['x0'] }}" x2="{{ $g['x1'] }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"
              class="stroke-gray-200" stroke="#e5e7eb" stroke-width="1" />
        <text x="{{ $g['x0'] - 8 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end" font-size="10"
              class="fill-gray-500" fill="#6b7280">{{ $tick['rotulo'] }}</text>
    @endforeach

    {{-- Barras --}}
    @foreach ($g['barras'] as $b)
        @if ($g['comparar'])
            <rect x="{{ $b['anterior']['x'] }}" y="{{ $b['anterior']['y'] }}" width="{{ $b['anterior']['w'] }}" height="{{ $b['anterior']['h'] }}" rx="2"
                  class="fill-gray-300 hover:opacity-80" fill="#d1d5db"><title>{{ $b['anterior']['tooltip'] }}</title></rect>
        @endif

        <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}" rx="2"
              class="{{ $b['melhor'] ? 'fill-emerald-600' : 'fill-emerald-400' }} hover:opacity-80"
              fill="{{ $b['melhor'] ? '#059669' : '#34d399' }}"
              @if ($b['parcial']) fill-opacity="0.6" @endif><title>{{ $b['tooltip'] }}</title></rect>

        @if ($g['mostrar_valores'] && $b['h'] > 0)
            <text x="{{ $b['x_valor'] }}" y="{{ $b['y_valor'] }}" text-anchor="middle" font-size="9"
                  class="fill-gray-600" fill="#4b5563">{{ $b['valor_rotulo'] }}</text>
        @endif

        <text x="{{ $b['x_rotulo'] }}" y="{{ $b['y_rotulo'] }}" text-anchor="middle" font-size="10"
              class="fill-gray-600" fill="#4b5563">{{ $b['rotulo'] }}</text>
    @endforeach

    {{-- Eixo horizontal --}}
    <line x1="{{ $g['x0'] }}" x2="{{ $g['x1'] }}" y1="{{ $g['y_base'] }}" y2="{{ $g['y_base'] }}"
          class="stroke-gray-400" stroke="#9ca3af" stroke-width="1" />

    @if ($g['vazio'])
        <text x="{{ $g['largura'] / 2 }}" y="{{ $g['altura'] / 2 }}" text-anchor="middle" font-size="13"
              class="fill-gray-400" fill="#9ca3af">Nenhuma venda emitida no período</text>
    @endif
</svg>

<div class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-500">
    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-emerald-400"></span>{{ $g['metrica'] }}</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-emerald-600"></span>Melhor mês</span>
    @if ($g['comparar'])
        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-gray-300"></span>Mesmo mês do ano anterior</span>
    @endif
    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-emerald-400 opacity-60"></span>Mês em andamento</span>
</div>
