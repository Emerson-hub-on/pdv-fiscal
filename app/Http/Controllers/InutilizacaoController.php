<?php

namespace App\Http\Controllers;

use App\Models\Pdv;
use App\Services\FiscalEmissorService;
use Illuminate\Http\Request;

class InutilizacaoController extends Controller
{
    public function executar(Request $request)
    {
        $validado = $request->validate([
            'numero_inicial' => 'required|integer|min:1',
            'numero_final' => 'required|integer|min:1|gte:numero_inicial',
            'justificativa' => 'required|string|min:15',
        ]);

        $pdv = Pdv::findOrFail(config('app.pdv_id'));

        try {
            $resultado = (new FiscalEmissorService())->inutilizar(
                $pdv,
                $validado['numero_inicial'],
                $validado['numero_final'],
                $validado['justificativa']
            );

            return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }
}