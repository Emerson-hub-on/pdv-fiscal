<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificaAcesso
{
    public function handle(Request $request, Closure $next, string ...$modulos)
    {
        $usuario = $request->user();

        $permitido = $usuario && collect($modulos)->contains(fn ($modulo) => match ($modulo) {
            'admin'  => $usuario->isAdmin(),
            'caixa'  => $usuario->podeAcessarCaixa(),
            'fiscal' => $usuario->podeAcessarFiscal(),
            default  => false,
        });

        abort_unless($permitido, 403, 'Você não tem permissão para acessar esta área.');

        return $next($request);
    }
}