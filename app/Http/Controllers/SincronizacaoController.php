<?php

namespace App\Http\Controllers;

use App\Services\SyncService;

class SincronizacaoController extends Controller
{
    public function executar()
    {
        try {
            $resultado = (new SyncService())->sincronizarTudo();

            return response()->json([
                'sucesso' => true,
                'produtos_atualizados' => $resultado['catalogo']['produtos_atualizados'] ?? 0,
                'catalogo_ok' => $resultado['catalogo']['sucesso'] ?? false,
                'usuarios_ok' => $resultado['usuarios']['sucesso'] ?? false,
                'usuarios_erro' => $resultado['usuarios']['erro'] ?? null,
                'usuarios_atualizados' => $resultado['usuarios']['usuarios_atualizados'] ?? 0,
                'vendas_enviadas' => $resultado['vendas']['enviadas'] ?? 0,
                'vendas_falhas' => $resultado['vendas']['falhas'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()], 500);
        }
    }
}