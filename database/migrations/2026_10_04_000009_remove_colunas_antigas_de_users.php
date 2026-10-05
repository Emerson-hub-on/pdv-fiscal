<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toda pessoa precisa ter código antes de a coluna virar obrigatória
        if (DB::table('users')->whereNull('codigo')->exists()) {
            throw new RuntimeException('Existem usuários sem código. Corrija antes de remover as colunas antigas.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'acesso_caixa',
                'acesso_fiscal',
                'acesso_supervisor',
                'permissoes',
                'permissoes_caixa',
                'permissoes_supervisor',
                'codigo_caixa',
                'codigo_servidor',
                'tipo',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('codigo')->nullable(false)->change();
        });
    }

    /**
     * Recria as colunas antigas e refaz os dados a partir de "acessos" (fonte de verdade atual).
     * Best effort: a ordem das colunas na tabela não é restaurada.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('codigo')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tipo')->default('operador')->after('name');
            $table->boolean('acesso_caixa')->default(false);
            $table->boolean('acesso_fiscal')->default(false);
            $table->boolean('acesso_supervisor')->default(false);
            $table->json('permissoes')->nullable();
            $table->json('permissoes_caixa')->nullable();
            $table->json('permissoes_supervisor')->nullable();
            $table->unsignedInteger('codigo_caixa')->nullable()->unique();
            $table->unsignedInteger('codigo_servidor')->nullable()->unique();
        });

        $slugs = DB::table('tipos_operador')->pluck('slug', 'id');

        foreach (DB::table('users')->get() as $u) {
            $dados = ['tipo' => $u->is_admin ? 'admin' : 'operador'];
            $noCaixa = (bool) $u->is_admin;    // admin entra nos dois lados
            $noServidor = (bool) $u->is_admin;

            $acessos = DB::table('acessos')->where('user_id', $u->id)->where('ativo', true)->get();

            foreach ($acessos as $a) {
                $slug = $slugs[$a->tipo_operador_id] ?? null;

                if ($slug === 'caixa') {
                    $dados['acesso_caixa'] = true;
                    $dados['permissoes_caixa'] = $a->permissoes;
                    $noCaixa = true;
                } elseif ($slug === 'supervisor') {
                    $dados['acesso_supervisor'] = true;
                    $dados['permissoes_supervisor'] = $a->permissoes;
                    $noCaixa = true;
                } elseif ($slug === 'fiscal') {
                    $dados['acesso_fiscal'] = true;
                    $dados['permissoes'] = $a->permissoes;
                    $noServidor = true;
                }
            }

            $dados['codigo_caixa'] = $noCaixa ? $u->codigo : null;
            $dados['codigo_servidor'] = $noServidor ? $u->codigo : null;

            DB::table('users')->where('id', $u->id)->update($dados);
        }
    }
};