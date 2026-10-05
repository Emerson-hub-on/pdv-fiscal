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
            $table->unsignedInteger('codigo')->unique(); // um código por pessoa
            $table->string('name');
            $table->string('password'); // hash bcrypt, nunca a senha em texto
            $table->boolean('is_admin')->default(false);
            $table->text('mapa_acessos')->nullable(); // JSON: slug => {contexto, permite_login, permissoes}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_local')->dropIfExists('usuarios_cache');
    }
};