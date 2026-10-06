<?php

namespace App\Services;

use App\Models\Pdv;
use App\Support\DadosFiscaisLocais;

class NfeServiceLocal extends NfeService
{
    public function __construct(Pdv $pdv)
    {
        // Não chama o construtor do pai: ele lê a empresa do MySQL central
        $this->empresa = DadosFiscaisLocais::empresa();
        $this->pdv = $pdv;
        $this->tools = $this->criarTools();
    }
}