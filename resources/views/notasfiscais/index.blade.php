@extends('layouts.app')

@section('titulo', 'Notas Fiscais')

@section('conteudo')
@include('notasfiscais._recalculo_flash')

<div class="bg-white rounded-lg shadow overflow-visible">
    <form method="GET" action="{{ route('notasfiscais.index') }}" id="form-filtro-notas"
        class="flex flex-col gap-3 p-4 border-b border-gray-100">

        <!-- Linha 1: título + filtro de busca -->
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold mr-1">Notas Fiscais</h1>

            @if ($statusFiltro !== 'inutilizada')
                <span class="text-sm text-gray-400">Filtrar por:</span>

                <select name="tipo_filtro" id="campo-tipo-filtro" onchange="atualizarCampoFiltroNota()"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="data" @selected($tipoFiltro === 'data')>Data</option>
                    <option value="numero" @selected($tipoFiltro === 'numero')>Número da NF-e</option>
                    <option value="cliente" @selected($tipoFiltro === 'cliente')>Cliente</option>
                    <option value="documento" @selected($tipoFiltro === 'documento')>CPF/CNPJ</option>
                </select>

                <div id="campo-filtro-data" class="flex items-center gap-1 {{ $tipoFiltro !== 'data' ? 'hidden' : '' }}">
                    <span class="text-sm text-gray-400">De</span>
                    <input type="date" name="data_inicio"
                        value="{{ request('data_inicio', now()->toDateString()) }}"
                        onchange="this.form.submit()"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">

                    <span class="text-sm text-gray-400">até</span>
                    <input type="date" name="data_fim"
                        value="{{ request('data_fim', now()->toDateString()) }}"
                        onchange="this.form.submit()"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <input type="text" name="numero" id="campo-filtro-numero" placeholder="Número da NF-e"
                    value="{{ request('numero') }}"
                    class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm {{ $tipoFiltro !== 'numero' ? 'hidden' : '' }}">

                <input type="text" name="cliente" id="campo-filtro-cliente" placeholder="Nome do cliente"
                    value="{{ request('cliente') }}"
                    class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm {{ $tipoFiltro !== 'cliente' ? 'hidden' : '' }}">

                <input type="text" name="documento" id="campo-filtro-documento" placeholder="CPF ou CNPJ"
                    value="{{ request('documento') }}"
                    class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm {{ $tipoFiltro !== 'documento' ? 'hidden' : '' }}">

                <button type="submit" id="btn-buscar-filtro-nota"
                        class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700 {{ $tipoFiltro === 'data' ? 'hidden' : '' }}">
                    Buscar
                </button>
            @endif
        </div>

        <!-- Linha 2: status + ações -->
        <div class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="rascunho" @selected($statusFiltro === 'rascunho')>Rascunho</option>
                <option value="emitida" @selected($statusFiltro === 'emitida')>Emitida</option>
                <option value="cancelada" @selected($statusFiltro === 'cancelada')>Cancelada</option>
                <option value="inutilizada" @selected($statusFiltro === 'inutilizada')>Inutilizada</option>
            </select>

            <button type="button" onclick="abrirModalInutilizacaoNfe()"
                    class="border border-red-300 text-red-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-50">
                Inutilizar
            </button>
            <a href="{{ route('notasfiscais.create') }}"
            class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-700">Nova nota</a>
        </div>
    </form>

    @if ($statusFiltro === 'inutilizada')
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-4 py-2">Número</th>
                        <th class="text-left px-4 py-2">Motivo</th>
                        <th class="text-left px-4 py-2">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($inutilizacoes as $inutilizacao)
                        <tr>
                            <td class="px-4 py-2">
                                {{ $inutilizacao->numero_inicial }}
                                @if ($inutilizacao->numero_inicial !== $inutilizacao->numero_final)
                                    a {{ $inutilizacao->numero_final }}
                                @endif
                                <span class="text-gray-400">(série {{ $inutilizacao->serie }})</span>
                            </td>
                            <td class="px-4 py-2">{{ $inutilizacao->justificativa }}</td>
                            <td class="px-4 py-2">{{ $inutilizacao->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-400">Nenhuma numeração inutilizada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="p-4">{{ $inutilizacoes->links() }}</div>
        @else
            <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Número</th>
                <th class="text-left px-4 py-2">Série</th>
                <th class="text-left px-4 py-2">Cliente</th>
                <th class="text-left px-4 py-2">CPF/CNPJ</th>
                <th class="text-left px-4 py-2">CFOP</th>
                <th class="text-right px-4 py-2">Total</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-left px-4 py-2">Data</th>
                <th class="text-right px-4 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($notas as $nota)
                @php
                    $rotaLinha = $nota->status === 'rascunho'
                        ? route('notasfiscais.edit', $nota)
                        : route('notasfiscais.show', $nota);
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->numero ?? '—' }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->serie ?? '—' }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->cliente->nome }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->cliente->cpf_cnpj_formatado }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->cfopSaida->codigo ?? '—' }}</td>
                    <td class="px-4 py-2 text-right cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">R$ {{ number_format($nota->valor_total, 2, ',', '.') }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ ucfirst($nota->status) }}</td>
                    <td class="px-4 py-2 cursor-pointer" onclick="location.href='{{ $rotaLinha }}'">{{ $nota->created_at->format('d/m/Y H:i') }}</td>

                    <td class="px-4 py-2 text-right relative">
                        <button type="button" onclick="toggleAcoesLinha({{ $nota->id }})"
                                class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                            ⋮
                        </button>

                        <div id="dropdown-acoes-{{ $nota->id }}"
                            class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-44 z-50 text-left">
                            <a href="{{ route('notasfiscais.previsualizar', $nota) }}" target="_blank"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Pré-visualizar</a>

                                @if ($nota->status === 'rascunho')
                                    <a href="{{ route('notasfiscais.edit', $nota) }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                                    <form method="POST" action="{{ route('notasfiscais.recalcular', $nota) }}"
                                        onsubmit="return confirm('Recalcular dados fiscais dos itens a partir do cadastro atual dos produtos?')">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recalcular</button>
                                    </form>

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <form method="POST" action="{{ route('notasfiscais.emitir', $nota) }}" onsubmit="return confirm('Confirma a emissão desta NF-e?')">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 font-medium hover:bg-green-50">Emitir NF-e</button>
                                    </form>

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <form method="POST" action="{{ route('notasfiscais.destroy', $nota) }}"
                                        onsubmit="return confirm('Excluir esta nota em rascunho? Esta ação não pode ser desfeita.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Excluir rascunho</button>
                                    </form>
                                @elseif ($nota->status === 'emitida')
                                <a href="{{ route('notasfiscais.xml', $nota) }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Baixar XML</a>

                                <div class="border-t border-gray-100 my-1"></div>

                                <a href="{{ route('notasfiscais.cancelar-form', $nota) }}"
                                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar</a>
                            @endif
                            
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">Nenhuma nota encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

        <div class="p-4">{{ $notas->links() }}</div>
    @endif
