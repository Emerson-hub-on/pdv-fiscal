@extends('layouts.app')

@section('titulo', 'Conciliação iFood')
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Comandas</span>
    <span class="text-gray-300">›</span>
    <span>Relatórios</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Conciliação iFood</span>
@endsection

@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $valorCor = fn ($v) => (float) $v < 0 ? 'text-red-600' : ((float) $v > 0 ? 'text-gray-800' : 'text-gray-400');
    $situacaoEstilo = [
        'ok'          => ['Conferido', 'bg-green-50 text-green-700', 'bg-green-500'],
        'divergente'  => ['Divergente', 'bg-red-50 text-red-700', 'bg-red-500'],
        'sem_evento'  => ['Sem lançamento', 'bg-amber-50 text-amber-700', 'bg-amber-500'],
        'sem_sistema' => ['Fora do sistema', 'bg-purple-50 text-purple-700', 'bg-purple-500'],
        'aguardando'  => ['Aguardando iFood', 'bg-slate-100 text-slate-600', 'bg-slate-400'],
    ];
    $statusRepasse = [
        'SUCCEED' => 'bg-green-50 text-green-700', 'PENDING' => 'bg-amber-50 text-amber-700',
        'FAILED' => 'bg-red-50 text-red-700', 'TRANSFER_RENEGOTIATED' => 'bg-slate-100 text-slate-600',
    ];
    $podeSincronizar = auth()->user()?->podeAlterar('ifood');
    $query = fn (array $mais = []) => array_merge(request()->except('page'), $mais);
@endphp

