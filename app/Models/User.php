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
    'codigo',
    'email',
    'password',
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

    // Mapa de acessos já calculado (evita refazer a cada checagem na mesma requisição)
    private ?array $mapaAcessosCalculado = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'ativo'             => 'boolean',
        ];
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    /**
     * Acessos ativos da pessoa: slug => ['contexto', 'permite_login', 'permissoes'].
     * Opcionalmente filtra por contexto (caixa | servidor).
     */
    public function mapaAcessos(?string $contexto = null): array
    {
        $this->mapaAcessosCalculado ??= $this->calcularMapaAcessos();

        if ($contexto === null) {
            return $this->mapaAcessosCalculado;
        }

        return array_filter($this->mapaAcessosCalculado, fn ($acesso) => $acesso['contexto'] === $contexto);
    }

    private function calcularMapaAcessos(): array
    {
        // Pessoa desativada: sem nenhum acesso
        if (!$this->pessoaAtiva()) {
            return [];
        }

        // Usuário vindo do cache local do caixa: o mapa já vem pronto
        if (array_key_exists('mapa_acessos', $this->attributes)) {
            return json_decode($this->attributes['mapa_acessos'] ?? '[]', true) ?: [];
        }

        $this->loadMissing('acessos.tipo');

        $mapa = [];

        foreach ($this->acessos as $acesso) {
            if (!$acesso->ativo || !$acesso->tipo || !$acesso->tipo->ativo) {
                continue;
            }

            $mapa[$acesso->tipo->slug] = [
                'contexto'      => $acesso->tipo->contexto,
                'permite_login' => (bool) $acesso->tipo->permite_login,
                'permissoes'    => $acesso->permissoes ?? [],
            ];
        }

        return $mapa;
    }

    private function pessoaAtiva(): bool
    {
        return !array_key_exists('ativo', $this->attributes) || (bool) $this->attributes['ativo'];
    }

    public function temAcesso(string $slug): bool
    {
        return isset($this->mapaAcessos()[$slug]);
    }

    private function permissoesDe(string $slug): array
    {
        return $this->mapaAcessos()[$slug]['permissoes'] ?? [];
    }

    public function isAdmin(): bool
    {
        return $this->pessoaAtiva() && (bool) $this->is_admin;
    }

    public function podeAcessarCaixa(): bool
    {
        return $this->isAdmin() || $this->temLoginEm('caixa');
    }

    public function podeAcessarFiscal(): bool
    {
        return $this->isAdmin() || $this->temLoginEm('servidor');
    }

    private function temLoginEm(string $contexto): bool
    {
        foreach ($this->mapaAcessos($contexto) as $acesso) {
            if ($acesso['permite_login']) {
                return true;
            }
        }

        return false;
    }

    public function podeAutorizar(): bool
    {
        return $this->isAdmin() || $this->temAcesso('supervisor');
    }

    public function nivelPermissaoSupervisor(string $acao): string
    {
        $nivel = $this->permissoesDe('supervisor')[$acao] ?? 'libera';

        return $nivel === 'nao_libera' ? 'nao_libera' : 'libera';
    }

    // Admin autoriza tudo; supervisor autoriza o que não foi restringido
    public function supervisorLibera(string $acao): bool
    {
        return $this->isAdmin()
            || ($this->temAcesso('supervisor') && $this->nivelPermissaoSupervisor($acao) === 'libera');
    }

    public function nivelPermissao(string $modulo): string
    {
        if ($this->isAdmin()) {
            return 'total';
        }

        if (!$this->temAcesso('fiscal')) {
            return 'bloqueado';
        }

        $nivel = $this->permissoesDe('fiscal')[$modulo] ?? 'total';

        return in_array($nivel, ['total', 'consulta', 'bloqueado'], true) ? $nivel : 'total';
    }

    public function nivelPermissaoCaixa(string $acao): string
    {
        $nivel = $this->permissoesDe('caixa')[$acao] ?? 'supervisor';

        return $nivel === 'liberado' ? 'liberado' : 'supervisor';
    }

    public function caixaLiberado(string $acao): bool
    {
        return $this->temAcesso('caixa') && $this->nivelPermissaoCaixa($acao) === 'liberado';
    }

    public function podeVer(string $modulo): bool
    {
        return $this->nivelPermissao($modulo) !== 'bloqueado';
    }

    public function podeAlterar(string $modulo): bool
    {
        return $this->nivelPermissao($modulo) === 'total';
    }
}