<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'acesso' => \App\Http\Middleware\VerificaAcesso::class,
            'permissao' => \App\Http\Middleware\PermissaoModulo::class,
        ]);

        // Quem não está logado volta para o login do contexto certo (caixa x cadastros)
        $middleware->redirectGuestsTo(function (Request $request) {
                    \Log::info('barrado como visitante', [
                        'path' => $request->path(),
                        'sid' => $request->session()->getId(),
                        'tem_login_caixa' => collect($request->session()->all())->keys()->contains(fn ($k) => str_starts_with($k, 'login_caixa')),
                    ]);

                    return route('auth.login', [
                        'modo' => in_array('auth:caixa', $request->route()?->gatherMiddleware() ?? [], true)
                            ? 'operador'
                            : 'admin',
                    ]);
                });
            })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();