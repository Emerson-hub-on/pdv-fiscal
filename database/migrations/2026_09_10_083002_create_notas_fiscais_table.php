<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('operador_id')->constrained('users');
            $table->foreignId('serie_nfe_id')->nullable()->constrained('series_nfe');

            $table->unsignedTinyInteger('modelo')->default(55);
            $table->unsignedInteger('serie')->nullable();
            $table->unsignedBigInteger('numero')->nullable();

            $table->string('natureza_operacao');
            $table->unsignedTinyInteger('finalidade')->default(1); // 1 normal, 2 complementar, 3 ajuste, 4 devolução
            $table->enum('tipo_operacao', ['entrada', 'saida'])->default('saida');

            $table->enum('origem_tipo', ['manual', 'venda', 'pedido'])->default('manual');
            $table->foreignId('venda_id')->nullable()->constrained('vendas');
            // pedido_id entra depois, quando o módulo de pedidos existir

            $table->enum('status', ['rascunho', 'emitida', 'cancelada', 'contingencia', 'inutilizada'])->default('rascunho');

            $table->string('chave_acesso', 44)->nullable();
            $table->string('protocolo')->nullable();
            $table->longText('xml')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->timestamp('emitida_em')->nullable();

            $table->decimal('valor_produtos', 12, 2)->default(0);
            $table->decimal('valor_desconto', 12, 2)->default(0);
            $table->decimal('valor_frete', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_fiscais');
    }
};