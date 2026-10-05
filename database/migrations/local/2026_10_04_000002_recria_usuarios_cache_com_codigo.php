<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('usuarios_cache');

        Schema::connection('sqlite_local')->create('usuarios_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // mesmo id do central
            $table->unsignedInteger('codigo')->unique(); // codigo_caixa do central
            $table->string('name');
            $table->string('tipo')->default('operador');
            $table->string('password'); // hash bcrypt, nunca a senha em texto
            $table->boolean('acesso_caixa')->default(false);
            $table->boolean('acesso_fiscal')->default(false);
            $table->boolean('acesso_supervisor')->default(false);
            $table->text('permissoes')->nullable();
            $table->text('permissoes_caixa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('usuarios_cache');
    }
};