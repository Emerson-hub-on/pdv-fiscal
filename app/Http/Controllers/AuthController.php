<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;


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
            'modo'     => 'required|in:admin,operador',
        ]);

        $usuario = User::where('username', User::normalizarUsername($credenciais['username']))->first();

        if (!$usuario || !Hash::check($credenciais['password'], $usuario->password)) {
            return back()->withErrors(['username' => 'Usuário ou senha inválidos.'])->withInput($request->only('modo'));
        }

        // "admin" = sistema de cadastros/faturamento; "operador" = caixa
        $modo = $credenciais['modo'];
        $permitido = $modo === 'admin' ? $usuario->podeAcessarFiscal() : $usuario->podeAcessarCaixa();

        if (!$permitido) {
            return back()->withErrors([
                'username' => $modo === 'admin'
                    ? 'Este usuário não tem acesso ao sistema de cadastros.'
                    : 'Este usuário não tem acesso ao caixa.',
            ]);
        }

        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('modo', $modo);

        return $modo === 'admin'
            ? redirect()->route('produtos.index')
            : redirect()->route('caixa.abrir-form');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.escolha');
    }
}