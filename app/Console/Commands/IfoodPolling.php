<?php

namespace App\Console\Commands;

use App\Models\IfoodConfiguracao;
use App\Services\IfoodService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Busca os pedidos do iFood (polling). Sem opção: uma consulta. Com --sempre: fica rodando e consulta
 * a cada 30 s (para deixar aberto no servidor, ou iniciar com o Windows), mesmo sem a tela de pedidos aberta.
 */
class IfoodPolling extends Command
{
    protected $signature = 'ifood:polling {--sempre : Fica rodando e consulta a cada 30 segundos}';

    protected $description = 'Busca os pedidos novos do iFood (polling)';

    public function handle(IfoodService $ifood): int
    {
        do {
            $inicio = microtime(true);

            try {
                $cfg = IfoodConfiguracao::atual();
                if (!$cfg->ativo) {
                    $this->line(now()->format('H:i:s') . ' Recebimento do iFood desligado (Cadastros → iFood).');
                } else {
                    $r = $ifood->polling($cfg, true);
                    $this->line(now()->format('H:i:s') . " {$r['eventos']} evento(s), {$r['pedidos_novos']} pedido(s) novo(s)"
                        . ($r['erros'] ? ' · ERRO: ' . implode(' | ', $r['erros']) : ''));
                }
            } catch (Throwable $e) {
                $this->error(now()->format('H:i:s') . ' ' . $e->getMessage());
            }

            if ($this->option('sempre')) {
                $espera = IfoodService::INTERVALO_POLLING - (microtime(true) - $inicio);
                if ($espera > 0) {
                    usleep((int) ($espera * 1_000_000));
                }
            }
        } while ($this->option('sempre'));

        return self::SUCCESS;
    }
}
