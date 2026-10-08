<?php

namespace App\Support;

class AutorizacaoSupervisor
{
    // Validade, em minutos. null = sem prazo: vale até a operação terminar
    // (os descontos são descartados ao finalizar a venda, cancelar o cupom ou abrir uma venda nova).
    private const VALIDADE = [
        'desconto_item'   => null,
        'desconto_global' => null,
        'cancelar_nfce'   => 10,
        'inutilizar'      => 10,
    ];

    // Cancelar item e cancelar cupom não entram: só mexem no carrinho local,
    // nada fiscal é gravado.
    public static function tipoDaAcao(?string $acao): ?string
    {
        return array_key_exists($acao, self::VALIDADE) ? $acao : null;
    }

    public static function conceder(string $tipo, int $supervisorId): void
    {
        $minutos = self::VALIDADE[$tipo];

        session()->put("autorizacao_supervisor.{$tipo}", [
            'supervisor_id' => $supervisorId,
            'expira_em'     => $minutos ? now()->addMinutes($minutos)->timestamp : null,
        ]);
    }

    public static function valida(string $tipo): bool
    {
        $autorizacao = session("autorizacao_supervisor.{$tipo}");

        if (!is_array($autorizacao)) {
            return false;
        }

        $expiraEm = $autorizacao['expira_em'] ?? null;

        return $expiraEm === null || $expiraEm >= now()->timestamp;
    }

    public static function supervisorId(string $tipo): ?int
    {
        return self::valida($tipo)
            ? session("autorizacao_supervisor.{$tipo}.supervisor_id")
            : null;
    }

    public static function consumir(string $tipo): void
    {
        session()->forget("autorizacao_supervisor.{$tipo}");
    }
}