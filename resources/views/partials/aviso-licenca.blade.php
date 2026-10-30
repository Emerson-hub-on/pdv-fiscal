{{-- Aviso de licença perto do vencimento ou na tolerância (bloqueada, o middleware já leva à tela da licença) --}}
@if (isset($licenca) && in_array($licenca['estado'], ['vencendo', 'tolerancia'], true))
    <a href="{{ route(auth('web')->user()?->isAdmin() ? 'licenca.painel' : 'licenca.tela') }}"
       class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg
              {{ $licenca['estado'] === 'tolerancia' ? 'border-red-300 bg-red-50 text-red-700' : 'border-amber-300 bg-amber-50 text-amber-800' }}">
        <span class="font-semibold">Licença:</span> {{ $licenca['mensagem'] }}
        <span class="underline">Registrar nova licença</span>
    </a>
@endif
