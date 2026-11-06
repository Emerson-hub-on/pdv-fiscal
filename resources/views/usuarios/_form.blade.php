@php extract(config('estilo.formulario')); @endphp
@php
    $usuario = $usuario ?? null;
    // tipo que não faz login (ex.: Técnico) não tem senha nem "código de acesso"
    $permiteLogin = (bool) $cfg['tipo']->permite_login;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-xl">
    <div class="space-y-5">
        <div>
            <label class="{{ $labelCls }}">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="name" maxlength="100" required autocomplete="off"
                   value="{{ old('name', $usuario->name ?? '') }}"
                   class="{{ $inputCls }}">
        </div>

        <div>
            <label class="{{ $labelCls }}">{{ $permiteLogin ? 'Código de acesso' : 'Código' }}</label>
            @if ($usuario)
                <input type="text" value="{{ $usuario->codigo }}" disabled
                       class="w-full border border-gray-200 bg-gray-50 text-gray-500 font-mono rounded-lg px-3 py-2.5 text-sm">
                <p class="text-xs text-gray-400 mt-1">
                    {{ $permiteLogin
                        ? 'Usado no login junto com a senha, em todos os tipos de acesso desta pessoa. Não pode ser alterado.'
                        : 'Identifica esta pessoa no cadastro. Não pode ser alterado.' }}
                </p>
            @else
                <p class="text-xs text-gray-500">
                    {{ $permiteLogin
                        ? 'Gerado automaticamente ao salvar. É o código que a pessoa digita no login, junto com a senha.'
                        : 'Gerado automaticamente ao salvar.' }}
                </p>
            @endif
        </div>

        @if ($permiteLogin)
        <div>
            <label class="{{ $labelCls }}">
                Senha @unless ($usuario) <span class="text-red-500">*</span> @endunless
            </label>
            <input type="password" name="password" minlength="4" autocomplete="new-password"
                   class="{{ $inputCls }}">
            @if ($usuario)
                <p class="text-xs text-gray-400 mt-1">Deixe em branco para manter a senha atual. A senha vale para todos os tipos de acesso desta pessoa.</p>
            @endif
        </div>
        @else
        <p class="text-xs text-gray-500">
            Este tipo não acessa o sistema: o cadastro serve apenas para a pessoa aparecer na Ordem de Serviço.
        </p>
        @endif
    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        Salvar
    </button>
    <a href="{{ route('usuarios.index', $perfil) }}" class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>