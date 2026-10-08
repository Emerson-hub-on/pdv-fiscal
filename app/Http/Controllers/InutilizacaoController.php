<?php

namespace App\Http\Controllers;

use App\Models\Pdv;
use App\Services\EmissorLocalService;
use App\Services\FiscalEmissorService;
use App\Support\AutorizacaoSupervisor;
use App\Support\CentralStatus;
use App\Support\EmissaoLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InutilizacaoController extends Controller
{
    public function executar(Request $request)
    {
        $usuario = Auth::user();
        $liberado = $usuario->caixaLiberado('inutilizar');

        // Operador liberado dispensa o supervisor; os demais precisam da autorização de sessão
        if (!$liberado && !AutorizacaoSupervisor::valida('inutilizar')) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Autorização do supervisor ausente, expirada ou já utilizada. Feche esta janela e solicite novamente.',
            ], 403);
        }

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
            $supervisorId = $liberado ? null : AutorizacaoSupervisor::supervisorId('inutilizar');

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

            // Uso único: só gasta a autorização quando a inutilização deu certo
            AutorizacaoSupervisor::consumir('inutilizar');

            Log::info('Numeração inutilizada', [
                'pdv_id'         => $pdvId,
                'numero_inicial' => $validado['numero_inicial'],
                'numero_final'   => $validado['numero_final'],
                'operador_id'    => $usuario->id,
                'supervisor_id'  => $supervisorId,
                'liberado'       => $liberado,
            ]);

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