@section('conteudo')
    <div class="flex items-center gap-4 mb-4">
        <p class="flex-1 min-w-0 text-xs text-gray-500">
            @if ($sincronizadoEm)
                Última sincronização com o iFood: {{ \Carbon\Carbon::parse($sincronizadoEm)->format('d/m/Y H:i') }}
            @else
                Ainda não sincronizado com o iFood.
            @endif
            <span class="text-gray-400">· O iFood lança os valores alguns dias depois do pedido; o repasse é semanal.</span>
        </p>
        {{-- Botões sempre lado a lado --}}
        <div class="flex shrink-0 items-center gap-2">
            @if ($podeSincronizar)
                <button type="button" id="btn-sincronizar" onclick="sincronizarIfood(this)"
                        class="whitespace-nowrap bg-[#10284A] hover:bg-[#1a3a66] text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition disabled:opacity-50">
                    Sincronizar com o iFood
                </button>
            @endif
            <a href="{{ route('ifood.financeiro.exportar-excel', request()->query()) }}"
               class="whitespace-nowrap bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-lg font-semibold text-sm transition">
                Exportar Excel
            </a>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('ifood.financeiro.index') }}" class="flex flex-wrap items-end gap-3 mb-4 bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <input type="hidden" name="aba" value="{{ $aba }}">
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">De
            <input type="date" name="data_inicio" value="{{ $f['data_inicio'] }}"
                   class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </label>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Até
            <input type="date" name="data_fim" value="{{ $f['data_fim'] }}"
                   class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </label>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Situação
            <select name="situacao" class="mt-1 block bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
                @foreach ($situacoes as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($f['situacao'] === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Busca
            <input type="text" name="busca" value="{{ $f['busca'] }}" placeholder="Nº do pedido ou cliente"
                   class="mt-1 block w-56 bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800">
        </label>
        <button class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg font-semibold text-sm transition">Aplicar</button>
        <p class="basis-full text-xs text-gray-400">Pedidos pela data do pedido. Por padrão, o mês atual.</p>
    </form>

    {{-- Resumo --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Vendas no iFood</p>
            <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->vendas) }}</p>
            <p class="text-xs text-gray-400">{{ $totais->pedidos }} pedidos · cancelamentos {{ $dinheiro($totais->cancelamentos) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Comissões</p>
            <p class="text-lg font-semibold text-red-600">{{ $dinheiro($totais->comissoes) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Taxas e entrega</p>
            <p class="text-lg font-semibold text-red-600">{{ $dinheiro($totais->taxas) }}</p>
            <p class="text-xs text-gray-400">promoções {{ $dinheiro($totais->promocoes) }} · outros {{ $dinheiro($totais->outros) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Líquido do repasse</p>
            <p class="text-lg font-semibold text-green-700">{{ $dinheiro($totais->liquido) }}</p>
            <p class="text-xs text-gray-400">recebido direto (ex.: dinheiro na entrega) {{ $dinheiro($totais->direto) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Para conferir</p>
            <p class="text-lg font-semibold {{ $totais->divergentes ? 'text-red-600' : 'text-green-700' }}">{{ $totais->divergentes }}</p>
            <p class="text-xs text-gray-400">divergentes, sem lançamento ou fora do sistema</p>
        </div>
    </div>

    {{-- Abas --}}
    <div class="flex gap-2 mb-3">
        <a href="{{ route('ifood.financeiro.index', $query(['aba' => 'pedidos'])) }}"
           class="rounded-lg px-4 py-2 text-sm font-semibold {{ $aba === 'pedidos' ? 'bg-[#10284A] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">Pedidos</a>
        <a href="{{ route('ifood.financeiro.index', $query(['aba' => 'repasses'])) }}"
           class="rounded-lg px-4 py-2 text-sm font-semibold {{ $aba === 'repasses' ? 'bg-[#10284A] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">
            Repasses <span class="ml-1 text-xs opacity-75">{{ $repasses->count() }}</span>
        </a>
    </div>

    @if ($aba === 'pedidos')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Pedido</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">No sistema</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Venda iFood</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Comissão</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Taxas</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Outros</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Líquido</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Repasse</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                            <th class="px-4 py-3 text-center font-medium text-amber-50">Lançamentos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pedidos as $l)
                            @php
                                $c = $l['categorias'];
                                $s = $situacaoEstilo[$l['situacao']] ?? $situacaoEstilo['aguardando'];
                                $outros = $c['promocao'] + $c['cancelamento'] + $c['outros'];
                            @endphp
                            <tr class="hover:bg-gray-100 transition">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800">{{ $l['pedido'] }} <span class="text-xs font-normal text-gray-500">{{ $l['tipo'] }}</span></p>
                                    <p class="text-xs text-gray-500 max-w-48 truncate" title="{{ $l['cliente'] }}">{{ $l['cliente'] ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $l['data'] ? \Carbon\Carbon::parse($l['data'])->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-4 py-3 text-right {{ $l['cancelado'] ? 'text-red-600 line-through' : 'text-gray-700' }}">{{ $l['sistema'] !== null ? $dinheiro($l['sistema']) : '—' }}</td>
                                <td class="px-4 py-3 text-right {{ $valorCor($c['venda']) }}">{{ $dinheiro($c['venda']) }}</td>
                                <td class="px-4 py-3 text-right {{ $valorCor($c['comissao']) }}">{{ $dinheiro($c['comissao']) }}</td>
                                <td class="px-4 py-3 text-right {{ $valorCor($c['taxa'] + $c['entrega']) }}">{{ $dinheiro($c['taxa'] + $c['entrega']) }}</td>
                                <td class="px-4 py-3 text-right {{ $valorCor($outros) }}">{{ $dinheiro($outros) }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $l['liquido'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $dinheiro($l['liquido']) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $l['previsao'] ? \Carbon\Carbon::parse($l['previsao'])->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $s[1] }}" title="{{ $l['motivo'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $s[2] }}"></span>{{ $s[0] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" onclick="abrirLancamentos(this)" data-chave="{{ $l['order_id'] }}"
                                            data-titulo="Pedido iFood {{ $l['pedido'] }}" data-subtitulo="{{ $l['cliente'] }}"
                                            class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                        Ver <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ count($l['eventos']) }}</span>
                                    </button>
                                    <template id="lancamentos-{{ $l['order_id'] }}">
                                        @if ($l['motivo'])
                                            <p class="mb-3 rounded-lg px-3 py-2 text-sm text-left {{ $s[1] }}">{{ $l['motivo'] }}</p>
                                        @endif
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500">No sistema</p>
                                                <p class="text-sm font-semibold text-gray-800">{{ $l['sistema'] !== null ? $dinheiro($l['sistema']) : '—' }}</p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500">Líquido do repasse</p>
                                                <p class="text-sm font-semibold text-green-700">{{ $dinheiro($l['liquido']) }}</p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500">Recebido direto</p>
                                                <p class="text-sm font-semibold text-gray-800">{{ $dinheiro($l['direto']) }}</p>
                                            </div>
                                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                <p class="text-xs uppercase tracking-wide text-gray-500">Previsão do repasse</p>
                                                <p class="text-sm font-semibold text-gray-800">{{ $l['previsao'] ? \Carbon\Carbon::parse($l['previsao'])->format('d/m/Y') : '—' }}</p>
                                            </div>
                                        </div>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                                            <table class="w-full text-sm whitespace-nowrap">
                                                <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                                                    <tr>
                                                        <th class="px-3 py-2.5 text-left font-medium text-amber-50">Lançamento</th>
                                                        <th class="px-3 py-2.5 text-left font-medium text-amber-50">Categoria</th>
                                                        <th class="px-3 py-2.5 text-left font-medium text-amber-50">Pagamento</th>
                                                        <th class="px-3 py-2.5 text-right font-medium text-amber-50">Base / %</th>
                                                        <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor</th>
                                                        <th class="px-3 py-2.5 text-left font-medium text-amber-50">No repasse</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @forelse ($l['eventos'] as $e)
                                                        <tr class="text-gray-700">
                                                            <td class="px-3 py-2.5 text-left">
                                                                <p class="font-medium">{{ $e['descricao'] }}</p>
                                                                <p class="text-xs text-gray-400 font-mono">{{ $e['nome'] }}</p>
                                                            </td>
                                                            <td class="px-3 py-2.5 text-left">{{ $e['categoria'] }}</td>
                                                            <td class="px-3 py-2.5 text-left text-xs">{{ $e['pagamento'] ?: '—' }}</td>
                                                            <td class="px-3 py-2.5 text-right text-xs text-gray-500">
                                                                {{ $e['base'] !== null ? $dinheiro($e['base']) : '' }}{{ $e['percentual'] !== null ? ' · ' . number_format($e['percentual'], 2, ',', '.') . '%' : '' }}
                                                            </td>
                                                            <td class="px-3 py-2.5 text-right font-semibold {{ $valorCor($e['valor']) }}">{{ $dinheiro($e['valor']) }}</td>
                                                            <td class="px-3 py-2.5 text-left text-xs">{{ $e['impacta'] ? 'Sim' : 'Não (pago direto à loja)' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">O iFood ainda não lançou valores deste pedido.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">Nenhum pedido do iFood no período. Use "Sincronizar com o iFood" para buscar os lançamentos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $pedidos->links() }}</div>

        @if ($semPedido->isNotEmpty())
            <h3 class="mt-6 mb-2 text-sm font-semibold text-gray-700">Lançamentos sem pedido (ajustes, retenções, mensalidade…)</h3>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($semPedido as $e)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ $e->ocorrido_em ? \Carbon\Carbon::parse($e->ocorrido_em)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-4 py-2.5"><p class="font-medium text-gray-800">{{ $e->descricao ?: $e->nome }}</p><p class="text-xs text-gray-400 font-mono">{{ $e->nome }}</p></td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">{{ $e->impacta_repasse ? 'Entra no repasse' : 'Fora do repasse' }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap {{ $valorCor($e->valor) }}">{{ $dinheiro($e->valor) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Pagamento</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Vendas de</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Tipo</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Banco</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-50">Valor</th>
                            <th class="px-4 py-3 text-left font-medium text-amber-50">Contas a Receber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($repasses as $r)
                            <tr class="hover:bg-gray-100 transition">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $r->data_pagamento ? \Carbon\Carbon::parse($r->data_pagamento)->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $r->apuracao_inicio ? \Carbon\Carbon::parse($r->apuracao_inicio)->format('d/m') . ' a ' . \Carbon\Carbon::parse($r->apuracao_fim)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ \App\Services\IfoodFinanceiroService::TIPOS_REPASSE[$r->tipo] ?? $r->tipo }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $statusRepasse[$r->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ \App\Services\IfoodFinanceiroService::STATUS_REPASSE[$r->status] ?? ($r->status ?: '—') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $r->banco ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $r->tipo === 'BOLETO' ? 'text-red-600' : 'text-gray-800' }}">{{ $dinheiro($r->valor) }}</td>
                                <td class="px-4 py-3">
                                    @if ($r->conta_receber_id)
                                        <a href="{{ route('contas-receber.index', ['busca' => 'IFOOD-' . $r->item_id]) }}" class="text-xs font-medium text-blue-600 hover:underline">
                                            {{ $r->conta_status === 'recebida' ? 'Recebida' : 'Em aberto' }} · ver conta
                                        </a>
                                    @elseif ($r->tipo === 'BOLETO')
                                        <span class="text-xs text-red-600">Saldo devedor: pagar o boleto do iFood</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhum repasse no período (inclui os previstos para as próximas semanas).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-400">Cada repasse vira uma conta a receber "Repasse iFood". Quando o iFood informa o pagamento, a conta é baixada sozinha.</p>
    @endif

    {{-- Modal dos lançamentos de um pedido --}}
    <div id="modal-lancamentos" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" onclick="fecharLancamentos()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-full flex flex-col">
            <div class="flex items-start justify-between gap-4 px-6 py-4 bg-gray-700 rounded-t-xl">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-amber-50/70">Lançamentos do iFood</p>
                    <h2 id="lancamentos-titulo" class="text-base font-semibold text-amber-50"></h2>
                    <p id="lancamentos-subtitulo" class="text-sm text-amber-50/80 truncate"></p>
                </div>
                <button type="button" onclick="fecharLancamentos()" class="text-amber-50/70 hover:text-amber-50 text-2xl leading-none" aria-label="Fechar">&times;</button>
            </div>
            <div id="lancamentos-corpo" class="px-6 py-5 overflow-y-auto"></div>
        </div>
    </div>

    <script>
        const modalLancamentos = document.getElementById('modal-lancamentos');

        function abrirLancamentos(botao) {
            const modelo = document.getElementById('lancamentos-' + botao.dataset.chave);
            if (!modelo) return;
            document.getElementById('lancamentos-titulo').innerText = botao.dataset.titulo;
            document.getElementById('lancamentos-subtitulo').innerText = botao.dataset.subtitulo;
            document.getElementById('lancamentos-corpo').innerHTML = modelo.innerHTML;
            modalLancamentos.classList.remove('hidden');
        }

        function fecharLancamentos() {
            modalLancamentos.classList.add('hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modalLancamentos.classList.contains('hidden')) fecharLancamentos();
        });

        // Erro: aviso flutuante no mesmo padrão do layout (some em 8 s, pausa com o mouse em cima, fecha no X)
        function toastErro(texto) {
            document.getElementById('toast-erro-sincronizar')?.remove();
            const toast = document.createElement('div');
            toast.id = 'toast-erro-sincronizar';
            toast.setAttribute('role', 'alert');
            toast.className = 'fixed top-16 right-4 z-50 flex items-start gap-3 w-full max-w-md bg-white border border-red-200 border-l-4 border-l-red-500 shadow-lg rounded-lg px-4 py-3 text-sm text-gray-800 transition-all duration-300';
            toast.innerHTML = `
                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <div class="flex-1 min-w-0"><p class="font-semibold text-red-700">Não foi possível sincronizar</p><p data-texto class="mt-0.5 text-gray-700"></p></div>
                <button type="button" aria-label="Fechar" class="text-gray-400 hover:text-gray-600 text-lg leading-none cursor-pointer">&times;</button>`;
            toast.querySelector('[data-texto]').textContent = texto;
            document.body.appendChild(toast);

            let timer;
            const fechar = () => {
                if (!toast.isConnected) return;
                toast.classList.add('opacity-0', 'translate-x-4');
                setTimeout(() => toast.remove(), 300);
            };
            const agendar = () => { clearTimeout(timer); timer = setTimeout(fechar, 8000); };
            agendar();
            toast.addEventListener('mouseenter', () => clearTimeout(timer));
            toast.addEventListener('mouseleave', agendar);
            toast.querySelector('button').addEventListener('click', fechar);
        }

        async function sincronizarIfood(botao) {
            botao.disabled = true;
            botao.textContent = 'Sincronizando…';
            try {
                const resp = await fetch(@json(route('ifood.financeiro.sincronizar')), {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                });
                const r = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(r.message || 'Não foi possível sincronizar.');
                // O servidor guardou a mensagem: ao recarregar, aparece o aviso flutuante padrão do sistema
                location.reload();
            } catch (e) {
                toastErro(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message);
                botao.disabled = false;
                botao.textContent = 'Sincronizar com o iFood';
            }
        }
    </script>
@endsection
