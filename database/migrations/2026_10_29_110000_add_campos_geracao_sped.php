<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sped_configuracoes', function (Blueprint $table) {
            $table->char('ind_perfil', 1)->default('A')->after('ind_ativ');          // perfil definido pela SEFAZ da UF
            $table->string('clas_estab_ind', 2)->nullable()->after('ind_perfil');   // registro 0002 (só indústria)
            $table->string('cod_receita_icms', 20)->nullable()->after('clas_estab_ind'); // E116 (código da UF)
        });

        // Histórico das gerações: o saldo credor de um mês vira o "saldo anterior" do mês seguinte
        Schema::create('sped_geracoes', function (Blueprint $table) {
            $table->id();
            $table->date('competencia');          // sempre o dia 1 do mês
            $table->char('finalidade', 1);        // 0 original | 1 substituto
            $table->boolean('destacar_icms');
            $table->decimal('saldo_credor_anterior', 15, 2)->default(0);
            $table->decimal('icms_recolher', 15, 2)->default(0);
            $table->decimal('saldo_credor_transportar', 15, 2)->default(0);
            $table->string('arquivo');            // caminho em storage/app
            $table->unsignedInteger('total_linhas')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('competencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sped_geracoes');

        Schema::table('sped_configuracoes', function (Blueprint $table) {
            $table->dropColumn(['ind_perfil', 'clas_estab_ind', 'cod_receita_icms']);
        });
    }
};
