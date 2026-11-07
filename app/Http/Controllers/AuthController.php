<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UsuarioCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Modo da tela de login => guard da sessão. */
    private const GUARDS = [
        'admin'    => 'web',
        'operador' => 'caixa',
        'garcom'   => 'garcom',
    ];

    public function tela()
    {
        return view('auth.escolha');
    }

    public function formulario(Request $request)
    {
        $modo = $request->query('modo', 'admin'); // admin | operador | garcom
        $caixaVazio = UsuarioCache::vazio(); // caixa novo: a tela faz a primeira carga ao escolher Operador
        return view('auth.login', compact('modo', 'caixaVazio'));
    }

    public function login(Request $request)
    {
        $credenciais = $request->validate([
            'codigo'   => 'required|integer|min:1',
            'password' => 'required|string',
            'modo'     => 'required|in:admin,operador,garcom',
        ]);

        // "admin" = sistema de cadastros/faturamento (servidor central, guard "web")
        // "operador" = caixa (cache local do SQLite, guard "caixa")
        // "garcom" = tela do garçom (servidor central, guard "garcom", independente do painel)
        $modo = $credenciais['modo'];
        $guard = self::GUARDS[$modo];
        $codigo = (int) $credenciais['codigo'];

        $usuario = $modo === 'operador'
            ? UsuarioCache::porCodigo($codigo) // caixa: só o SQLite local
            : User::where('codigo', $codigo)->where('ativo', true)->first();

        // Caixa novo: os dados chegam só pela primeira carga (modal da tela de login), sempre completa
        if (!$usuario && $modo === 'operador' && UsuarioCache::vazio()) {
            return back()->withErrors([
                'codigo' => 'Este caixa ainda não recebeu os dados do servidor. Escolha Operador de Caixa de novo para fazer a primeira carga.',
            ])->withInput($request->only('modo'));
        }

        // Sem limite de tentativas: o operador pode errar a senha quantas vezes precisar
        if (!$usuario || !Hash::check($credenciais['password'], $usuario->password)) {
            return back()->withErrors(['codigo' => 'Código ou senha inválidos.'])->withInput($request->only('modo'));
        }

        if (!$this->permitido($usuario, $modo)) {
            return back()->withErrors([
                'codigo' => match ($modo) {
                    'admin'  => 'Este usuário não tem acesso ao sistema de cadastros.',
                    'garcom' => 'Este usuário não está cadastrado como garçom.',
                    default  => 'Este usuário não tem acesso ao caixa.',
                },
            ])->withInput($request->only('modo'));
        }

        Auth::guard($guard)->login($usuario);
        \Log::info('login ok', ['guard' => $guard, 'sid' => $request->session()->getId()]);
        $request->session()->regenerate();
        $request->session()->put('modo', $modo);

        return match ($modo) {
            'admin'  => redirect()->route('produtos.index'),
            'garcom' => redirect()->route('garcom.index'),
            default  => redirect()->route('caixa.abrir-form'),
        };
    }

    private function permitido(User $usuario, string $modo): bool
    {
        return match ($modo) {
            'admin'  => $usuario->podeAcessarFiscal(),
            'garcom' => $usuario->podeAcessarGarcom(),
            default  => $usuario->podeAcessarCaixa(),
        };
    }


        // Mostra o nome do usuário na tela de login assim que o código é digitado
    public function nomePorCodigo(Request $request)
    {
        $dados = $request->validate([
            'codigo' => 'required|integer|min:1',
            'modo'   => 'required|in:admin,operador,garcom',
        ]);

        $codigo = (int) $dados['codigo'];

        $usuario = $dados['modo'] === 'operador'
            ? UsuarioCache::porCodigo($codigo) // caixa: só o SQLite local
            : User::where('codigo', $codigo)->where('ativo', true)->first();

        $permitido = $usuario && $this->permitido($usuario, $dados['modo']);

        return response()->json(['nome' => $permitido ? $usuario->name : null]);
    }

    public function logout(Request $request)
    {
        // "contexto" vem do formulário de sair: caixa | admin | garcom. Sem ele, sai de tudo.
        $contexto = $request->input('contexto');
        $guards = [
            'caixa'  => 'caixa',
            'admin'  => 'web',
            'garcom' => 'garcom',
        ];

        foreach ($guards as $chave => $guard) {
            if ($contexto === null || $contexto === $chave) {
                Auth::guard($guard)->logout();
            }
        }

        // Só destrói a sessão se não restou nenhum login (admin, caixa e garçom são independentes)
        if (collect($guards)->every(fn ($guard) => !Auth::guard($guard)->check())) {
            $request->session()->invalidate();
        }

        $request->session()->regenerateToken();

        // Garçom volta direto para o login de garçom (é o mesmo aparelho, o próximo garçom entra)
        return $contexto === 'garcom'
            ? redirect()->route('auth.login', ['modo' => 'garcom'])
            : redirect()->route('auth.escolha');
    }

    // Tela de login, ao escolher Operador de Caixa: o banco deste caixa já tem os dados do servidor?
    public function situacaoCaixa()
    {
        return response()->json(['vazio' => UsuarioCache::vazio()]);
    }
}