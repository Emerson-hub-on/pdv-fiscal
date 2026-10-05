<?php

namespace App\Http\Controllers;

use App\Models\Acesso;
use App\Models\TipoOperador;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    /**
     * O que se configura em cada tipo: itens, níveis e o nível padrão (o que NÃO é gravado).
     * Para um tipo novo (ex.: garçom), basta acrescentar um case aqui e o bloco no config/permissoes.php.
     */
    private function regrasPermissao(string $slug): ?array
    {
        return match ($slug) {
            'fiscal' => [
                'itens'     => config('permissoes.modulos'),
                'niveis'    => config('permissoes.niveis'),
                'padrao'    => 'total',
                'coluna'    => 'Módulo',
                'restaurar' => 'Liberar tudo',
                'descricao' => 'Por padrão o Operador Fiscal tem acesso total a todos os módulos. Restrinja abaixo o que for necessário. Em "Somente consulta" ele visualiza, mas não cria, edita, inativa, emite nem cancela.',
            ],
            'caixa' => [
                'itens'     => config('permissoes.caixa.acoes'),
                'niveis'    => config('permissoes.caixa.niveis'),
                'padrao'    => 'supervisor',
                'coluna'    => 'Ação',
                'restaurar' => 'Exigir supervisor em tudo',
                'descricao' => 'Por padrão, estas ações exigem a autorização de um supervisor no caixa. Marque "Liberado" para que este operador execute a ação sem pedir autorização.',
            ],
            'supervisor' => [
                'itens'     => config('permissoes.caixa.acoes'),
                'niveis'    => config('permissoes.supervisor.niveis'),
                'padrao'    => 'libera',
                'coluna'    => 'Ação',
                'restaurar' => 'Liberar tudo',
                'descricao' => 'Por padrão o Supervisor pode autorizar todas as ações do caixa. Marque "Não libera" para impedir que ele autorize a ação: o operador precisará de outro supervisor ou do administrador.',
            ],
            default => null,
        };
    }

    public function permissoes(string $perfil, User $usuario)
    {
        $cfg    = $this->cfg($perfil);
        $regras = $this->regrasPermissao($perfil) ?? abort(404);
        $acesso = $this->acessoDoPerfil($usuario, $cfg);

        $itens  = $regras['itens'];
        $niveis = $regras['niveis'];
        $padrao = $regras['padrao'];

        $atuais = collect($itens)->mapWithKeys(function ($item, $chave) use ($acesso, $niveis, $padrao) {
            $nivel = $acesso->permissoes[$chave] ?? $padrao;

            return [$chave => array_key_exists($nivel, $niveis) ? $nivel : $padrao];
        });

        return view('usuarios.permissoes', compact('perfil', 'cfg', 'usuario', 'regras', 'itens', 'niveis', 'padrao', 'atuais'));
    }

    public function salvarPermissoes(Request $request, string $perfil, User $usuario)
    {
        $cfg    = $this->cfg($perfil);
        $regras = $this->regrasPermissao($perfil) ?? abort(404);
        $acesso = $this->acessoDoPerfil($usuario, $cfg);

        $request->validate([
            'permissoes'   => ['required', 'array'],
            'permissoes.*' => [Rule::in(array_keys($regras['niveis']))],
        ]);

        // Só guarda o que foge do padrão do tipo
        $desvios = [];
        foreach (array_keys($regras['itens']) as $chave) {
            $nivel = $request->input("permissoes.{$chave}", $regras['padrao']);

            if ($nivel !== $regras['padrao']) {
                $desvios[$chave] = $nivel;
            }
        }

        // Atualiza só o acesso deste tipo: os outros tipos da pessoa não mudam
        $acesso->update(['permissoes' => $desvios ?: null]);

        return redirect()->route('usuarios.index', $perfil)
            ->with('sucesso', "Permissões de {$usuario->name} atualizadas.");
    }

    private function cfg(string $perfil): array
    {
        $tipo = TipoOperador::where('slug', $perfil)->where('ativo', true)->first() ?? abort(404);

        return [
            'tipo'     => $tipo,
            'singular' => $tipo->nome,
            'titulo'   => $tipo->nome_plural ?? $tipo->nome,
        ];
    }

    public function index(Request $request, string $perfil)
    {
        $cfg   = $this->cfg($perfil);
        $busca = trim((string) $request->get('busca', ''));
        $comPermissoes = $this->regrasPermissao($perfil) !== null;

        $usuarios = User::with('acessos.tipo')
            ->where('is_admin', false)
            ->whereHas('acessos', fn ($q) => $q
                ->where('tipo_operador_id', $cfg['tipo']->id)
                ->where('ativo', true))
            ->when($busca !== '', fn ($q) => $q->where(function ($q) use ($busca) {
                $q->where('name', 'like', "%{$busca}%");

                if (ctype_digit($busca)) {
                    $q->orWhere('codigo', (int) $busca);
                }
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return view('usuarios._tabela', compact('usuarios', 'perfil', 'cfg', 'comPermissoes'));
        }

        return view('usuarios.index', compact('usuarios', 'perfil', 'cfg', 'comPermissoes'));
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
            'password' => ['required', 'string', 'min:4', 'max:100'],
        ], [
            'password.required' => 'Informe a senha (mínimo de 4 caracteres).',
        ]);

        $usuario = DB::transaction(function () use ($dados, $cfg) {
            // Um código por pessoa: o próximo livre (códigos nunca são reaproveitados)
            $usuario = User::create([
                'name'     => trim($dados['name']),
                'tipo'     => 'operador', // coluna antiga, ainda obrigatória até a etapa 3
                'password' => $dados['password'],
                'codigo'   => (int) User::max('codigo') + 1,
            ]);

            Acesso::create([
                'user_id'          => $usuario->id,
                'tipo_operador_id' => $cfg['tipo']->id,
                'ativo'            => true,
            ]);

            return $usuario;
        });

        return redirect()->route('usuarios.index', $perfil)
            ->with('sucesso', "{$cfg['singular']} cadastrado. Código de acesso: {$usuario->codigo}");
    }

    // Pessoa que já tem código e senha ganha mais um tipo de acesso (sem mexer em código nem senha)
    public function vincular(Request $request, string $perfil)
    {
        $cfg = $this->cfg($perfil);

        $dados = $request->validate([
            'codigo' => ['required', 'integer', 'min:1'],
        ], [
            'codigo.required' => 'Informe o código da pessoa.',
        ]);

        $usuario = User::where('codigo', $dados['codigo'])->where('is_admin', false)->first();

        if (!$usuario) {
            throw ValidationException::withMessages(['codigo' => 'Nenhuma pessoa encontrada com esse código.']);
        }

        if (!$usuario->ativo) {
            throw ValidationException::withMessages(['codigo' => "{$usuario->name} está desativado."]);
        }

        $acesso = Acesso::firstOrNew([
            'user_id'          => $usuario->id,
            'tipo_operador_id' => $cfg['tipo']->id,
        ]);

        if ($acesso->exists && $acesso->ativo) {
            throw ValidationException::withMessages(['codigo' => "{$usuario->name} já está cadastrado como {$cfg['singular']}."]);
        }

        $acesso->ativo = true; // cria o acesso, ou reativa um que tinha sido removido
        $acesso->save();

        return redirect()->route('usuarios.index', $perfil)
            ->with('sucesso', "{$usuario->name} agora também é {$cfg['singular']}. Código {$usuario->codigo}, senha mantida.");
    }

    public function edit(string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->acessoDoPerfil($usuario, $cfg);

        return view('usuarios.edit', compact('perfil', 'cfg', 'usuario'));
    }

    public function update(Request $request, string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->acessoDoPerfil($usuario, $cfg);

        $dados = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $atualizar = ['name' => trim($dados['name'])];

        if (!empty($dados['password'])) {
            $atualizar['password'] = $dados['password']; // vale para todos os tipos da pessoa
        }

        $usuario->update($atualizar);

        return redirect()->route('usuarios.index', $perfil)->with('sucesso', 'Cadastro atualizado.');
    }

    // Desativa só o acesso deste tipo: a pessoa, o código e os outros tipos continuam
    public function revogar(string $perfil, User $usuario)
    {
        $cfg = $this->cfg($perfil);
        $this->acessoDoPerfil($usuario, $cfg)->update(['ativo' => false]);

        return back()->with('sucesso', "O acesso de {$usuario->name} como {$cfg['singular']} foi removido.");
    }

    private function acessoDoPerfil(User $usuario, array $cfg): Acesso
    {
        abort_if($usuario->is_admin, 404);

        return Acesso::where('user_id', $usuario->id)
            ->where('tipo_operador_id', $cfg['tipo']->id)
            ->where('ativo', true)
            ->firstOr(fn () => abort(404));
    }
}