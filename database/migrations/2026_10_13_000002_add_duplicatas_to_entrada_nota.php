<?php

use App\Models\EntradaNota;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // usa o nome real da tabela definido no model EntradaNota
    private function tabela(): string
    {
        return (new EntradaNota)->getTable();
    }

    public function up(): void
    {
        Schema::table($this->tabela(), function (Blueprint $t) {
            // duplicatas lidas do XML (numero, vencimento, valor), guardadas no rascunho
            $t->json('duplicatas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tabela(), function (Blueprint $t) {
            $t->dropColumn('duplicatas');
        });
    }
};
