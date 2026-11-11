@extends('layouts.app')

@section('titulo', 'Cliente iFood')
@section('body-class', 'conteudo-largo')

@section('breadcrumb')
    <span>Comandas</span>
    <span class="text-gray-300">›</span>
    <a href="{{ route('clientes-ifood.index') }}" class="hover:underline">Clientes iFood</a>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">{{ $cliente->nome ?: 'Cliente' }}</span>
@endsection

@php
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $statusPedido = [
        'PLACED' => ['Aguardando aceite', 'bg-amber-50 text-amber-700'],
        'CONFIRMED' => ['Em preparo', 'bg-amber-50 text-amber-700'],
        'READY_TO_PICKUP' => ['Pronto', 'bg-blue-50 text-blue-700'],
        'DISPATCHED' => ['A caminho', 'bg-indigo-50 text-indigo-700'],
        'CONCLUDED' => ['Concluído', 'bg-green-50 text-green-700'],
        'CANCELLED' => ['Cancelado', 'bg-red-50 text-red-700'],
    ];
    $ticket = $cliente->pedidos_validos ? $cliente->total_gasto / $cliente->pedidos_validos : 0;
@endphp

@section('conteudo')
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Cliente do iFood</p>
            <h1 class="text-xl font-bold text-gray-800">{{ $cliente->nome ?: 'Cliente sem nome' }}</h1>
            <p class="text-sm text-gray-500">
                {{ $cliente->segmento() }}
                · cliente desde {{ $cliente->primeiro_pedido_em?->format('d/m/Y') ?? '—' }}
            </p>
        </div>
        <a href="{{ route('clientes-ifood.index') }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg font-semibold text-sm transition">Voltar</a>
    </div>

    {{-- Números --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Pedidos aqui</p>
            <p class="text-lg font-semibold text-gray-800">{{ $cliente->pedidos_validos }}</p>
            <p class="text-xs text-gray-400">{{ $cliente->pedidos_cancelados }} cancelado(s) à parte</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Pedidos na loja (iFood)</p>
            <p class="text-lg font-semibold text-gray-800">{{ $cliente->pedidos_na_loja_ifood ?? '—' }}</p>
            <p class="text-xs text-gray-400">número informado pelo iFood</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Total gasto</p>
            <p class="text-lg font-semibold text-green-700">{{ $dinheiro($cliente->total_gasto) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Ticket médio</p>
            <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($ticket) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-gray-500">Frequência</p>
            <p class="text-lg font-semibold text-gray-800">{{ $frequencia !== null ? 'a cada ' . number_format($frequencia, 1, ',', '.') . ' dias' : '—' }}</p>
            <p class="text-xs text-gray-400">último: {{ $cliente->ultimo_pedido_em?->diffForHumans() ?? '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        {{-- Cadastro --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Cadastro</h2>
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-gray-500">Nome</dt><dd class="col-span-2 text-gray-800">{{ $cliente->nome ?: '—' }}</dd>
                <dt class="text-gray-500">CPF</dt><dd class="col-span-2 text-gray-800 font-mono">{{ $cliente->cpfFormatado() ?: 'não informado' }}</dd>
                <dt class="text-gray-500">Endereço</dt><dd class="col-span-2 text-gray-800">{{ $cliente->ultimo_endereco ?: '—' }}</dd>
                <dt class="text-gray-500">Cidade</dt><dd class="col-span-2 text-gray-800">{{ collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / ') ?: '—' }}</dd>
                <dt class="text-gray-500">1º pedido</dt><dd class="col-span-2 text-gray-800">{{ $cliente->primeiro_pedido_em?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="text-gray-500">Último</dt><dd class="col-span-2 text-gray-800">{{ $cliente->ultimo_pedido_em?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
            <p class="mt-3 text-xs text-gray-400">Telefone: o iFood não passa o número do cliente (só um 0800 com localizador, que muda a cada pedido).</p>

            <form method="POST" action="{{ route('clientes-ifood.observacao', $cliente) }}" class="mt-4">
                @csrf
                @method('PATCH')
                <label class="block text-xs font-medium uppercase tracking-wide text-gray-500 mb-1.5" for="observacao">Anotação da loja</label>
                <textarea name="observacao" id="observacao" rows="3" maxlength="1000" placeholder="Ex.: prefere sem cebola, cliente VIP…"
                          @disabled(!auth()->user()?->podeAlterar('comandas'))
                          class="w-full bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-slate-800 disabled:bg-gray-50">{{ $cliente->observacao }}</textarea>
                @if (auth()->user()?->podeAlterar('comandas'))
                    <button class="mt-2 bg-[#10284A] hover:bg-[#1a3a66] text-white px-4 py-2 rounded-lg font-semibold text-sm transition">Salvar anotação</button>
                @endif
            </form>
        </section>

        {{-- Produtos mais pedidos --}}
        <section class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Mais pedidos por este cliente</h2>
            @forelse ($produtos as $i => $p)
                <div class="flex items-center gap-3 py-2 {{ $i ? 'border-t border-gray-100' : '' }}">
                    <span class="w-6 text-center text-xs font-semibold text-gray-400">{{ $i + 1 }}º</span>
                    <p class="flex-1 min-w-0 truncate text-sm font-medium text-gray-800">{{ $p->nome }}</p>
                    <span class="text-xs text-gray-500">{{ (int) $p->quantidade }} un · em {{ $p->pedidos }} pedido(s)</span>
                    <span class="w-24 text-right text-sm font-semibold text-gray-800">{{ $dinheiro($p->total) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">Ainda sem itens.</p>
            @endforelse
        </section>
    </div>

    {{-- Histórico --}}
    <h2 class="text-sm font-semibold text-gray-800 mb-2">Histórico de pedidos</h2>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#10284A] text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Data</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Pedido</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Itens</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Pagamento</th>
                        <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pedidos as $p)
                        @php
                            $s = $statusPedido[$p->status] ?? [$p->status, 'bg-slate-100 text-slate-600'];
                            $cancelado = $p->status === 'CANCELLED';
                            $itens = $p->pedido?->itens ?? collect();
                        @endphp
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ ($p->criado_ifood_em ?? $p->created_at)?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="font-medium text-gray-800">#{{ $p->display_id ?: $p->id }}</p>
                                <p class="text-xs text-gray-500">{{ \App\Models\IfoodPedido::TIPOS[$p->tipo] ?? $p->tipo }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700 {{ $cancelado ? 'line-through text-gray-400' : '' }}">
                                {{ $itens->map(fn ($i) => $i->quantidade . 'x ' . $i->nome)->implode(', ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ collect($p->paraTela()['pagamentos'])->pluck('descricao')->implode(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $s[1] }}">{{ $s[0] }}</span></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold {{ $cancelado ? 'text-red-600 line-through' : 'text-gray-800' }}">{{ $dinheiro($p->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Nenhum pedido.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $pedidos->links() }}</div>
@endsection
