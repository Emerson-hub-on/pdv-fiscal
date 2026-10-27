@extends('layouts.app')

@section('titulo', 'MDF-e')

@section('conteudo')
    @php
        $badge = match ($mdfe->status) {
            'emitido'   => ['bg-green-50 text-green-700', 'bg-green-500', 'Emitido'],
            'encerrado' => ['bg-slate-100 text-slate-700', 'bg-slate-500', 'Encerrado'],
            'cancelado' => ['bg-red-50 text-red-600', 'bg-red-500', 'Cancelado'],
            default     => ['bg-amber-50 text-amber-700', 'bg-amber-500', 'Rascunho'],
        };
        $carregamentos = $mdfe->municipios->where('tipo', 'carregamento');
        $descargas     = $mdfe->municipios->where('tipo', 'descarregamento');
        $tracao        = $mdfe->veiculos->firstWhere('papel', 'tracao');
        $reboques      = $mdfe->veiculos->where('papel', 'reboque');
        $rotuloCard    = 'text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3';
        $card          = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6';
    @endphp

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-800">
                MDF-e {{ $mdfe->numero ? 'nº ' . $mdfe->numero . ' / série ' . $mdfe->serie : '(rascunho)' }}
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $badge[0] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $badge[1] }}"></span>
                {{ $badge[2] }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('mdfes.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">Voltar</a>

            @if ($mdfe->status === 'rascunho')
                <a href="{{ route('mdfes.edit', $mdfe) }}"
                   class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">Editar</a>
                <form action="{{ route('mdfes.emitir', $mdfe) }}" method="POST"
                      onsubmit="return confirm('Emitir este MDF-e? O número será consumido e o documento enviado à SEFAZ.')">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition">
                        Emitir MDF-e
                    </button>
                </form>
            @endif

            @if ($mdfe->xml)
                <a href="{{ route('mdfes.xml', $mdfe) }}"
                   class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium transition">Baixar XML</a>
            @endif
        </div>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('sucesso') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($mdfe->status === 'rascunho' && $mdfe->motivo_rejeicao)
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <p class="font-semibold mb-1">Última tentativa de emissão falhou</p>
            <p>{{ $mdfe->motivo_rejeicao }}</p>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-6">

        {{-- ===================== COLUNA PRINCIPAL ===================== --}}
        <div class="col-span-2 space-y-6">

            <div class="{{ $card }}">
                <h2 class="{{ $rotuloCard }}">Dados gerais</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-gray-400 text-xs">Tipo de emitente</dt>
                        <dd class="text-gray-800">{{ \App\Models\Mdfe::TIPOS_EMITENTE[$mdfe->tipo_emitente] ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs">Tipo de transportador</dt>
                        <dd class="text-gray-800">{{ \App\Models\Mdfe::TIPOS_TRANSPORTADOR[$mdfe->tipo_transportador] ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs">Percurso</dt>
                        <dd class="text-gray-800">
                            {{ $mdfe->uf_inicio }}
                            @foreach ($mdfe->percursos as $p) → {{ $p->uf }} @endforeach
                            → {{ $mdfe->uf_fim }}
                        </dd></div>
                    <div><dt class="text-gray-400 text-xs">Início da viagem</dt>
                        <dd class="text-gray-800">{{ $mdfe->inicio_viagem?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs">RNTRC</dt>
                        <dd class="text-gray-800 font-mono">{{ $mdfe->rntrc ?? '—' }}</dd></div>
                    @if ($mdfe->informacoes_adicionais)
                        <div class="col-span-2"><dt class="text-gray-400 text-xs">Informações adicionais</dt>
                            <dd class="text-gray-800 whitespace-pre-line">{{ $mdfe->informacoes_adicionais }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="{{ $card }}">
                <h2 class="{{ $rotuloCard }}">Carregamento e descarga</h2>

                <p class="text-xs text-gray-400 mb-1">Carregamento</p>
                <ul class="mb-5 space-y-1 text-sm text-gray-800">
                    @foreach ($carregamentos as $m)
                        <li>{{ $m->nome }}/{{ $m->uf }} <span class="text-xs text-gray-400 font-mono ml-1">IBGE {{ $m->codigo_ibge }}</span></li>
                    @endforeach
                </ul>

                <p class="text-xs text-gray-400 mb-2">Descarga e NF-e</p>
                <div class="space-y-4">
                    @foreach ($descargas as $m)
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700">
                                {{ $m->nome }}/{{ $m->uf }}
                                <span class="text-xs text-gray-400 font-mono ml-1">IBGE {{ $m->codigo_ibge }}</span>
                            </div>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($m->documentos as $doc)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                                {{ $doc->notaFiscal ? $doc->notaFiscal->numero . ' / ' . $doc->notaFiscal->serie : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-700">{{ $doc->notaFiscal?->cliente?->nome }}</td>
                                            <td class="px-3 py-2 text-gray-600 text-right whitespace-nowrap">R$ {{ number_format($doc->valor, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-gray-600 text-right whitespace-nowrap">{{ number_format($doc->peso_bruto_kg, 3, ',', '.') }} kg</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-sm text-gray-600">
                    <span class="font-medium">{{ $mdfe->quantidade_nfe }}</span> NF-e ·
                    valor da carga <span class="font-medium">R$ {{ number_format($mdfe->valor_carga, 2, ',', '.') }}</span> ·
                    peso <span class="font-medium">{{ number_format($mdfe->peso_carga_kg, 3, ',', '.') }} kg</span>
                </p>
            </div>

            <div class="{{ $card }}">
                <h2 class="{{ $rotuloCard }}">Veículos e condutores</h2>
                <ul class="space-y-2 text-sm text-gray-800">
                    @if ($tracao)
                        <li><span class="text-xs text-gray-400 mr-2">Tração</span>
                            <span class="font-mono font-medium">{{ $tracao->placa }}</span> / {{ $tracao->uf }}
                            @if ($tracao->proprietario_nome) <span class="text-xs text-gray-400 ml-2">proprietário: {{ $tracao->proprietario_nome }}</span> @endif
                        </li>
                    @endif
                    @foreach ($reboques as $r)
                        <li><span class="text-xs text-gray-400 mr-2">Reboque</span>
                            <span class="font-mono font-medium">{{ $r->placa }}</span> / {{ $r->uf }}
                            @if ($r->proprietario_nome) <span class="text-xs text-gray-400 ml-2">proprietário: {{ $r->proprietario_nome }}</span> @endif
                        </li>
                    @endforeach
                </ul>

                @if ($tracao)
                    <p class="text-xs text-gray-400 mt-4 mb-1">Condutores</p>
                    <ul class="space-y-1 text-sm text-gray-800">
                        @foreach ($tracao->condutores as $c)
                            <li>{{ $c->nome }} <span class="text-xs text-gray-400 font-mono ml-1">{{ preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $c->cpf) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ===================== COLUNA LATERAL ===================== --}}
        <div class="space-y-6">

            @if ($mdfe->chave_acesso)
                <div class="{{ $card }}">
                    <h2 class="{{ $rotuloCard }}">Autorização</h2>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-gray-400 text-xs">Chave de acesso</dt>
                            <dd class="font-mono text-xs break-all text-gray-800">{{ $mdfe->chave_acesso }}</dd></div>
                        <div><dt class="text-gray-400 text-xs">Protocolo</dt>
                            <dd class="font-mono text-gray-800">{{ $mdfe->protocolo }}</dd></div>
                        <div><dt class="text-gray-400 text-xs">Emitido em</dt>
                            <dd class="text-gray-800">{{ $mdfe->emitido_em?->format('d/m/Y H:i') }}</dd></div>
                        @if ($mdfe->encerrado_em)
                            <div><dt class="text-gray-400 text-xs">Encerrado em</dt>
                                <dd class="text-gray-800">{{ $mdfe->encerrado_em->format('d/m/Y H:i') }}</dd></div>
                        @endif
                        @if ($mdfe->cancelado_em)
                            <div><dt class="text-gray-400 text-xs">Cancelado em</dt>
                                <dd class="text-gray-800">{{ $mdfe->cancelado_em->format('d/m/Y H:i') }}</dd>
                                <dd class="text-xs text-gray-500 mt-1">{{ $mdfe->motivo_cancelamento }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($mdfe->status === 'emitido')
                <div class="{{ $card }}">
                    <h2 class="{{ $rotuloCard }}">Encerrar viagem</h2>
                    <form action="{{ route('mdfes.encerrar', $mdfe) }}" method="POST"
                          onsubmit="return confirm('Encerrar este MDF-e? Depois disso ele não pode mais ser cancelado.')">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700 mb-1">Município de encerramento</label>
                        <select name="municipio_id" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            @foreach ($descargas as $m)
                                <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="mt-3 w-full bg-gray-700 hover:bg-gray-800 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition">
                            Encerrar MDF-e
                        </button>
                    </form>
                </div>

                <div class="{{ $card }}">
                    <h2 class="{{ $rotuloCard }}">Cancelar</h2>
                    <form action="{{ route('mdfes.cancelar', $mdfe) }}" method="POST"
                          onsubmit="return confirm('Cancelar este MDF-e na SEFAZ? Esta ação não pode ser desfeita.')">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa <span class="text-xs text-gray-400 font-normal">(mín. 15 caracteres)</span></label>
                        <textarea name="justificativa" rows="3" required minlength="15" maxlength="255"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">{{ old('justificativa') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">A SEFAZ limita o prazo de cancelamento; se passou, ela rejeita e mostramos o motivo.</p>
                        <button type="submit" class="mt-3 w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition">
                            Cancelar MDF-e
                        </button>
                    </form>
                </div>
            @endif

            @if ($mdfe->eventos->isNotEmpty())
                <div class="{{ $card }}">
                    <h2 class="{{ $rotuloCard }}">Eventos</h2>
                    <ul class="space-y-3 text-sm">
                        @foreach ($mdfe->eventos->sortByDesc('registrado_em') as $e)
                            <li>
                                <p class="font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $e->tipo)) }}</p>
                                <p class="text-xs text-gray-500">{{ $e->registrado_em?->format('d/m/Y H:i') }} · {{ $e->operador?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-400 font-mono">Protocolo {{ $e->protocolo }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
