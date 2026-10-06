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

        // No balcão, não vale esperar muito pela SEFAZ antes de seguir em contingência
        if (method_exists($this->tools, 'setSoapTimeout')) {
            $this->tools->setSoapTimeout(10);
        }
    }
}