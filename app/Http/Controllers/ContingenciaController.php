<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\EmissorLocalService;
use App\Services\FiscalEmissorService;
use App\Services\SyncService;
use App\Support\EmissaoLocal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\AutorizacaoSupervisor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContingenciaController extends Controller
{
    public function listar()
    {
        // PDV que emite pelo caixa: as vendas em contingência estão no SQLite
        if (EmissaoLocal::ativa()) {
            return response()->json($this->listarLocal());
        }

        $vendas = Venda::where('status', 'contingencia')
            ->with('itens.produto')
            ->orderBy('created_at')
            ->get()
            ->map(function ($venda) {
                return [
                    'id' => $venda->id, // ainda precisamos do id interno pra fazer o reenvio
                    'numero_nfce' => $venda->numero_nfce,
                    'serie_nfce' => $venda->serie_nfce,
                    'chave_nfe' => $venda->chave_nfe,
                    'total' => $venda->total,
                    'criada_em' => $venda->created_at->format('d/m/Y H:i'),
                    'motivo' => $venda->motivo_rejeicao,
                    'itens' => $venda->itens->map(fn($i) => $i->produto->nome . ' x' . $i->quantidade)->values(),
                ];
            });

        return response()->json($vendas);
    }

    public function reenviar(string $venda)
    {
        // Venda do SQLite: o identificador é o uuid
        if (Str::isUuid($venda)) {
            return $this->reenviarLocal($venda);
        }

        $registro = Venda::findOrFail($venda);

        if ($registro->status !== 'contingencia') {
            return response()->json(['sucesso' => false, 'erro' => 'Venda não está mais em contingência.'], 422);
        }

        try {
            (new FiscalEmissorService())->emitir($registro);
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }

    private function listarLocal()
    {
        $db = DB::connection('sqlite_local');

        return $db->table('vendas_pendentes')
            ->where('status_fiscal', 'contingencia')
            ->where('status', '!=', 'cancelada')
            ->get()
            ->sortBy(fn ($v) => $v->created_at ?? '')
            ->map(function ($v) use ($db) {
                $itens = collect(json_decode($v->itens, true) ?? [])->map(function ($i) use ($db) {
                    $nome = $db->table('produtos_cache')->where('id', $i['produto_id'])->value('nome')
                        ?? 'Produto #' . $i['produto_id'];

                    return $nome . ' x' . $i['quantidade'];
                });

                return [
                    'id' => $v->uuid, // no caixa, o identificador do reenvio é o uuid
                    'venda_id' => $v->id,
                    'numero_nfce' => $v->numero_nfce,
                    'serie_nfce' => $v->serie_nfce,
                    'chave_nfe' => $v->chave_nfe,
                    'total' => $v->total,
                    'criada_em' => !empty($v->created_at) ? Carbon::parse($v->created_at)->format('d/m/Y H:i') : '',
                    'motivo' => $v->motivo_rejeicao,
                    'itens' => $itens->values(),
                ];
            })
            ->values();
    }

    private function reenviarLocal(string $uuid)
    {
        $situacao = DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('uuid', $uuid)->value('status_fiscal');

        if ($situacao !== 'contingencia') {
            return response()->json(['sucesso' => false, 'erro' => 'Venda não está mais em contingência.'], 422);
        }

        try {
            (new EmissorLocalService())->emitirLocal($uuid);
            $resposta = ['sucesso' => true];
        } catch (\Throwable $e) {
            $resposta = ['sucesso' => false, 'erro' => $e->getMessage()];
        }

        return response()->json($resposta);
    }
}