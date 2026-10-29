<?php

namespace App\Services;

use App\Models\ContagemEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use App\Models\Unidade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contagem física do estoque:
 *  1. planilha() — lista os itens (produto simples ou cada variação) com o saldo do sistema na data
 *  2. importar() — lê a planilha preenchida e grava um rascunho com o comparativo (nada muda no estoque)
 *  3. aplicar()  — cada diferença vira um ajuste no EstoqueService, datado no fim do dia da contagem
 *
 * Como o ajuste entra na data da contagem, o InventarioService já enxerga o estoque contado
 * naquela data, e o saldo atual muda pela mesma diferença (as vendas depois da contagem continuam valendo).
 */
class ContagemEstoqueService
{
    /** Linhas da planilha: um item por produto simples e um por variação. */
    public function planilha(Carbon $data): array
    {
        $saldos = $this->saldosNaData($data);
        $linhas = [];

        Produto::query()
            ->with('variantes')
            ->where(fn ($q) => $q->where('ativo', true)->orWhere('estoque', '!=', 0))
            ->orderBy('nome')
            ->chunk(500, function ($produtos) use (&$linhas, $saldos) {
                foreach ($produtos as $p) {
                    $unidade = strtoupper(trim((string) $p->unidade_comercial)) ?: 'UN';

                    if (! $p->tem_variacao) {
                        $linhas[] = [
                            'produto_id' => $p->id, 'variante_id' => null, 'codigo' => $p->codigo_interno,
                            'codigo_barras' => $p->codigo_barras_valido ? $p->codigo_barras : '',
                            'descricao' => $p->nome, 'unidade' => $unidade, 'saldo' => $saldos[$p->id . ':0'] ?? 0.0,
                            'custo' => round((float) ($p->preco_custo ?? 0), 4),
                        ];
                        continue;
                    }

                    foreach ($p->variantes->sortBy(fn ($v) => $v->rotulo()) as $v) {
                        $linhas[] = [
                            'produto_id' => $p->id, 'variante_id' => $v->id, 'codigo' => $p->codigo_interno,
                            'codigo_barras' => $v->codigo_barras_valido ? $v->codigo_barras : '',
                            'descricao' => trim($p->nome . ' - ' . $v->rotulo(), ' -'), 'unidade' => $unidade,
                            'saldo' => $saldos[$p->id . ':' . $v->id] ?? 0.0,
                            'custo' => round((float) ($p->preco_custo ?? 0), 4),
                        ];
                    }
                }
            });

        return $linhas;
    }

