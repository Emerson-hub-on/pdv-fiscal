<?php

namespace App\Providers;

use App\Auth\UserProviderLocal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('usuarios-locais', function ($app, array $config) {
            return new UserProviderLocal($app['hash'], $config['model']);
        });
    }
}
