<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('empresa', function (Blueprint $table) {
        $table->id();
        $table->string('cnpj', 14)->unique();
        $table->string('razao_social');
        $table->string('nome_fantasia')->nullable();
        $table->string('ie')->nullable(); // inscricao estadual
        $table->string('im')->nullable(); // inscricao municipal
        $table->unsignedTinyInteger('crt')->default(1); // 1=Simples Nacional

        // Endereco
        $table->string('logradouro');
        $table->string('numero');
        $table->string('complemento')->nullable();
        $table->string('bairro');
        $table->string('cep', 8);
        $table->string('municipio');
        $table->string('cod_municipio', 7); // codigo IBGE
        $table->string('uf', 2);

        // Certificado digital A1
        $table->longText('certificado_base64')->nullable();
        $table->string('certificado_senha')->nullable();
        $table->date('certificado_validade')->nullable();

        // Configuracao fiscal NFC-e / NF-e
        $table->string('csc')->nullable(); // Codigo de Seguranca do Contribuinte
        $table->string('csc_id')->nullable();
        $table->unsignedInteger('serie_nfce')->default(1);
        $table->unsignedBigInteger('numero_atual_nfce')->default(0);
        $table->unsignedInteger('serie_nfe')->default(1);
        $table->unsignedBigInteger('numero_atual_nfe')->default(0);
        $table->unsignedTinyInteger('ambiente')->default(2); // 1=producao, 2=homologacao

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('empresa');
}
};
