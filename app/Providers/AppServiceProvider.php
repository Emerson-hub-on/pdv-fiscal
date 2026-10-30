<?php

namespace App\Providers;

use App\Auth\UserProviderLocal;
use App\Services\LicencaService;
use Illuminate\Support\Facades\Auth;
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

        View::composer(['layouts.app', 'layouts.caixa'], function ($view) {
            $view->with('licenca', app(LicencaService::class)->situacao());
        });
    }
}
