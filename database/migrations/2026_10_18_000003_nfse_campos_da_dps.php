<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lista nacional de serviços (Anexo B do leiaute NFS-e): valida o código de tributação nacional.
        // dropIfExists deixa a migration repetível caso uma tentativa anterior tenha parado depois de criá-la.
        Schema::dropIfExists('lista_servico_nacional');

        Schema::create('lista_servico_nacional', function (Blueprint $t) {
            $t->char('codigo', 6)->primary();            // item(2) + subitem(2) + desdobro(2)
            $t->unsignedTinyInteger('item');
            $t->unsignedTinyInteger('subitem');
            $t->unsignedTinyInteger('desdobro');
            $t->string('descricao', 600);
        });

        $this->carregarListaNacional();

        Schema::table('empresa', function (Blueprint $t) {
            // IM só vai na DPS se o município tem cadastro complementar no CNC; fora disso é rejeitada (E0120)
            $t->boolean('nfse_informar_im')->default(false)->after('nfse_regime_especial');
            // Só vale para ME/EPP: 1 = tudo pelo Simples; 2 = ISSQN fora do Simples; 3 = federais e ISSQN fora
            $t->unsignedTinyInteger('nfse_reg_ap_trib_sn')->default(1)->after('nfse_informar_im');
            // Percentual aproximado de tributos (Lei 12.741) da alíquota do Simples, para ME/EPP
            $t->decimal('nfse_p_tot_trib_sn', 4, 2)->default(0)->after('nfse_reg_ap_trib_sn');
            // Declarar IBS/CBS na DPS (opcional no leiaute; só com competência de 2026 em diante)
            $t->boolean('nfse_informar_ibscbs')->default(false)->after('nfse_p_tot_trib_sn');
        });

        Schema::table('servicos', function (Blueprint $t) {
            $t->string('cnbs', 9)->nullable()->after('cnae');                 // NBS 2.0 (obrigatório com IBS/CBS)
            $t->string('cindop', 6)->nullable()->after('cnbs');               // indicador da operação (Anexo C)
            $t->string('cst_ibscbs', 3)->nullable()->after('cindop');
            $t->string('cclass_trib_ibscbs', 6)->nullable()->after('cst_ibscbs');
            // Totais aproximados (Lei 12.741) para prestador não optante pelo Simples
            $t->decimal('p_tot_trib_fed', 5, 2)->default(0)->after('cclass_trib_ibscbs');
            $t->decimal('p_tot_trib_est', 5, 2)->default(0)->after('p_tot_trib_fed');
            $t->decimal('p_tot_trib_mun', 5, 2)->default(0)->after('p_tot_trib_est');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->dropColumn(['cnbs', 'cindop', 'cst_ibscbs', 'cclass_trib_ibscbs', 'p_tot_trib_fed', 'p_tot_trib_est', 'p_tot_trib_mun']);
        });

        Schema::table('empresa', function (Blueprint $t) {
            $t->dropColumn(['nfse_informar_im', 'nfse_reg_ap_trib_sn', 'nfse_p_tot_trib_sn', 'nfse_informar_ibscbs']);
        });

        Schema::dropIfExists('lista_servico_nacional');
    }

    // Sem o CSV a tabela fica vazia: numa instalação nova os dados vêm do referencia.sql
    private function carregarListaNacional(): void
    {
        $arquivo = database_path('data/lista_servico_nacional.csv');

        if (! is_file($arquivo)) {
            return;
        }

        $f = fopen($arquivo, 'r');
        fgetcsv($f, 0, ';'); // cabeçalho

        $lote = [];
        while (($l = fgetcsv($f, 0, ';')) !== false) {
            $lote[] = ['codigo' => $l[0], 'item' => (int) $l[1], 'subitem' => (int) $l[2], 'desdobro' => (int) $l[3], 'descricao' => $l[4]];

            if (count($lote) === 100) {
                DB::table('lista_servico_nacional')->insert($lote);
                $lote = [];
            }
        }

        if ($lote) {
            DB::table('lista_servico_nacional')->insert($lote);
        }

        fclose($f);
    }
};