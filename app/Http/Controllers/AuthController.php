<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SyncService;
use App\Services\UsuarioCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        // "admin" = sistema de cadastros/faturamento (servidor central, guard "web")
        // "operador" = caixa (cache local do SQLite, guard "caixa")
        $modo = $credenciais['modo'];
        $guard = $modo === 'admin' ? 'web' : 'caixa';
        $username = User::normalizarUsername($credenciais['username']);

        $usuario = $modo === 'admin'
            ? User::where('username', $username)->first()
            : $this->usuarioDoCaixa($username);

        if (!$usuario || !Hash::check($credenciais['password'], $usuario->password)) {
            return back()->withErrors(['username' => 'Usuário ou senha inválidos.'])->withInput($request->only('modo'));
        }

        $permitido = $modo === 'admin' ? $usuario->podeAcessarFiscal() : $usuario->podeAcessarCaixa();

        if (!$permitido) {
            return back()->withErrors([
                'username' => $modo === 'admin'
                    ? 'Este usuário não tem acesso ao sistema de cadastros.'
                    : 'Este usuário não tem acesso ao caixa.',
            ]);
        }

        Auth::guard($guard)->login($usuario);
        $request->session()->regenerate();
        $request->session()->put('modo', $modo);

        return $modo === 'admin'
            ? redirect()->route('produtos.index')
            : redirect()->route('caixa.abrir-form');
    }

    public function logout(Request $request)
    {
        // "contexto" vem do formulário de sair: caixa | admin. Sem ele, sai de tudo.
        $contexto = $request->input('contexto');

        if ($contexto !== 'admin') {
            Auth::guard('caixa')->logout();
        }
        if ($contexto !== 'caixa') {
            Auth::guard('web')->logout();
        }

        // Só destrói a sessão se não restou nenhum login (admin e caixa são independentes)
        if (!Auth::guard('web')->check() && !Auth::guard('caixa')->check()) {
            $request->session()->invalidate();
        }

        $request->session()->regenerateToken();

        return redirect()->route('auth.escolha');
    }

    // Primeira vez (cache vazio): tenta puxar os usuários do servidor uma única vez
    private function usuarioDoCaixa(string $username): ?User
    {
        if (UsuarioCache::vazio()) {
            (new SyncService())->puxarUsuarios();
        }

        return UsuarioCache::porUsername($username);
    }
}