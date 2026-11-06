<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de cada conta a receber: criação, baixas (painel ou caixa), estornos (com o motivo),
 * alterações e cancelamento da venda do convênio. Aparece na aba "Histórico" da conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_receber_historico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conta_receber_id')->constrained('contas_receber')->cascadeOnDelete();
            $t->unsignedBigInteger('parcela_id')->nullable();
            $t->unsignedSmallInteger('parcela_numero')->nullable();
            $t->string('tipo', 20);                 // criacao | baixa | estorno | alteracao | cancelamento
            $t->string('origem', 10)->default('painel'); // painel | caixa | sistema
            $t->decimal('valor', 12, 2)->nullable();
            $t->string('forma', 60)->nullable();
            $t->string('descricao', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ocorrido_em')->nullable();
            $t->timestamps();

            $t->index(['conta_receber_id', 'ocorrido_em']);
        });

        // Contas já existentes: registra a criação e as baixas que já estão nas parcelas
        $agora = now();
        foreach (DB::table('contas_receber')->get() as $c) {
            DB::table('contas_receber_historico')->insert([
                'conta_receber_id' => $c->id, 'tipo' => 'criacao', 'origem' => $c->origem === 'convenio' ? 'caixa' : 'painel',
                'valor' => $c->valor_total, 'descricao' => $c->origem === 'convenio' ? 'Venda no convênio' : 'Conta lançada',
                'ocorrido_em' => $c->created_at, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }

        $parcelas = DB::table('contas_receber_parcelas as p')
            ->leftJoin('formas_pagamento as f', 'f.id', '=', 'p.forma_pagamento_id')
            ->whereNotNull('p.data_recebimento')
            ->get(['p.*', 'f.descricao as forma_descricao']);
        foreach ($parcelas as $p) {
            DB::table('contas_receber_historico')->insert([
                'conta_receber_id' => $p->conta_receber_id, 'parcela_id' => $p->id, 'parcela_numero' => $p->numero,
                'tipo' => 'baixa', 'origem' => $p->recebido_caixa_uuid ? 'caixa' : 'painel',
                'valor' => $p->valor_recebido, 'forma' => $p->forma_descricao,
                'user_id' => $p->recebido_por_id && DB::table('users')->where('id', $p->recebido_por_id)->exists() ? $p->recebido_por_id : null,
                'ocorrido_em' => $p->updated_at, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber_historico');
    }
};
