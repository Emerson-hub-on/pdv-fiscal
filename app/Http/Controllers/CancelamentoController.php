<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\EmissorLocalService;
use App\Services\FiscalEmissorService;
use App\Services\SyncService;
use App\Support\AutorizacaoSupervisor;
use App\Support\EmissaoLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CancelamentoController extends Controller
{
    public function listar()
    {
        $prazoMinutos = config('app.prazo_cancelamento_minutos', 30);
        $limite = now()->subMinutes($prazoMinutos);

        // PDV que emite pelo caixa: as vendas estão no SQLite (vale com o servidor fora do ar)
        if (EmissaoLocal::ativa()) {
            return response()->json($this->listarLocal($limite));
        }

        $vendas = Venda::where('status', 'emitida')
            ->where('emitida_em', '>=', $limite)
            ->with('itens.produto')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($venda) {
                return [
                    'id' => $venda->id,
                    'numero_nfce' => $venda->numero_nfce,
                    'chave_nfe' => $venda->chave_nfe,
                    'total' => $venda->total,
                    'criada_em' => $venda->created_at->format('d/m/Y H:i'),
                    'itens' => $venda->itens->map(fn($i) => $i->produto->nome . ' x' . $i->quantidade)->values(),
                ];
            });

        return response()->json($vendas);
    }

    public function cancelar(Request $request, string $venda)
    {
        $usuario = Auth::user();
        $liberado = $usuario->caixaLiberado('cancelar_nfce');

        // Operador liberado dispensa o supervisor; os demais precisam da autorização de sessão
        if (!$liberado && !AutorizacaoSupervisor::valida('cancelar_nfce')) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Autorização do supervisor ausente, expirada ou já utilizada. Feche esta janela e solicite novamente.',
            ], 403);
        }

        $validado = $request->validate([
            'justificativa' => 'required|string|min:15',
        ]);

        // Venda do caixa (SQLite): o identificador é o uuid
        $local = Str::isUuid($venda);

        try {
            $supervisorId = $liberado ? null : AutorizacaoSupervisor::supervisorId('cancelar_nfce');

            if ($local) {
                $resultado = (new EmissorLocalService())->cancelarLocal($venda, $validado['justificativa']);
                $vendaRef = $venda;
            } else {
                $registro = Venda::findOrFail($venda);
                $resultado = (new FiscalEmissorService())->cancelar($registro, $validado['justificativa']);

                DB::connection('sqlite_local')->table('vendas_pendentes')
                    ->where('uuid', $registro->uuid)
                    ->update(['status' => 'cancelada', 'updated_at' => now()]);

                $vendaRef = $registro->id;
            }

            // Uso único: só gasta a autorização quando o cancelamento deu certo
            AutorizacaoSupervisor::consumir('cancelar_nfce');

            Log::info('NFC-e cancelada', [
                'venda_id'      => $vendaRef,
                'operador_id'   => $usuario->id,
                'supervisor_id' => $supervisorId,
                'liberado'      => $liberado,
                'origem'        => $local ? 'caixa' : 'servidor',
            ]);

            return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
        } catch (\Throwable $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
    }

    private function listarLocal(Carbon $limite)
    {
        $db = DB::connection('sqlite_local');

        return $db->table('vendas_pendentes')
            ->where('status_fiscal', 'emitida')
            ->where('status', '!=', 'cancelada')
            ->where('emitida_em', '>=', $limite->format('Y-m-d H:i:s'))
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($v) use ($db) {
                $itens = collect(json_decode($v->itens, true) ?? [])->map(function ($i) use ($db) {
                    $nome = $db->table('produtos_cache')->where('id', $i['produto_id'])->value('nome')
                        ?? 'Produto #' . $i['produto_id'];

                    return $nome . ' x' . $i['quantidade'];
                });

                $data = $v->vendida_em ?? $v->created_at;

                return [
                    'id' => $v->uuid, // no caixa, o identificador do cancelamento é o uuid
                    'numero_nfce' => $v->numero_nfce,
                    'chave_nfe' => $v->chave_nfe,
                    'total' => $v->total,
                    'criada_em' => $data ? Carbon::parse($data)->format('d/m/Y H:i') : '',
                    'itens' => $itens->values(),
                ];
            })
            ->values();
    }
}