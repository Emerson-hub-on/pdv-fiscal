<?php

namespace App\Http\Controllers;

use App\Services\CupomFiscalService;
use RuntimeException;

class CupomFiscalController extends Controller
{
    /** DANFE NFC-e em bobina 80 mm. Com ?auto=1 a própria página abre a impressão. */
    public function imprimir(string $uuid, CupomFiscalService $servico)
    {
        try {
            $cupom = $servico->dados($uuid);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return view('vendas.cupom-fiscal', ['c' => $cupom]);
    }
}