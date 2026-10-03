<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SupervisorController extends Controller
{
    public function autorizar(Request $request)
    {
        $validado = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'acao'     => 'nullable|string|max:60', // opcional: ex. "cancelar_item", "desconto_global"
        ]);

        // Pode autorizar: administrador ou usuário cadastrado como Supervisor
        $user = User::where('username', User::normalizarUsername($validado['username']))
            ->where(function ($q) {
                $q->where('tipo', 'admin')->orWhere('acesso_supervisor', true);
            })
            ->first();

        if ($user && Hash::check($validado['password'], $user->password)) {
            Log::info('Autorização de supervisor', [
                'supervisor_id' => $user->id,
                'operador_id'   => Auth::id(),
                'acao'          => $validado['acao'] ?? null,
            ]);

            return response()->json(['autorizado' => true, 'supervisor' => $user->name]);
        }

        return response()->json(['autorizado' => false], 403);
    }
}