<?php

namespace App\Services;

use App\Models\ComandaPedido;
use App\Models\IfoodConfiguracao;
use App\Models\IfoodPedido;
use App\Models\IfoodProduto;
use App\Models\Produto;
use App\Models\ProdutoVariante;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Integração com a API do iFood (Merchant API), aplicativo distribuído:
 *  - autenticação: o restaurante autoriza o app com um código (userCode) no Portal do Parceiro e cola aqui o
 *    código de autorização; o token é renovado sozinho com o refresh token. (Centralizado: client_credentials.)
 *  - cardápio: importa catálogos/categorias/itens e liga cada item a um produto do sistema;
 *  - pedidos: polling dos eventos a cada 30 s (o servidor só faz chamadas de saída: funciona sem IP público),
 *    cria o pedido na Lista de Pedidos e confirma o recebimento (acknowledgment);
 *  - ações: aceitar, pronto (retirada) / despachar (entrega), cancelar com motivo.
 */
class IfoodService
{
    public const URL = 'https://merchant-api.ifood.com.br';
    public const URL_IMAGEM = 'https://static-images.ifood.com.br/image/upload/t_high/pratos/';

    /** Intervalo do polling exigido pelo iFood. */
    public const INTERVALO_POLLING = 30;

    public function __construct(private ImpressaoComandaService $impressao)
    {
    }

    // =====================================================================
    // Autenticação
    // =====================================================================

    /** 1º passo do vínculo (app distribuído): gera o código que o restaurante digita no Portal do Parceiro. */
    public function gerarCodigoVinculo(IfoodConfiguracao $cfg): array
    {
        $this->exigirCredenciais($cfg, false);

        $resp = $this->http()->asForm()->post('/authentication/v1.0/oauth/userCode', ['clientId' => $cfg->client_id]);
        if ($resp->status() === 400 && str_contains(strtolower($resp->body()), 'grant type not authorized')) {
            throw new RuntimeException('Este aplicativo foi criado no iFood como CENTRALIZADO, por isso não aceita o vínculo por código. '
                . 'Para testar agora: em "Tipo do aplicativo" escolha "Centralizado", salve e clique em "Testar conexão". '
                . 'Para vender para vários restaurantes, crie no Portal do Desenvolvedor um aplicativo do tipo DISTRIBUÍDO e use as credenciais dele.');
        }
        $dados = $this->json($resp, 'gerar o código de vínculo');

        $cfg->forceFill([
            'user_code'           => $dados['userCode'] ?? null,
            'code_verifier'       => $dados['authorizationCodeVerifier'] ?? null,
            'verification_url'    => $dados['verificationUrlComplete'] ?? ($dados['verificationUrl'] ?? null),
            'user_code_expira_em' => now()->addSeconds((int) ($dados['expiresIn'] ?? 600)),
        ])->save();

        return [
            'codigo'     => $cfg->user_code,
            'url'        => $cfg->verification_url,
            'expira_em'  => $cfg->user_code_expira_em->toIso8601String(),
        ];
    }

    /** 2º passo: troca o código de autorização (mostrado pelo iFood depois de autorizar) pelos tokens. */
    public function concluirVinculo(IfoodConfiguracao $cfg, string $codigoAutorizacao): void
    {
        $this->exigirCredenciais($cfg, false);
        if (blank($cfg->code_verifier)) {
            throw new RuntimeException('Gere o código de vínculo primeiro.');
        }

        $this->pedirToken($cfg, [
            'grantType'                 => 'authorization_code',
            'authorizationCode'         => trim($codigoAutorizacao),
            'authorizationCodeVerifier' => $cfg->code_verifier,
        ]);

        $cfg->forceFill(['user_code' => null, 'code_verifier' => null, 'verification_url' => null, 'user_code_expira_em' => null])->save();
    }

    public function desvincular(IfoodConfiguracao $cfg): void
    {
        $cfg->forceFill([
            'access_token' => null, 'refresh_token' => null, 'token_expira_em' => null,
            'user_code' => null, 'code_verifier' => null, 'verification_url' => null, 'user_code_expira_em' => null,
        ])->save();
    }

    /** Token válido (renova antes de vencer). */
    public function token(IfoodConfiguracao $cfg, bool $forcarRenovacao = false): string
    {
        if (!$forcarRenovacao && filled($cfg->access_token) && $cfg->token_expira_em?->gt(now()->addMinutes(2))) {
            return $cfg->access_token;
        }

        if ($cfg->modo_autenticacao === IfoodConfiguracao::CENTRALIZADO) {
            $this->exigirCredenciais($cfg, false);
            $this->pedirToken($cfg, ['grantType' => 'client_credentials']);

            return $cfg->access_token;
        }

        if (blank($cfg->refresh_token)) {
            throw new RuntimeException('A loja ainda não foi vinculada ao iFood. Em Cadastros → iFood, clique em "Vincular loja".');
        }

        try {
            $this->pedirToken($cfg, ['grantType' => 'refresh_token', 'refreshToken' => $cfg->refresh_token]);
        } catch (RuntimeException $e) {
            // Refresh vencido ou revogado: precisa vincular de novo
            $cfg->forceFill(['access_token' => null, 'refresh_token' => null, 'token_expira_em' => null])->save();
            throw new RuntimeException('A autorização do iFood venceu. Vincule a loja de novo em Cadastros → iFood. (' . $e->getMessage() . ')');
        }

        return $cfg->access_token;
    }

    private function pedirToken(IfoodConfiguracao $cfg, array $campos): void
    {
        $resp = $this->http()->asForm()->post('/authentication/v1.0/oauth/token', $campos + [
            'clientId'     => $cfg->client_id,
            'clientSecret' => $cfg->client_secret,
        ]);
        $dados = $this->json($resp, 'obter o token de acesso');

        if (blank($dados['accessToken'] ?? null)) {
            throw new RuntimeException('O iFood não devolveu o token de acesso.');
        }

        $cfg->forceFill([
            'access_token'    => $dados['accessToken'],
            'refresh_token'   => $dados['refreshToken'] ?? $cfg->refresh_token,
            'token_expira_em' => now()->addSeconds((int) ($dados['expiresIn'] ?? 21600)),
        ])->save();
    }

    // =====================================================================
    // Loja
    // =====================================================================

    /** Confere o acesso: lojas autorizadas e situação da loja configurada. */
    public function testarConexao(IfoodConfiguracao $cfg): array
    {
        $this->exigirCredenciais($cfg);
        $lojas = collect($this->chamar($cfg, 'get', '/merchant/v1.0/merchants') ?? []);
        $loja = $lojas->firstWhere('id', $cfg->merchant_id);

        if (!$loja) {
            $ids = $lojas->map(fn ($l) => ($l['name'] ?? '?') . ' (' . ($l['id'] ?? '?') . ')')->implode(', ');
            throw new RuntimeException('A loja ' . $cfg->merchant_id . ' não está entre as autorizadas para este aplicativo.'
                . ($ids ? " Lojas autorizadas: {$ids}." : ' Nenhuma loja autorizada ainda.'));
        }

        $situacao = null;
        try {
            $situacao = $this->chamar($cfg, 'get', "/merchant/v1.0/merchants/{$cfg->merchant_id}/status");
        } catch (Throwable) {
            // situação é informativa
        }

        $estado = collect(is_array($situacao) && array_is_list($situacao) ? $situacao : [$situacao])
            ->filter()->map(fn ($s) => ($s['operation'] ?? '') . ': ' . ($s['message']['title'] ?? $s['state'] ?? ''))->implode(' · ');

        return ['loja' => $loja['name'] ?? $cfg->merchant_id, 'situacao' => $estado ?: null];
    }

    // =====================================================================
    // Cardápio
    // =====================================================================

    /** Importa os itens de todos os catálogos da loja. Mantém a ligação com o produto e o preço mudado no sistema. */
    public function importarCardapio(IfoodConfiguracao $cfg): array
    {
        $this->exigirCredenciais($cfg);
        $m = $cfg->merchant_id;
        $catalogos = collect($this->chamar($cfg, 'get', "/catalog/v2.0/merchants/{$m}/catalogs") ?? []);

        if ($catalogos->isEmpty()) {
            throw new RuntimeException('A loja não tem cardápio no iFood.');
        }

        $vistos = [];
        $novos = 0;
        $atualizados = 0;

        foreach ($catalogos as $catalogo) {
            $catalogoId = $catalogo['catalogId'] ?? $catalogo['id'] ?? null;
            if (!$catalogoId) {
                continue;
            }

            $categorias = $this->chamar($cfg, 'get', "/catalog/v2.0/merchants/{$m}/catalogs/{$catalogoId}/categories", ['includeItems' => 'true']) ?? [];

            foreach ($categorias as $categoria) {
                foreach ($categoria['items'] ?? [] as $item) {
                    $itemId = $item['id'] ?? null;
                    if (!$itemId || isset($vistos[$itemId])) {
                        continue;
                    }
                    $vistos[$itemId] = true;

                    $precoIfood = round((float) data_get($item, 'price.value', data_get($item, 'price', 0)), 2);
                    $imagem = $item['imagePath'] ?? null;
                    $existente = IfoodProduto::where('ifood_item_id', $itemId)->first();

                    $dados = [
                        'ifood_product_id' => $item['productId'] ?? null,
                        'catalogo_id'      => $catalogoId,
                        'categoria'        => $categoria['name'] ?? null,
                        'nome'             => mb_substr((string) ($item['name'] ?? 'Item sem nome'), 0, 255),
                        'descricao'        => $item['description'] ?? null,
                        'codigo_externo'   => filled($item['externalCode'] ?? null) ? mb_substr((string) $item['externalCode'], 0, 60) : null,
                        'ean'              => filled($item['ean'] ?? null) ? mb_substr((string) $item['ean'], 0, 20) : null,
                        'preco_ifood'      => $precoIfood,
                        'imagem_url'       => $imagem ? (str_starts_with($imagem, 'http') ? $imagem : self::URL_IMAGEM . ltrim($imagem, '/')) : null,
                        'status'           => $item['status'] ?? 'AVAILABLE',
                        'importado_em'     => now(),
                    ];

                    if ($existente) {
                        // Preço do sistema acompanha o do iFood, a não ser que o operador tenha mudado
                        if ((float) $existente->preco === (float) $existente->preco_ifood) {
                            $dados['preco'] = $precoIfood;
                        }
                        $existente->update($dados);
                        $atualizados++;
                    } else {
                        IfoodProduto::create($dados + ['ifood_item_id' => $itemId, 'preco' => $precoIfood]);
                        $novos++;
                    }
                }
            }
        }

        // Itens que saíram do cardápio do iFood: tira da lista (o produto do sistema ligado a ele continua no cadastro).
        // Só com a importação completa e com itens: uma resposta vazia não apaga tudo.
        $removidos = collect();
        if ($vistos) {
            $removidos = IfoodProduto::whereNotIn('ifood_item_id', array_keys($vistos))->get();
            IfoodProduto::whereIn('id', $removidos->pluck('id'))->delete();
        }

        $assimilados = $this->assimilarAutomatico();
        $cfg->forceFill(['cardapio_importado_em' => now()])->save();

        return [
            'novos'       => $novos,
            'atualizados' => $atualizados,
            'removidos'   => $removidos->pluck('nome')->all(),
            'assimilados' => $assimilados,
            'total'       => count($vistos),
        ];
    }

