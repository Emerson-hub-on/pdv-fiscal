<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UsuarioCache;
use App\Support\AutorizacaoSupervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SupervisorController extends Controller
{
    public function autorizar(Request $request)
    {
        $validado = $request->validate([
            'codigo'   => 'required|integer|min:1',
            'password' => 'required|string',
            'acao'     => 'nullable|string|in:desconto_item,desconto_global,cancelar_item,cancelar_cupom,cancelar_nfce',
        ]);

        // O caixa só consulta o SQLite local
        $user = UsuarioCache::porCodigo((int) $validado['codigo']);

        // Pode autorizar: administrador ou usuário cadastrado como Supervisor
        if (!$user || !$user->podeAutorizar() || !Hash::check($validado['password'], $user->password)) {
            return response()->json(['autorizado' => false], 403);
        }

        $acao = $validado['acao'] ?? null;

        // Registra no servidor o que foi autorizado, para o finalizar/cancelar conferirem
        if ($tipo = AutorizacaoSupervisor::tipoDaAcao($acao)) {
            AutorizacaoSupervisor::conceder($tipo, $user->id);
        }

        Log::info('Autorização de supervisor', [
            'supervisor_id' => $user->id,
            'operador_id'   => Auth::id(),
            'acao'          => $acao,
        ]);

        return response()->json(['autorizado' => true, 'supervisor' => $user->name]);
    }
}