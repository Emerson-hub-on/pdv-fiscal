<?php

use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Support\ClienteEspelho;
use Illuminate\Database\Migrations\Migration;

// Fornecedores e empresa já cadastrados ganham o cadastro de cliente (os novos passam a ganhar ao salvar).
// Pode rodar de novo: o vínculo é pelo CPF/CNPJ e não duplica.
return new class extends Migration
{
    public function up(): void
    {
        Fornecedor::query()->orderBy('id')->each(fn (Fornecedor $f) => ClienteEspelho::deFornecedor($f));

        if ($empresa = Empresa::first()) {
            ClienteEspelho::daEmpresa($empresa);
        }
    }

    public function down(): void
    {
        // Não apaga clientes: podem já ter sido usados em notas
    }
};