</div>

<!-- Modal de Inutilização de Numeração -->
<div id="modal-inutilizacao-nfe" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Inutilizar Numeração de NF-e</h2>
            <button type="button" onclick="fecharModalInutilizacaoNfe()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="bg-yellow-100 text-yellow-800 border border-yellow-300 rounded px-3 py-2 mb-4 text-xs">
            Atenção: esta ação é irreversível perante a SEFAZ. Use apenas para números pulados ou com falha
            técnica que nunca chegaram a ser autorizados.
        </div>

        <label class="block text-sm font-medium mb-1">Série</label>
        <select id="inut-nfe-serie" class="w-full border rounded px-3 py-2 mb-3 text-sm">
            @foreach ($series as $serie)
                <option value="{{ $serie->id }}">
                    Série {{ $serie->serie }} (último número: {{ $serie->numero_atual }}){{ $serie->descricao ? ' — ' . $serie->descricao : '' }}
                </option>
            @endforeach
        </select>

        <label class="block text-sm font-medium mb-1">Número inicial</label>
        <input type="number" id="inut-nfe-numero-inicial" class="w-full border rounded px-3 py-2 mb-3 text-sm">

        <label class="block text-sm font-medium mb-1">Número final</label>
        <input type="number" id="inut-nfe-numero-final" class="w-full border rounded px-3 py-2 mb-3 text-sm">

        <label class="block text-sm font-medium mb-1">Justificativa (mín. 15 caracteres)</label>
        <textarea id="inut-nfe-justificativa" rows="3" class="w-full border rounded px-3 py-2 mb-4 text-sm"></textarea>

        <p id="inut-nfe-erro" class="text-red-600 text-sm mb-3 hidden"></p>

        <button type="button" onclick="confirmarInutilizacaoNfe()"
                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold">
            Inutilizar
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleAcoesLinha(id) {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(el => {
        if (el.id !== `dropdown-acoes-${id}`) el.classList.add('hidden');
    });
    document.getElementById(`dropdown-acoes-${id}`).classList.toggle('hidden');
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('[id^="dropdown-acoes-"]').forEach(dropdown => {
        const container = dropdown.closest('td');
        if (!dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});

function abrirModalInutilizacaoNfe() {
    document.getElementById('inut-nfe-numero-inicial').value = '';
    document.getElementById('inut-nfe-numero-final').value = '';
    document.getElementById('inut-nfe-justificativa').value = '';
    document.getElementById('inut-nfe-erro').classList.add('hidden');
    document.getElementById('modal-inutilizacao-nfe').classList.remove('hidden');
    document.getElementById('modal-inutilizacao-nfe').classList.add('flex');
}

function fecharModalInutilizacaoNfe() {
    document.getElementById('modal-inutilizacao-nfe').classList.add('hidden');
    document.getElementById('modal-inutilizacao-nfe').classList.remove('flex');
}

async function confirmarInutilizacaoNfe() {
    const serieId = document.getElementById('inut-nfe-serie').value;
    const numeroInicial = document.getElementById('inut-nfe-numero-inicial').value;
    const numeroFinal = document.getElementById('inut-nfe-numero-final').value;
    const justificativa = document.getElementById('inut-nfe-justificativa').value;
    const erroP = document.getElementById('inut-nfe-erro');

    erroP.classList.add('hidden');

    if (!numeroInicial || !numeroFinal || justificativa.length < 15) {
        erroP.innerText = 'Preencha os números e uma justificativa com pelo menos 15 caracteres.';
        erroP.classList.remove('hidden');
        return;
    }

    if (!confirm(`Confirma a inutilização da numeração ${numeroInicial} a ${numeroFinal}? Esta ação não pode ser desfeita.`)) {
        return;
    }

    const resp = await fetch('{{ route("inutilizacao-nfe.executar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
            serie_nfe_id: serieId,
            numero_inicial: numeroInicial,
            numero_final: numeroFinal,
            justificativa,
        }),
    });

    const resultado = await resp.json();

    if (resultado.sucesso) {
        alert('Numeração inutilizada com sucesso. Protocolo: ' + resultado.protocolo);
        location.reload();
    } else {
        erroP.innerText = resultado.erro;
        erroP.classList.remove('hidden');
    }
}

function atualizarCampoFiltroNota() {
    const tipo = document.getElementById('campo-tipo-filtro').value;

    document.getElementById('campo-filtro-data').classList.toggle('hidden', tipo !== 'data');
    document.getElementById('campo-filtro-numero').classList.toggle('hidden', tipo !== 'numero');
    document.getElementById('campo-filtro-cliente').classList.toggle('hidden', tipo !== 'cliente');
    document.getElementById('campo-filtro-documento').classList.toggle('hidden', tipo !== 'documento');
    document.getElementById('btn-buscar-filtro-nota').classList.toggle('hidden', tipo === 'data');
}

</script>
@endsection