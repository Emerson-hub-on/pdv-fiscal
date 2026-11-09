<?php

namespace App\Http\Controllers;

use App\Models\CardapioCategoria;
use App\Models\ComandaImpressora;
use App\Services\ImpressaoComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Comandas → Pedidos → ícone de impressora: cadastro das impressoras do restaurante
 * (balcão, cozinha, churrascaria...) e o teste de impressão. Tudo em JSON (janela na Lista de Pedidos).
 */
class ImpressoraComandaController extends Controller
{
    public function __construct(private ImpressaoComandaService $impressao)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->lista());
    }

    public function salvar(Request $request, ?ComandaImpressora $impressora = null): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'nome'            => ['required', 'string', 'max:60'],
            'conexao'         => ['required', Rule::in([ComandaImpressora::REDE, ComandaImpressora::USB])],
            'endereco'        => ['required', 'string', 'max:120'],
            'porta'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'colunas'         => ['required', Rule::in([32, 42, 48])],
            'copias'          => ['required', 'integer', 'min:1', 'max:5'],
            'pedido_completo' => ['boolean'],
            'sem_categoria'   => ['boolean'],
            'mostrar_precos'  => ['boolean'],
            'ativo'           => ['boolean'],
            'categorias'      => ['array'],
            'categorias.*'    => ['integer', 'exists:cardapio_categorias,id'],
        ], [
            'nome.required'     => 'Informe o nome da impressora (ex.: Cozinha).',
            'endereco.required' => 'Informe o IP (rede) ou o nome da impressora compartilhada (USB).',
            'porta.max'         => 'Porta inválida.',
            'copias.max'        => 'No máximo 5 cópias.',
        ]);

        $validador->after(function ($v) use ($request) {
            $dados = $request->all();
            if (($dados['conexao'] ?? '') === ComandaImpressora::REDE
                && !filter_var(trim((string) ($dados['endereco'] ?? '')), FILTER_VALIDATE_IP)
                && !preg_match('/^[a-z0-9.-]+$/i', trim((string) ($dados['endereco'] ?? '')))) {
                $v->errors()->add('endereco', 'IP inválido (ex.: 192.168.1.50).');
            }
            if (($dados['conexao'] ?? '') === ComandaImpressora::USB && preg_match('/["<>|*?]/', (string) ($dados['endereco'] ?? ''))) {
                $v->errors()->add('endereco', 'O nome da impressora compartilhada tem caracteres inválidos.');
            }
            if (empty($dados['pedido_completo']) && empty($dados['sem_categoria']) && empty($dados['categorias'])) {
                $v->errors()->add('categorias', 'Marque "Pedido completo" ou escolha ao menos uma categoria para esta impressora.');
            }
        });

        if ($validador->fails()) {
            return response()->json(['message' => $validador->errors()->first()], 422);
        }

        $dados = $validador->validated();
        $dados['endereco'] = trim($dados['endereco']);
        $dados['porta'] = $dados['conexao'] === ComandaImpressora::REDE ? ($dados['porta'] ?? 9100) : 9100;
        foreach (['pedido_completo', 'sem_categoria', 'mostrar_precos', 'ativo'] as $campo) {
            $dados[$campo] = (bool) ($dados[$campo] ?? false);
        }

        DB::transaction(function () use (&$impressora, $dados) {
            $impressora ??= new ComandaImpressora();
            $impressora->fill(collect($dados)->except('categorias')->all())->save();
            $impressora->categorias()->sync($dados['pedido_completo'] ? [] : ($dados['categorias'] ?? []));
        });

        return response()->json(['salva' => $impressora->id] + $this->lista());
    }

    public function excluir(ComandaImpressora $impressora): JsonResponse
    {
        $impressora->delete();

        return response()->json($this->lista());
    }

    public function testar(ComandaImpressora $impressora): JsonResponse
    {
        try {
            $this->impressao->testar($impressora);
        } catch (Throwable $e) {
            return response()->json(['message' => "{$impressora->nome}: {$e->getMessage()}"], 422);
        }

        return response()->json(['message' => "Página de teste enviada para {$impressora->nome}."]);
    }

    private function lista(): array
    {
        return [
            'impressoras' => ComandaImpressora::with('categorias')->orderBy('nome')->get()->map->paraTela()->values(),
            'categorias'  => CardapioCategoria::ordenadas()->get(['id', 'nome']),
        ];
    }
}
