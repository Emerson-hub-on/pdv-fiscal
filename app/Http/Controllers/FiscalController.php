<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\FiscalEmissorService;
use App\Services\SyncService;
use Illuminate\Support\Facades\DB;
use Exception;

class FiscalController extends Controller
{
    public function comprovante(string $uuid)
    {
        $dados = $this->buscarVendaPorUuid($uuid);

        if (!$dados) {
            abort(404, 'Venda não encontrada.');
        }

        return view('vendas.comprovante', $dados);
    }

    public function emitir(string $uuid)
    {
        $dados = $this->buscarVendaPorUuid($uuid);

        if (!$dados) {
            return response()->json(['sucesso' => false, 'erro' => 'Venda não encontrada.'], 404);
        }

        if ($dados['origem'] === 'local') {
            (new SyncService())->enviarVendasPendentes();
            $dados = $this->buscarVendaPorUuid($uuid);

            if ($dados['origem'] === 'local') {
                return response()->json([
                    'sucesso' => false,
                    'contingencia' => true,
                    'erro' => 'Venda ainda não sincronizada com o servidor.',
                ]);
            }
        }

        $venda = $dados['venda'];

        if ($venda->status === 'emitida') {
            return response()->json(['sucesso' => true, 'ja_emitida' => true, 'chave' => $venda->chave_nfe]);
        }

        try {
            $service = new FiscalEmissorService();
            $resultado = $service->emitir($venda);

            return response()->json(['sucesso' => true, 'chave' => $resultado['chave']]);
        } catch (Exception $e) {
            // Se a venda caiu em contingencia (reserva de numero ja feita), avisa nesse formato especifico
            $venda->refresh();
            $contingencia = $venda->status === 'contingencia';

            return response()->json([
                'sucesso' => false,
                'contingencia' => $contingencia,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Busca a venda pelo UUID, primeiro no central (MySQL), depois no local (SQLite).
     * Retorna um formato unificado pra view conseguir exibir os dois casos.
     */
    protected function buscarVendaPorUuid(string $uuid): ?array
    {
        $venda = Venda::where('uuid', $uuid)->with('itens.produto', 'pagamentos')->first();

        if ($venda) {
            return [
                'origem' => 'central',
                'venda' => $venda,
                'itens' => $venda->itens,
                'pagamentos' => $venda->pagamentos,
                'troco' => $venda->troco,
                'total' => $venda->total,
                'status' => $venda->status,
                'chave_nfe' => $venda->chave_nfe,
            ];
        }

        $vendaLocal = DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('uuid', $uuid)->first();

        if (!$vendaLocal) {
            return null;
        }

        $itensLocais = collect(json_decode($vendaLocal->itens, true))->map(function ($item) {
            $produto = DB::connection('sqlite_local')->table('produtos_cache')
                ->where('id', $item['produto_id'])->first();

            return (object) [
                'produto' => $produto,
                'quantidade' => $item['quantidade'],
                'subtotal' => $item['preco_unitario'] * $item['quantidade'],
            ];
        });

        $pagamentosLocais = collect(json_decode($vendaLocal->pagamentos, true) ?? [])
            ->map(fn($p) => (object) $p);

        return [
            'origem' => 'local',
            'venda' => null,
            'itens' => $itensLocais,
            'pagamentos' => $pagamentosLocais,
            'troco' => $vendaLocal->troco ?? 0,
            'total' => $vendaLocal->total,
            'status' => 'aguardando_sincronizacao',
            'chave_nfe' => null,
        ];
    }
}