<?php

namespace Tests\Feature;

use App\Http\Controllers\IfoodIndicadoresController;
use App\Models\IfoodPedido;
use App\Services\IfoodIndicadoresService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IfoodIndicadoresTempTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql'];

    private function pedido(array $dados, array $itens = []): IfoodPedido
    {
        $cozinha = null;
        if ($itens) {
            $cozinhaId = DB::table('comanda_pedidos')->insertGetId([
                'uuid' => (string) Str::uuid(), 'origem' => 'ifood', 'status' => 'concluido', 'total' => $dados['total'] ?? 0,
                'enviado_em' => $dados['criado_ifood_em'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($itens as [$nome, $qtd, $sub]) {
                DB::table('comanda_pedido_itens')->insert([
                    'pedido_id' => $cozinhaId, 'nome' => $nome, 'quantidade' => $qtd, 'preco_unitario' => $sub / $qtd, 'subtotal' => $sub,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $cozinha = $cozinhaId;
        }

        return IfoodPedido::create($dados + [
            'ifood_order_id' => (string) Str::uuid(), 'display_id' => (string) random_int(1000, 9999), 'tipo' => 'DELIVERY',
            'momento' => 'IMMEDIATE', 'comanda_pedido_id' => $cozinha,
        ]);
    }

    public function test_indicadores(): void
    {
        DB::table('ifood_pedidos')->delete(); // só os do teste (rollback no final)
        $d = '2030-03-'; // seg 04/03/2030 ... (2030-03-04 é segunda)

        // 1) entrega concluída: aceite 2 min, preparo 20 min, entrega 30 min, total 52 min
        $this->pedido(['status' => 'CONCLUDED', 'total' => 100, 'criado_ifood_em' => "{$d}04 19:00:00", 'confirmado_em' => "{$d}04 19:02:00",
            'despachado_em' => "{$d}04 19:22:00", 'concluido_em' => "{$d}04 19:52:00"], [['X-Burger', 2, 60], ['Coca', 2, 20]]);
        // 2) retirada concluída: aceite 4 min, preparo 10 min
        $this->pedido(['tipo' => 'TAKEOUT', 'status' => 'CONCLUDED', 'total' => 50, 'criado_ifood_em' => "{$d}04 19:30:00", 'confirmado_em' => "{$d}04 19:34:00",
            'pronto_em' => "{$d}04 19:44:00", 'concluido_em' => "{$d}04 19:50:00"], [['X-Burger', 1, 30], ['Batata', 1, 20]]);
        // 3) cancelado pelo iFood com motivo
        $this->pedido(['status' => 'CANCELLED', 'total' => 80, 'criado_ifood_em' => "{$d}05 12:10:00", 'cancelado_por' => 'ifood',
            'cancelado_em' => "{$d}05 12:20:00", 'motivo_cancelamento' => 'Cliente desistiu'], [['Pizza', 1, 80]]);
        // 4) agendado: aceite conta, preparo não; tempo absurdo (10 h) fica fora
        $this->pedido(['status' => 'CONCLUDED', 'momento' => 'SCHEDULED', 'total' => 150, 'criado_ifood_em' => "{$d}06 08:00:00",
            'confirmado_em' => "{$d}06 08:06:00", 'despachado_em' => "{$d}06 18:00:00", 'concluido_em' => "{$d}06 18:30:00"]);
        // período anterior (mesmo tamanho: 7 dias antes) — 1 pedido, aceite 10 min
        $this->pedido(['status' => 'CONCLUDED', 'total' => 40, 'criado_ifood_em' => '2030-03-01 19:00:00', 'confirmado_em' => '2030-03-01 19:10:00']);

        $r = app(IfoodIndicadoresService::class)->calcular('2030-03-04', '2030-03-10');

        $this->assertSame(4, $r['resumo']['recebidos']);
        $this->assertSame(3, $r['resumo']['pedidos']);
        $this->assertSame(1, $r['resumo']['cancelados']);
        $this->assertEquals(300.0, $r['resumo']['faturamento']);
        $this->assertEquals(100.0, $r['resumo']['ticket']);
        $this->assertEquals(25.0, $r['resumo']['taxa_cancelamento']);
        $this->assertEquals(200.0, $r['variacao']['pedidos']); // 3 vs 1
        $this->assertSame('2030-02-25', $r['anterior']['inicio']);

        $t = $r['tempos'];
        $this->assertEquals(4.0, $t['aceite']['valores']['media']);   // (2 + 4 + 6) / 3
        $this->assertEquals(4.0, $t['aceite']['valores']['mediana']);
        $this->assertEquals(-6.0, $t['aceite']['variacao']);           // 4 vs 10: mais rápido
        $this->assertEquals(15.0, $t['preparo']['valores']['media']);  // 20 e 10 (agendado fora)
        $this->assertSame(2, $t['preparo']['valores']['pedidos']);
        $this->assertEquals(30.0, $t['entrega']['valores']['media']);  // só a entrega; agendado: 30 min também → média 30
        $this->assertEquals(36.0, $t['total']['valores']['media']);    // 52 e 20

        // Pico: segunda 19h com 2 pedidos
        $this->assertSame('Seg, 19h às 20h', $r['mapa']['pico']['texto']);
        $this->assertSame(2, $r['por_hora'][19]['pedidos']);
        $this->assertSame(7, count($r['por_dia']));

        $this->assertSame('Cliente desistiu', $r['cancelamentos']['motivos'][0]['motivo']);
        $this->assertSame(0, $r['cancelamentos']['por_quem'][0]['pedidos']);

        // Mais vendidos: X-Burger 3 unid. (cancelado fora)
        $this->assertSame('X-Burger', $r['produtos'][0]['nome']);
        $this->assertEquals(3.0, $r['produtos'][0]['quantidade']);
        $this->assertFalse(collect($r['produtos'])->contains('nome', 'Pizza'));

        $tipos = collect($r['por_tipo'])->keyBy('tipo');
        $this->assertSame(2, $tipos['Entrega']['pedidos']);
        $this->assertSame(1, $tipos['Entrega']['cancelados']);

        // Filtro: só retirada
        $ret = app(IfoodIndicadoresService::class)->calcular('2030-03-04', '2030-03-10', 'TAKEOUT');
        $this->assertSame(1, $ret['resumo']['pedidos']);

        // Tela e Excel
        $this->actingAs(\App\Models\User::query()->first());
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = app(IfoodIndicadoresController::class)->index(Request::create('/x', 'GET', ['data_inicio' => '2030-03-04', 'data_fim' => '2030-03-10']))->render();
        $this->assertStringContainsString('Seg, 19h às 20h', $html);
        $this->assertStringContainsString('R$ 300,00', $html);
        $this->assertStringContainsString('▼ 6,0 min', $html);
        $this->assertStringContainsString('Cliente desistiu', $html);
        file_put_contents(sys_get_temp_dir() . '/indicadores.html', $html);
        $mes = app(IfoodIndicadoresController::class)->index(Request::create('/x', 'GET', ['data_inicio' => '2030-03-01', 'data_fim' => '2030-03-31']))->render();
        file_put_contents(sys_get_temp_dir() . '/indicadores-mes.html', $mes);

        $resp = app(IfoodIndicadoresController::class)->exportarExcel(Request::create('/x', 'GET', ['data_inicio' => '2030-03-04', 'data_fim' => '2030-03-10']));
        $this->assertSame(200, $resp->getStatusCode());

        // Vazio
        $vazio = app(IfoodIndicadoresController::class)->index(Request::create('/x', 'GET', ['data_inicio' => '2031-01-01', 'data_fim' => '2031-01-31']))->render();
        $this->assertStringContainsString('Sem pedidos no período', $vazio);
    }

    public function test_evento_grava_horario_do_ifood(): void
    {
        $p = $this->pedido(['status' => 'CONFIRMED', 'total' => 10, 'criado_ifood_em' => '2030-03-04 19:00:00', 'confirmado_em' => '2030-03-04 19:01:00']);
        $svc = app(\App\Services\IfoodService::class);
        $m = new \ReflectionMethod($svc, 'processarEvento');
        $cfg = \App\Models\IfoodConfiguracao::query()->first() ?? new \App\Models\IfoodConfiguracao();

        $m->invoke($svc, $cfg, ['orderId' => $p->ifood_order_id, 'fullCode' => 'DISPATCHED', 'createdAt' => '2030-03-04T22:20:00.000Z']);
        $m->invoke($svc, $cfg, ['orderId' => $p->ifood_order_id, 'fullCode' => 'CONCLUDED', 'createdAt' => '2030-03-04T22:50:00.000Z']);
        $p->refresh();
        $this->assertSame('2030-03-04 19:20:00', $p->despachado_em->format('Y-m-d H:i:s')); // UTC → São Paulo
        $this->assertSame('2030-03-04 19:50:00', $p->concluido_em->format('Y-m-d H:i:s'));
    }
}

