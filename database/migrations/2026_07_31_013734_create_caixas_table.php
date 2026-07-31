<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('caixas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('operador_id')->constrained('users');
        $table->timestamp('data_abertura');
        $table->decimal('valor_abertura', 12, 2);
        $table->timestamp('data_fechamento')->nullable();
        $table->decimal('valor_fechamento_informado', 12, 2)->nullable(); // valor que o operador contou
        $table->decimal('valor_fechamento_esperado', 12, 2)->nullable(); // calculado pelo sistema
        $table->enum('status', ['aberto', 'fechado'])->default('aberto');
        $table->text('observacao')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('caixas');
}
};
