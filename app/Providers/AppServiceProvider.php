<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::connection('sqlite_local')->statement('PRAGMA journal_mode=WAL;');
        DB::connection('sqlite_local')->statement('PRAGMA busy_timeout=5000;'); // espera ate 5s antes de falhar
    }
}
