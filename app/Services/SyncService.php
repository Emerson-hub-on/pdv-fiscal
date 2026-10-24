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
use App\Models\Empresa;
use Illuminate\Support\Facades\Crypt;
use Exception;
use App\Support\Maquina;
use App\Models\Inutilizacao;
use App\Models\PedidoVenda;


class SyncService
{
    /**
     * Direcao 1: puxa do MySQL central pro SQLite local (catalogo de produtos).
     * So traz o que mudou desde a ultima sincronizacao - e reconcilia exclusoes.
     */

        /**
     * Direcao 1f: copia os dados da empresa e o certificado para o SQLite (cifrados com a APP_KEY).
     */

    // PDVs que este computador pode usar: sem restrição ou vinculados ao nome desta máquina
    private function pdvsDestaMaquina()
    {
        return Pdv::where(function ($q) {
            $q->whereNull('maquina')->orWhere('maquina', Maquina::nome());
        });
    }

    public function puxarEmpresa(): array
    {
        try {
            $empresa = Empresa::first();

            if (!$empresa) {
                return ['sucesso' => true, 'empresa' => false];
            }

            $ultimaSync = $this->obterMeta('ultima_sincronizacao_empresa', '1970-01-01 00:00:00');
            $existeLocal = DB::connection('sqlite_local')->table('empresa_cache')->exists();

            if ($existeLocal && $empresa->updated_at->lte($ultimaSync)) {
                return ['sucesso' => true, 'empresa' => false]; // sem mudanças
            }

            DB::connection('sqlite_local')->table('empresa_cache')->updateOrInsert(
                ['id' => 1],
                [
                    'cnpj' => $empresa->cnpj,
                    'razao_social' => $empresa->razao_social,
                    'nome_fantasia' => $empresa->nome_fantasia,
                    'ie' => $empresa->ie,
                    'im' => $empresa->im,
                    'crt' => $empresa->crt,
                    'logradouro' => $empresa->logradouro,
                    'numero' => $empresa->numero,
                    'complemento' => $empresa->complemento,
                    'bairro' => $empresa->bairro,
                    'cep' => $empresa->cep,
                    'municipio' => $empresa->municipio,
                    'cod_municipio' => $empresa->cod_municipio,
                    'uf' => $empresa->uf,
                    'ambiente' => (int) $empresa->ambiente,
                    'certificado' => $empresa->certificado_base64 ? Crypt::encryptString($empresa->certificado_base64) : null,
                    'certificado_senha' => $empresa->certificado_senha ? Crypt::encryptString($empresa->certificado_senha) : null,
                    'certificado_validade' => $empresa->certificado_validade?->format('Y-m-d'),
                    'updated_at' => now(),
                ]
            );

            $this->salvarMeta('ultima_sincronizacao_empresa', now()->toDateTimeString());

            return ['sucesso' => true, 'empresa' => true];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }


    public function puxarCatalogo(): array
    {
        try {
            $ultimaSync = $this->obterMeta('ultima_sincronizacao_produtos', '1970-01-01 00:00:00');

            $produtos = Produto::with(['ncm', 'cest', 'tributacao', 'pisCofins', 'classificacaoTributaria', 'variantes'])
                ->where(function ($q) use ($ultimaSync) {
                    $q->where('updated_at', '>', $ultimaSync)
                      ->orWhereHas('tributacao', fn ($t) => $t->where('updated_at', '>', $ultimaSync))
                      ->orWhereHas('pisCofins', fn ($t) => $t->where('updated_at', '>', $ultimaSync))
                      ->orWhereHas('classificacaoTributaria', fn ($t) => $t->where('updated_at', '>', $ultimaSync))
                      ->orWhereHas('ncm', fn ($t) => $t->where('updated_at', '>', $ultimaSync))
                      ->orWhereHas('cest', fn ($t) => $t->where('updated_at', '>', $ultimaSync))
                      ->orWhereHas('variantes', fn ($t) => $t->where('updated_at', '>', $ultimaSync));
                })
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
                        'cst_icms' => $produto->tributacao?->cst_icms,
                        'aliquota_icms' => $produto->tributacao?->aliquota_icms,
                        'pis_cofins_cst' => $produto->pisCofins?->codigo,
                        'aliquota_pis' => $produto->pisCofins?->aliquota_pis,
                        'aliquota_cofins' => $produto->pisCofins?->aliquota_cofins,
                        'class_trib_cst' => $produto->classificacaoTributaria?->cst_codigo,
                        'percentual_reducao_ibs' => $produto->classificacaoTributaria?->percentual_reducao_ibs,
                        'percentual_reducao_cbs' => $produto->classificacaoTributaria?->percentual_reducao_cbs,
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
                                'codigo_barras' => $variante->codigo_barras,
                                'codigo_barras_valido' => $variante->codigo_barras_valido,
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

            // Variante removida na tela do produto (ou "tem variação" desligado):
            // o produto pai continua existindo, então o bloco acima não pega.
            // Sem isso a variante apagada continua vendável no caixa.
            DB::connection('sqlite_local')->table('produto_variantes_cache')
                ->whereNotIn('id', DB::table('produto_variantes')->pluck('id'))
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
            // 1. Busca usando o escopo da máquina atual e já traz o get()
            $pdvs = $this->pdvsDestaMaquina()->get();
            $agora = now();

            $linhas = $pdvs->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'serie_nfce' => $p->serie_nfce,
                'numero_atual_nfce' => $p->numero_atual_nfce ?? 0,
                'ativo' => (int) $p->ativo,
                'emissao_local' => (int) $p->emissao_local,
                'csc' => $p->csc ? Crypt::encryptString($p->csc) : null,
                'csc_id' => $p->csc_id,
                'maquina' => $p->maquina, // <-- Garante que a coluna 'maquina' é mapeada corretamente
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all();

            DB::connection('sqlite_local')->transaction(function () use ($linhas) {
                DB::connection('sqlite_local')->table('pdvs_cache')->delete();
                if (!empty($linhas)) {
                    DB::connection('sqlite_local')->table('pdvs_cache')->insert($linhas);
                }
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
    /**
     * Direcao 2b: sobe os caixas (aberturas e fechamentos) do SQLite para o servidor.
     * Idempotente: o servidor localiza o caixa pelo uuid.
     */
    public function enviarCaixas(): array
    {
        if (CentralStatus::fora()) {
            return ['sucesso' => true, 'enviados' => 0, 'falhas' => 0];
        }

        $pendentes = DB::connection('sqlite_local')->table('caixas_local')
            ->where('sync_pendente', true)
            ->orderBy('id')
            ->get();

        $enviados = 0;
        $falhas = 0;

        foreach ($pendentes as $c) {
            try {
                // Um operador não pode ter dois caixas abertos no servidor
                if ($c->status === 'aberto') {
                    $conflito = Caixa::where('operador_id', $c->operador_id)
                        ->where('status', 'aberto')
                        ->where(fn ($q) => $q->whereNull('uuid')->orWhere('uuid', '!=', $c->uuid))
                        ->exists();

                    if ($conflito) {
                        throw new Exception('O operador já tem outro caixa aberto no servidor.');
                    }
                }

                $central = Caixa::updateOrCreate(
                    ['uuid' => $c->uuid],
                    [
                        'operador_id' => $c->operador_id,
                        'pdv_id' => $c->pdv_id,
                        'data_abertura' => $c->data_abertura,
                        'valor_abertura' => $c->valor_abertura,
                        'data_fechamento' => $c->data_fechamento,
                        'valor_fechamento_informado' => $c->valor_fechamento_informado,
                        'valor_fechamento_esperado' => $c->valor_fechamento_esperado,
                        'status' => $c->status,
                        'observacao' => $c->observacao,
                    ]
                );

                DB::connection('sqlite_local')->table('caixas_local')
                    ->where('id', $c->id)
                    ->update([
                        'id_central' => $central->id,
                        'sync_pendente' => false,
                        'sync_erro' => null,
                        'sincronizado_em' => now(),
                        'updated_at' => now(),
                    ]);

                $enviados++;
            } catch (\Throwable $e) {
                // Servidor fora do ar: não é erro do caixa, continua pendente
                if (CentralStatus::erroDeConexao($e)) {
                    CentralStatus::marcarFora();
                    break;
                }

                DB::connection('sqlite_local')->table('caixas_local')
                    ->where('id', $c->id)
                    ->update(['sync_erro' => $e->getMessage(), 'updated_at' => now()]);

                $falhas++;
            }
        }

        return ['sucesso' => true, 'enviados' => $enviados, 'falhas' => $falhas];
    }

        /**
     * Informa ao servidor o último número de NFC-e usado pelo caixa em cada PDV (nunca diminui o contador do servidor).
     */
    public function enviarNumeracao(): array
    {
        if (CentralStatus::fora()) {
            return ['sucesso' => true, 'atualizados' => 0];
        }

        try {
            $atualizados = 0;

            foreach (DB::connection('sqlite_local')->table('numeracao_nfce')->get() as $n) {
                $atualizados += Pdv::where('id', $n->pdv_id)
                    ->where('serie_nfce', $n->serie)
                    ->where('numero_atual_nfce', '<', $n->ultimo_numero)
                    ->update(['numero_atual_nfce' => $n->ultimo_numero]);
            }

            return ['sucesso' => true, 'atualizados' => $atualizados];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    public function enviarInutilizacoes(): array
    {
        if (CentralStatus::fora()) {
            return ['sucesso' => true, 'enviadas' => 0];
        }

        try {
            $db = DB::connection('sqlite_local');
            $enviadas = 0;

            foreach ($db->table('inutilizacoes_local')->where('sync_pendente', true)->get() as $i) {
                // Só o registro: as vendas presas na faixa sobem pelo envio de vendas (com a devolução de estoque)
                Inutilizacao::firstOrCreate([
                    'pdv_id' => $i->pdv_id,
                    'serie' => $i->serie,
                    'numero_inicial' => $i->numero_inicial,
                    'numero_final' => $i->numero_final,
                    'status' => $i->status,
                    'protocolo' => $i->protocolo,
                ], [
                    'justificativa' => $i->justificativa,
                    'motivo' => $i->motivo,
                    'operador_id' => $i->operador_id,
                ]);

                $db->table('inutilizacoes_local')->where('id', $i->id)
                    ->update(['sync_pendente' => false, 'updated_at' => now()]);

                $enviadas++;
            }

            return ['sucesso' => true, 'enviadas' => $enviadas];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    /**
     * Depois de uma venda ou emissão: tenta enviar ao servidor sem atrapalhar o operador se ele estiver fora.
     */
    public function enviarSePossivel(): void
    {
        if (CentralStatus::fora()) {
            return;
        }

        try {
            $this->enviarCaixas();
            $this->enviarVendasPendentes();
            $this->enviarInutilizacoes();
            $this->enviarNumeracao();
        } catch (\Throwable $e) {
            if (CentralStatus::erroDeConexao($e)) {
                CentralStatus::marcarFora();
            }
        }
    }

    /**
     * Direcao 1e: traz do servidor os caixas abertos que esta maquina ainda nao conhece,
     * e fecha localmente os que o servidor ja fechou por outra via.
     */
    public function puxarCaixasAbertos(): array
    {
        try {
            $local = DB::connection('sqlite_local');
            $agora = now();

            $pdvsPermitidos = $this->pdvsDestaMaquina()->pluck('id');

            foreach (Caixa::where('status', 'aberto')->whereIn('pdv_id', $pdvsPermitidos)->get() as $c) {
                if ($local->table('caixas_local')->where('uuid', $c->uuid)->exists()) {
                    continue;
                }

                $local->table('caixas_local')->insert([
                    'uuid' => $c->uuid,
                    'id_central' => $c->id,
                    'operador_id' => $c->operador_id,
                    'pdv_id' => $c->pdv_id,
                    'data_abertura' => $c->data_abertura,
                    'valor_abertura' => $c->valor_abertura,
                    'status' => 'aberto',
                    'sync_pendente' => false,
                    'sincronizado_em' => $agora,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $idsAbertos = $local->table('caixas_local')
                ->where('status', 'aberto')
                ->where('sync_pendente', false)
                ->whereNotNull('id_central')
                ->pluck('id_central');

            if ($idsAbertos->isNotEmpty()) {
                foreach (Caixa::whereIn('id', $idsAbertos)->where('status', '!=', 'aberto')->get() as $c) {
                    $local->table('caixas_local')->where('id_central', $c->id)->update([
                        'status' => $c->status,
                        'data_fechamento' => $c->data_fechamento,
                        'valor_fechamento_informado' => $c->valor_fechamento_informado,
                        'valor_fechamento_esperado' => $c->valor_fechamento_esperado,
                        'observacao' => $c->observacao,
                        'updated_at' => $agora,
                    ]);
                }
            }

            return ['sucesso' => true];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    // Os dois sentidos dos caixas: primeiro sobe o que é local, depois traz o que falta
    public function sincronizarCaixas(): array
    {
        $envio = $this->enviarCaixas();
        $pull = CentralStatus::fora()
            ? ['sucesso' => false, 'erro' => 'Servidor indisponível']
            : $this->puxarCaixasAbertos();

        return [
            'sucesso' => $envio['sucesso'] && $pull['sucesso'],
            'erro' => $pull['erro'] ?? null,
            'enviados' => $envio['enviados'],
        ];
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
                        'email' => $cliente->email,
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
     * Direcao 1g: espelha no SQLite os pedidos de venda FINALIZADOS (os que ainda podem ser emitidos no caixa).
     * Troca o espelho inteiro numa transacao: pedido faturado/cancelado no servidor sai do caixa.
     * Pedido que ja virou venda aqui no caixa (e ainda nao subiu) nao volta para a lista.
     */
    public function puxarPedidosVenda(): array
    {
        try {
            $local = DB::connection('sqlite_local');

            $pedidos = PedidoVenda::with(['itens', 'cliente', 'operador'])
                ->where('status', 'finalizado')
                ->get();

            $emUsoLocal = $local->table('vendas_pendentes')
                ->whereNotNull('pedido_venda_id')
                ->where('status_fiscal', '!=', 'cancelada')
                ->pluck('pedido_venda_id')
                ->all();

            $agora = now();
            $linhasPedidos = [];
            $linhasItens = [];

            foreach ($pedidos as $p) {
                if (in_array($p->id, $emUsoLocal, true)) {
                    continue;
                }

                $linhasPedidos[] = [
                    'id' => $p->id,
                    'numero' => $p->numero,
                    'data_pedido' => $p->data_pedido->format('Y-m-d'),
                    'previsao_entrega' => $p->previsao_entrega?->format('Y-m-d'),
                    'operador_id' => $p->operador_id,
                    'operador_nome' => $p->operador?->name,
                    'cliente_id' => $p->cliente_id,
                    'cliente_nome' => $p->cliente?->nome,
                    'valor_total' => $p->valor_total,
                    'descricao' => $p->descricao,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];

                foreach ($p->itens as $i) {
                    $linhasItens[] = [
                        'id' => $i->id,
                        'pedido_venda_id' => $p->id,
                        'produto_id' => $i->produto_id,
                        'produto_variante_id' => $i->produto_variante_id,
                        'codigo_barras' => $i->codigo_barras,
                        'descricao' => $i->descricao,
                        'unidade' => $i->unidade,
                        'quantidade' => $i->quantidade,
                        'valor_unitario' => $i->valor_unitario,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
            }

            $local->transaction(function () use ($local, $linhasPedidos, $linhasItens) {
                $local->table('pedido_venda_itens_cache')->delete();
                $local->table('pedidos_venda_cache')->delete();

                // SQLite limita o número de parâmetros por comando: insere em blocos
                foreach (array_chunk($linhasPedidos, 100) as $bloco) {
                    $local->table('pedidos_venda_cache')->insert($bloco);
                }
                foreach (array_chunk($linhasItens, 50) as $bloco) {
                    $local->table('pedido_venda_itens_cache')->insert($bloco);
                }
            });

            $this->salvarMeta('ultima_sincronizacao_pedidos_venda', now()->toDateTimeString());

            return ['sucesso' => true, 'pedidos_atualizados' => count($linhasPedidos)];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    /**
     * Direcao 1h: traz do servidor a situacao fiscal das vendas que o SERVIDOR emitiu/cancelou
     * (emissao pelo servidor, modo de emergencia ou versoes antigas) e que no caixa ficaram "pendente".
     * Sem isso o caixa nao sabe que a venda tem NFC-e e nao consegue reimprimir o cupom.
     * So estados finais (emitida/cancelada): contingencia continua sendo tratada por quem a emitiu.
     */
    public function puxarSituacaoFiscal(): array
    {
        try {
            $local = DB::connection('sqlite_local');

            $candidatas = $local->table('vendas_pendentes')
                ->where('status_fiscal', 'pendente')
                ->whereNull('numero_nfce')
                ->where('status', 'sincronizada')
                ->where('vendida_em', '>=', now()->subDays(90)->toDateTimeString())
                ->pluck('uuid')
                ->all();

            $atualizadas = 0;

            foreach (array_chunk($candidatas, 200) as $bloco) {
                $centrais = DB::table('vendas')
                    ->whereIn('uuid', $bloco)
                    ->whereIn('status', ['emitida', 'cancelada'])
                    ->whereNotNull('numero_nfce')
                    ->get();

                foreach ($centrais as $c) {
                    $campos = [
                        'status_fiscal' => $c->status,
                        'numero_nfce' => $c->numero_nfce,
                        'serie_nfce' => $c->serie_nfce,
                        'chave_nfe' => $c->chave_nfe,
                        'protocolo_nfe' => $c->protocolo_nfe,
                        'tp_emis' => $c->tp_emis ?? 1,
                        'emitida_em' => $c->emitida_em,
                        'fiscal_sync_pendente' => false, // veio do servidor: nao precisa subir de volta
                        'updated_at' => now(),
                    ];

                    if ($c->status === 'cancelada') {
                        $campos['motivo_cancelamento'] = $c->motivo_cancelamento ?? null;
                    }

                    $arquivo = $this->guardarXmlDoCentral($c);
                    if ($arquivo) {
                        $campos['ultimo_arquivo_xml'] = $arquivo;
                    }

                    $local->table('vendas_pendentes')->where('uuid', $c->uuid)->update($campos);
                    $atualizadas++;
                }
            }

            return ['sucesso' => true, 'atualizadas' => $atualizadas];
        } catch (\Throwable $e) {
            return $this->registrarFalha($e);
        }
    }

    /**
     * Grava o XML autorizado vindo do servidor na mesma pasta/convencao do emissor local
     * (storage/app/XML_nfce/aa/mm/dd/{chave}-nfe.xml). Devolve o caminho, ou null se o servidor nao tem o XML.
     */
    private function guardarXmlDoCentral(object $c): ?string
    {
        $chave = (string) ($c->chave_nfe ?? '');

        if ($chave === '') {
            return null;
        }

        $referencia = $c->emitida_em ? \Carbon\Carbon::parse($c->emitida_em) : now();
        $pasta = storage_path('app/XML_nfce/' . $referencia->format('y/m/d'));
        $arquivo = $pasta . '/' . $chave . '-nfe.xml';

        if (is_file($arquivo)) {
            return $arquivo;
        }

        $xml = $c->xml_nfce ?? null;

        // Servidor e caixa na mesma maquina: o arquivo gravado pelo emissor do servidor ja esta ao alcance
        if (!$xml && !empty($c->ultimo_arquivo_xml) && is_file($c->ultimo_arquivo_xml)) {
            $xml = file_get_contents($c->ultimo_arquivo_xml);
        }

        if (!$xml) {
            return null;
        }

        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        file_put_contents($arquivo, $xml);

        return $arquivo;
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
            ->where(function ($q) {
                $q->whereIn('status', ['pendente_sync', 'erro_sync'])
                    ->orWhere('fiscal_sync_pendente', true);
            })
            ->where('status', '!=', 'cancelada')
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

                        $caixaId = $vendaLocal->caixa_id_central
                            ?? Caixa::where('uuid', $vendaLocal->caixa_uuid)->value('id');

                        if (!$caixaId) {
                            throw new Exception('O caixa desta venda ainda não foi sincronizado com o servidor.');
                        }

                        $vendaId = DB::table('vendas')->insertGetId([
                            'uuid' => $vendaLocal->uuid,
                            'caixa_id' => $caixaId,
                            'operador_id' => $vendaLocal->operador_id_central,
                            'cliente_id' => $vendaLocal->cliente_id,
                            'pedido_venda_id' => $vendaLocal->pedido_venda_id ?? null,
                            'cpf_na_nota' => $vendaLocal->cpf_na_nota,
                            'total' => $vendaLocal->total,
                            'troco' => $vendaLocal->troco,
                            'desconto' => $vendaLocal->desconto,
                            'forma_pagamento' => $vendaLocal->forma_pagamento,
                            'created_at' => $vendaLocal->vendida_em,
                            'updated_at' => now(),
                        ] + $this->camposFiscaisParaCentral($vendaLocal, true));

                        // [MOVIMENTAÇÃO] baixa do estoque central + histórico, pelo EstoqueService
                        // (idempotente pelo uuid). Venda que já chega cancelada nunca baixou estoque.
                        if ($vendaLocal->status_fiscal !== 'cancelada') {
                            app(EstoqueService::class)->baixarVenda($vendaLocal->uuid, $itens ?? [], [
                                'user_id'          => $vendaLocal->operador_id_central,
                                'data'             => $vendaLocal->vendida_em,
                                'documento_rotulo' => "Venda #{$vendaId}",
                                'tocar_updated_at' => false,
                            ]);
                        }

                        foreach ($itens as $item) {
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
                            ] + $this->camposImpostosItem($item));
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
                } elseif ($vendaLocal->fiscal_sync_pendente) {
                    DB::transaction(function () use ($vendaLocal) {
                        // A venda já está no servidor: só atualiza a situação fiscal (nunca por cima de uma cancelada)
                        $afetadas = DB::table('vendas')
                            ->where('uuid', $vendaLocal->uuid)
                            ->where('status', '!=', 'cancelada')
                            ->update($this->camposFiscaisParaCentral($vendaLocal, false) + ['updated_at' => now()]);

                        // Impostos dos itens calculados depois da inclusão da venda (emissão no caixa)
                        $this->atualizarImpostosItens($vendaLocal);

                        // Cancelada no caixa: devolve o estoque que o servidor baixou. Só na transição, para nunca devolver duas vezes.
                        if ($afetadas && $vendaLocal->status_fiscal === 'cancelada') {
                            // [MOVIMENTAÇÃO] devolução pelo EstoqueService (registra no histórico e não devolve duas vezes)
                            $vendaId = DB::table('vendas')->where('uuid', $vendaLocal->uuid)->value('id');

                            app(EstoqueService::class)->devolverVenda(
                                $vendaLocal->uuid,
                                json_decode($vendaLocal->itens, true) ?? [],
                                [
                                    'documento_rotulo' => "Venda #{$vendaId}",
                                    'observacao'       => 'Venda cancelada no caixa',
                                    'tocar_updated_at' => false,
                                ]
                            );
                        }
                    });
                }

                // Venda nascida de um pedido: o pedido passa a "emitido" no servidor (idempotente)
                if (!empty($vendaLocal->pedido_venda_id) && $vendaLocal->status_fiscal !== 'cancelada') {
                    DB::table('pedidos_venda')
                        ->where('id', $vendaLocal->pedido_venda_id)
                        ->where('status', '!=', 'emitido')
                        ->update(['status' => 'emitido', 'updated_at' => now()]);
                }

                DB::connection('sqlite_local')->table('vendas_pendentes')
                    ->where('id', $vendaLocal->id)
                    ->update([
                        'status' => 'sincronizada',
                        'sincronizada_em' => now(),
                        'fiscal_sync_pendente' => false,
                        'erro_sync_mensagem' => null,
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
                        // Limitar a mensagem de erro para não estourar o campo no SQLite (500 caracteres)
                        'erro_sync_mensagem' => mb_substr($e->getMessage(), 0, 500),
                        'updated_at' => now(),
                    ]);

                $falhas++;
            }
        }

        return ['sucesso' => true, 'enviadas' => $enviadas, 'falhas' => $falhas];
    }

    // Impostos do item (gravados pelo emissor no JSON) no formato das colunas de venda_itens
    private function camposImpostosItem(array $item): array
    {
        $i = $item['impostos'] ?? null;

        if (!$i) {
            return [];
        }

        return [
            'descricao' => $i['descricao'] ?? null,
            'codigo_produto' => $i['codigo_produto'] ?? null,
            'ncm' => $i['ncm'] ?? null,
            'cfop' => $i['cfop'] ?? null,
            'cst_csosn' => $i['cst_csosn'] ?? null,
            'valor_bruto' => $i['valor_bruto'] ?? null,
            'desconto_efetivo' => $i['desconto_efetivo'] ?? 0,
            'bc_icms' => $i['bc_icms'] ?? 0,
            'aliquota_icms' => $i['aliquota_icms'] ?? 0,
            'valor_icms' => $i['valor_icms'] ?? 0,
            'valor_pis' => $i['valor_pis'] ?? 0,
            'valor_cofins' => $i['valor_cofins'] ?? 0,
            'valor_ibs' => $i['valor_ibs'] ?? 0,
            'valor_cbs' => $i['valor_cbs'] ?? 0,
            'custo_unitario' => $i['custo_unitario'] ?? null,
            'valor_custo' => $i['valor_custo'] ?? null,
            'impostos_registrados_em' => now(),
        ];
    }

    // Venda já no servidor: copia os impostos do JSON para as linhas de venda_itens (mesma ordem da inclusão)
    private function atualizarImpostosItens(object $vendaLocal): void
    {
        $itens = json_decode($vendaLocal->itens, true) ?? [];
        $vendaId = DB::table('vendas')->where('uuid', $vendaLocal->uuid)->value('id');

        if (!$vendaId || !collect($itens)->contains(fn ($i) => !empty($i['impostos']))) {
            return;
        }

        $ids = DB::table('venda_itens')->where('venda_id', $vendaId)->orderBy('id')->pluck('id')->values();

        foreach ($itens as $posicao => $item) {
            if (isset($ids[$posicao]) && !empty($item['impostos'])) {
                DB::table('venda_itens')->where('id', $ids[$posicao])
                    ->update($this->camposImpostosItem($item) + ['updated_at' => now()]);
            }
        }
    }

    // Situação fiscal da venda no formato da tabela vendas do servidor
    private function camposFiscaisParaCentral(object $v, bool $inclusao): array
    {
        $xml = null;

        if (in_array($v->status_fiscal, ['emitida', 'cancelada'], true) && $v->ultimo_arquivo_xml && is_file($v->ultimo_arquivo_xml)) {
            $xml = file_get_contents($v->ultimo_arquivo_xml);
        }

        $campos = [
            'status' => match ($v->status_fiscal) {
                'emitida' => 'emitida',
                'cancelada' => 'cancelada',
                'contingencia' => 'contingencia',
                default => 'pendente',
            },
            'numero_nfce' => $v->numero_nfce,
            'serie_nfce' => $v->serie_nfce,
            'chave_nfe' => $v->chave_nfe,
            'protocolo_nfe' => $v->protocolo_nfe,
            'tp_emis' => $v->tp_emis,
            'dh_cont' => $v->dh_cont,
            'x_just' => $v->x_just,
            'xml_contingencia' => $v->xml_contingencia,
            'motivo_rejeicao' => $v->motivo_rejeicao,
            'emitida_em' => $v->emitida_em,
            'xml_nfce' => $xml,
        ];

        if ($v->status_fiscal === 'cancelada') {
            $campos['motivo_cancelamento'] = $v->motivo_cancelamento ?? null;
        }

        // tp_emis é obrigatório no servidor: a emissão normal é tpEmis 1
        if ($campos['tp_emis'] === null && in_array($v->status_fiscal, ['emitida', 'cancelada'], true)) {
            $campos['tp_emis'] = 1;
        }

        // Na inclusão, o que está vazio fica de fora e o servidor usa o padrão da coluna
        if ($inclusao) {
            return array_filter($campos, fn ($valor) => $valor !== null);
        }

        // Na atualização, nunca manda tp_emis nulo
        if ($campos['tp_emis'] === null) {
            unset($campos['tp_emis']);
        }

        return $campos;
    }

    /**
     * Roda os dois sentidos de uma vez. Chamado pelo scheduler ou manualmente.
     */
    public function sincronizarTudo(): array
    {
        $resultado = [];

        $passos = [
            'caixas'   => 'sincronizarCaixas',
            'empresa'  => 'puxarEmpresa',
            'catalogo' => 'puxarCatalogo',
            'clientes' => 'puxarClientes',
            'usuarios' => 'puxarUsuarios',
            'pdvs'     => 'puxarPdvs',
            'pedidos'  => 'puxarPedidosVenda',

        ];

        foreach ($passos as $chave => $metodo) {
            $resultado[$chave] = CentralStatus::fora()
                ? ['sucesso' => false, 'erro' => 'Servidor indisponível']
                : $this->{$metodo}();
        }

        $resultado['vendas'] = $this->enviarVendasPendentes();
        $resultado['situacao_fiscal'] = CentralStatus::fora()
            ? ['sucesso' => false, 'erro' => 'Servidor indisponível']
            : $this->puxarSituacaoFiscal();
        $resultado['inutilizacoes'] = $this->enviarInutilizacoes();
        $resultado['numeracao'] = $this->enviarNumeracao();

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