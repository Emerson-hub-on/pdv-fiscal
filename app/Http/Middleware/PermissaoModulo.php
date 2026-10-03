<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissaoModulo
{
    // GETs que abrem formulário de alteração (além de *.create e *.edit)
    private const GETS_DE_ALTERACAO = ['empresa.editar', 'notasfiscais.cancelar-form'];

    public function handle(Request $request, Closure $next, string $modulo)
    {
        $nivel = $request->user()?->nivelPermissao($modulo) ?? 'bloqueado';

        abort_if($nivel === 'bloqueado', 403, 'Você não tem acesso a este módulo.');

        abort_if(
            $nivel === 'consulta' && $this->ehAlteracao($request),
            403,
            'Seu usuário tem acesso somente para consulta neste módulo.'
        );

        return $next($request);
    }

    private function ehAlteracao(Request $request): bool
    {
        // POST, PUT, PATCH, DELETE
        if (!$request->isMethodSafe()) {
            return true;
        }

        $nome = (string) $request->route()?->getName();

        return (bool) preg_match('/(^|\.)(create|edit)$/', $nome)
            || in_array($nome, self::GETS_DE_ALTERACAO, true);
    }
}