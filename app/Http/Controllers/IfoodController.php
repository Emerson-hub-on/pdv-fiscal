<?php

namespace App\Http\Controllers;

use App\Models\IfoodConfiguracao;
use App\Models\IfoodProduto;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Services\IfoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Cadastros → iFood: credenciais do aplicativo e da loja, vínculo da loja (app distribuído),
 * aceite automático/manual, polling e o cardápio importado do iFood com a ligação aos produtos do sistema.
 * As ações da tela respondem JSON (validação manual: o sistema só devolve erro em JSON para api/*).
 */
class IfoodController extends Controller
{
    public function __construct(private IfoodService $ifood)
    {
    }

    public function index()
    {
        $cfg = IfoodConfiguracao::atual();

        return view('ifood.index', [
            'cfg'      => $cfg,
            'produtos' => $this->produtos(),
        ]);
    }

    public function salvar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'modo_autenticacao' => ['required', Rule::in([IfoodConfiguracao::DISTRIBUIDO, IfoodConfiguracao::CENTRALIZADO])],
            'client_id'         => ['nullable', 'string', 'max:120'],
            'client_secret'     => ['nullable', 'string', 'max:255'],
            'merchant_id'       => ['nullable', 'string', 'max:60'],
        ], [
            'client_id.max'   => 'Client ID muito longo.',
            'merchant_id.max' => 'ID da loja muito longo.',
        ]);

        $cfg = IfoodConfiguracao::atual();
        $mudouApp = trim((string) $dados['client_id']) !== (string) $cfg->client_id
            || $dados['modo_autenticacao'] !== $cfg->modo_autenticacao
            || filled($dados['client_secret'] ?? null);

        $cfg->fill([
            'modo_autenticacao' => $dados['modo_autenticacao'],
            'client_id'         => trim((string) $dados['client_id']) ?: null,
            'merchant_id'       => trim((string) $dados['merchant_id']) ?: null,
            'ativo'             => $request->boolean('ativo'),
            'aceite_automatico' => $request->boolean('aceite_automatico'),
        ]);
        // Segredo em branco = mantém o que já está salvo
        if (filled($dados['client_secret'] ?? null)) {
            $cfg->client_secret = trim($dados['client_secret']);
        }
        $cfg->save();

        // Trocou o aplicativo: a autorização antiga não vale mais
        if ($mudouApp && $cfg->wasChanged(['client_id', 'client_secret', 'modo_autenticacao'])) {
            $this->ifood->desvincular($cfg);
        }

        if ($cfg->ativo && !$cfg->credenciaisPreenchidas()) {
            return back()->with('erro', 'Configuração salva, mas para receber pedidos preencha Client ID, Client Secret e ID da loja.');
        }

        return back()->with('sucesso', 'Configuração do iFood salva.');
    }

    /** Aceite automático/manual pela tela (também usado na Lista de Pedidos). */
    public function aceite(Request $request): JsonResponse
    {
        $cfg = IfoodConfiguracao::atual();
        $cfg->update(['aceite_automatico' => $request->boolean('automatico')]);

        return response()->json(['aceite_automatico' => $cfg->aceite_automatico]);
    }

    public function gerarCodigo(): JsonResponse
    {
        return $this->tentar(fn () => $this->ifood->gerarCodigoVinculo(IfoodConfiguracao::atual()));
    }

    public function concluirVinculo(Request $request): JsonResponse
    {
        $codigo = trim((string) $request->input('codigo'));
        if ($codigo === '') {
            return response()->json(['message' => 'Cole o código de autorização que o iFood mostrou.'], 422);
        }

        return $this->tentar(function () use ($codigo) {
            $cfg = IfoodConfiguracao::atual();
            $this->ifood->concluirVinculo($cfg, $codigo);

            return ['message' => 'Loja vinculada ao iFood.'] + $this->situacao($cfg->fresh());
        });
    }

    public function desvincular(): JsonResponse
    {
        $cfg = IfoodConfiguracao::atual();
        $this->ifood->desvincular($cfg);

        return response()->json(['message' => 'Loja desvinculada. Os pedidos do iFood param de chegar.'] + $this->situacao($cfg->fresh()));
    }

    public function testar(): JsonResponse
    {
        return $this->tentar(function () {
            $cfg = IfoodConfiguracao::atual();
            $r = $this->ifood->testarConexao($cfg);

            return ['message' => "Conectado à loja {$r['loja']}." . ($r['situacao'] ? " {$r['situacao']}" : '')] + $this->situacao($cfg->fresh());
        });
    }

    public function buscarPedidos(): JsonResponse
    {
        return $this->tentar(function () {
            $cfg = IfoodConfiguracao::atual();
            if (!$cfg->ativo) {
                throw new RuntimeException('Marque "Receber pedidos do iFood" e salve antes.');
            }
            $r = $this->ifood->polling($cfg, true);
            if ($r['erros']) {
                throw new RuntimeException(implode(' | ', $r['erros']));
            }

            return ['message' => $r['pedidos_novos']
                ? "{$r['pedidos_novos']} pedido(s) novo(s) do iFood na Lista de Pedidos."
                : 'Nenhum pedido novo no iFood.'] + $this->situacao($cfg->fresh());
        });
    }

    public function situacaoJson(): JsonResponse
    {
        return response()->json($this->situacao(IfoodConfiguracao::atual()));
    }

    // ===== Cardápio =====

    public function importarCardapio(): JsonResponse
    {
        return $this->tentar(function () {
            $r = $this->ifood->importarCardapio(IfoodConfiguracao::atual());

            return [
                'message'  => "Cardápio importado: {$r['total']} itens ({$r['novos']} novos, {$r['atualizados']} atualizados"
                    . ($r['removidos'] ? ', ' . count($r['removidos']) . ' removido(s) por não estarem mais no iFood: ' . implode(', ', $r['removidos']) : '')
                    . "). {$r['assimilados']} ligados sozinhos a produtos do sistema."
                    . ($r['removidos'] ? ' Os produtos do sistema ligados a eles continuam em Cadastros → Produtos.' : ''),
                'produtos' => $this->produtos(),
            ];
        });
    }

    public function assimilarAutomatico(): JsonResponse
    {
        $n = $this->ifood->assimilarAutomatico();

        return response()->json([
            'message'  => $n ? "{$n} item(ns) ligados pelo código de barras, código ou nome." : 'Nenhum item novo encontrado para ligar automaticamente.',
            'produtos' => $this->produtos(),
        ]);
    }

    /** Liga (ou desliga) o item do iFood a um produto do sistema e/ou muda o preço no sistema. */
    public function atualizarProduto(Request $request, IfoodProduto $item): JsonResponse
    {
        if ($request->has('preco')) {
            $preco = round((float) str_replace(',', '.', (string) $request->input('preco')), 2);
            if ($preco < 0 || $preco > 999999) {
                return response()->json(['message' => 'Preço inválido.'], 422);
            }
            $item->preco = $preco;
        }

        if ($request->has('produto_id')) {
            $produtoId = $request->input('produto_id');
            $varianteId = $request->input('produto_variante_id');

            if ($produtoId === null || $produtoId === '') {
                $item->produto_id = null;
                $item->produto_variante_id = null;
            } else {
                $produto = Produto::find((int) $produtoId);
                if (!$produto) {
                    return response()->json(['message' => 'Produto não encontrado.'], 422);
                }
                if ($varianteId && !ProdutoVariante::where('produto_id', $produto->id)->whereKey((int) $varianteId)->exists()) {
                    return response()->json(['message' => 'Variação não encontrada.'], 422);
                }
                $item->produto_id = $produto->id;
                $item->produto_variante_id = $varianteId ? (int) $varianteId : null;
            }
        }

        // Pausar no iFood quando o estoque acabar (e voltar quando repor)
        $aviso = null;
        if ($request->has('pausar_sem_estoque')) {
            $item->pausar_sem_estoque = $request->boolean('pausar_sem_estoque');
            if (!$item->pausar_sem_estoque) {
                $item->pausado_automatico = false; // desligou: uma pausa que já estava fica, e volta à mão
            }
        }
        if (!$item->produto_id) {
            $item->pausar_sem_estoque = false;
            $item->pausado_automatico = false;
        }

        $item->save();

        // Ligou a pausa automática: confere o estoque agora (sem esperar o próximo polling)
        if ($request->boolean('pausar_sem_estoque') && $item->pausar_sem_estoque) {
            $r = $this->ifood->sincronizarDisponibilidade(IfoodConfiguracao::atual(), $item);
            $aviso = $r['erros'] ? 'Não foi possível pausar no iFood: ' . implode(' | ', $r['erros'])
                : ($r['pausados'] ? "\"{$item->nome}\" está sem estoque: foi pausado no iFood." : null);
        }

        return response()->json(['produto' => $this->item($item), 'message' => $aviso, 'erro' => $aviso !== null && str_starts_with($aviso, 'Não')]);
    }

    /** Pausa ou ativa o item no iFood pela tela (pausa manual: só volta à mão). */
    public function disponibilidade(Request $request, IfoodProduto $item): JsonResponse
    {
        $disponivel = $request->boolean('disponivel');

        return $this->tentar(function () use ($item, $disponivel) {
            $this->ifood->alterarDisponibilidade(IfoodConfiguracao::atual(), $item, $disponivel);
            $produto = $this->item($item);
            $semEstoque = $disponivel && $item->pausar_sem_estoque && ($produto['estoque'] ?? 1) <= 0;

            return [
                'message' => $disponivel
                    ? "\"{$item->nome}\" ativado no iFood." . ($semEstoque ? ' Atenção: está sem estoque e a pausa automática vai pausar de novo em até 30 s.' : '')
                    : "\"{$item->nome}\" pausado no iFood. Ele só volta quando você ativar.",
                'erro'    => $semEstoque,
                'produto' => $produto,
            ];
        });
    }

    public function enviarPreco(IfoodProduto $item): JsonResponse
    {
        return $this->tentar(function () use ($item) {
            $this->ifood->enviarPreco(IfoodConfiguracao::atual(), $item);

            return ['message' => "Preço de \"{$item->nome}\" atualizado no iFood.", 'produto' => $this->item($item)];
        });
    }

    // ===== Cadastro automático (modelo fiscal por categoria) =====

    /** Categorias do iFood com itens sem produto e o modelo fiscal de cada uma. */
    public function modelos(): JsonResponse
    {
        return response()->json($this->dadosModelos());
    }

    public function salvarModelos(Request $request): JsonResponse
    {
        $padraoId = $request->input('padrao_id');
        $categorias = (array) $request->input('categorias', []);
        $ids = collect($categorias)->filter()->push($padraoId)->filter()->map(fn ($v) => (int) $v)->unique();
        if ($ids->isNotEmpty() && Produto::whereIn('id', $ids)->count() !== $ids->count()) {
            return response()->json(['message' => 'Algum produto modelo não foi encontrado.'], 422);
        }

        IfoodConfiguracao::atual()->update(['produto_modelo_id' => $padraoId ? (int) $padraoId : null]);
        foreach ($categorias as $categoria => $modeloId) {
            \App\Models\IfoodCategoriaFiscal::updateOrCreate(
                ['categoria' => mb_substr((string) $categoria, 0, 120)],
                ['produto_modelo_id' => $modeloId ? (int) $modeloId : null]
            );
        }

        return response()->json($this->dadosModelos());
    }

    public function cadastrarAutomatico(Request $request): JsonResponse
    {
        $ids = $request->has('ids') ? array_map('intval', (array) $request->input('ids')) : null;
        $r = $this->ifood->cadastrarAutomatico($ids);

        $partes = [$r['criados'] ? "{$r['criados']} produto(s) cadastrado(s) e ligado(s)." : 'Nenhum produto cadastrado.'];
        if ($r['sem_modelo']) {
            $partes[] = count($r['sem_modelo']) . ' sem modelo fiscal (escolha o modelo da categoria).';
        }
        if ($r['erros']) {
            $partes[] = 'Erros: ' . implode(' | ', array_slice($r['erros'], 0, 5)) . (count($r['erros']) > 5 ? ' ...' : '');
        }

        return response()->json([
            'message'  => implode(' ', $partes),
            'erro'     => (bool) ($r['erros'] || $r['sem_modelo']),
            'produtos' => $this->produtos(),
        ] + $this->dadosModelos());
    }

    private function dadosModelos(): array
    {
        $resumo = fn (?Produto $p) => $p ? [
            'id'         => $p->id,
            'nome'       => $p->nome,
            'ncm'        => $p->ncm?->codigo,
            'tributacao' => $p->tributacao?->descricao,
        ] : null;

        $cfg = IfoodConfiguracao::atual()->load('produtoModelo.ncm', 'produtoModelo.tributacao');
        $definidos = \App\Models\IfoodCategoriaFiscal::with('modelo.ncm', 'modelo.tributacao')->get()->keyBy('categoria');
        $pendentes = IfoodProduto::whereNull('produto_id')->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');
        $todas = IfoodProduto::query()->distinct()->orderBy('categoria')->pluck('categoria');

        return [
            'padrao'     => $resumo($cfg->produtoModelo),
            'categorias' => $todas->map(fn ($c) => [
                'categoria' => $c ?? '',
                'rotulo'    => $c ?? 'Sem categoria',
                'pendentes' => (int) ($pendentes[$c] ?? 0),
                'modelo'    => $resumo($c !== null ? $definidos->get($c)?->modelo : null),
            ])->values(),
            'total_pendentes' => (int) $pendentes->sum(),
        ];
    }

    /** Busca de produtos do sistema para ligar (uma linha por variação, como no caixa). */
    public function buscarProduto(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('termo'));
        if ($termo === '') {
            return response()->json([]);
        }

        // Escolha do modelo fiscal: um por produto, com NCM e tributação para conferir
        if ($request->boolean('modelo')) {
            $like = '%' . addcslashes($termo, '\\%_') . '%';

            return response()->json(Produto::with(['ncm:id,codigo', 'tributacao:id,descricao'])
                ->where(fn ($q) => $q->where('nome', 'like', $like)->orWhere('codigo_interno', $termo)->orWhere('codigo_barras', $termo))
                ->whereNotNull('ncm_id')->whereNotNull('tributacao_id')
                ->orderBy('nome')->limit(30)->get()
                ->map(fn ($p) => ['produto_id' => $p->id, 'produto_variante_id' => null, 'nome' => $p->nome,
                    'codigo' => 'NCM ' . ($p->ncm?->codigo ?? '—') . ' · ' . ($p->tributacao?->descricao ?? 'sem tributação'),
                    'preco' => (float) $p->preco_venda]));
        }

        $like = '%' . addcslashes($termo, '\\%_') . '%';
        $linhas = [];

        $produtos = Produto::ativos()
            ->with('variantes')
            ->where(fn ($q) => $q->where('nome', 'like', $like)->orWhere('codigo_interno', $termo)->orWhere('codigo_barras', $termo)
                ->orWhereHas('variantes', fn ($v) => $v->where('codigo_barras', $termo)))
            ->orderBy('nome')->limit(30)->get();

        foreach ($produtos as $p) {
            if ($p->tem_variacao && $p->variantes->isNotEmpty()) {
                foreach ($p->variantes as $v) {
                    $linhas[] = ['produto_id' => $p->id, 'produto_variante_id' => $v->id,
                        'nome' => $v->rotulo() ? "{$p->nome} — {$v->rotulo()}" : $p->nome,
                        'codigo' => $v->codigo_barras, 'preco' => (float) $p->preco_venda];
                }
            } else {
                $linhas[] = ['produto_id' => $p->id, 'produto_variante_id' => null, 'nome' => $p->nome,
                    'codigo' => $p->codigo_barras ?: $p->codigo_interno, 'preco' => (float) $p->preco_venda];
            }
        }

        return response()->json(array_slice($linhas, 0, 40));
    }

    // ===== Apoio =====

    private function item(IfoodProduto $item): array
    {
        return $item->fresh(['produto:id,nome,estoque', 'variante'])->paraTela();
    }

    private function produtos()
    {
        return IfoodProduto::with(['produto:id,nome,estoque', 'variante'])->orderBy('categoria')->orderBy('nome')->get()->map->paraTela()->values();
    }

    private function situacao(IfoodConfiguracao $cfg): array
    {
        return ['situacao' => [
            'conectado'        => $cfg->conectado(),
            'token_expira_em'  => $cfg->token_expira_em?->toIso8601String(),
            'polling_em_dia'   => $cfg->pollingEmDia(),
            'ultimo_polling_em' => $cfg->ultimo_polling_em?->toIso8601String(),
            'ultimo_erro'      => $cfg->ultimo_erro,
            'ultimo_erro_em'   => $cfg->ultimo_erro_em?->toIso8601String(),
            'aceite_automatico' => $cfg->aceite_automatico,
            'ativo'            => $cfg->ativo,
        ]];
    }

    private function tentar(callable $acao): JsonResponse
    {
        try {
            return response()->json($acao());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
