<?php

namespace App\Support;

/**
 * Onde o servidor do sistema está rodando (APP_MODO no .env):
 *  - local: servidor instalado na loja (padrão). Alcança as impressoras e a rede da loja.
 *  - web:   servidor na nuvem. Não enxerga a rede da loja; o que depende dela precisa de outro caminho.
 *
 * Os caixas (SQLite local) continuam sendo instalações locais nos dois casos.
 */
class ModoSistema
{
    public const LOCAL = 'local';
    public const WEB = 'web';

    public static function atual(): string
    {
        return config('app.modo') === self::WEB ? self::WEB : self::LOCAL;
    }

    public static function web(): bool
    {
        return self::atual() === self::WEB;
    }

    public static function local(): bool
    {
        return self::atual() === self::LOCAL;
    }
}