    /**
     * Lê as linhas da planilha (primeira aba) e cria o rascunho da contagem.
     * Linha com "Quantidade contada" em branco = item não contado (fica de fora; o saldo não muda).
     * Qualquer linha inválida recusa a importação inteira, com a lista dos problemas.
     */
    public function importar(array $planilha, Carbon $data, ?string $arquivo, ?string $observacao): ContagemEstoque
    {
        $this->exigirDataNoHistorico($data);

        $saldos = $this->saldosNaData($data);
        $erros = [];
        $contados = [];

        // Coluna da contagem: a do cabeçalho "Quantidade contada"; sem cabeçalho, a última
        $cabecalho = array_values($planilha[0] ?? []);
        $colContada = collect($cabecalho)->search(fn ($c) => str_contains(mb_strtolower((string) $c), 'contada'));

        foreach ($planilha as $i => $linha) {
            $n = $i + 1;
            $linha = array_values($linha);

            // Cabeçalho e linhas totalmente vazias
            if ($i === 0 && ! is_numeric($linha[0] ?? null)) {
                continue;
            }
            if (collect($linha)->filter(fn ($c) => $c !== null && $c !== '')->isEmpty()) {
                continue;
            }

            $bruto = $colContada !== false ? ($linha[$colContada] ?? null) : end($linha);
            if ($bruto === null || trim((string) $bruto) === '') {
                continue;                             // não contado: o saldo do item não muda
            }

            $contada = $this->numero($bruto);
            if ($contada === null) {
                $erros[] = "Linha {$n}: quantidade contada inválida (\"{$bruto}\").";
                continue;
            }

            $produtoId = (int) ($linha[0] ?? 0);
            $varianteId = ($linha[1] ?? '') !== '' && $linha[1] !== null ? (int) $linha[1] : null;
            $chave = $produtoId . ':' . ($varianteId ?? 0);

            if ($contada < 0) {
                $erros[] = "Linha {$n}: a quantidade contada não pode ser negativa.";
                continue;
            }
            if (isset($contados[$chave])) {
                $erros[] = "Linha {$n}: o item aparece mais de uma vez na planilha (repetido da linha {$contados[$chave]['linha']}).";
                continue;
            }

            $contados[$chave] = ['linha' => $n, 'produto_id' => $produtoId, 'variante_id' => $varianteId, 'contada' => $contada];
        }

        if (! $contados && ! $erros) {
            throw ValidationException::withMessages(['planilha' => 'Nenhuma quantidade contada foi encontrada na planilha (última coluna).']);
        }

        // Confere produtos e variações de uma vez
        $produtos = Produto::whereIn('id', collect($contados)->pluck('produto_id')->unique())->get()->keyBy('id');
        $variantes = ProdutoVariante::whereIn('id', collect($contados)->pluck('variante_id')->filter()->unique())->get()->keyBy('id');
        $itens = [];

        foreach ($contados as $chave => $c) {
            $n = $c['linha'];
            $produto = $produtos[$c['produto_id']] ?? null;

            if (! $produto) {
                $erros[] = "Linha {$n}: produto ID {$c['produto_id']} não encontrado. Não altere as colunas de ID da planilha.";
                continue;
            }

            $variante = $c['variante_id'] ? ($variantes[$c['variante_id']] ?? null) : null;

            if ($produto->tem_variacao && ! $variante) {
                $erros[] = "Linha {$n}: \"{$produto->nome}\" tem variações; a contagem precisa ser por variação (coluna ID variação).";
                continue;
            }
            if ($variante && (int) $variante->produto_id !== (int) $produto->id) {
                $erros[] = "Linha {$n}: a variação ID {$c['variante_id']} não pertence ao produto \"{$produto->nome}\".";
                continue;
            }

            $fracionado = ! $variante && Unidade::permiteFracionado($produto->unidade_comercial);
            if (! $fracionado && fmod($c['contada'], 1.0) != 0.0) {
                $erros[] = "Linha {$n}: \"{$produto->nome}\" não aceita quantidade fracionada ({$c['contada']}).";
                continue;
            }

            $saldo = (float) ($saldos[$chave] ?? 0);
            $custo = round((float) ($produto->preco_custo ?? 0), 6);
            $diferenca = round($c['contada'] - $saldo, 3);

            $itens[] = [
                'produto_id'          => $produto->id,
                'produto_variante_id' => $variante?->id,
                'descricao'           => mb_substr(trim($produto->nome . ($variante ? ' - ' . $variante->rotulo() : ''), ' -'), 0, 255),
                'unidade'             => strtoupper(trim((string) $produto->unidade_comercial)) ?: 'UN',
                'saldo_sistema'       => $saldo,
                'quantidade_contada'  => $c['contada'],
                'diferenca'           => $diferenca,
                'custo_unitario'      => $custo,
                'valor_diferenca'     => round($diferenca * $custo, 2),
            ];
        }

        if ($erros) {
            throw ValidationException::withMessages(['planilha' => array_slice($erros, 0, 30)]);
        }

        return DB::transaction(function () use ($itens, $data, $arquivo, $observacao) {
            $contagem = ContagemEstoque::create([
                'data'         => $data->toDateString(),
                'status'       => ContagemEstoque::RASCUNHO,
                'arquivo_nome' => $arquivo ? mb_substr($arquivo, 0, 255) : null,
                'observacao'   => $observacao,
                'user_id'      => auth()->id(),
            ]);

            $contagem->itens()->createMany($itens);

            return $contagem;
        });
    }

