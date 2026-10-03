<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Nome</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Login</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Acessos</th>
                <th class="px-4 py-3 text-left font-medium text-amber-50">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($usuarios as $usuario)
                <tr class="hover:bg-gray-200 transition">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $usuario->name }}</td>
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $usuario->username }}</td>
                    
                    <!-- Coluna Acessos -->
                    <td class="px-4 py-3">
                        <div class="flex gap-1.5 items-center">
                            @if ($usuario->acesso_caixa)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">Caixa</span>
                            @endif
                            @if ($usuario->acesso_fiscal)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Fiscal</span>
                            @endif
                            
                            <!-- Bloco inserido: Selo de Restrições -->
                            @if ($perfil === 'fiscal' && !empty($usuario->permissoes))
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Restrições</span>
                            @endif
                        </div>
                    </td>

                    <!-- Coluna Ações -->
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('usuarios.edit', [$perfil, $usuario]) }}" class="text-blue-600 hover:text-blue-700 font-medium">Editar</a>
                            
                            <!-- Bloco inserido: Link Permissões -->
                            @if ($perfil === 'fiscal')
                                <a href="{{ route('usuarios.permissoes', [$perfil, $usuario]) }}" class="text-purple-600 hover:text-purple-700 font-medium">Permissões</a>
                            @endif

                            <form action="{{ route('usuarios.revogar', [$perfil, $usuario]) }}" method="POST"
                                  onsubmit="return confirm({{ Illuminate\Support\Js::from('Remover o acesso de ' . $usuario->name . ' como ' . $cfg['singular'] . '?') }})">
                                @csrf
                                <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">Remover acesso</button>
                            </form>
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