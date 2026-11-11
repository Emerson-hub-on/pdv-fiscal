<?php

namespace App\Console\Commands;

use App\Models\IfoodCliente;
use App\Models\IfoodPedido;
use Illuminate\Console\Command;

/** Cadastra/atualiza os clientes do iFood a partir dos pedidos já gravados (do mais antigo ao mais novo). */
class IfoodClientes extends Command
{
    protected $signature = 'ifood:clientes';

    protected $description = 'Atualiza o cadastro de clientes do iFood a partir dos pedidos';

    public function handle(): int
    {
        $n = 0;
        IfoodPedido::orderBy('id')->chunkById(200, function ($pedidos) use (&$n) {
            foreach ($pedidos as $pedido) {
                if (IfoodCliente::registrar($pedido)) {
                    $n++;
                }
            }
        });

        $this->info("{$n} pedido(s) ligados a " . IfoodCliente::count() . ' cliente(s).');

        return self::SUCCESS;
    }
}
