<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conta da mesa (comanda) e situação dos pedidos (Comandas → Pedidos → Lista de Pedidos).
 *
 * comandas: a conta abre no 1º pedido da mesa e fecha quando o cliente paga (pelo garçom na mesa ou
 * pelo operador no balcão). Fechar registra os pagamentos (mais de uma forma), conclui os pedidos e
 * libera a mesa.
 *
 * comanda_pedidos.status: preparo → pronto → entregue → concluido (conta paga) | cancelado.
 * origem: "mesa" hoje; a coluna existe para os pedidos do iFood entrarem na mesma lista depois.
 * cliente_nome: opcional, informado pelo garçom ao finalizar o pedido.
 *
 * As criações conferem se a tabela/coluna já existe: a 1ª tentativa desta migration parou no meio
 * (MySQL não desfaz alteração de tabela) e deixou comandas e comanda_pagamentos criadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('comandas')) {
            Schema::create('comandas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('mesa_id')->constrained('mesas');
                $t->string('status', 20)->default('aberta'); // aberta | paga | cancelada
                $t->decimal('total', 12, 2)->default(0);
                $t->decimal('troco', 12, 2)->default(0);
                $t->timestamp('aberta_em');
                $t->timestamp('fechada_em')->nullable();
                $t->foreignId('fechada_por')->nullable()->constrained('users')->nullOnDelete();
                $t->string('fechada_onde', 10)->nullable(); // garcom | painel
                $t->timestamps();

                $t->index(['mesa_id', 'status']);
            });
        }

        if (!Schema::hasTable('comanda_pagamentos')) {
            Schema::create('comanda_pagamentos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
                $t->string('forma_pagamento', 20);  // codigo de formas_pagamento_caixa
                $t->string('descricao', 60);        // nome da forma no momento do pagamento
                $t->string('meio_pagamento', 2);    // tPag (para a futura NFC-e)
                $t->decimal('valor', 12, 2);
                $t->timestamps();
            });
        }

        if (!Schema::hasColumn('comanda_pedidos', 'status')) {
            Schema::table('comanda_pedidos', function (Blueprint $t) {
                $t->foreignId('comanda_id')->nullable()->after('uuid')->constrained('comandas')->nullOnDelete();
                $t->string('origem', 20)->default('mesa')->after('comanda_id');
                $t->string('status', 20)->default('preparo')->after('origem');
                $t->string('cliente_nome', 60)->nullable()->after('garcom_id');
                $t->timestamp('pronto_em')->nullable()->after('enviado_em');
                $t->timestamp('concluido_em')->nullable()->after('entregue_em');
                $t->timestamp('cancelado_em')->nullable()->after('concluido_em');
                $t->foreignId('status_por')->nullable()->after('cancelado_em')->constrained('users')->nullOnDelete();

                $t->index(['status', 'enviado_em']);
                // a chave estrangeira de mesa_id usava o índice (mesa_id, fechado_em), que sai abaixo
                $t->index('mesa_id');
            });
        }

        // Pedidos já gravados: os da mesa ainda ocupada entram numa conta aberta; os fechados, como concluídos
        if (Schema::hasColumn('comanda_pedidos', 'fechado_em')) {
            $agora = now();

            foreach (DB::table('comanda_pedidos')->whereNull('comanda_id')->orderBy('id')->get() as $p) {
                if ($p->fechado_em) {
                    DB::table('comanda_pedidos')->where('id', $p->id)->update([
                        'status' => 'concluido', 'concluido_em' => $p->fechado_em,
                    ]);
                    continue;
                }

                $contaId = DB::table('comandas')->where('mesa_id', $p->mesa_id)->where('status', 'aberta')->value('id')
                    ?? DB::table('comandas')->insertGetId([
                        'mesa_id' => $p->mesa_id, 'status' => 'aberta', 'aberta_em' => $p->enviado_em,
                        'created_at' => $agora, 'updated_at' => $agora,
                    ]);

                DB::table('comanda_pedidos')->where('id', $p->id)->update([
                    'comanda_id' => $contaId,
                    'status'     => $p->entregue_em ? 'entregue' : 'preparo',
                ]);
            }

            // total das contas abertas = soma dos pedidos não cancelados
            foreach (DB::table('comandas')->where('status', 'aberta')->pluck('id') as $contaId) {
                DB::table('comandas')->where('id', $contaId)->update([
                    'total' => DB::table('comanda_pedidos')->where('comanda_id', $contaId)->where('status', '!=', 'cancelado')->sum('total'),
                ]);
            }

            Schema::table('comanda_pedidos', function (Blueprint $t) {
                $t->dropIndex(['mesa_id', 'fechado_em']);
                $t->dropColumn('fechado_em');
            });
        }
    }

    public function down(): void
    {
        Schema::table('comanda_pedidos', function (Blueprint $t) {
            $t->timestamp('fechado_em')->nullable();
            $t->index(['mesa_id', 'fechado_em']);
        });

        DB::table('comanda_pedidos')->whereIn('status', ['concluido', 'cancelado'])
            ->update(['fechado_em' => DB::raw('COALESCE(concluido_em, cancelado_em)')]);

        Schema::table('comanda_pedidos', function (Blueprint $t) {
            $t->dropIndex(['status', 'enviado_em']);
            $t->dropIndex(['mesa_id']);
            $t->dropConstrainedForeignId('comanda_id');
            $t->dropConstrainedForeignId('status_por');
            $t->dropColumn(['origem', 'status', 'cliente_nome', 'pronto_em', 'concluido_em', 'cancelado_em']);
        });

        Schema::dropIfExists('comanda_pagamentos');
        Schema::dropIfExists('comandas');
    }
};
