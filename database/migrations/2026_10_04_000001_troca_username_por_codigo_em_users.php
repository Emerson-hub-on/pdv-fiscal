<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('codigo_caixa')->nullable()->unique()->after('name');
            $table->unsignedInteger('codigo_servidor')->nullable()->unique()->after('codigo_caixa');
        });

        // Preenche quem já existe, em ordem de id, dentro de cada lado
        $proximoCaixa = 1;
        $proximoServidor = 1;

        foreach (DB::table('users')->orderBy('id')->get() as $u) {
            $codigos = [];

            if ($u->tipo === 'admin' || $u->acesso_fiscal) {
                $codigos['codigo_servidor'] = $proximoServidor++;
            }

            if ($u->tipo === 'admin' || $u->acesso_caixa || $u->acesso_supervisor) {
                $codigos['codigo_caixa'] = $proximoCaixa++;
            }

            if ($codigos) {
                DB::table('users')->where('id', $u->id)->update($codigos);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username'); // o MySQL remove junto o índice unique da coluna
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        foreach (DB::table('users')->get() as $u) {
            DB::table('users')->where('id', $u->id)->update([
                'username' => Str::slug($u->name, '.') . '.' . $u->id,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['codigo_caixa', 'codigo_servidor']);
        });
    }
};