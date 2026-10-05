<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 
    'tipo', 
    'email', 
    'password', 
    'acesso_caixa', 
    'acesso_fiscal', 
    'acesso_supervisor', 
    'permissoes',
    'permissoes_caixa',
    'codigo_caixa',
    'codigo_servidor',
    'permissoes_supervisor',
    'codigo',
    'is_admin',
    'ativo',

    ])]
#[Hidden([
    'password', 
    'remember_token'

    ])]

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
            'acesso_supervisor' => 'boolean',
            'permissoes'        => 'array',
            'permissoes_caixa'  => 'array',
            'permissoes_supervisor' => 'array',
            'is_admin'          => 'boolean',
            'ativo'             => 'boolean',
        ];
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    public function nivelPermissaoSupervisor(string $acao): string
    {
        $nivel = $this->permissoes_supervisor[$acao] ?? 'libera';

        return $nivel === 'nao_libera' ? 'nao_libera' : 'libera';
    }

    // Admin autoriza tudo; supervisor autoriza o que não foi restringido
    public function supervisorLibera(string $acao): bool
    {
        return $this->isAdmin()
            || ($this->acesso_supervisor && $this->nivelPermissaoSupervisor($acao) === 'libera');
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

    public function nivelPermissaoCaixa(string $acao): string
    {
        $nivel = $this->permissoes_caixa[$acao] ?? 'supervisor';

        return $nivel === 'liberado' ? 'liberado' : 'supervisor';
    }

    public function caixaLiberado(string $acao): bool
    {
        return $this->nivelPermissaoCaixa($acao) === 'liberado';
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
        return $this->isAdmin() || $this->acesso_caixa || $this->acesso_supervisor;
    }

    public function podeAcessarFiscal(): bool
    {
        return $this->isAdmin() || $this->acesso_fiscal;
    }

    public function podeAutorizar(): bool
    {
        return $this->isAdmin() || (bool) $this->acesso_supervisor;
    }

}