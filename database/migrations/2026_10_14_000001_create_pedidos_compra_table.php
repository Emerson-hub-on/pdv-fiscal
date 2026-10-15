<?php

use App\Models\Fornecedor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->date('data_pedido');
            $table->date('previsao_entrega')->nullable();
            // usa o nome real da tabela do model Fornecedor, seja qual for
            $table->foreignId('fornecedor_id')->nullable()->constrained((new Fornecedor)->getTable());
            $table->string('status', 15)->default('aberto')->index(); // aberto | enviado | recebido | cancelado
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->foreignId('produto_variante_id')->nullable()->constrained('produto_variantes')->nullOnDelete();
            $table->string('codigo_barras', 20)->nullable();
            $table->string('descricao', 255);
            $table->string('unidade', 6)->default('UN');
            $table->decimal('quantidade', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_compra_itens');
        Schema::dropIfExists('pedidos_compra');
    }
};