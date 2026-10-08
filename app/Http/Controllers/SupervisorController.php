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
    // Mostra o nome do supervisor no modal assim que o código é digitado
    public function nomePorCodigo(Request $request)
    {
        $dados = $request->validate([
            'codigo' => 'required|integer|min:1',
        ]);

        // O caixa só consulta o SQLite local
        $user = UsuarioCache::porCodigo((int) $dados['codigo']);

        if (!$user || !$user->podeAutorizar()) {
            return response()->json(['nome' => null, 'aviso' => 'Supervisor não encontrado']);
        }

        return response()->json(['nome' => $user->name]);
    }

    public function autorizar(Request $request)
    {
        $validado = $request->validate([
            'codigo'   => 'required|integer|min:1',
            'password' => 'required|string',
            'acao' => 'required|string|in:' . implode(',', array_keys(config('permissoes.caixa.acoes'))),
        ]);

        // O caixa só consulta o SQLite local
        $user = UsuarioCache::porCodigo((int) $validado['codigo']);

        // Pode autorizar: administrador ou usuário cadastrado como Supervisor
        if (!$user || !$user->podeAutorizar() || !Hash::check($validado['password'], $user->password)) {
            return response()->json(['autorizado' => false], 403);
        }

        $acao = $validado['acao'];

        // Credenciais corretas, mas este supervisor não pode liberar esta ação
        if (!$user->supervisorLibera($acao)) {
            Log::info('Supervisor sem permissão para a ação', [
                'supervisor_id' => $user->id,
                'operador_id'   => Auth::id(),
                'acao'          => $acao,
            ]);

            return response()->json([
                'autorizado' => false,
                'motivo'     => 'Este supervisor não tem permissão para liberar esta operação.',
            ], 403);
        }

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