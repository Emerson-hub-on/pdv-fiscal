<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_operador', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();            // chave usada no código e no config/permissoes.php
            $table->string('nome');
            $table->string('contexto');                  // caixa | servidor
            $table->boolean('permite_login')->default(true); // false = só autoriza, não abre sessão
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('acessos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tipo_operador_id')->constrained('tipos_operador')->restrictOnDelete();
            $table->json('permissoes')->nullable();      // só o que foge do padrão do tipo
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tipo_operador_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('codigo')->nullable()->unique()->after('name'); // um código por pessoa
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('ativo')->default(true)->after('is_admin');             // desliga a pessoa inteira
        });

        // Tipos atuais (slug = mesma chave de perfil que o UsuarioController já usa)
        $agora = now();

        DB::table('tipos_operador')->insert([
            ['slug' => 'caixa',      'nome' => 'Operador de Caixa', 'contexto' => 'caixa',    'permite_login' => true, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            // permite_login = true reproduz o podeAcessarCaixa() atual (supervisor entra no caixa)
            ['slug' => 'supervisor', 'nome' => 'Supervisor',        'contexto' => 'caixa',    'permite_login' => true, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['slug' => 'fiscal',     'nome' => 'Operador Fiscal',   'contexto' => 'servidor', 'permite_login' => true, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'is_admin', 'ativo']);
        });

        Schema::dropIfExists('acessos');
        Schema::dropIfExists('tipos_operador');
    }
};