<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** sigla => [descrição, permite fracionado] */
    private const PADRAO = [
        'UN'  => ['Unidade', false],
        'PC'  => ['Peça', false],
        'CX'  => ['Caixa', false],
        'PCT' => ['Pacote', false],
        'FD'  => ['Fardo', false],
        'DZ'  => ['Dúzia', false],
        'PAR' => ['Par', false],
        'KIT' => ['Kit', false],
        'CJ'  => ['Conjunto', false],
        'SC'  => ['Saco', false],
        'RL'  => ['Rolo', false],
        'BD'  => ['Balde', false],
        'FR'  => ['Frasco', false],
        'GL'  => ['Galão', false],
        'KG'  => ['Quilograma', true],
        'G'   => ['Grama', true],
        'TON' => ['Tonelada', true],
        'LT'  => ['Litro', true],
        'ML'  => ['Mililitro', true],
        'MT'  => ['Metro', true],
        'CM'  => ['Centímetro', true],
        'M2'  => ['Metro quadrado', true],
        'M3'  => ['Metro cúbico', true],
    ];

    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('sigla', 6)->unique();   // é o que vai no XML (uCom / uTrib)
            $table->string('descricao', 60);
            $table->boolean('permite_fracionado')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        $agora = now();

        // 1) unidades padrão
        foreach (self::PADRAO as $sigla => [$descricao, $fracionado]) {
            DB::table('unidades')->insert([
                'sigla' => $sigla,
                'descricao' => $descricao,
                'permite_fracionado' => $fracionado,
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        // 2) padroniza as siglas que já existem nos produtos (texto livre: "un", " Kg "...)
        DB::table('produtos')->update([
            'unidade_comercial'  => DB::raw('UPPER(TRIM(unidade_comercial))'),
            'unidade_tributavel' => DB::raw('UPPER(TRIM(unidade_tributavel))'),
        ]);

        // 3) qualquer sigla já usada que não está na lista padrão entra como unidade (não fracionada)
        $usadas = DB::table('produtos')->pluck('unidade_comercial')
            ->merge(DB::table('produtos')->pluck('unidade_tributavel'))
            ->filter()->unique()->diff(array_keys(self::PADRAO));

        foreach ($usadas as $sigla) {
            DB::table('unidades')->insert([
                'sigla' => $sigla,
                'descricao' => $sigla,
                'permite_fracionado' => false,
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        // 4) estoque passa a aceitar 3 casas (só será usado com unidade fracionada)
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('estoque', 12, 3)->default(0)->change();
            $table->decimal('estoque_minimo', 12, 3)->nullable()->default(0)->change();
        });

        Schema::table('produto_variantes', function (Blueprint $table) {
            $table->decimal('estoque', 12, 3)->default(0)->change();
            $table->decimal('estoque_minimo', 12, 3)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('produto_variantes', function (Blueprint $table) {
            $table->integer('estoque')->default(0)->change();
            $table->integer('estoque_minimo')->nullable()->default(0)->change();
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->integer('estoque')->default(0)->change();
            $table->integer('estoque_minimo')->nullable()->default(0)->change();
        });

        Schema::dropIfExists('unidades');
    }
};
