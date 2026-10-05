<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tipos = DB::table('tipos_operador')->pluck('id', 'slug');
        $agora = now();

        foreach (DB::table('users')->orderBy('id')->get() as $u) {
            // Um código por pessoa. Começa igual ao id (os próximos seguem max + 1)
            DB::table('users')->where('id', $u->id)->update([
                'codigo'   => $u->id,
                'is_admin' => $u->tipo === 'admin',
            ]);

            // slug => [tinha o acesso?, permissões antigas daquele tipo]
            $antigos = [
                'caixa'      => [$u->acesso_caixa,      $u->permissoes_caixa],
                'supervisor' => [$u->acesso_supervisor, $u->permissoes_supervisor],
                'fiscal'     => [$u->acesso_fiscal,     $u->permissoes],
            ];

            foreach ($antigos as $slug => [$tinhaAcesso, $permissoes]) {
                if (!$tinhaAcesso) {
                    continue;
                }

                DB::table('acessos')->insert([
                    'user_id'          => $u->id,
                    'tipo_operador_id' => $tipos[$slug],
                    'permissoes'       => $permissoes, // JSON copiado como está (ou null)
                    'ativo'            => true,
                    'created_at'       => $agora,
                    'updated_at'       => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('acessos')->delete();
        DB::table('users')->update(['codigo' => null, 'is_admin' => false]);
    }
};