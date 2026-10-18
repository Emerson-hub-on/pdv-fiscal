<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Serviço</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Cód. tributação</th>
                <th class="px-4 py-3 text-right font-medium text-amber-50">Valor padrão</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Situação</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($servicos as $servico)
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $servico->codigo }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $servico->nome }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        <span class="font-mono text-xs">{{ $servico->codigoTributacaoFormatado() }}</span>
                        @if ($servico->itemListaServico())
                            <span class="text-xs text-gray-400">· item {{ $servico->itemListaServico() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">R$ {{ number_format((float) $servico->valor_padrao, 2, ',', '.') }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $servico->ativo ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $servico->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </td>

                    <!-- Coluna Ações -->
                    <td class="px-4 py-3">
                        <div class="relative inline-block">
                            <button type="button" data-menu-acoes title="Ações" aria-label="Ações" aria-haspopup="true"
                                    class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition">
                                <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                                    <circle cx="10" cy="4" r="1.6"></circle>
                                    <circle cx="10" cy="10" r="1.6"></circle>
                                    <circle cx="10" cy="16" r="1.6"></circle>
                                </svg>
                            </button>

                            <div data-menu-acoes-painel
                                 class="hidden fixed z-50 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">
                                <a href="{{ route('servicos.edit', $servico) }}"
                                   class="block px-4 py-2.5 text-gray-800 hover:bg-gray-50">
                                    Editar
                                </a>

                                <div class="border-t border-gray-100 my-1"></div>

                                <form action="{{ route('servicos.toggle-ativo', $servico) }}" method="POST"
                                      @if ($servico->ativo)
                                          onsubmit="return confirm({{ Illuminate\Support\Js::from('Inativar o serviço ' . $servico->nome . '?') }})"
                                      @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="w-full text-left px-4 py-2.5 hover:bg-gray-50 {{ $servico->ativo ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $servico->ativo ? 'Inativar' : 'Reativar' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum serviço encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $servicos->links() }}
</div>
