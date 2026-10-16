<?php

namespace App\Http\Controllers;

use App\Services\CupomFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CupomFiscalController extends Controller
{
    /**
     * Vendas com NFC-e emitida ou em contingência, para o modal "Reimprimir cupom" do PDV.
     * Sem número: vendas da data informada (padrão: hoje). Com número: busca em qualquer data.
     */
    public function listar(Request $request): JsonResponse
    {
        $numero = preg_replace('/\D/', '', (string) $request->query('numero'));
        $data = (string) $request->query('data');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = now()->toDateString();
        }

        $vendas = DB::connection('sqlite_local')->table('vendas_pendentes')
            ->select('uuid', 'numero_nfce', 'serie_nfce', 'total', 'status_fiscal', 'vendida_em')
            ->whereIn('status_fiscal', ['emitida', 'contingencia'])
            ->whereNotNull('numero_nfce')
            ->when(
                $numero !== '',
                fn ($q) => $q->where('numero_nfce', (int) $numero),
                fn ($q) => $q->whereDate('vendida_em', $data)
            )
            ->orderByDesc('vendida_em')
            ->limit(100)
            ->get();

        return response()->json($vendas);
    }

    /** DANFE NFC-e em bobina 80 mm. Com ?auto=1 a própria página abre a impressão. */
    public function imprimir(string $uuid, CupomFiscalService $servico)
    {
        try {
            $cupom = $servico->dados($uuid);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return view('vendas.cupom-fiscal', ['c' => $cupom]);
    }
}