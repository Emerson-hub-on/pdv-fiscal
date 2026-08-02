<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SupervisorController extends Controller
{
    public function autorizar(Request $request)
    {
        $validado = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Por enquanto so existe 'admin' - quando houver papel de supervisor,
        // trocar 'admin' por 'supervisor' aqui
        $user = User::where('username', $validado['username'])
            ->where('tipo', 'admin')
            ->first();

        if ($user && Hash::check($validado['password'], $user->password)) {
            return response()->json(['autorizado' => true]);
        }

        return response()->json(['autorizado' => false], 403);
    }
}