<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Licenças registradas no servidor central; os caixas copiam a mais nova para storage/app/licenca.json
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencas', function (Blueprint $table) {
            $table->id();
            $table->text('codigo');            // código assinado, como foi colado
            $table->char('cnpj', 14);
            $table->date('valida_ate');
            $table->date('emitida_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencas');
    }
};
