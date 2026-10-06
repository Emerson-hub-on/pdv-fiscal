<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\SyncService;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $resultado = (new SyncService())->sincronizarTudo();

    // O retorno antes era descartado: erros de cada parte passam a ir para o log
    foreach (['catalogo', 'clientes', 'usuarios', 'pdvs', 'caixas', 'empresa'] as $parte) {
        if (!($resultado[$parte]['sucesso'] ?? false)) {
            Log::warning("Sincronização falhou: {$parte}", [
                'erro' => $resultado[$parte]['erro'] ?? 'sem mensagem',
            ]);
        }
    }
})->everyMinute()->name('sincronizar-pdv')->withoutOverlapping(5);
