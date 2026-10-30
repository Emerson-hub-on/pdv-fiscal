<?php

namespace App\Http\Middleware;

use App\Services\LicencaService;
use Closure;
use Illuminate\Http\Request;

// Com a licença vencida (depois da tolerância) só ficam abertas as rotas abaixo
class VerificaLicenca
{
    private const LIBERADAS = [
        'auth.*',
        'licenca.*',
        // Documentos fiscais já emitidos: a empresa é obrigada a guardá-los e poder consultá-los
        'notasfiscais.index', 'notasfiscais.show', 'notasfiscais.danfe', 'notasfiscais.xml',
        'mdfes.index', 'mdfes.show', 'mdfes.xml', 'mdfes.pdf',
        'vendas.cupons', 'vendas.cupom-fiscal',
        // Fechar o caixa que ficou aberto e transmitir as NFC-e emitidas em contingência
        'caixa.fechar-form', 'caixa.fechar', 'caixa.fechado', 'caixa.relatorio-fechamento',
        'contingencias.*', 'sincronizacao.executar',
    ];

    public function __construct(private LicencaService $licenca)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs(...self::LIBERADAS) || !$this->licenca->bloqueada()) {
            return $next($request);
        }

        $mensagem = $this->licenca->situacao()['mensagem'] . ' Sistema bloqueado: entre em contato com o suporte.';

        if ($request->expectsJson()) {
            return response()->json(['sucesso' => false, 'erro' => $mensagem, 'message' => $mensagem], 423);
        }

        return redirect()->route('licenca.tela');
    }
}
