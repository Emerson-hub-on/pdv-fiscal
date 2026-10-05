<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class CentralStatus
{
    private const CHAVE = 'central_fora_do_ar';
    private const SEGUNDOS = 20;

    // Cache em arquivo: funciona mesmo sem o MySQL (o cache padrão pode ser o "database")
    public static function fora(): bool
    {
        return Cache::store('file')->has(self::CHAVE);
    }

    public static function marcarFora(): void
    {
        Cache::store('file')->put(self::CHAVE, true, self::SEGUNDOS);
    }

    public static function marcarOnline(): void
    {
        Cache::store('file')->forget(self::CHAVE);
    }

    /**
     * True só para falha de CONEXÃO (servidor fora, rede caída, timeout).
     * Erro de SQL de verdade (coluna inexistente, etc.) não cai aqui e segue estourando.
     */
    public static function erroDeConexao(\Throwable $e): bool
    {
        $pdo = $e instanceof QueryException ? $e->getPrevious() : $e;

        if (!$pdo instanceof \PDOException) {
            return false;
        }

        $codigo = (int) ($pdo->errorInfo[1] ?? $pdo->getCode());
        $mensagem = $pdo->getMessage();

        return in_array($codigo, [2002, 2003, 2006, 2013], true)
            || str_contains($mensagem, 'getaddrinfo')
            || str_contains($mensagem, 'timed out');
    }
}
