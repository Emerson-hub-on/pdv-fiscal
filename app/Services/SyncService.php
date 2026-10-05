<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\Cliente;
use App\Models\Caixa;
use App\Models\Pdv;
use App\Support\CentralStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use Exception;

class SyncService
{
    /**
     * Direcao 1: puxa do MySQL central pro SQLite local (catalogo de produtos).
     * So traz o que mudou desde a ultima sincronizacao - e reconcilia exclusoes.
     */
    public function puxarCatalogo(): array
    {
        try {
            $ultimaSync = $this->obterMeta('ultima_sincronizacao_produtos', '1970-01-01 00:00:00');

            $produtos = Produto::with(['ncm', 'cest', 'tributacao', 'classificacaoTributaria', 'variantes'])
                ->where('updated_at', '>', $ultimaSync)
                ->get();

            foreach ($produtos as $produto) {
                DB::connection('sqlite_local')->table('produtos_cache')->updateOrInsert(
                    ['id' => $produto->id],
                    [
                        'nome' => $produto->nome,
                        'codigo_interno' => $produto->codigo_interno,
                        'codigo_barras' => $produto->codigo_barras,
                        'codigo_barras_valido' => $produto->codigo_barras_valido,
                        'ncm' => $produto->ncm?->codigo,
                        'cest' => $produto->cest?->codigo,
                        'cfop_padrao' => $produto->tributacao?->cfop,
                        'unidade_comercial' => $produto->unidade_comercial,
                        'unidade_tributavel' => $produto->unidade_tributavel,
                        'origem_mercadoria' => $produto->origem_mercadoria,
                        'csosn' => $produto->tributacao?->csosn,
                        'class_trib_ibs_cbs' => $produto->classificacaoTributaria?->codigo,
                        'preco_venda' => $produto->preco_venda,
                        'preco_custo' => $produto->preco_custo,
                        'tem_variacao' => $produto->tem_variacao,
                        'estoque' => $produto->estoque,
                        'ativo' => $produto->ativo,
                        'atualizado_em_origem' => $produto->updated_at,
                        'produto_balanca' => $produto->produto_balanca,
                        'preco_atacado' => $produto->preco_atacado,
                        'quantidade_minima_atacado' => $produto->quantidade_minima_atacado,
                        'atacado_tem_prazo' => $produto->atacado_tem_prazo,
                        'atacado_data_inicio' => $produto->atacado_data_inicio?->format('Y-m-d'),
                        'atacado_data_fim' => $produto->atacado_data_fim?->format('Y-m-d'),
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

            // Reconciliação de exclusões: o diff por updated_at acima NUNCA pega uma
            // linha apagada no central - ela simplesmente não existe mais pra cair em
            // nenhum "where updated_at > X". Sem isso, um produto excluído no MySQL
            // (ex: via DELETE direto) fica pra sempre no cache do caixa, disponível
            // pra venda mesmo não existindo mais na origem.
            //
            // Nota: o app não expõe destroy() de produto de propósito (só inativar) -
            // se isso está removendo algo, o mais provável é que o produto tenha sido
            // apagado direto no banco central, fora do fluxo normal. Idealmente
            // produtos nunca são hard-deleted (podem existir venda_itens referenciando
            // o id), então vale checar se não há histórico de venda vinculado antes de
            // confiar cegamente nessa exclusão.
            $idsAtuaisCentral = Produto::pluck('id');

            DB::connection('sqlite_local')->table('produtos_cache')
                ->whereNotIn('id', $idsAtuaisCentral)
                ->delete();

            // Variantes órfãs (produto pai já não existe mais no central) também saem
            DB::connection('sqlite_local')->table('produto_variantes_cache')
                ->whereNotIn('produto_id', $idsAtuaisCentral)
                ->delete();

            $this->salvarMeta('ultima_sincronizacao_produtos', now()->toDateTimeString());

            return ['sucesso' => true, 'produtos_atualizados' => $produtos->count()];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

        /**
     * Direcao 1c: puxa do MySQL central os usuarios que precisam entrar no caixa
     * (admin, operadores de caixa e supervisores) para o login funcionar offline.
     * Troca o cache inteiro numa transacao: quem perdeu acesso sai junto.
     */
    public function puxarUsuarios(): array
    {
        try {
            $usuarios = User::with('acessos.tipo')
                ->where('ativo', true)
                ->whereNotNull('codigo')
                ->get();

            $agora = now();
            $linhas = [];

            foreach ($usuarios as $u) {
                $mapa = $u->mapaAcessos('caixa'); // o caixa só precisa dos acessos do próprio contexto

                if (!$u->is_admin && !$mapa) {
                    continue;
                }

                $linhas[] = [
                    'id' => $u->id,
                    'codigo' => $u->codigo,
                    'name' => $u->name,
                    'password' => $u->getRawOriginal('password'),
                    'is_admin' => (int) $u->is_admin,
                    'mapa_acessos' => json_encode($mapa),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }

            // Nunca esvazia o cache por causa de uma resposta vazia
            if (!$linhas) {
                return ['sucesso' => true, 'usuarios_atualizados' => 0];
            }

            DB::connection('sqlite_local')->transaction(function () use ($linhas) {
                DB::connection('sqlite_local')->table('usuarios_cache')->delete();
                DB::connection('sqlite_local')->table('usuarios_cache')->insert($linhas);
            });

            $this->salvarMeta('ultima_sincronizacao_usuarios', now()->toDateTimeString());

            return ['sucesso' => true, 'usuarios_atualizados' => count($linhas)];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }


        /**
     * Direcao 1d: espelha os PDVs no SQLite (so o que o caixa precisa para exibir; sem CSC).
     */
    public function puxarPdvs(): array
    {
        try {
            $pdvs = Pdv::all();

            if ($pdvs->isEmpty()) {
                return ['sucesso' => true, 'pdvs_atualizados' => 0];
            }

            $agora = now();

            $linhas = $pdvs->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'serie_nfce' => $p->serie_nfce,
                'numero_atual_nfce' => $p->numero_atual_nfce ?? 0,
                'ativo' => (int) $p->ativo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all();

            DB::connection('sqlite_local')->transaction(function () use ($linhas) {
                DB::connection('sqlite_local')->table('pdvs_cache')->delete();
                DB::connection('sqlite_local')->table('pdvs_cache')->insert($linhas);
            });

            $this->salvarMeta('ultima_sincronizacao_pdvs', now()->toDateTimeString());

            return ['sucesso' => true, 'pdvs_atualizados' => count($linhas)];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    /**
     * Direcao 1e: espelha os caixas ABERTOS no SQLite. Lista vazia e valida (nenhum caixa aberto):
     * apaga o espelho, e assim um caixa fechado no servidor sai do PDV.
     */
    public function puxarCaixasAbertos(): array
    {
        try {
            $caixas = Caixa::where('status', 'aberto')->get();
            $agora = now();

            $linhas = $caixas->map(fn ($c) => [
                'id' => $c->id,
                'operador_id' => $c->operador_id,
                'pdv_id' => $c->pdv_id,
                'data_abertura' => $c->data_abertura,
                'valor_abertura' => $c->valor_abertura,
                'status' => 'aberto',
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all();

            DB::connection('sqlite_local')->transaction(function () use ($linhas) {
                DB::connection('sqlite_local')->table('caixas_cache')->delete();

                if ($linhas) {
                    DB::connection('sqlite_local')->table('caixas_cache')->insert($linhas);
                }
            });

            return ['sucesso' => true, 'caixas_abertos' => count($linhas)];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    // Marca o servidor como fora do ar quando o erro for de conexao
    private function registrarFalha(\Throwable $e): array
    {
        if (CentralStatus::erroDeConexao($e)) {
            CentralStatus::marcarFora();
        }

        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
    /**
     * Direcao 1b: puxa do MySQL central pro SQLite local (cadastro de clientes).
     * Mesmo padrao do puxarCatalogo() - so traz o que mudou.
     */
    public function puxarClientes(): array
    {
        try {
            $ultimaSync = $this->obterMeta('ultima_sincronizacao_clientes', '1970-01-01 00:00:00');

            $clientes = Cliente::where('updated_at', '>', $ultimaSync)->get();

            foreach ($clientes as $cliente) {
                DB::connection('sqlite_local')->table('clientes_cache')->updateOrInsert(
                    ['id' => $cliente->id],
                    [
                        'tipo_pessoa' => $cliente->tipo_pessoa,
                        'nome' => $cliente->nome,
                        'nome_fantasia' => $cliente->nome_fantasia,
                        'cpf_cnpj' => $cliente->cpf_cnpj,
                        'indicador_ie' => $cliente->indicador_ie,
                        'ie' => $cliente->ie,
                        'cep' => $cliente->cep,
                        'logradouro' => $cliente->logradouro,
                        'numero' => $cliente->numero,
                        'complemento' => $cliente->complemento,
                        'bairro' => $cliente->bairro,
                        'municipio' => $cliente->municipio,
                        'cod_municipio' => $cliente->cod_municipio,
                        'uf' => $cliente->uf,
                        'ativo' => $cliente->ativo,
                        'updated_at' => now(),
                    ]
                );
            }

            $this->salvarMeta('ultima_sincronizacao_clientes', now()->toDateTimeString());

            return ['sucesso' => true, 'clientes_atualizados' => $clientes->count()];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    /**
     * Direcao 2: sobe vendas pendentes do SQLite local pro MySQL central.
     */
    public function enviarVendasPendentes(): array
    {
        // Servidor fora do ar: as vendas ficam pendentes e sobem quando a conexão voltar
        if (CentralStatus::fora()) {
            return ['sucesso' => true, 'enviadas' => 0, 'falhas' => 0];
        }

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
                            'cliente_id' => $vendaLocal->cliente_id,
                            'cpf_na_nota' => $vendaLocal->cpf_na_nota,
                            'total' => $vendaLocal->total,
                            'troco' => $vendaLocal->troco,
                            'desconto' => $vendaLocal->desconto,
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
                                'desconto' => $item['desconto'] ?? 0,
                                'subtotal' => ($item['preco_unitario'] * $item['quantidade']) - ($item['desconto'] ?? 0),
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
                // Falha de conexão: não é erro da venda. Mantém pendente e não insiste nas outras
                if (CentralStatus::erroDeConexao($e)) {
                    CentralStatus::marcarFora();
                    break;
                }

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
        $resultado = [];

        $passos = [
            'catalogo' => 'puxarCatalogo',
            'clientes' => 'puxarClientes',
            'usuarios' => 'puxarUsuarios',
            'pdvs'     => 'puxarPdvs',
            'caixas'   => 'puxarCaixasAbertos',
        ];

        foreach ($passos as $chave => $metodo) {
            $resultado[$chave] = CentralStatus::fora()
                ? ['sucesso' => false, 'erro' => 'Servidor indisponível']
                : $this->{$metodo}();
        }

        $resultado['vendas'] = $this->enviarVendasPendentes();

        return $resultado;
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