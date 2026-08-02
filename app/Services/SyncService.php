<?php

namespace App\Services;

use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SyncService
{
    /**
     * Direcao 1: puxa do MySQL central pro SQLite local (catalogo de produtos).
     * So traz o que mudou desde a ultima sincronizacao.
     */
    public function puxarCatalogo(): array
    {
        try {
            $ultimaSync = $this->obterMeta('ultima_sincronizacao_produtos', '1970-01-01 00:00:00');

            $produtos = Produto::where('updated_at', '>', $ultimaSync)->get();

            foreach ($produtos as $produto) {
            DB::connection('sqlite_local')->table('produtos_cache')->updateOrInsert(
                ['id' => $produto->id],
                [
                    'nome' => $produto->nome,
                    'codigo_interno' => $produto->codigo_interno,
                    'codigo_barras' => $produto->codigo_barras,
                    'ncm' => $produto->ncm,
                    'cest' => $produto->cest,
                    'cfop_padrao' => $produto->cfop_padrao,
                    'unidade_comercial' => $produto->unidade_comercial,
                    'unidade_tributavel' => $produto->unidade_tributavel,
                    'origem_mercadoria' => $produto->origem_mercadoria,
                    'csosn' => $produto->csosn,
                    'class_trib_ibs_cbs' => $produto->class_trib_ibs_cbs,
                    'preco_venda' => $produto->preco_venda,
                    'preco_custo' => $produto->preco_custo,
                    'tem_variacao' => $produto->tem_variacao,
                    'estoque' => $produto->estoque,
                    'ativo' => $produto->ativo,
                    'atualizado_em_origem' => $produto->updated_at,
                    'updated_at' => now(),
                ]
            );

                // Sincroniza variantes desse produto, se tiver
                if ($produto->tem_variacao) {
                    foreach ($produto->variantes as $variante) {
                        DB::connection('sqlite_local')->table('produto_variantes_cache')->updateOrInsert(
                            ['id' => $variante->id],
                            [
                                'produto_id' => $variante->produto_id,
                                'cor' => $variante->cor,
                                'tamanho' => $variante->tamanho,
                                'estoque' => $variante->estoque,
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }

            $this->salvarMeta('ultima_sincronizacao_produtos', now()->toDateTimeString());

            return ['sucesso' => true, 'produtos_atualizados' => $produtos->count()];
        } catch (Exception $e) {
            // Servidor pode estar fora do ar - falha silenciosa, tenta de novo na proxima chamada
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Direcao 2: sobe vendas pendentes do SQLite local pro MySQL central.
     */
    public function enviarVendasPendentes(): array
    {
        $pendentes = DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('status', 'pendente_sync')
            ->orWhere('status', 'erro_sync')
            ->get();

        $enviadas = 0;
        $falhas = 0;

        foreach ($pendentes as $vendaLocal) {
            try {
                $jaExiste = DB::table('vendas')->where('uuid', $vendaLocal->uuid)->exists();

                if (!$jaExiste) {
                    DB::transaction(function () use ($vendaLocal) {
                        $itens = json_decode($vendaLocal->itens, true);
                        $pagamentos = json_decode($vendaLocal->pagamentos, true) ?? [];

                        $vendaId = DB::table('vendas')->insertGetId([
                            'uuid' => $vendaLocal->uuid,
                            'caixa_id' => $vendaLocal->caixa_id_central,
                            'operador_id' => $vendaLocal->operador_id_central,
                            'total' => $vendaLocal->total,
                            'troco' => $vendaLocal->troco,
                            'forma_pagamento' => $vendaLocal->forma_pagamento,
                            'status' => 'pendente',
                            'created_at' => $vendaLocal->vendida_em,
                            'updated_at' => now(),
                        ]);

                        foreach ($itens as $item) {
                            if (!empty($item['produto_variante_id'])) {
                                DB::table('produto_variantes')
                                    ->where('id', $item['produto_variante_id'])
                                    ->lockForUpdate()
                                    ->decrement('estoque', $item['quantidade']);
                            } else {
                                DB::table('produtos')
                                    ->where('id', $item['produto_id'])
                                    ->lockForUpdate()
                                    ->decrement('estoque', $item['quantidade']);
                            }

                            DB::table('venda_itens')->insert([
                                'venda_id' => $vendaId,
                                'produto_id' => $item['produto_id'],
                                'produto_variante_id' => $item['produto_variante_id'] ?? null,
                                'quantidade' => $item['quantidade'],
                                'preco_unitario' => $item['preco_unitario'],
                                'subtotal' => $item['preco_unitario'] * $item['quantidade'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        foreach ($pagamentos as $pagamento) {
                            DB::table('venda_pagamentos')->insert([
                                'venda_id' => $vendaId,
                                'forma_pagamento' => $pagamento['forma_pagamento'],
                                'valor' => $pagamento['valor'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    });
                }

                DB::connection('sqlite_local')->table('vendas_pendentes')
                    ->where('id', $vendaLocal->id)
                    ->update([
                        'status' => 'sincronizada',
                        'sincronizada_em' => now(),
                        'updated_at' => now(),
                    ]);

                $enviadas++;
            } catch (Exception $e) {
                DB::connection('sqlite_local')->table('vendas_pendentes')
                    ->where('id', $vendaLocal->id)
                    ->update([
                        'status' => 'erro_sync',
                        'erro_sync_mensagem' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);

                $falhas++;
            }
        }

        return ['sucesso' => true, 'enviadas' => $enviadas, 'falhas' => $falhas];
    }

    /**
     * Roda os dois sentidos de uma vez. Chamado pelo scheduler ou manualmente.
     */
    public function sincronizarTudo(): array
    {
        $catalogo = $this->puxarCatalogo();
        $vendas = $this->enviarVendasPendentes();

        return ['catalogo' => $catalogo, 'vendas' => $vendas];
    }

    protected function obterMeta(string $chave, string $default = null): ?string
    {
        $registro = DB::connection('sqlite_local')->table('sync_meta')->where('chave', $chave)->first();
        return $registro->valor ?? $default;
    }

    protected function salvarMeta(string $chave, string $valor): void
    {
        DB::connection('sqlite_local')->table('sync_meta')->updateOrInsert(
            ['chave' => $chave],
            ['valor' => $valor, 'updated_at' => now()]
        );
    }
}