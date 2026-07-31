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
            abort(404, 'Venda não encontrada.');
        }

        // So emite se a venda ja estiver sincronizada no central
        if ($dados['origem'] === 'local') {
            // Tenta sincronizar agora, na hora, antes de desistir
            (new SyncService())->enviarVendasPendentes();
            $dados = $this->buscarVendaPorUuid($uuid);

            if ($dados['origem'] === 'local') {
                return back()->with('erro_fiscal', 'Venda ainda não sincronizada com o servidor. Verifique a conexão e tente novamente.');
            }
        }

        $venda = $dados['venda'];

        if ($venda->status === 'emitida') {
            return back()->with('sucesso', 'Esta venda já foi emitida.');
        }

        try {
            $service = new FiscalEmissorService();
            $resultado = $service->emitir($venda);

            return back()->with('sucesso', 'Cupom fiscal emitido com sucesso! Chave: ' . $resultado['chave']);
        } catch (Exception $e) {
            return back()->with('erro_fiscal', $e->getMessage());
        }
    }

    /**
     * Busca a venda pelo UUID, primeiro no central (MySQL), depois no local (SQLite).
     * Retorna um formato unificado pra view conseguir exibir os dois casos.
     */
    protected function buscarVendaPorUuid(string $uuid): ?array
    {
        // 1. Tenta no MySQL central primeiro (caso mais comum, ja sincronizada)
        $venda = Venda::where('uuid', $uuid)->with('itens.produto')->first();

        if ($venda) {
            return [
                'origem' => 'central',
                'venda' => $venda,
                'itens' => $venda->itens,
                'total' => $venda->total,
                'status' => $venda->status,
                'chave_nfe' => $venda->chave_nfe,
            ];
        }

        // 2. Se nao achou, busca no SQLite local (ainda nao sincronizada)
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

        return [
            'origem' => 'local',
            'venda' => null,
            'itens' => $itensLocais,
            'total' => $vendaLocal->total,
            'status' => 'aguardando_sincronizacao',
            'chave_nfe' => null,
        ];
    }
}