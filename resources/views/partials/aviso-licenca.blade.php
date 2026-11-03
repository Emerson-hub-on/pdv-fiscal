{{-- Aviso de licença perto do vencimento ou na tolerância (bloqueada, o middleware já leva à tela da licença).
     Não consulta o usuário aqui: no caixa o servidor pode estar fora do ar. Cada layout informa a rota.
     faixa = true: faixa fina no topo do conteúdo (painel); sem ela, aviso flutuante no canto (caixa). --}}
@if (isset($licenca) && in_array($licenca['estado'], ['vencendo', 'tolerancia'], true))
    @php($cores = $licenca['estado'] === 'tolerancia' ? 'border-red-300 bg-red-50 text-red-700' : 'border-amber-300 bg-amber-50 text-amber-800')
    @if ($faixa ?? false)
        <div class="border-b px-6 py-1.5 text-sm flex flex-wrap items-center justify-center gap-x-2 {{ $cores }}">
            <span><span class="font-semibold">Licença:</span> {{ $licenca['mensagem'] }}</span>
            <a href="{{ route($rotaLicenca ?? 'licenca.tela') }}" class="underline font-medium">Registrar nova licença</a>
        </div>
    @else
        <a href="{{ route($rotaLicenca ?? 'licenca.tela') }}"
           class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg {{ $cores }}">
            <span class="font-semibold">Licença:</span> {{ $licenca['mensagem'] }}
            <span class="underline">Registrar nova licença</span>
        </a>
    @endif
@endif
