<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro automático dos produtos do iFood: o iFood não manda dados fiscais, então cada categoria do
 * cardápio do iFood usa um produto do sistema como "modelo fiscal" (NCM, CEST, tributação, PIS/COFINS,
 * IPI, IBS/CBS, origem, unidade). Sem modelo na categoria, vale o modelo padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ifood_categorias_fiscais')) {
            Schema::create('ifood_categorias_fiscais', function (Blueprint $table) {
                $table->id();
                $table->string('categoria', 120)->unique();
                $table->foreignId('produto_modelo_id')->nullable()->constrained('produtos')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('ifood_configuracoes', function (Blueprint $table) {
            if (!Schema::hasColumn('ifood_configuracoes', 'produto_modelo_id')) {
                $table->foreignId('produto_modelo_id')->nullable()->constrained('produtos')->nullOnDelete();
            }
        });

        Schema::table('ifood_produtos', function (Blueprint $table) {
            if (!Schema::hasColumn('ifood_produtos', 'cadastrado_automaticamente')) {
                $table->boolean('cadastrado_automaticamente')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ifood_produtos', fn (Blueprint $t) => $t->dropColumn('cadastrado_automaticamente'));
        Schema::table('ifood_configuracoes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('produto_modelo_id');
        });
        Schema::dropIfExists('ifood_categorias_fiscais');
    }
};
