<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name', 
    'username', 
    'tipo', 
    'email', 
    'password', 
    'acesso_caixa', 
    'acesso_fiscal', 
    'permissoes'])]
#[Hidden([
    'password', 
    'remember_token'])]

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'acesso_caixa'      => 'boolean',
            'acesso_fiscal'     => 'boolean',
            'permissoes'        => 'array'
        ];
    }

    public function nivelPermissao(string $modulo): string
    {
        if ($this->isAdmin()) {
            return 'total';
        }

        if (!$this->acesso_fiscal) {
            return 'bloqueado';
        }

        $nivel = ($this->permissoes ?? [])[$modulo] ?? 'total';

        return in_array($nivel, ['total', 'consulta', 'bloqueado'], true) ? $nivel : 'total';
    }

    public function podeVer(string $modulo): bool
    {
        return $this->nivelPermissao($modulo) !== 'bloqueado';
    }

    public function podeAlterar(string $modulo): bool
    {
        return $this->nivelPermissao($modulo) === 'total';
    }

    public function isAdmin(): bool
    {
        return $this->tipo === 'admin';
    }

    public function podeAcessarCaixa(): bool
    {
        return $this->isAdmin() || $this->acesso_caixa;
    }

    public function podeAcessarFiscal(): bool
    {
        return $this->isAdmin() || $this->acesso_fiscal;
    }

    /** "João da Silva" => "joao.da.silva" (o mesmo cálculo existe em JS no formulário) */
    public static function normalizarUsername(string $valor): string
    {
        $valor = strtolower(trim(Str::ascii($valor)));
        $valor = preg_replace('/\s+/', '.', $valor);

        return trim(preg_replace('/[^a-z0-9._-]/', '', $valor), '.');
    }
}