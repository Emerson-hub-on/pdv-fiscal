<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UsuarioCache
{
    public static function vazio(): bool
    {
        return !DB::connection('sqlite_local')->table('usuarios_cache')->exists();
    }

    public static function porId(int|string $id): ?User
    {
        return self::hidratar(
            DB::connection('sqlite_local')->table('usuarios_cache')->where('id', $id)->first()
        );
    }

    public static function porCodigo(int $codigo): ?User
    {
        return self::hidratar(
            DB::connection('sqlite_local')->table('usuarios_cache')->where('codigo', $codigo)->first()
        );
    }

    // Monta um User "existente" a partir da linha do cache: os métodos do model
    // (isAdmin, podeAcessarCaixa, caixaLiberado, supervisorLibera...) leem o mapa_acessos.
    private static function hidratar(?object $linha): ?User
    {
        if (!$linha) {
            return null;
        }

        $atributos = (array) $linha;
        $atributos['ativo'] = 1;             // o cache só guarda pessoas ativas
        $atributos['remember_token'] = null; // evita erro em logout/strict mode

        return (new User)->newFromBuilder($atributos);
    }
}