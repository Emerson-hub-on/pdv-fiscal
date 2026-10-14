@extends('layouts.app')

@section('titulo', 'Contas a Receber')

@section('conteudo')
<div class="bg-white rounded-lg shadow overflow-visible">
    @if (session('erro'))
        <div id="aviso-erro" role="alert"
             class="m-4 mb-0 border border-red-300 bg-red-50 text-red-800 rounded-lg px-4 py-3 text-sm flex items-start gap-3">
            <div class="flex-1 whitespace-pre-line">{{ session('erro') }}</div>
            <button type="button" onclick="fecharAvisoErro()"
                    class="shrink-0 bg-red-600 text-white rounded-lg px-3 py-1 text-xs font-medium hover:bg-red-700">
                Ok (<span id="aviso-erro-contador">10</span>)
            </button>
        </div>
    @endif

    {{-- Resumo --}}
    <div class="grid grid-cols-3 gap-3 p-4 pb-0 max-w-3xl">
        <div class="border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs text-gray-500 uppercase">Em aberto</p>
            <p class="text-lg font-semibold">R$ {{ number_format($resumo['aberto'], 2, ',', '.') }}</p>
        </div>
        <div class="border border-red-200 bg-red-50 rounded-lg px-4 py-3">
            <p class="text-xs text-red-700 uppercase">Vencido</p>
            <p class="text-lg font-semibold text-red-700">R$ {{ number_format($resumo['vencido'], 2, ',', '.') }}</p>
        </div>
        <div class="border border-amber-200 bg-amber-50 rounded-lg px-4 py-3">
            <p class="text-xs text-amber-700 uppercase">Vence em 7 dias</p>
            <p class="text-lg font-semibold text-amber-700">R$ {{ number_format($resumo['proximos'], 2, ',', '.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('contas-receber.index') }}"
          class="flex flex-col gap-3 p-4 border-b border-gray-100">

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold mr-1">Contas a Receber</h1>

            <input type="text" name="busca" placeholder="Descrição, cliente ou nº do documento"
                   value="{{ request('busca') }}"
                   class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-72">

            <button type="submit"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700">
                Buscar
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="aberta" @selected(request('status') === 'aberta')>Em aberto</option>
                <option value="vencida" @selected(request('status') === 'vencida')>Vencidas</option>
                <option value="recebida" @selected(request('status') === 'recebida')>Recebidas</option>
            </select>

            <select name="tipo" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->id }}" @selected(request('tipo') == $tipo->id)>{{ $tipo->descricao }}</option>
                @endforeach
            </select>

            <label class="flex items-center gap-1 text-sm text-gray-600">
                Vencimento de
                <input type="date" name="venc_de" value="{{ request('venc_de') }}" onchange="this.form.submit()"
                       class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </label>
            <label class="flex items-center gap-1 text-sm text-gray-600">
                até
                <input type="date" name="venc_ate" value="{{ request('venc_ate') }}" onchange="this.form.submit()"
                       class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </label>

            @if (request()->hasAny(['busca', 'status', 'tipo', 'venc_de', 'venc_ate']))
                <a href="{{ route('contas-receber.index') }}" class="text-sm text-gray-500 hover:underline">Limpar filtros</a>
            @endif

            <a href="{{ route('contas-receber.create') }}"
               class="bg-gray-700 text-amber-50 rounded-lg px-4 py-2 text-sm hover:bg-gray-500">+ Cadastrar nova conta</a>
        </div>
    </form>

    @include('financeiro.contas-receber._tabela', ['contas' => $contas])
</div>
@endsection

@section('scripts')
<script>
let timerAvisoErro = null;

function fecharAvisoErro() {
    clearInterval(timerAvisoErro);
    document.getElementById('aviso-erro')?.remove();
}

// aviso de erro: fecha sozinho em 10s, com contagem no botão Ok
(function () {
    const contador = document.getElementById('aviso-erro-contador');
    if (!contador) return;

    let restante = 10;
    timerAvisoErro = setInterval(() => {
        restante--;
        contador.textContent = restante;
        if (restante <= 0) fecharAvisoErro();
    }, 1000);
})();

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
</script>
@endsection