    /**
     * Aplica a contagem: recalcula o saldo na data (pode ter mudado desde a importação) e grava
     * cada diferença como ajuste no EstoqueService, no fim do dia da contagem.
     */
    public function aplicar(ContagemEstoque $contagem, EstoqueService $estoque): array
    {
        $this->exigirDataNoHistorico($contagem->data);

        return DB::transaction(function () use ($contagem, $estoque) {
            $contagem = ContagemEstoque::whereKey($contagem->id)->lockForUpdate()->firstOrFail();

            if (! $contagem->isRascunho()) {
                return ['ajustes' => 0];   // duplo clique
            }

            $saldos = $this->saldosNaData($contagem->data);
            $dataMovimento = $contagem->data->isToday() ? now() : $contagem->data->copy()->endOfDay();
            $ajustes = 0;

            foreach ($contagem->itens as $item) {
                $saldo = (float) ($saldos[$item->produto_id . ':' . ($item->produto_variante_id ?? 0)] ?? 0);
                $diferenca = round($item->quantidade_contada - $saldo, 3);

                $item->update([
                    'saldo_sistema'   => $saldo,
                    'diferenca'       => $diferenca,
                    'valor_diferenca' => round($diferenca * $item->custo_unitario, 2),
                ]);

                $movimento = $estoque->movimentar(
                    (int) $item->produto_id,
                    $item->produto_variante_id ? (int) $item->produto_variante_id : null,
                    $diferenca,
                    EstoqueService::AJUSTE_MANUAL,
                    [
                        'documento_tipo'   => 'contagem_estoque',
                        'documento_id'     => $contagem->id,
                        'documento_rotulo' => $contagem->rotulo(),
                        'observacao'       => $diferenca > 0 ? 'Sobra apurada na contagem física' : 'Falta apurada na contagem física',
                        'custo_unitario'   => $item->custo_unitario,
                        'data'             => $dataMovimento,
                    ]
                );

                $movimento && $ajustes++;
            }

            $contagem->update([
                'status'       => ContagemEstoque::APLICADA,
                'aplicada_em'  => now(),
                'aplicada_por' => auth()->id(),
            ]);

            return ['ajustes' => $ajustes];
        });
    }

    /**
     * Saldo de cada item no fim do dia: saldo atual − movimentações registradas depois da data
     * (mesma regra do InventarioService). Chave "produto:variante" (0 = produto sem variação).
     */
    public function saldosNaData(Carbon $data): array
    {
        $fimDoDia = $data->copy()->endOfDay();

        $depois = MovimentacaoEstoque::query()
            ->where('created_at', '>', $fimDoDia)
            ->selectRaw('produto_id, COALESCE(produto_variante_id, 0) as variante, SUM(quantidade) as total')
            ->groupBy('produto_id', DB::raw('COALESCE(produto_variante_id, 0)'))
            ->get()
            ->mapWithKeys(fn ($m) => [$m->produto_id . ':' . $m->variante => (float) $m->total]);

        $saldos = [];

        foreach (Produto::where('tem_variacao', false)->pluck('estoque', 'id') as $id => $estoque) {
            $chave = $id . ':0';
            $saldos[$chave] = round((float) $estoque - ($depois[$chave] ?? 0), 3);
        }

        foreach (ProdutoVariante::get(['id', 'produto_id', 'estoque']) as $v) {
            $chave = $v->produto_id . ':' . $v->id;
            $saldos[$chave] = round((float) $v->estoque - ($depois[$chave] ?? 0), 3);
        }

        return $saldos;
    }

    /** Antes do início do histórico não dá para saber o saldo na data. */
    private function exigirDataNoHistorico(Carbon $data): void
    {
        $inicio = InventarioService::inicioDoHistorico();

        if ($inicio && $data->copy()->endOfDay()->lt($inicio)) {
            throw ValidationException::withMessages([
                'data' => "A data da contagem é anterior ao início do histórico de estoque ({$inicio->format('d/m/Y')}): o saldo do sistema nessa data não é conhecido.",
            ]);
        }

        if ($data->copy()->startOfDay()->gt(now())) {
            throw ValidationException::withMessages(['data' => 'A contagem não pode ter data futura.']);
        }
    }

    /** Número da planilha: aceita 12, 12.5, "12,5" e "1.234,5". Vazio = null. */
    private function numero($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return round((float) $valor, 3);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_contains($texto, ',') ? str_replace(['.', ','], ['', '.'], $texto) : $texto;

        return is_numeric($texto) ? round((float) $texto, 3) : null;
    }
}