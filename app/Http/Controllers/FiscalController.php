<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\EmissorLocalService;
use App\Services\FiscalEmissorService;
use App\Services\SyncService;
use App\Support\CentralStatus;
use App\Support\EmissaoLocal;
use Illuminate\Support\Facades\DB;
use App\Models\CaixaLocal;
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
        $vendaLocal = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $uuid)->first();

        // PDV que emite pelo caixa: tudo acontece no SQLite
        if (EmissaoLocal::ativa($this->pdvIdDaVenda($vendaLocal))) {
            return $this->emitirPeloCaixa($uuid);
        }

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

    private function emitirPeloCaixa(string $uuid)
    {
        $venda = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $uuid)->first();

        if (!$venda) {
            return response()->json(['sucesso' => false, 'erro' => 'Venda não encontrada.'], 404);
        }

        if ($venda->status_fiscal === 'emitida') {
            return response()->json(['sucesso' => true, 'ja_emitida' => true, 'chave' => $venda->chave_nfe]);
        }

        try {
            $resultado = (new EmissorLocalService())->emitirLocal($uuid);
            $resposta = ['sucesso' => true, 'chave' => $resultado['chave']];
        } catch (\Throwable $e) {
            $situacao = DB::connection('sqlite_local')->table('vendas_pendentes')
                ->where('uuid', $uuid)->value('status_fiscal');

            $resposta = [
                'sucesso' => false,
                'contingencia' => $situacao === 'contingencia',
                'erro' => $e->getMessage(),
            ];
        }

        (new SyncService())->enviarSePossivel();

        return response()->json($resposta);
    }

    // PDV do caixa em que a venda foi feita (null se não achar: cai no PDV do caixa aberto)
    private function pdvIdDaVenda(?object $vendaLocal): ?int
    {
        if (!$vendaLocal || empty($vendaLocal->caixa_uuid)) {
            return null;
        }

        $pdvId = CaixaLocal::where('uuid', $vendaLocal->caixa_uuid)->value('pdv_id');

        return $pdvId ? (int) $pdvId : null;
    }

    /**
     * Busca a venda pelo UUID. Com a emissão local ativa, o SQLite é a fonte da verdade;
     * senão procura primeiro no central (MySQL) e depois no local.
     * Retorna um formato unificado pra view conseguir exibir os dois casos.
     */
    protected function buscarVendaPorUuid(string $uuid): ?array
    {
        $vendaLocal = DB::connection('sqlite_local')->table('vendas_pendentes')->where('uuid', $uuid)->first();

        if ($vendaLocal && EmissaoLocal::ativa($this->pdvIdDaVenda($vendaLocal))) {
            return $this->dadosDaVendaLocal($vendaLocal, true);
        }

        $venda = $this->vendaCentral($uuid);

        if ($venda) {
            return [
                'origem' => 'central',
                'venda' => $venda,
                'itens' => $venda->itens,
                'pagamentos' => $venda->pagamentos,
                'troco' => $venda->troco,
                'desconto' => $venda->desconto,
                'total' => $venda->total,
                'status' => $venda->status,
                'chave_nfe' => $venda->chave_nfe,
            ];
        }

        return $vendaLocal ? $this->dadosDaVendaLocal($vendaLocal, false) : null;
    }

    private function dadosDaVendaLocal(object $vendaLocal, bool $emissaoLocal): array
    {
        $itensLocais = collect(json_decode($vendaLocal->itens, true))->map(function ($item) {
            $produto = DB::connection('sqlite_local')->table('produtos_cache')
                ->where('id', $item['produto_id'])->first()
                ?? (object) ['nome' => 'Produto #' . $item['produto_id']];

            return (object) [
                'produto' => $produto,
                'quantidade' => $item['quantidade'],
                'subtotal' => ($item['preco_unitario'] * $item['quantidade']) - ($item['desconto'] ?? 0),
            ];
        });

        $pagamentosLocais = collect(json_decode($vendaLocal->pagamentos, true) ?? [])
            ->map(fn ($p) => (object) $p);

        $status = $emissaoLocal
            ? match ($vendaLocal->status_fiscal) {
                'emitida' => 'emitida',
                'contingencia' => 'contingencia',
                default => 'pendente',
            }
            : 'aguardando_sincronizacao';

        return [
            'origem' => 'local',
            'venda' => null,
            'itens' => $itensLocais,
            'pagamentos' => $pagamentosLocais,
            'troco' => $vendaLocal->troco ?? 0,
            'desconto' => $vendaLocal->desconto ?? 0,
            'total' => $vendaLocal->total,
            'status' => $status,
            'chave_nfe' => $vendaLocal->chave_nfe,
        ];
    }

    /**
     * Venda no servidor central. Servidor fora do ar não é "venda não encontrada":
     * devolve null e quem chama segue para o SQLite.
     */
    private function vendaCentral(string $uuid): ?Venda
    {
        if (CentralStatus::fora()) {
            return null;
        }

        try {
            return Venda::where('uuid', $uuid)->with('itens.produto', 'pagamentos')->first();
        } catch (\Throwable $e) {
            if (CentralStatus::erroDeConexao($e)) {
                CentralStatus::marcarFora();

                return null;
            }

            throw $e;
        }
    }
}