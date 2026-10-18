<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        // Técnico: aparece na Ordem de Serviço. Fica no contexto "servidor" (não vai para o caixa)
        // e não faz login: é só um cadastro de pessoa, vinculado ao tipo pelo slug.
        DB::table('tipos_operador')->updateOrInsert(
            ['slug' => 'tecnico'],
            [
                'nome'          => 'Técnico',
                'nome_plural'   => 'Técnicos',
                'contexto'      => 'servidor',
                'permite_login' => 0,
                'ativo'         => 1,
                'created_at'    => $agora,
                'updated_at'    => $agora,
            ]
        );
    }

    public function down(): void
    {
        DB::table('tipos_operador')->where('slug', 'tecnico')->delete();
    }
};
