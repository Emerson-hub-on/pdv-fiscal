@php
    $badges = [
        'aberta'  => ['Em aberto', 'bg-amber-100 text-amber-800'],
        'vencida' => ['Vencida',   'bg-red-100 text-red-700'],
        'paga'    => ['Paga',      'bg-green-100 text-green-700'],
    ];
@endphp

{{-- Tabela de largura e altura fixas: texto longo é cortado com "…" (passe o mouse para ver tudo) e o detalhe completo fica na tela da conta (clique na linha). --}}
<table class="w-full text-sm table-fixed">
    <colgroup>
        <col class="w-[16%]">
        <col class="w-[14%]">
        <col class="w-[15%]">
        <col class="w-[10%]">
        <col class="w-[11%]">
        <col class="w-[8%]">
        <col class="w-[10%]">
        <col class="w-[10%]">
        <col class="w-[6%]">
    </colgroup>
    <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
        <tr>
            <th class="text-left px-2 py-2">Número</th>
            <th class="text-left px-2 py-2">Tipo</th>
            <th class="text-left px-2 py-2">Fornecedor</th>
            <th class="text-left px-2 py-2">Data da conta</th>
            <th class="text-right px-2 py-2">Total</th>
            <th class="text-center px-2 py-2">Parcelas</th>
            <th class="text-left px-2 py-2">Próx. vencimento</th>
            <th class="text-left px-2 py-2">Status</th>
            <th class="text-right px-2 py-2">Ações</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @forelse ($contas as $conta)
            @php
                $rotaLinha = route('contas-pagar.edit', $conta);
                $situacao  = $conta->situacao();
                $proxima   = $conta->proximaParcela();
                $pagas     = $conta->parcelas->filter(fn ($p) => $p->isPaga())->count();
                $numero    = $conta->numeroExibicao();
                $nomeForn  = $conta->fornecedor?->nome_exibicao;
                $total     = 'R$ ' . number_format($conta->valor_total, 2, ',', '.');
            @endphp
            <tr class="h-14 hover:bg-gray-50">
                <td class="px-2 py-2 cursor-pointer font-medium" title="{{ $numero }} — {{ $conta->descricao }}" onclick="location.href='{{ $rotaLinha }}'">
                    <span class="block truncate">{{ $numero }}</span>
                    <span class="block truncate text-xs font-normal text-gray-400">{{ $conta->descricao }}</span>
                </td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $conta->tipo->descricao }}" onclick="location.href='{{ $rotaLinha }}'">{{ $conta->tipo->descricao }}</td>
                <td class="px-2 py-2 cursor-pointer truncate" title="{{ $nomeForn }}" onclick="location.href='{{ $rotaLinha }}'">{{ $nomeForn ?? '—' }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">{{ $conta->data_conta->format('d/m/Y') }}</td>
                <td class="px-2 py-2 text-right cursor-pointer truncate" title="{{ $total }}" onclick="location.href='{{ $rotaLinha }}'">{{ $total }}</td>
                <td class="px-2 py-2 text-center cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">{{ $pagas }}/{{ $conta->parcelas->count() }}</td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap {{ $proxima?->isVencida() ? 'text-red-600 font-medium' : '' }}" onclick="location.href='{{ $rotaLinha }}'">
                    {{ $proxima ? $proxima->vencimento->format('d/m/Y') : '—' }}
                </td>
                <td class="px-2 py-2 cursor-pointer whitespace-nowrap" onclick="location.href='{{ $rotaLinha }}'">
                    <span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badges[$situacao][1] }}">{{ $badges[$situacao][0] }}</span>
                </td>

                {{-- sem truncate aqui: o menu de ações é absoluto e seria cortado --}}
                <td class="px-2 py-2 text-right relative">
                    <button type="button" onclick="toggleAcoesLinha({{ $conta->id }})"
                            class="text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100">
                        ⋮
                    </button>

                    <div id="dropdown-acoes-{{ $conta->id }}"
                         class="hidden absolute right-4 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-44 z-50 text-left">
                        <a href="{{ $rotaLinha }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            {{ $situacao === 'paga' ? 'Visualizar' : 'Editar / Baixar parcelas' }}
                        </a>

                        <div class="border-t border-gray-100 my-1"></div>

                        @if ($conta->temParcelaPaga())
                            <span class="block px-4 py-2 text-xs text-gray-400">Estorne os pagamentos para excluir</span>
                        @else
                            <form method="POST" action="{{ route('contas-pagar.destroy', $conta) }}"
                                  onsubmit="return confirm('Excluir esta conta e suas parcelas? Esta ação não pode ser desfeita.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Excluir conta</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-4 py-8 text-center text-gray-400">Nenhuma conta encontrada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4">{{ $contas->links() }}</div>