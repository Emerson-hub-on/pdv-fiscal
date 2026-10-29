@extends('layouts.app')

@section('titulo', 'Gerar SPED')

@section('conteudo')
    @php
        $campo  = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $card   = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6';
        $erros  = $resultado ? collect($resultado['problemas'])->where('nivel', 'erro') : collect();
        $avisos = $resultado ? collect($resultado['problemas'])->where('nivel', 'aviso') : collect();
        $podeGerar = $resultado && $erros->isEmpty();

        // Abas: as da verificação só existem depois de verificar; o histórico existe sempre.
        // cor = classes da aba ativa (borda + texto); a de erros fica vermelha mesmo inativa quando há erro.
        $abas = [];
        if ($resultado) {
            $abas['resumo'] = ['titulo' => 'Resumo do período', 'qtd' => null, 'cor' => 'border-blue-600 text-blue-600'];
            $abas['erros']  = ['titulo' => 'Erros', 'qtd' => $erros->count(),
                               'cor' => $erros->isNotEmpty() ? 'border-red-600 text-red-600' : 'border-blue-600 text-blue-600'];
            $abas['avisos'] = ['titulo' => 'Avisos', 'qtd' => $avisos->count(),
                               'cor' => $avisos->isNotEmpty() ? 'border-amber-500 text-amber-600' : 'border-blue-600 text-blue-600'];
            $abas['gerar']  = ['titulo' => 'Gerar arquivo', 'qtd' => null, 'cor' => 'border-green-600 text-green-700'];
        }
        $abas['historico'] = ['titulo' => 'Arquivos gerados', 'qtd' => $geracoes->count(), 'cor' => 'border-blue-600 text-blue-600'];

        $abaInicial = match (true) {
            !$resultado            => 'historico',
            $erros->isNotEmpty()   => 'erros',
            default                => 'gerar',
        };

        // Classes da aba inativa: erro/aviso mantêm a cor para chamar atenção
        $inativa = fn (string $chave) => match (true) {
            $chave === 'erros' && $erros->isNotEmpty()   => 'border-transparent text-red-600 hover:text-red-700',
            $chave === 'avisos' && $avisos->isNotEmpty() => 'border-transparent text-amber-600 hover:text-amber-700',
            default                                      => 'border-transparent text-gray-500 hover:text-gray-700',
        };

        $badge = fn (string $chave) => match (true) {
            $chave === 'erros' && $erros->isNotEmpty()   => 'bg-red-600 text-white',
            $chave === 'avisos' && $avisos->isNotEmpty() => 'bg-amber-100 text-amber-700',
            default                                      => 'bg-gray-100 text-gray-500',
        };
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

    {{-- ===================== PERÍODO (sempre visível) ===================== --}}
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
                    <option value="1" {{ old('finalidade', request('finalidade')) === '1' ? 'selected' : '' }}>Substituto (SPED Fiscal) / Retificadora (Contribuições)</option>
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

    {{-- ===================== ABAS ===================== --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200 px-6 pt-4">
            <nav class="flex gap-6">
                @foreach ($abas as $chave => $aba)
                    <button type="button" data-tab="{{ $chave }}" onclick="mudarAba('{{ $chave }}')"
                            data-ativa="{{ $aba['cor'] }}" data-inativa="{{ $inativa($chave) }}"
                            class="tab-btn pb-3 text-sm font-medium border-b-2 transition flex items-center gap-2 {{ $abaInicial === $chave ? $aba['cor'] : $inativa($chave) }}">
                        {{ $aba['titulo'] }}
                        @if ($aba['qtd'] !== null)
                            <span class="min-w-5 px-1.5 py-0.5 rounded-full text-xs font-semibold text-center {{ $badge($chave) }}">{{ $aba['qtd'] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-6">

            @if ($resultado)
                {{-- ===================== ABA: RESUMO ===================== --}}
                <div id="aba-resumo" class="tab-conteudo {{ $abaInicial === 'resumo' ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $tipos[$tipo] }} · {{ $resultado['inicio']->format('d/m/Y') }} a {{ $resultado['fim']->format('d/m/Y') }}
                        </h2>
                        @if ($erros->isNotEmpty())
                            <button type="button" onclick="mudarAba('erros')" class="text-xs font-medium text-red-600 hover:text-red-700">
                                {{ $erros->count() }} erro(s) impedem a geração →
                            </button>
                        @endif
                    </div>

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

                {{-- ===================== ABAS: ERROS E AVISOS ===================== --}}
                @foreach ([['erros', 'Erros — impedem a geração do arquivo', $erros, 'red'], ['avisos', 'Avisos — vale conferir, não impedem a geração', $avisos, 'amber']] as [$chave, $tituloBloco, $lista, $cor])
                    <div id="aba-{{ $chave }}" class="tab-conteudo {{ $abaInicial === $chave ? '' : 'hidden' }}">
                        @if ($lista->isEmpty())
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                                {{ $chave === 'erros' ? 'Nenhum erro encontrado neste período.' : 'Nenhum aviso para este período.' }}
                            </div>
                        @else
                            <h2 class="text-sm font-semibold mb-3 {{ $cor === 'red' ? 'text-red-700' : 'text-amber-700' }}">{{ $tituloBloco }} ({{ $lista->count() }})</h2>

                            <div class="space-y-3">
                                @foreach ($lista as $problema)
                                    <div class="rounded-xl border p-4 {{ $cor === 'red' ? 'border-red-200 bg-red-50/40' : 'border-amber-200 bg-amber-50/40' }}">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-xs text-gray-400">{{ $problema['grupo'] }}</p>
                                                <p class="text-sm font-medium {{ $cor === 'red' ? 'text-red-700' : 'text-amber-700' }}">{{ $problema['titulo'] }}</p>
                                            </div>
                                            <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-medium {{ $cor === 'red' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
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
                        @endif
                    </div>
                @endforeach

                {{-- ===================== ABA: GERAR ARQUIVO ===================== --}}
                <div id="aba-gerar" class="tab-conteudo {{ $abaInicial === 'gerar' ? '' : 'hidden' }}">
                    @if (!$podeGerar)
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-center justify-between gap-4">
                            <span>O arquivo só pode ser gerado depois de corrigir os {{ $erros->count() }} erro(s) da verificação.</span>
                            <button type="button" onclick="mudarAba('erros')" class="shrink-0 font-semibold underline">Ver erros</button>
                        </div>
                    @else
                        <form method="POST" action="{{ route('sped.gerar') }}">
                            @csrf
                            <input type="hidden" name="tipo" value="{{ request('tipo', 'icms_ipi') }}">
                            <input type="hidden" name="competencia" value="{{ request('competencia', $competencia) }}">
                            <input type="hidden" name="finalidade" value="{{ request('finalidade', '0') }}">

                            <h2 class="text-sm font-semibold text-gray-800 mb-1">Gerar arquivo — {{ $tipos[$tipo] }}</h2>
                            <p class="text-xs text-gray-400 mb-4">
                                Nenhum erro encontrado.
                                @if ($avisos->isNotEmpty())
                                    Há {{ $avisos->count() }} aviso(s) — <button type="button" onclick="mudarAba('avisos')" class="text-amber-600 hover:text-amber-700 font-medium">confira antes</button>.
                                @endif
                                Confira os dados da apuração e gere o arquivo para validar no PVA.
                            </p>

                            @if ($tipo === 'contribuicoes')
                                <div class="grid grid-cols-3 gap-5">
                                    @if (request('finalidade') === '1')
                                        <div class="col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Recibo da escrituração original <span class="text-red-500">*</span></label>
                                            <input type="text" name="num_rec_anterior" maxlength="41" required value="{{ old('num_rec_anterior') }}" class="{{ $campo }} font-mono uppercase">
                                            <p class="text-xs text-gray-400 mt-1">Número do recibo de entrega do arquivo que está sendo retificado.</p>
                                        </div>
                                    @endif

                                    <div class="col-span-3 p-4 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-800 space-y-1">
                                        <p>O arquivo leva as NF-e e NFC-e autorizadas do mês (com o PIS/COFINS gravado na emissão) e, no regime não cumulativo, as NF-e de entrada com direito a crédito.</p>
                                        <p>Nesta versão o Bloco M (apuração) sai sem dados: depois de importar o arquivo no PVA, use a opção de gerar as apurações antes de validar.</p>
                                    </div>
                                </div>
                            @else
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
                            @endif

                            <div class="mt-5">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                                    {{ $tipo === 'contribuicoes' ? 'Gerar arquivo da EFD-Contribuições' : 'Gerar arquivo do SPED' }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ===================== ABA: ARQUIVOS GERADOS ===================== --}}
            <div id="aba-historico" class="tab-conteudo {{ $abaInicial === 'historico' ? '' : 'hidden' }}">
                @if (!$resultado)
                    <p class="text-xs text-gray-400 mb-4">Escolha a escrituração e a competência acima e clique em "Verificar dados do período" para conferir e gerar um arquivo novo.</p>
                @endif

                @if ($geracoes->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">Nenhum arquivo gerado ainda.</p>
                @else
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-700 font-medium text-xs uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Escrituração</th>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Competência</th>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Finalidade</th>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Valores</th>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Gerado em</th>
                                    <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($geracoes as $g)
                                    @php $contrib = $g->tipo === 'contribuicoes'; @endphp
                                    <tr class="hover:bg-gray-200 transition">
                                        <td class="px-4 py-3 text-gray-600">{{ $contrib ? 'Contribuições' : 'SPED Fiscal' }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $g->competencia->format('m/Y') }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $g->finalidade === '1' ? ($contrib ? 'Retificadora' : 'Substituto') : 'Original' }}</td>
                                        <td class="px-4 py-3 text-gray-600">
                                            @if ($contrib)
                                                PIS R$ {{ number_format($g->totais['pis_debito'] ?? 0, 2, ',', '.') }}
                                                · COFINS R$ {{ number_format($g->totais['cofins_debito'] ?? 0, 2, ',', '.') }}
                                                @if (($g->totais['pis_credito'] ?? 0) > 0 || ($g->totais['cofins_credito'] ?? 0) > 0)
                                                    <span class="block text-xs text-gray-400">Créditos: PIS R$ {{ number_format($g->totais['pis_credito'], 2, ',', '.') }} · COFINS R$ {{ number_format($g->totais['cofins_credito'], 2, ',', '.') }}</span>
                                                @endif
                                            @else
                                                ICMS a recolher R$ {{ number_format($g->icms_recolher, 2, ',', '.') }}
                                                <span class="block text-xs text-gray-400">Saldo credor R$ {{ number_format($g->saldo_credor_transportar, 2, ',', '.') }}</span>
                                            @endif
                                        </td>
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
            </div>

        </div>
    </div>

    <script>
        // Cada aba guarda as próprias classes de ativa/inativa (a de erros fica vermelha quando há erro)
        function mudarAba(aba) {
            document.querySelectorAll('.tab-conteudo').forEach(el => el.classList.add('hidden'));
            document.getElementById('aba-' + aba).classList.remove('hidden');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                const ativa = btn.dataset.tab === aba;
                btn.classList.remove(...btn.dataset.ativa.split(' '), ...btn.dataset.inativa.split(' '));
                btn.classList.add(...(ativa ? btn.dataset.ativa : btn.dataset.inativa).split(' '));
            });
        }
    </script>
@endsection