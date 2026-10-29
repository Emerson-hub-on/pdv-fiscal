@extends('layouts.caixa')

@section('titulo', 'Caixa fechado')

@php
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('conteudo')
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10">
        <div class="flex items-center gap-3 mb-5">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-green-100 text-green-700 text-xl font-bold">✓</span>
            <div>
                <h1 class="text-xl font-bold">Caixa fechado</h1>
                <p class="text-xs text-gray-500">
                    {{ $caixa->pdv->nome ?? 'PDV' }} · {{ $caixa->data_fechamento?->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>

        @if (session('sucesso'))
            <p class="mb-4 text-sm bg-green-50 text-green-800 border border-green-200 rounded px-3 py-2">{{ session('sucesso') }}</p>
        @endif

        <dl class="text-sm divide-y divide-gray-100 border border-gray-200 rounded">
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Vendas</dt>
                <dd class="font-medium">{{ $r['qtd_validas'] }}</dd>
            </div>
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Total vendido</dt>
                <dd class="font-medium">{{ $moeda($r['total_liquido']) }}</dd>
            </div>
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Cancelamentos</dt>
                <dd class="font-medium">{{ $r['qtd_canceladas'] }} ({{ $moeda($r['total_cancelado']) }})</dd>
            </div>
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Descontos</dt>
                <dd class="font-medium">{{ $moeda($r['total_descontos']) }}</dd>
            </div>
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Esperado em dinheiro</dt>
                <dd class="font-medium">{{ $moeda($r['dinheiro_esperado']) }}</dd>
            </div>
            <div class="flex justify-between px-3 py-2">
                <dt class="text-gray-600">Informado</dt>
                <dd class="font-medium">{{ $r['informado'] !== null ? $moeda($r['informado']) : '-' }}</dd>
            </div>
            @if ($r['diferenca'] !== null)
                <div class="flex justify-between px-3 py-2">
                    <dt class="text-gray-600">Diferença</dt>
                    <dd class="font-bold {{ abs($r['diferenca']) < 0.01 ? 'text-green-700' : 'text-red-600' }}">
                        {{ $moeda($r['diferenca']) }}
                    </dd>
                </div>
            @endif
        </dl>

        <p id="aviso-impressao" class="hidden mt-4 text-sm bg-blue-50 text-blue-800 border border-blue-200 rounded px-3 py-2">
            Relatório enviado para a impressora.
        </p>

        <div class="flex gap-2 mt-6">
            <button type="button" onclick="imprimirRelatorioFechamento()"
                    class="flex-1 bg-slate-700 hover:bg-slate-800 text-white py-2 rounded font-semibold cursor-pointer">
                <span id="rotulo-imprimir">Imprimir relatório</span>
            </button>
            <a href="{{ route('auth.escolha') }}"
               class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded font-semibold">
                Concluir
            </a>
        </div>
    </div>

    {{-- Pergunta ao abrir a tela --}}
    <div id="modal-imprimir" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
            <h2 class="text-lg font-bold mb-2">Relatório de fechamento</h2>
            <p class="text-sm text-gray-600 mb-6">Deseja imprimir o relatório de fechamento?</p>
            <div class="flex gap-2">
                <button type="button" id="btn-imprimir-sim" onclick="responderImpressao(true)"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded font-semibold cursor-pointer">
                    Sim <span class="opacity-70 text-xs">Enter</span>
                </button>
                <button type="button" onclick="responderImpressao(false)"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded font-semibold cursor-pointer">
                    Não <span class="opacity-60 text-xs">Esc</span>
                </button>
            </div>
        </div>
    </div>

    {{-- A página do relatório carrega fora da tela e chama a impressão sozinha --}}
    <iframe id="frame-relatorio-fechamento" title="Relatório de fechamento"
            class="fixed bottom-0 right-0 w-0 h-0 border-0 opacity-0 pointer-events-none"></iframe>
@endsection

@section('scripts')
<script>
    const urlRelatorio = @json(route('caixa.relatorio-fechamento', $caixa->uuid));
    const urlConcluir = @json(route('auth.escolha'));
    let jaImpresso = false;

    function imprimirRelatorioFechamento() {
        const url = new URL(urlRelatorio, window.location.origin);
        url.searchParams.set('auto', '1');
        if (jaImpresso) {
            url.searchParams.set('reimpressao', '1');
        }
        url.searchParams.set('t', Date.now());

        document.getElementById('frame-relatorio-fechamento').src = url.toString();
        jaImpresso = true;
        document.getElementById('rotulo-imprimir').textContent = 'Imprimir novamente';
    }

    // Chamado pela página do relatório depois de mandar para a impressora
    window.relatorioImpresso = function () {
        document.getElementById('aviso-impressao').classList.remove('hidden');
    };

    function responderImpressao(sim) {
        document.getElementById('modal-imprimir').classList.add('hidden');
        document.removeEventListener('keydown', teclaModal);

        if (sim) {
            imprimirRelatorioFechamento();
        } else {
            window.location.href = urlConcluir;
        }
    }

    function teclaModal(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            responderImpressao(true);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            responderImpressao(false);
        }
    }

    document.addEventListener('keydown', teclaModal);
    document.getElementById('btn-imprimir-sim').focus();
</script>
@endsection
