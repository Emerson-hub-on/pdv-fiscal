<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function tela()
    {
        return view('auth.escolha');
    }

    public function formulario(Request $request)
    {
        $modo = $request->query('modo', 'admin'); // admin | operador
        return view('auth.login', compact('modo'));
    }

    public function login(Request $request)
    {
        $credenciais = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'modo' => 'required|in:admin,operador',
        ]);

        if (Auth::attempt(['username' => $credenciais['username'], 'password' => $credenciais['password']])) {
            $request->session()->regenerate();
            $request->session()->put('modo', $credenciais['modo']);

            return $credenciais['modo'] === 'admin'
                ? redirect()->route('produtos.index')
                : redirect()->route('caixa.abrir-form'); // ainda vamos criar essa rota
        }

        return back()->withErrors(['username' => 'Usuário ou senha inválidos.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.escolha');
    }
}