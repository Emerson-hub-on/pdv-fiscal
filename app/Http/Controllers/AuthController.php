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
            'codigo'   => 'required|integer|min:1',
            'password' => 'required|string',
            'modo'     => 'required|in:admin,operador',
        ]);

        // "admin" = sistema de cadastros/faturamento (servidor central, guard "web", codigo_servidor)
        // "operador" = caixa (cache local do SQLite, guard "caixa", codigo_caixa)
        $modo = $credenciais['modo'];
        $guard = $modo === 'admin' ? 'web' : 'caixa';
        $codigo = (int) $credenciais['codigo'];

        $usuario = $modo === 'admin'
            ? User::where('codigo_servidor', $codigo)->first()
            : $this->usuarioDoCaixa($codigo);

        if (!$usuario || !Hash::check($credenciais['password'], $usuario->password)) {
            return back()->withErrors(['codigo' => 'Código ou senha inválidos.'])->withInput($request->only('modo'));
        }

        $permitido = $modo === 'admin' ? $usuario->podeAcessarFiscal() : $usuario->podeAcessarCaixa();

        if (!$permitido) {
            return back()->withErrors([
                'codigo' => $modo === 'admin'
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

        // Mostra o nome do usuário na tela de login assim que o código é digitado
    public function nomePorCodigo(Request $request)
    {
        $dados = $request->validate([
            'codigo' => 'required|integer|min:1',
            'modo'   => 'required|in:admin,operador',
        ]);

        $codigo = (int) $dados['codigo'];

        $usuario = $dados['modo'] === 'admin'
            ? User::where('codigo_servidor', $codigo)->first()
            : UsuarioCache::porCodigo($codigo); // caixa: só o SQLite local

        $permitido = $usuario && ($dados['modo'] === 'admin'
            ? $usuario->podeAcessarFiscal()
            : $usuario->podeAcessarCaixa());

        return response()->json(['nome' => $permitido ? $usuario->name : null]);
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
    private function usuarioDoCaixa(int $codigo): ?User
    {
        if (UsuarioCache::vazio()) {
            (new SyncService())->puxarUsuarios();
        }

        return UsuarioCache::porCodigo($codigo);
    }
}