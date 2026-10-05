<?php

namespace App\Auth;

use App\Services\UsuarioCache;
use Illuminate\Auth\EloquentUserProvider;

class UserProviderLocal extends EloquentUserProvider
{
    // Chamado pelo guard "caixa" a cada requisição: lê só do SQLite local
    public function retrieveById($identifier)
    {
        return UsuarioCache::porId($identifier);
    }

    // Sem "lembrar de mim" no caixa
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }
}