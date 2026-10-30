<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Services\SyncService;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $resultado = (new SyncService())->sincronizarTudo();

    // O retorno antes era descartado: erros de cada parte passam a ir para o log
    // (todas as partes do sincronizarTudo, incluindo os envios de vendas, inutilizações e ocorrências)
    foreach ($resultado as $parte => $retorno) {
        if (!($retorno['sucesso'] ?? false)) {
            Log::warning("Sincronização falhou: {$parte}", [
                'erro' => $retorno['erro'] ?? 'sem mensagem',
            ]);
        }

        // Envios que rodam item a item: a parte "deu certo", mas algum registro ficou com erro
        if (!empty($retorno['falhas'])) {
            Log::warning("Sincronização com falhas: {$parte}", ['falhas' => $retorno['falhas']]);
        }
    }
})->everyMinute()->name('sincronizar-pdv')->withoutOverlapping(5);