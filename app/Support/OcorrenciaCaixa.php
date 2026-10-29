<?php

namespace App\Support;

use App\Models\CaixaLocal;
use App\Services\UsuarioCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Grava no SQLite do caixa as ocorrências que saem no relatório de fechamento.
 * Nunca derruba a operação: se a gravação falhar, só registra no log.
 */
class OcorrenciaCaixa
{
    public const DESCONTO_ITEM = 'desconto_item';
    public const DESCONTO_GLOBAL = 'desconto_global';
    public const CANCELAMENTO_NFCE = 'cancelamento_nfce';
    public const AUTORIZACAO = 'autorizacao';

    /**
     * @param array{tipo:string, venda_uuid?:?string, caixa_uuid?:?string, acao?:?string,
     *              descricao?:?string, valor?:float|int, supervisor_id?:?int, liberado_operador?:bool} $dados
     */
    public static function registrar(array $dados): void
    {
        try {
            $agora = now();
            $supervisorId = $dados['supervisor_id'] ?? null;

            $caixaUuid = $dados['caixa_uuid']
                ?? (Auth::id() ? CaixaLocal::aberto(Auth::id())?->uuid : null);

            DB::connection('sqlite_local')->table('ocorrencias_caixa')->insert([
                'caixa_uuid' => $caixaUuid,
                'venda_uuid' => $dados['venda_uuid'] ?? null,
                'tipo' => $dados['tipo'],
                'acao' => $dados['acao'] ?? null,
                'descricao' => isset($dados['descricao']) ? mb_substr((string) $dados['descricao'], 0, 255) : null,
                'valor' => round((float) ($dados['valor'] ?? 0), 2),
                'operador_id' => Auth::id(),
                'supervisor_id' => $supervisorId,
                'supervisor_nome' => $supervisorId ? UsuarioCache::porId($supervisorId)?->name : null,
                'liberado_operador' => (bool) ($dados['liberado_operador'] ?? false),
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível registrar a ocorrência do caixa', [
                'dados' => $dados,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
