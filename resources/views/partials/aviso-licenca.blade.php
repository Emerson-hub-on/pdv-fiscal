{{-- Aviso de licença perto do vencimento ou na tolerância (bloqueada, o middleware já leva à tela da licença).
     Não consulta o usuário aqui: no caixa o servidor pode estar fora do ar. Cada layout informa a rota.
     faixa = true: faixa fina no topo do conteúdo (painel), com o link para registrar a licença;
     sem ela, aviso flutuante no canto (caixa), só informativo: a licença é registrada pelo painel administrativo. --}}
@if (isset($licenca) && in_array($licenca['estado'], ['vencendo', 'tolerancia'], true))
    @php($cores = $licenca['estado'] === 'tolerancia' ? 'border-red-300 bg-red-50 text-red-700' : 'border-amber-300 bg-amber-50 text-amber-800')
    @if ($faixa ?? false)
        <div class="border-b px-6 py-1.5 text-sm flex flex-wrap items-center justify-center gap-x-2 {{ $cores }}">
            <span><span class="font-semibold">Licença:</span> {{ $licenca['mensagem'] }}</span>
            <a href="{{ route($rotaLicenca ?? 'licenca.tela') }}" class="underline font-medium">Registrar nova licença</a>
        </div>
    @else
        <div role="status" class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg pointer-events-none {{ $cores }}">
            <span class="font-semibold">Licença:</span> {{ $licenca['mensagem'] }}
            <span class="block mt-1 text-xs opacity-80">Avise o técnico para renovar pelo painel administrativo.</span>
        </div>
    @endif
@endif
