@extends('layouts.app')

@section('titulo', 'Gerar SPED')

@section('conteudo')
    @php
        $campo  = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $card   = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6';
        $erros  = $resultado ? collect($resultado['problemas'])->where('nivel', 'erro') : collect();
        $avisos = $resultado ? collect($resultado['problemas'])->where('nivel', 'aviso') : collect();
        $podeGerar = $resultado && $erros->isEmpty();
    @endphp

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Gerar SPED</h1>
        <a href="{{ route('sped-config.editar') }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            Configuração do SPED
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!$config || !$config->contadorCompleto())
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            Antes de gerar o arquivo, preencha os dados do contador e da atividade em
            <a href="{{ route('sped-config.editar') }}" class="font-semibold underline">Configuração do SPED</a>.
        </div>
    @endif

    <form method="GET" action="{{ route('sped.index') }}" id="form-sped" class="{{ $card }} mb-6">
        <input type="hidden" name="verificar" value="1">

        <div class="grid grid-cols-3 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Escrituração</label>
                <select name="tipo" class="{{ $campo }} bg-white">
                    @foreach ($tipos as $valor => $rotulo)
                        <option value="{{ $valor }}" {{ request('tipo', 'icms_ipi') === $valor ? 'selected' : '' }}>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Competência <span class="text-red-500">*</span></label>
                <input type="month" name="competencia" required value="{{ old('competencia', $competencia) }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Finalidade do arquivo</label>
                <select name="finalidade" class="{{ $campo }} bg-white">
                    <option value="0" {{ old('finalidade', request('finalidade', '0')) === '0' ? 'selected' : '' }}>Remessa do arquivo original</option>
                    <option value="1" {{ old('finalidade', request('finalidade')) === '1' ? 'selected' : '' }}>Remessa do arquivo substituto</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex items-center gap-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Verificar dados do período
            </button>
            <p class="text-xs text-gray-400">Primeiro a verificação; o arquivo só pode ser gerado quando não houver erros.</p>
        </div>
    </form>

    @if ($resultado)
        <div class="{{ $card }} mb-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                Período {{ $resultado['inicio']->format('d/m/Y') }} a {{ $resultado['fim']->format('d/m/Y') }}
            </h2>

            <div class="grid grid-cols-3 gap-4 text-sm">
                @foreach ($resultado['resumo'] as $grupo => $contagem)
                    <div class="border border-gray-200 rounded-lg px-4 py-3">
                        <p class="text-gray-400 text-xs mb-1">{{ $grupo }}</p>
                        @forelse ($contagem as $rotulo => $total)
                            <p class="text-gray-800"><span class="font-medium">{{ $total }}</span> <span class="text-gray-500">{{ $rotulo }}</span></p>
                        @empty
                            <p class="text-gray-400">nenhum no período</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ===================== GERAÇÃO ===================== --}}
        @if ($podeGerar)
            <form method="POST" action="{{ route('sped.gerar') }}" class="{{ $card }} mb-6">
                @csrf
                <input type="hidden" name="tipo" value="{{ request('tipo', 'icms_ipi') }}">
                <input type="hidden" name="competencia" value="{{ request('competencia', $competencia) }}">
                <input type="hidden" name="finalidade" value="{{ request('finalidade', '0') }}">

                <h2 class="text-sm font-semibold text-gray-800 mb-1">Gerar arquivo</h2>
                <p class="text-xs text-gray-400 mb-4">Nenhum erro encontrado. Confira os dados da apuração e gere o arquivo para validar no PVA.</p>

                <div class="grid grid-cols-3 gap-5">
                    <div class="col-span-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="destacar_icms" value="0">
                            <input type="checkbox" name="destacar_icms" value="1" {{ old('destacar_icms', $destacarPadrao) ? 'checked' : '' }}
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">Destacar ICMS (débitos e créditos)</span>
                                <span class="block text-xs text-gray-400 mt-0.5">
                                    Marcado por padrão no Lucro Presumido e no Lucro Real. Desmarcado (Simples Nacional), base e ICMS saem zerados
                                    e a apuração fica sem valores. O IPI das entradas é sempre informado como está na nota.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Saldo credor do mês anterior</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                            <input type="number" step="0.01" min="0" name="saldo_credor_anterior"
                                   value="{{ old('saldo_credor_anterior', number_format($saldoAnteriorPadrao, 2, '.', '')) }}"
                                   class="{{ $campo }} pl-9">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Preenchido com o saldo do último arquivo gerado para o mês anterior, quando houver.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vencimento do ICMS</label>
                        <input type="date" name="dt_vencimento" value="{{ old('dt_vencimento') }}" class="{{ $campo }}">
                        <p class="text-xs text-gray-400 mt-1">Obrigatório só se houver ICMS a recolher no mês.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Inventário (Bloco H)</label>
                        <select name="inventario_id" class="{{ $campo }} bg-white">
                            <option value="">Não informar inventário</option>
                            @foreach ($inventarios as $inv)
                                <option value="{{ $inv->id }}" {{ (string) old('inventario_id', $inventarioSugerido) === (string) $inv->id ? 'selected' : '' }}>
                                    {{ $inv->data->format('d/m/Y') }} — R$ {{ number_format($inv->valor_total, 2, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            O inventário de 31/12 vai no arquivo de fevereiro.
                            <a href="{{ route('inventarios.index') }}" class="text-blue-600 hover:text-blue-700">Gerenciar inventários</a>
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                        Gerar arquivo do SPED
                    </button>
                </div>
            </form>
        @elseif ($erros->isEmpty() && $avisos->isEmpty())
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                Nenhum problema encontrado nos dados deste período.
            </div>
        @endif

        @foreach ([['Erros — impedem a geração do arquivo', $erros, 'red'], ['Avisos — vale conferir', $avisos, 'amber']] as [$tituloBloco, $lista, $cor])
            @continue($lista->isEmpty())

            <div class="mb-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">{{ $tituloBloco }} ({{ $lista->count() }})</h2>

                <div class="space-y-3">
                    @foreach ($lista as $problema)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs text-gray-400">{{ $problema['grupo'] }}</p>
                                    <p class="text-sm font-medium {{ $cor === 'red' ? 'text-red-700' : 'text-amber-700' }}">{{ $problema['titulo'] }}</p>
                                </div>
                                <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-medium {{ $cor === 'red' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $problema['quantidade'] }}
                                </span>
                            </div>

                            <ul class="mt-3 space-y-1 text-sm text-gray-700">
                                @foreach ($problema['exemplos'] as $exemplo)
                                    <li class="flex items-center justify-between gap-3">
                                        <span>{{ $exemplo['texto'] }}</span>
                                        @if (!empty($exemplo['url']))
                                            <a href="{{ $exemplo['url'] }}" target="_blank" class="text-blue-600 hover:text-blue-700 font-medium text-xs shrink-0">Abrir</a>
                                        @endif
                                    </li>
                                @endforeach
                                @if ($problema['quantidade'] > count($problema['exemplos']))
                                    <li class="text-xs text-gray-400">e mais {{ $problema['quantidade'] - count($problema['exemplos']) }}...</li>
                                @endif
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    {{-- ===================== HISTÓRICO ===================== --}}
    @if ($geracoes->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Arquivos gerados</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-700 font-medium text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Competência</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Finalidade</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">ICMS a recolher</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Saldo credor</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Gerado em</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($geracoes as $g)
                        <tr class="hover:bg-gray-200 transition">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $g->competencia->format('m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $g->finalidade === '1' ? 'Substituto' : 'Original' }}</td>
                            <td class="px-4 py-3 text-gray-600">R$ {{ number_format($g->icms_recolher, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-gray-600">R$ {{ number_format($g->saldo_credor_transportar, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $g->created_at->format('d/m/Y H:i') }} · {{ $g->usuario?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('sped.baixar', $g) }}" class="text-blue-600 hover:text-blue-700 font-medium">Baixar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection