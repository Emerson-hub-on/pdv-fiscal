<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração da integração com o iFood (uma por instalação): credenciais do aplicativo,
 * loja (merchant), tokens de acesso e preferências. Segredos e tokens ficam criptografados no banco.
 */
class IfoodConfiguracao extends Model
{
    public const DISTRIBUIDO  = 'distribuido';   // cada restaurante autoriza o app com um código (venda para vários)
    public const CENTRALIZADO = 'centralizado';  // credenciais da própria loja (client_credentials)

    protected $table = 'ifood_configuracoes';

    protected $fillable = [
        'modo_autenticacao', 'client_id', 'client_secret', 'merchant_id', 'ativo', 'aceite_automatico',
        'access_token', 'refresh_token', 'token_expira_em', 'user_code', 'code_verifier',
        'verification_url', 'user_code_expira_em', 'ultimo_polling_em', 'ultimo_erro', 'ultimo_erro_em',
        'cardapio_importado_em', 'produto_modelo_id',
    ];

    protected $hidden = ['client_secret', 'access_token', 'refresh_token', 'code_verifier'];

    protected $casts = [
        'client_secret'         => 'encrypted',
        'access_token'          => 'encrypted',
        'refresh_token'         => 'encrypted',
        'code_verifier'         => 'encrypted',
        'ativo'                 => 'boolean',
        'aceite_automatico'     => 'boolean',
        'token_expira_em'       => 'datetime',
        'user_code_expira_em'   => 'datetime',
        'ultimo_polling_em'     => 'datetime',
        'ultimo_erro_em'        => 'datetime',
        'cardapio_importado_em' => 'datetime',
    ];

    /** Produto do sistema usado como modelo fiscal padrão no cadastro automático dos itens do iFood. */
    public function produtoModelo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_modelo_id');
    }

    /** A configuração desta instalação (cria vazia na primeira vez). */
    public static function atual(): self
    {
        return static::query()->first() ?? static::create(['modo_autenticacao' => self::DISTRIBUIDO]);
    }

    public function credenciaisPreenchidas(): bool
    {
        return filled($this->client_id) && filled($this->client_secret) && filled($this->merchant_id);
    }

    /** Loja autorizada: tem token válido ou como renovar. */
    public function conectado(): bool
    {
        return filled($this->access_token) && ($this->token_expira_em?->isFuture() || filled($this->refresh_token));
    }

    /** Polling em dia (o iFood considera a loja conectada enquanto o polling roda a cada 30 s). */
    public function pollingEmDia(): bool
    {
        return $this->ultimo_polling_em !== null && $this->ultimo_polling_em->gt(now()->subSeconds(90));
    }
}
