<?php

namespace App\Http\Controllers;

use App\Models\Pdv;
use App\Services\EmissorLocalService;
use App\Services\FiscalEmissorService;
use App\Support\CentralStatus;
use App\Support\EmissaoLocal;
use Illuminate\Http\Request;

class InutilizacaoController extends Controller
{
    public function executar(Request $request)
    {
        $validado = $request->validate([
            'pdv_id' => 'nullable|integer',
            'numero_inicial' => 'required|integer|min:1',
            'numero_final' => 'required|integer|min:1|gte:numero_inicial',
            'justificativa' => 'required|string|min:15',
        ]);

        $pdvId = $validado['pdv_id'] ?? EmissaoLocal::pdvId();

        if (!$pdvId) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Nenhum PDV definido: abra um caixa ou informe o PDV para inutilizar a numeração.',
            ], 422);
        }

        try {
            if (EmissaoLocal::ativa((int) $pdvId)) {
                // PDV que emite pelo caixa: tudo acontece no SQLite
                $resultado = (new EmissorLocalService())->inutilizarLocal(
                    (int) $pdvId,
                    $validado['numero_inicial'],
                    $validado['numero_final'],
                    $validado['justificativa']
                );
            } else {
                $pdv = Pdv::findOrFail($pdvId);

                $resultado = (new FiscalEmissorService())->inutilizar(
                    $pdv,
                    $validado['numero_inicial'],
                    $validado['numero_final'],
                    $validado['justificativa']
                );
            }

            return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
        } catch (\Throwable $e) {
            if (CentralStatus::erroDeConexao($e)) {
                CentralStatus::marcarFora();
                $mensagem = 'Servidor central indisponível. A inutilização ainda depende do servidor.';
            } else {
                $mensagem = $e->getMessage();
            }

            return response()->json(['sucesso' => false, 'erro' => $mensagem]);
        }
    }
}