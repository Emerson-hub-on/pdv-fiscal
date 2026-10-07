<?php

namespace App\Support;

class Maquina
{
    public static function nome(): string
    {
        return strtoupper(trim(gethostname() ?: php_uname('n')));
    }
}