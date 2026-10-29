<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/** Só lê a planilha: a interpretação das linhas fica no ContagemEstoqueService. */
class ContagemEstoqueImport implements ToArray
{
    public function array(array $array): void
    {
    }
}
