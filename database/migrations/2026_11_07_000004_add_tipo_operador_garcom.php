<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        // Garçom: entra na tela do garçom (celular/tablet ligado ao servidor), pelo login "Garçom".
        // Contexto próprio "garcom": NÃO é "servidor" (senão entraria no painel administrativo, ver
        // User::podeAcessarFiscal) nem "caixa" (não vai para o banco local do caixa).
        DB::table('tipos_operador')->updateOrInsert(
            ['slug' => 'garcom'],
            [
                'nome'          => 'Garçom',
                'nome_plural'   => 'Garçons',
                'contexto'      => 'garcom',
                'permite_login' => 1,
                'ativo'         => 1,
                'created_at'    => $agora,
                'updated_at'    => $agora,
            ]
        );
    }

    public function down(): void
    {
        $id = DB::table('tipos_operador')->where('slug', 'garcom')->value('id');

        if ($id) {
            DB::table('acessos')->where('tipo_operador_id', $id)->delete();
            DB::table('tipos_operador')->where('id', $id)->delete();
        }
    }
};
