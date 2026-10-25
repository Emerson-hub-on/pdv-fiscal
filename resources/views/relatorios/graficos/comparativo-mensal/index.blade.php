@extends('layouts.app')

@section('titulo', 'Comparativo mensal de vendas')

{{-- Libera o max-w-5xl do layout para o gráfico e a tabela usarem a largura toda da tela --}}
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Relatórios</span>
    <span class="text-gray-300">›</span>
    <span>Gráficos</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Comparativo mensal de vendas</span>
@endsection

@section('conteudo')
    <div class="flex justify-end items-center gap-2 mb-6">
        <button type="button" onclick="exportarRelatorio('excel')"
                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar Excel
        </button>
        <button type="button" onclick="exportarRelatorio('pdf')"
                class="bg-red-600 hover:bg-red-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
            Exportar PDF
        </button>
    </div>

    {{-- Poucos filtros: ficam todos na barra e atualizam o gráfico e a tabela na hora --}}
    <form id="form-relatorio" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label for="filtro-mes-inicio" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">De</label>
            <input type="month" name="mes_inicio" id="filtro-mes-inicio" value="{{ $filtros['mes_inicio'] }}" max="{{ now()->format('Y-m') }}" data-filtro
                   class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </div>
        <div>
            <label for="filtro-mes-fim" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Até</label>
            <input type="month" name="mes_fim" id="filtro-mes-fim" value="{{ $filtros['mes_fim'] }}" max="{{ now()->format('Y-m') }}" data-filtro
                   class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </div>
        <div>
            <label for="filtro-tipo" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Documentos</label>
            <select name="tipo" id="filtro-tipo" data-filtro
                    class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                <option value="todos" @selected($filtros['tipo'] === 'todos')>NFC-e e NF-e</option>
                <option value="nfce" @selected($filtros['tipo'] === 'nfce')>Só NFC-e</option>
                <option value="nfe" @selected($filtros['tipo'] === 'nfe')>Só NF-e</option>
            </select>
        </div>
        <div>
            <label for="filtro-metrica" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Gráfico mostra</label>
            <select name="metrica" id="filtro-metrica" data-filtro
                    class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                @foreach ($metricas as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected($filtros['metrica'] === $chave)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-comparar" class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Comparar com ano anterior</label>
            <select name="comparar" id="filtro-comparar" data-filtro
                    class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                <option value="nao" @selected($filtros['comparar'] === 'nao')>Não</option>
                <option value="sim" @selected($filtros['comparar'] === 'sim')>Sim, mesmo mês do ano anterior</option>
            </select>
        </div>
        <button type="button" onclick="restaurarPadrao()"
                class="text-sm font-medium text-gray-500 hover:text-gray-700 transition pb-2">
            Últimos 12 meses
        </button>
    </form>

    <div id="resultado-relatorio">
        @include('relatorios.graficos.comparativo-mensal._conteudo', ['meses' => $meses, 'totais' => $totais, 'grafico' => $grafico, 'filtros' => $filtros])
    </div>

    <script>
        const formRelatorio = document.getElementById('form-relatorio');

        function parametrosRelatorio() {
            return new URLSearchParams(new FormData(formRelatorio));
        }

        async function buscarRelatorio() {
            const url = `{{ route('relatorios.graficos.comparativo-mensal.index') }}?${parametrosRelatorio().toString()}`;

            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!resp.ok) return;

            document.getElementById('resultado-relatorio').innerHTML = await resp.text();
            window.history.replaceState(null, '', url);
        }

        const rotasExportarRelatorio = {
            excel: `{{ route('relatorios.graficos.comparativo-mensal.exportar-excel') }}`,
            pdf: `{{ route('relatorios.graficos.comparativo-mensal.exportar-pdf') }}`,
        };

        // Exporta o mesmo período e filtros que estão na tela
        function exportarRelatorio(formato) {
            window.location.href = `${rotasExportarRelatorio[formato]}?${parametrosRelatorio().toString()}`;
        }

        // Volta para os últimos 12 meses (o mês atual e os 11 anteriores)
        function restaurarPadrao() {
            formRelatorio.elements['mes_inicio'].value = '{{ now()->subMonths(11)->format('Y-m') }}';
            formRelatorio.elements['mes_fim'].value = '{{ now()->format('Y-m') }}';
            buscarRelatorio();
        }

        document.querySelectorAll('[data-filtro]').forEach((el) => {
            el.addEventListener('change', buscarRelatorio);
        });

        formRelatorio.addEventListener('submit', (e) => e.preventDefault());
    </script>
@endsection
