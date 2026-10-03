<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    private const PERFIS = [
        'caixa' => [
            'campo' => 'acesso_caixa', 'titulo' => 'Operadores de Caixa',
            'singular' => 'Operador de Caixa', 'sistema' => 'o caixa',
        ],
        'fiscal' => [
            'campo' => 'acesso_fiscal', 'titulo' => 'Operadores Fiscais',
            'singular' => 'Operador Fiscal', 'sistema' => 'o sistema de cadastros',
        ],
    ];

    private function cfg(string $perfil): array
    {
        return self::PERFIS[$perfil] ?? abort(404);
    }

    public function index(Request $request, string $perfil)
    {
        $cfg   = $this->cfg($perfil);
        $busca = trim((string) $request->get('busca', ''));

        $usuarios = User::where('tipo', '!=', 'admin')
            ->where($cfg['campo'], true)
            ->when($busca !== '', fn ($q) => $q->where('name', 'like', "%{$busca}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return view('usuarios._tabela', compact('usuarios', 'perfil', 'cfg'));
        }

        return view('usuarios.index', compact('usuarios', 'perfil', 'cfg'));
    }

    public function create(string $perfil)
    {
        return view('usuarios.create', ['perfil' => $perfil, 'cfg' => $this->cfg($perfil)]);
    }

    public function store(Request $request, string $perfil)
    {
        $cfg = $this->cfg($perfil);

        $dados = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $nome     = trim($dados['name']);
        $username = User::normalizarUsername($nome);

        if ($username === '') {
            throw ValidationException::withMessages(['name' => 'Informe um nome com letras ou números.']);
        }

        $existente = User::where('username', $username)->first();

        if ($existente) {
            if ($existente->isAdmin()) {
                throw ValidationException::withMessages(['name' => 'Esse nome é reservado ao administrador.']);
            }

            if ($existente->{$cfg['campo']}) {
                throw ValidationException::withMessages(['name' => "{$existente->name} já está cadastrado como {$cfg['singular']}."]);
            }

            // Mesma pessoa em outro perfil: só acrescenta o acesso e mantém a senha atual
            $existente->update([$cfg['campo'] => true]);

            return redirect()->route('usuarios.index', $perfil)
                ->with('sucesso', "{$existente->name} já estava cadastrado em outro perfil. O acesso como {$cfg['singular']} foi adicionado e a senha atual foi mantida.");
        }

        if (empty($dados['password'])) {
            throw ValidationException::withMessages(['password' => 'Informe a senha (mínimo de 4 caracteres).']);
        }

        User::create([
            'name'         => $nome,
            'username'     => $username,
            'tipo'         => 'operador',
            'password'     => $dados['password'],
            $cfg['campo']  => true,
        ]);

        return redirect()->route('usuarios.index', $perfil)
            ->with('sucesso', "{$cfg['singular']} cadastrado. Login: {$username}");
    }

    public function edit(string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->garantirDoPerfil($usuario, $cfg);

        return view('usuarios.edit', compact('perfil', 'cfg', 'usuario'));
    }

    public function update(Request $request, string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->garantirDoPerfil($usuario, $cfg);

        $dados = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $nome     = trim($dados['name']);
        $username = User::normalizarUsername($nome);

        if ($username === '' || User::where('username', $username)->where('id', '!=', $usuario->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Já existe um usuário com esse nome.']);
        }

        $atualizar = ['name' => $nome, 'username' => $username];

        if (!empty($dados['password'])) {
            $atualizar['password'] = $dados['password'];
        }

        $usuario->update($atualizar);

        return redirect()->route('usuarios.index', $perfil)->with('sucesso', 'Cadastro atualizado.');
    }

    public function revogar(string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->garantirDoPerfil($usuario, $cfg);

        $usuario->update([$cfg['campo'] => false]);

        return back()->with('sucesso', "O acesso de {$usuario->name} como {$cfg['singular']} foi removido.");
    }

    private function garantirDoPerfil(User $usuario, array $cfg): void
    {
        abort_if($usuario->isAdmin() || !$usuario->{$cfg['campo']}, 404);
    }
}