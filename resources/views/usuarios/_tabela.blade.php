<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Código</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Acessos</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($usuarios as $usuario)
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $usuario->{$cfg['codigo']} }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $usuario->name }}</td>

                    
                    <!-- Coluna Acessos -->
                    <td class="px-4 py-3">
                        <div class="flex gap-1.5 items-center">
                            @if ($usuario->acesso_caixa)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">Caixa</span>
                            @endif
                            @if ($usuario->acesso_fiscal)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Fiscal</span>
                            @endif
                            @if ($usuario->acesso_supervisor)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700">Supervisor</span>
                            @endif
                            
                            <!-- Bloco inserido: Selo de Restrições -->
                            @if ($perfil === 'caixa' && !empty($usuario->permissoes_caixa))
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Liberações</span>
                            @endif

                            @if ($perfil === 'supervisor' && !empty($usuario->permissoes_supervisor))
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Restrições</span>
                            @endif
                        </div>
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
                                <a href="{{ route('usuarios.edit', [$perfil, $usuario]) }}"
                                class="block px-4 py-2.5 text-gray-800 hover:bg-gray-50">
                                    Editar
                                </a>

                                @if (in_array($perfil, ['fiscal', 'caixa', 'supervisor'], true))
                                    <a href="{{ route('usuarios.permissoes', [$perfil, $usuario]) }}"
                                    class="block px-4 py-2.5 text-gray-800 hover:bg-gray-50">
                                        Permissões
                                    </a>
                                @endif

                                <div class="border-t border-gray-100 my-1"></div>

                                <form action="{{ route('usuarios.revogar', [$perfil, $usuario]) }}" method="POST"
                                    onsubmit="return confirm({{ Illuminate\Support\Js::from('Remover o acesso de ' . $usuario->name . ' como ' . $cfg['singular'] . '?') }})">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2.5 text-red-600 hover:bg-gray-50">
                                        Remover acesso
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhum cadastro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $usuarios->links() }}
</div>