    /**
     * Liga sozinho os itens do iFood sem produto: pelo código de barras (EAN), pelo código externo
     * (código interno / código de barras / id do produto) ou pelo nome igual (quando só um produto tem esse nome).
     */
    public function assimilarAutomatico(): int
    {
        $ligados = 0;

        foreach (IfoodProduto::whereNull('produto_id')->get() as $item) {
            [$produtoId, $varianteId] = $this->encontrarProduto($item);
            if ($produtoId) {
                $item->update(['produto_id' => $produtoId, 'produto_variante_id' => $varianteId]);
                $ligados++;
            }
        }

        return $ligados;
    }

    /**
     * Só liga com evidência forte, para não juntar produtos diferentes por coincidência de código curto:
     *  1) código de barras de verdade (EAN/GTIN válido, do iFood ou no código externo) igual ao de um produto/variação;
     *  2) código externo igual ao código interno do produto E nome parecido (alguma palavra em comum);
     *  3) nome igual a um único produto.
     */
    private function encontrarProduto(IfoodProduto $item): array
    {
        foreach (array_unique(array_filter([$item->ean, $item->codigo_externo], fn ($c) => $this->eanValido($c))) as $gtin) {
            $variante = ProdutoVariante::where('codigo_barras', $gtin)->whereHas('produto', fn ($p) => $p->ativos())->first();
            if ($variante) {
                return [$variante->produto_id, $variante->id];
            }

            $produto = Produto::ativos()->where('tem_variacao', false)->where('codigo_barras', $gtin)->first();
            if ($produto) {
                return [$produto->id, null];
            }
        }

        if (filled($item->codigo_externo)) {
            $produto = Produto::ativos()->where('tem_variacao', false)->where('codigo_interno', trim($item->codigo_externo))->first();
            if ($produto && $this->nomesParecidos($item->nome, $produto->nome)) {
                return [$produto->id, null];
            }
        }

        $porNome = Produto::ativos()->where('tem_variacao', false)->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($item->nome))])->limit(2)->pluck('id');

        return $porNome->count() === 1 ? [$porNome->first(), null] : [null, null];
    }

    /** Alguma palavra significativa (3+ letras, sem acento) em comum entre os dois nomes. */
    private function nomesParecidos(string $a, string $b): bool
    {
        $palavras = fn (string $t) => collect(preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($t))))
            ->filter(fn ($p) => strlen($p) >= 3)->unique();

        return $palavras($a)->intersect($palavras($b))->isNotEmpty();
    }

    /** Campos fiscais copiados do produto modelo no cadastro automático. */
    public const CAMPOS_FISCAIS = [
        'ncm_id', 'cest_id', 'class_trib_ibs_cbs_id', 'pis_cofins_id', 'ipi_id', 'tributacao_id',
        'unidade_comercial', 'unidade_tributavel', 'origem_mercadoria', 'tipo_item', 'nat_rec_pis_cofins',
        'categoria_id', 'grupo_id',
    ];

    /** Modelo fiscal de cada categoria do iFood (sem modelo próprio, vale o padrão). */
    public function modeloDaCategoria(?string $categoria, ?Produto $padrao, $porCategoria): ?Produto
    {
        return ($categoria !== null ? $porCategoria->get($categoria)?->modelo : null) ?? $padrao;
    }

    /**
     * Cadastra no sistema os itens do iFood que ainda não têm produto: nome, descrição, preço e código de
     * barras (EAN, se válido) vêm do iFood; a parte fiscal vem do produto modelo da categoria.
     *
     * @param  array<int>|null  $ids  itens do iFood (null = todos sem produto)
     * @return array{criados: int, sem_modelo: array<int, string>, erros: array<int, string>}
     */
    public function cadastrarAutomatico(?array $ids = null): array
    {
        $cfg = IfoodConfiguracao::atual();
        $padrao = $cfg->produtoModelo;
        $porCategoria = \App\Models\IfoodCategoriaFiscal::with('modelo')->get()->keyBy('categoria');
        $resultado = ['criados' => 0, 'sem_modelo' => [], 'erros' => []];
        $produtos = app(\App\Http\Controllers\ProdutoController::class);

        $itens = IfoodProduto::whereNull('produto_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('categoria')->orderBy('nome')->get();

        foreach ($itens as $item) {
            $modelo = $this->modeloDaCategoria($item->categoria, $padrao, $porCategoria);
            if (!$modelo) {
                $resultado['sem_modelo'][] = $item->nome;
                continue;
            }

            $dados = $modelo->only(self::CAMPOS_FISCAIS) + [
                'nome'        => mb_substr($item->nome, 0, 255),
                'descricao'   => $item->descricao,
                'preco_venda' => (float) $item->preco,
                'estoque'     => 0,
                'ativo'       => '1',
            ];
            $dados['codigo_barras'] = $this->eanValido($item->ean) && !Produto::where('codigo_barras', $item->ean)->exists()
                && !ProdutoVariante::where('codigo_barras', $item->ean)->exists() ? $item->ean : '';

            try {
                $produto = DB::transaction(function () use ($produtos, $dados, $item) {
                    try {
                        $produto = $produtos->criarAPartirDeDados($dados);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        // EAN do iFood recusado pela validação: cadastra com o código gerado pelo sistema
                        if ($dados['codigo_barras'] === '' || !array_key_exists('codigo_barras', $e->errors())) {
                            throw $e;
                        }
                        $produto = $produtos->criarAPartirDeDados(['codigo_barras' => ''] + $dados);
                    }
                    $item->update(['produto_id' => $produto->id, 'produto_variante_id' => null, 'cadastrado_automaticamente' => true]);

                    return $produto;
                });
                $resultado['criados']++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $resultado['erros'][] = "{$item->nome}: " . collect($e->errors())->flatten()->first();
            } catch (Throwable $e) {
                $resultado['erros'][] = "{$item->nome}: {$e->getMessage()}";
            }
        }

        return $resultado;
    }

    private function eanValido(?string $ean): bool
    {
        if (!$ean || !ctype_digit($ean) || !in_array(strlen($ean), [8, 12, 13, 14], true)) {
            return false;
        }
        $digitos = array_map('intval', str_split(str_pad($ean, 14, '0', STR_PAD_LEFT)));
        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += $digitos[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $soma % 10) % 10 === $digitos[13];
    }

    /** Envia o preço do sistema para o item no iFood. */
    public function enviarPreco(IfoodConfiguracao $cfg, IfoodProduto $item): void
    {
        $this->exigirCredenciais($cfg);
        $this->chamar($cfg, 'patch', "/catalog/v2.0/merchants/{$cfg->merchant_id}/items/price", [
            'itemId' => $item->ifood_item_id,
            'price'  => ['value' => (float) $item->preco],
        ]);
        $item->update(['preco_ifood' => $item->preco]);
    }

    // =====================================================================
    // Pedidos: polling
    // =====================================================================

    /**
     * Busca os eventos novos e processa (pedido novo, confirmado, pronto, concluído, cancelado).
     * Só confirma o recebimento (acknowledgment) dos eventos processados sem erro: os outros voltam no próximo polling.
     *
     * @return array{eventos: int, pedidos_novos: int, erros: array<int, string>}
     */
    public function polling(?IfoodConfiguracao $cfg = null, bool $forcar = false): array
    {
        $cfg ??= IfoodConfiguracao::atual();
        $resultado = ['eventos' => 0, 'pedidos_novos' => 0, 'erros' => []];

        if (!$cfg->ativo || !$cfg->credenciaisPreenchidas()) {
            return $resultado;
        }
        // Uma tentativa a cada 30 s, mesmo quando a anterior deu erro (não martela o iFood com a tela aberta)
        if (!$forcar && (Cache::has('ifood-polling-tentativa')
            || $cfg->ultimo_polling_em?->gt(now()->subSeconds(self::INTERVALO_POLLING - 2)))) {
            return $resultado;
        }
        Cache::put('ifood-polling-tentativa', true, self::INTERVALO_POLLING - 2);

        $trava = Cache::lock('ifood-polling', 60);
        if (!$trava->get()) {
            return $resultado; // outro polling rodando
        }

        try {
            $resp = $this->http()->withToken($this->token($cfg))
                ->withHeaders(['x-polling-merchants' => $cfg->merchant_id])
                ->get('/order/v1.0/events:polling');

            if ($resp->status() === 401) {
                $resp = $this->http()->withToken($this->token($cfg, true))
                    ->withHeaders(['x-polling-merchants' => $cfg->merchant_id])
                    ->get('/order/v1.0/events:polling');
            }

            $eventos = $resp->status() === 204 ? [] : ($this->json($resp, 'buscar os pedidos') ?? []);
            $eventos = collect($eventos)->sortBy('createdAt')->values();
            $confirmar = [];

            foreach ($eventos as $evento) {
                try {
                    if ($this->processarEvento($cfg, $evento)) {
                        $resultado['pedidos_novos']++;
                    }
                    $confirmar[] = ['id' => $evento['id']];
                } catch (Throwable $e) {
                    $resultado['erros'][] = 'Pedido ' . ($evento['orderId'] ?? '?') . ': ' . $e->getMessage();
                    Log::warning('iFood: falha ao processar evento', ['evento' => $evento, 'erro' => $e->getMessage()]);
                }
            }

            if ($confirmar) {
                $this->chamar($cfg, 'post', '/order/v1.0/events/acknowledgment', $confirmar);
            }

            $resultado['eventos'] = $eventos->count();
            $cfg->forceFill([
                'ultimo_polling_em' => now(),
                'ultimo_erro'       => $resultado['erros'] ? mb_substr(implode(' | ', $resultado['erros']), 0, 500) : null,
                'ultimo_erro_em'    => $resultado['erros'] ? now() : $cfg->ultimo_erro_em,
            ])->save();
        } catch (Throwable $e) {
            $cfg->forceFill(['ultimo_erro' => mb_substr($e->getMessage(), 0, 500), 'ultimo_erro_em' => now()])->save();
            $resultado['erros'][] = $e->getMessage();
        } finally {
            $trava->release();
        }

        return $resultado;
    }

    /** @return bool true quando criou um pedido novo */
    private function processarEvento(IfoodConfiguracao $cfg, array $evento): bool
    {
        $orderId = $evento['orderId'] ?? null;
        if (!$orderId) {
            return false;
        }

        $codigo = strtoupper((string) ($evento['fullCode'] ?? $evento['code'] ?? ''));
        $codigo = match ($codigo) {
            'PLC' => 'PLACED', 'CFM' => 'CONFIRMED', 'RTP' => 'READY_TO_PICKUP', 'DSP' => 'DISPATCHED',
            'CON' => 'CONCLUDED', 'CAN' => 'CANCELLED', 'CAR' => 'CANCELLATION_REQUESTED',
            'CARF' => 'CANCELLATION_REQUEST_FAILED', 'HSD' => 'HANDSHAKE_DISPUTE', 'HSS' => 'HANDSHAKE_SETTLEMENT',
            'CCR' => 'CONSUMER_CANCELLATION_REQUESTED', 'CCA' => 'CONSUMER_CANCELLATION_ACCEPTED',
            'CCD' => 'CONSUMER_CANCELLATION_DENIED',
            default => $codigo,
        };
        $meta = (array) ($evento['metadata'] ?? []);

        if ($codigo === 'PLACED') {
            return $this->importarPedido($cfg, $orderId);
        }

        $ifood = IfoodPedido::with('pedido')->where('ifood_order_id', $orderId)->first();
        if (!$ifood) {
            // Evento de pedido que não chegou aqui (ex.: sistema estava desligado): importa agora
            if (in_array($codigo, ['CONFIRMED', 'READY_TO_PICKUP', 'DISPATCHED'], true)) {
                $novo = $this->importarPedido($cfg, $orderId);
                IfoodPedido::where('ifood_order_id', $orderId)->update(['status' => $codigo]);

                return $novo;
            }

            return false;
        }

        $pedido = $ifood->pedido;
        $aberto = $pedido && !in_array($pedido->status, [ComandaPedido::CONCLUIDO, ComandaPedido::CANCELADO], true);
        $texto = fn (...$chaves) => collect($chaves)->map(fn ($c) => $meta[$c] ?? null)->first(fn ($v) => is_string($v) && trim($v) !== '');

        switch ($codigo) {
            case 'CONFIRMED':
                $ifood->update(['status' => IfoodPedido::CONFIRMED, 'confirmado_em' => $ifood->confirmado_em ?? now()]);
                break;
            case 'READY_TO_PICKUP':
            case 'DISPATCHED':
                $ifood->update(['status' => $codigo]);
                if ($pedido && $pedido->status === ComandaPedido::PREPARO) {
                    $pedido->mudarStatus(ComandaPedido::PRONTO, null);
                }
                break;
            case 'CONCLUDED':
                $ifood->update(['status' => IfoodPedido::CONCLUDED, 'codigo_entrega_solicitado_em' => null, 'disputa' => null]);
                if ($aberto) {
                    $pedido->mudarStatus(ComandaPedido::CONCLUIDO, null);
                }
                break;
            case 'CANCELLED':
                // Sem pedido de cancelamento da loja em andamento: foi o cliente ou o iFood → avisa a cozinha
                $porLoja = $ifood->cancelado_por === 'loja' || $ifood->cancelamento_solicitado_em !== null;
                $motivo = $texto('CANCEL_REASON', 'reason', 'cancelReason', 'CANCELLATION_REASON', 'message');
                $ifood->update([
                    'status'        => IfoodPedido::CANCELLED,
                    'cancelado_por' => $porLoja ? 'loja' : 'ifood',
                    'disputa'       => null,
                    'ultimo_erro'   => $porLoja ? null : mb_substr('Cancelado pelo iFood/cliente' . ($motivo ? ": {$motivo}" : '.'), 0, 500),
                ]);
                if ($aberto) {
                    $pedido->mudarStatus(ComandaPedido::CANCELADO, null);
                    if (!$porLoja) {
                        try {
                            $this->impressao->imprimirCancelamento($pedido->refresh(), $motivo);
                        } catch (Throwable $e) {
                            Log::warning('iFood: impressão do cancelamento falhou: ' . $e->getMessage());
                        }
                    }
                }
                break;
            case 'CANCELLATION_REQUEST_FAILED':
                // O iFood não aceitou o cancelamento pedido pela loja: o pedido continua valendo
                $motivo = $texto('reason', 'message', 'CANCEL_REASON', 'description');
                $ifood->update([
                    'cancelamento_solicitado_em' => null,
                    'cancelado_por'              => null,
                    'ultimo_erro'                => mb_substr('O iFood não aceitou o cancelamento' . ($motivo ? ": {$motivo}" : '. O pedido continua valendo.'), 0, 500),
                ]);
                break;
            case 'DELIVERY_DROP_CODE_REQUESTED':
                // Entrega própria: o iFood só conclui o pedido com o código que o cliente dá ao entregador
                $ifood->update(['codigo_entrega_solicitado_em' => $ifood->codigo_entrega_solicitado_em ?? now()]);
                break;
            case 'HANDSHAKE_DISPUTE':
            case 'CONSUMER_CANCELLATION_REQUESTED':
                // Cliente pediu cancelamento / reembolso: a loja aceita ou recusa dentro do prazo
                $ifood->update(['disputa' => $this->dadosDisputa($meta, $codigo === 'CONSUMER_CANCELLATION_REQUESTED')]);
                break;
            case 'HANDSHAKE_SETTLEMENT':
            case 'CONSUMER_CANCELLATION_ACCEPTED':
            case 'CONSUMER_CANCELLATION_DENIED':
                $ifood->update(['disputa' => null]);
                Log::info('iFood: negociação encerrada ' . $codigo, ['orderId' => $orderId, 'metadata' => $meta]);
                break;
            default:
                // Outros eventos (entregador chegando, código validado...) só ficam registrados no log
                Log::info('iFood: evento ' . $codigo, ['orderId' => $orderId, 'metadata' => $meta]);
        }

        return false;
    }

    /** Dados da disputa (Plataforma de Negociação) ou do pedido de cancelamento do cliente (modelo antigo). */
    private function dadosDisputa(array $m, bool $legado): array
    {
        $expira = $m['expiresAt'] ?? null;
        $mensagem = collect([$m['message'] ?? null, $m['reason'] ?? null])->first(fn ($v) => is_string($v) && $v !== '');
        $valor = data_get($m, 'metadata.proposedAmountRefund.value', data_get($m, 'proposedAmount.value'));

        return [
            'id'          => $m['disputeId'] ?? $m['handshakeId'] ?? $m['id'] ?? null,
            'legado'      => $legado,
            'acao'        => $legado ? 'CANCELLATION' : ($m['action'] ?? null),
            'tipo'        => $m['handshakeType'] ?? null,
            'mensagem'    => $mensagem ? mb_substr($mensagem, 0, 500) : null,
            'expira_em'   => $expira ? \Illuminate\Support\Carbon::parse($expira)->setTimezone(config('app.timezone'))->toIso8601String() : null,
            'no_prazo'    => $m['timeoutAction'] ?? null,
            'itens'       => collect(data_get($m, 'metadata.items', $m['items'] ?? []))
                ->map(fn ($i) => is_array($i) ? trim((isset($i['quantity']) ? ((int) $i['quantity']) . 'x ' : '') . ($i['name'] ?? $i['description'] ?? '')) : '')
                ->filter()->values()->all(),
            'valor'       => is_numeric($valor) ? (float) $valor : null,
            'recebida_em' => now()->toIso8601String(),
        ];
    }

    /** Busca o pedido no iFood e cria na Lista de Pedidos (uma vez só). Aceita sozinho se configurado. */
    public function importarPedido(IfoodConfiguracao $cfg, string $orderId): bool
    {
        if (IfoodPedido::where('ifood_order_id', $orderId)->exists()) {
            return false;
        }

        $o = $this->chamar($cfg, 'get', "/order/v1.0/orders/{$orderId}");
        if (!is_array($o)) {
            throw new RuntimeException('o iFood não devolveu os dados do pedido.');
        }

        $ifood = DB::transaction(function () use ($o, $orderId, $cfg) {
            $itens = $this->itensDoPedido($o['items'] ?? []);
            $subtotal = round(collect($itens)->sum('subtotal'), 2);
            $cliente = data_get($o, 'customer.name');

            $pedido = ComandaPedido::create([
                'uuid'         => (string) Str::uuid(),
                'origem'       => 'ifood',
                'status'       => ComandaPedido::PREPARO,
                'mesa_id'      => null,
                'cliente_nome' => $cliente ? mb_substr($cliente, 0, 60) : null,
                'total'        => $subtotal,
                'enviado_em'   => now(),
            ]);
            foreach ($itens as $item) {
                $pedido->itens()->create($item);
            }

            $metodos = collect(data_get($o, 'payments.methods', []))->map(fn ($p) => [
                'metodo'     => $p['method'] ?? null,
                'tipo'       => $p['type'] ?? null,
                'valor'      => (float) ($p['value'] ?? 0),
                'bandeira'   => data_get($p, 'card.brand'),
                'troco_para' => data_get($p, 'cash.changeFor'),
            ])->values()->all();

            $endereco = data_get($o, 'delivery.deliveryAddress');
            $textoEndereco = $endereco ? collect([
                $endereco['formattedAddress'] ?? trim(($endereco['streetName'] ?? '') . ', ' . ($endereco['streetNumber'] ?? ''), ', '),
                $endereco['complement'] ?? null,
                $endereco['neighborhood'] ?? null,
                isset($endereco['reference']) ? 'Ref.: ' . $endereco['reference'] : null,
            ])->filter()->implode(' · ') : null;

            return IfoodPedido::create([
                'ifood_order_id'    => $orderId,
                'display_id'        => $o['displayId'] ?? null,
                'comanda_pedido_id' => $pedido->id,
                'merchant_id'       => data_get($o, 'merchant.id', $cfg->merchant_id),
                'tipo'              => $o['orderType'] ?? null,
                'momento'           => $o['orderTiming'] ?? null,
                'status'            => IfoodPedido::PLACED,
                'entregue_por'      => data_get($o, 'delivery.deliveredBy'),
                'cliente_nome'      => $cliente,
                'cliente_telefone'  => data_get($o, 'customer.phone.number'),
                'localizador'       => data_get($o, 'customer.phone.localizer'),
                'endereco'          => $textoEndereco ? mb_substr($textoEndereco, 0, 500) : null,
                'codigo_retirada'   => data_get($o, 'delivery.pickupCode') ?? data_get($o, 'takeout.pickupCode'),
                'subtotal'          => (float) data_get($o, 'total.subTotal', $subtotal),
                'taxa_entrega'      => (float) data_get($o, 'total.deliveryFee', 0),
                'descontos'         => (float) data_get($o, 'total.benefits', 0),
                'taxas_adicionais'  => (float) data_get($o, 'total.additionalFees', 0),
                'total'             => (float) data_get($o, 'total.orderAmount', $subtotal),
                'pago_online'       => (float) data_get($o, 'payments.prepaid', 0),
                'cobrar_na_entrega' => (float) data_get($o, 'payments.pending', 0),
                'pagamentos'        => $metodos,
                'observacoes'       => ($obs = data_get($o, 'delivery.observations') ?? data_get($o, 'extraInfo')) ? mb_substr((string) $obs, 0, 500) : null,
                // O iFood manda em UTC: converte para o fuso do sistema antes de gravar
                'agendado_para'     => ($agenda = data_get($o, 'schedule.deliveryDateTimeStart')) ? \Illuminate\Support\Carbon::parse($agenda)->setTimezone(config('app.timezone')) : null,
                'criado_ifood_em'   => ($criado = $o['createdAt'] ?? null) ? \Illuminate\Support\Carbon::parse($criado)->setTimezone(config('app.timezone')) : null,
                'payload'           => $o,
            ]);
        });

        // Impressão nas impressoras dos setores (como os pedidos do garçom)
        try {
            $this->impressao->imprimirPedido($ifood->pedido);
        } catch (Throwable $e) {
            Log::warning('iFood: impressão do pedido falhou: ' . $e->getMessage());
        }

        if ($cfg->aceite_automatico) {
            try {
                $this->aceitar($cfg, $ifood);
            } catch (Throwable $e) {
                $ifood->update(['ultimo_erro' => mb_substr('Aceite automático: ' . $e->getMessage(), 0, 500)]);
            }
        }

        return true;
    }

    /** Itens do pedido do iFood → itens do pedido da Lista de Pedidos (complementos e observação na observação). */
    private function itensDoPedido(array $itens): array
    {
        $produtos = IfoodProduto::whereIn('ifood_item_id', collect($itens)->pluck('id')->filter())->get()->keyBy('ifood_item_id');

        return collect($itens)->map(function ($i) use ($produtos) {
            $quantidade = max(1, (int) round((float) ($i['quantity'] ?? 1)));
            $total = round((float) ($i['totalPrice'] ?? (($i['unitPrice'] ?? 0) * $quantidade)), 2);
            $ligado = $produtos[$i['id'] ?? ''] ?? IfoodProduto::where('codigo_externo', $i['externalCode'] ?? '__')->first();

            $complementos = collect($i['options'] ?? [])->map(fn ($op) =>
                ((float) ($op['quantity'] ?? 1) > 1 ? ((int) $op['quantity']) . 'x ' : '') . ($op['name'] ?? ''))->filter()->implode(', ');
            $observacao = collect([
                $complementos ? '+ ' . $complementos : null,
                filled($i['observations'] ?? null) ? $i['observations'] : null,
            ])->filter()->implode(' · ');

            return [
                'cardapio_item_id'    => null,
                'produto_id'          => $ligado?->produto_id,
                'produto_variante_id' => $ligado?->produto_variante_id,
                'nome'                => mb_substr((string) ($i['name'] ?? 'Item'), 0, 255),
                'quantidade'          => $quantidade,
                'preco_unitario'      => round($total / $quantidade, 2),
                'subtotal'            => $total,
                'observacao'          => $observacao !== '' ? mb_substr($observacao, 0, 200) : null,
            ];
        })->all();
    }

    // =====================================================================
    // Pedidos: ações
    // =====================================================================

    public function aceitar(IfoodConfiguracao $cfg, IfoodPedido $ifood): void
    {
        $this->chamar($cfg, 'post', "/order/v1.0/orders/{$ifood->ifood_order_id}/confirm");
        $ifood->update(['status' => IfoodPedido::CONFIRMED, 'confirmado_em' => now(), 'ultimo_erro' => null]);
    }

    /** Pronto: retirada/no local → "pronto para retirar"; entrega → "despachado". */
    public function marcarPronto(IfoodConfiguracao $cfg, IfoodPedido $ifood): void
    {
        if ($ifood->aguardandoAceite()) {
            throw new RuntimeException('Aceite o pedido do iFood antes de marcar como pronto.');
        }

        $acao = $ifood->tipo === 'DELIVERY' ? 'dispatch' : 'readyToPickup';
        $this->chamar($cfg, 'post', "/order/v1.0/orders/{$ifood->ifood_order_id}/{$acao}");
        $ifood->update(['status' => $acao === 'dispatch' ? IfoodPedido::DISPATCHED : IfoodPedido::READY_TO_PICKUP, 'ultimo_erro' => null]);
    }

    /** Motivos de cancelamento que o iFood aceita para este pedido. */
    public function motivosCancelamento(IfoodConfiguracao $cfg, IfoodPedido $ifood): array
    {
        return collect($this->chamar($cfg, 'get', "/order/v1.0/orders/{$ifood->ifood_order_id}/cancellationReasons") ?? [])
            ->map(fn ($m) => ['codigo' => (string) ($m['cancelCodeId'] ?? $m['code'] ?? ''), 'descricao' => $m['description'] ?? '—'])
            ->filter(fn ($m) => $m['codigo'] !== '')->values()->all();
    }

    /**
     * Pede o cancelamento ao iFood. O pedido só fica cancelado aqui quando o iFood confirmar (evento CANCELLED):
     * se ele recusar (CANCELLATION_REQUEST_FAILED), o pedido continua valendo e a tela mostra o motivo.
     */
    public function cancelar(IfoodConfiguracao $cfg, IfoodPedido $ifood, string $codigo, string $motivo): void
    {
        if ($ifood->cancelamento_solicitado_em && $ifood->status !== IfoodPedido::CANCELLED) {
            throw new RuntimeException('O cancelamento deste pedido já foi pedido: aguardando a resposta do iFood.');
        }

        $this->chamar($cfg, 'post', "/order/v1.0/orders/{$ifood->ifood_order_id}/requestCancellation", [
            'reason'           => mb_substr($motivo, 0, 250),
            'cancellationCode' => $codigo,
        ]);
        $ifood->update(['cancelamento_solicitado_em' => now(), 'cancelado_por' => 'loja', 'ultimo_erro' => null]);
    }

    /** Entrega própria: confirma a entrega com o código que o cliente informa ao entregador. O iFood conclui o pedido. */
    public function confirmarEntrega(IfoodConfiguracao $cfg, IfoodPedido $ifood, string $codigo): void
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if ($codigo === '') {
            throw new RuntimeException('Digite o código de entrega que o cliente informou.');
        }

        $resp = $this->chamar($cfg, 'post', "/order/v1.0/orders/{$ifood->ifood_order_id}/verifyDeliveryCode", ['code' => $codigo]);
        if (is_array($resp) && array_key_exists('success', $resp) && !$resp['success']) {
            throw new RuntimeException('Código de entrega incorreto. Confira com o cliente e tente de novo.');
        }

        $ifood->update(['codigo_entrega_solicitado_em' => null, 'ultimo_erro' => null]);
    }

    /** Responde ao pedido de cancelamento / reembolso do cliente: aceitar ou recusar (com motivo). */
    public function responderDisputa(IfoodConfiguracao $cfg, IfoodPedido $ifood, bool $aceitar, ?string $motivo = null): void
    {
        $d = $ifood->disputa;
        if (!$d) {
            throw new RuntimeException('Este pedido não tem pedido do cliente aguardando resposta.');
        }
        $motivo = trim((string) $motivo);
        if (!$aceitar && $motivo === '') {
            throw new RuntimeException('Escreva o motivo da recusa (o cliente vai ver).');
        }
        $corpo = $aceitar ? [] : ['reason' => mb_substr($motivo, 0, 250)];

        if (!empty($d['legado'])) {
            $this->chamar($cfg, 'post', "/order/v1.0/orders/{$ifood->ifood_order_id}/" . ($aceitar ? 'acceptCancellation' : 'denyCancellation'), $corpo);
        } else {
            if (blank($d['id'] ?? null)) {
                throw new RuntimeException('O iFood não informou o número da negociação: responda pelo Portal do Parceiro.');
            }
            $this->chamar($cfg, 'post', "/order/v1.0/disputes/{$d['id']}/" . ($aceitar ? 'accept' : 'reject'), $corpo);
        }

        // Aceitou o cancelamento: o iFood manda o CANCELLED em seguida
        $ifood->update(['disputa' => null, 'ultimo_erro' => null, 'cancelado_por' => $aceitar ? 'ifood' : $ifood->cancelado_por]);
    }

    // =====================================================================
    // HTTP
    // =====================================================================

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::URL)->acceptJson()->timeout(20)->connectTimeout(8);
    }

    /** Chamada autenticada; renova o token uma vez se o iFood responder 401. */
    private function chamar(IfoodConfiguracao $cfg, string $metodo, string $caminho, array $dados = []): mixed
    {
        $enviar = function (string $token) use ($metodo, $caminho, $dados) {
            $req = $this->http()->withToken($token);

            return match ($metodo) {
                'get'   => $req->get($caminho, $dados),
                'post'  => $dados ? $req->post($caminho, $dados) : $req->withBody('', 'application/json')->post($caminho),
                'patch' => $req->patch($caminho, $dados),
                'put'   => $req->put($caminho, $dados),
                default => throw new RuntimeException("Método {$metodo} não suportado."),
            };
        };

        try {
            $resp = $enviar($this->token($cfg));
            if ($resp->status() === 401) {
                $resp = $enviar($this->token($cfg, true));
            }
        } catch (ConnectionException) {
            throw new RuntimeException('Sem conexão com o iFood. Confira a internet do servidor.');
        }

        return $this->json($resp, $caminho);
    }

    private function json(Response $resp, string $acao): mixed
    {
        if ($resp->successful()) {
            return $resp->status() === 204 || trim($resp->body()) === '' ? null : $resp->json();
        }

        $corpo = $resp->json();
        $msg = is_array($corpo)
            ? ($corpo['error']['message'] ?? $corpo['message'] ?? $corpo['error_description'] ?? (is_string($corpo['error'] ?? null) ? $corpo['error'] : null))
            : null;
        $msg ??= mb_substr(trim(strip_tags($resp->body())), 0, 200) ?: 'sem detalhes';

        $dica = match ($resp->status()) {
            401 => ' (credenciais inválidas ou autorização vencida)',
            403 => ' (o aplicativo não tem permissão para esta loja ou este módulo)',
            404 => ' (não encontrado)',
            429 => ' (muitas chamadas: aguarde um pouco)',
            default => '',
        };

        throw new RuntimeException("iFood recusou ao {$acao}: {$msg}{$dica} [HTTP {$resp->status()}]");
    }

    private function exigirCredenciais(IfoodConfiguracao $cfg, bool $comLoja = true): void
    {
        if (blank($cfg->client_id) || blank($cfg->client_secret)) {
            throw new RuntimeException('Preencha o Client ID e o Client Secret em Cadastros → iFood.');
        }
        if ($comLoja && blank($cfg->merchant_id)) {
            throw new RuntimeException('Preencha o ID da loja (Merchant ID) em Cadastros → iFood.');
        }
    }
}
