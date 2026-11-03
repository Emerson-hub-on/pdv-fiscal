<?php

namespace App\Providers;

use App\Auth\UserProviderLocal;
use App\Services\LicencaService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uma conferência de licença por requisição (middleware e aviso dos layouts usam a mesma)
        $this->app->scoped(LicencaService::class);
    }

    public function boot(): void
    {
        Auth::provider('usuarios-locais', function ($app, array $config) {
            return new UserProviderLocal($app['hash'], $config['model']);
        });

        RateLimiter::for('consulta-login', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('registro-licenca', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('primeira-carga', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        View::composer(['layouts.app', 'layouts.caixa'], function ($view) {
            $view->with('licenca', app(LicencaService::class)->situacao());
        });
    }
}
