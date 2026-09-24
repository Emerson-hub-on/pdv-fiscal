<?php

namespace App\Http\Controllers;

use App\Models\InutilizacaoNfe;
use App\Models\SerieNfe;
use App\Services\NotaFiscalService;
use Illuminate\Http\Request;

class InutilizacaoNfeController extends Controller
{
    public function executar(Request $request)
    {
        $dados = $request->validate([
            'serie_nfe_id'   => ['required', 'exists:series_nfe,id'],
            'numero_inicial' => ['required', 'integer', 'min:1'],
            'numero_final'   => ['required', 'integer', 'min:1', 'gte:numero_inicial'],
            'justificativa'  => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $serieNfe = SerieNfe::findOrFail($dados['serie_nfe_id']);

        try {
            $resultado = (new NotaFiscalService())->inutilizar(
                $serieNfe,
                (int) $dados['numero_inicial'],
                (int) $dados['numero_final'],
                $dados['justificativa']
            );
        } catch (\Throwable $e) {
            InutilizacaoNfe::create([
                'serie_nfe_id'   => $serieNfe->id,
                'serie'          => $serieNfe->serie,
                'numero_inicial' => $dados['numero_inicial'],
                'numero_final'   => $dados['numero_final'],
                'justificativa'  => $dados['justificativa'],
                'status'         => 'erro',
                'motivo'         => $e->getMessage(),
                'operador_id'    => auth()->id(),
            ]);

            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()]);
        }

        InutilizacaoNfe::create([
            'serie_nfe_id'   => $serieNfe->id,
            'serie'          => $serieNfe->serie,
            'numero_inicial' => $dados['numero_inicial'],
            'numero_final'   => $dados['numero_final'],
            'justificativa'  => $dados['justificativa'],
            'status'         => 'sucesso',
            'protocolo'      => $resultado['protocolo'],
            'motivo'         => $resultado['motivo'],
            'operador_id'    => auth()->id(),
        ]);

        // Avança o contador da série se o número inutilizado estiver à frente do atual —
        // evita reutilizar essa faixa numa emissão futura (mesmo problema de duplicidade de antes).
        if ($dados['numero_final'] > $serieNfe->numero_atual) {
            $serieNfe->update(['numero_atual' => $dados['numero_final']]);
        }

        return response()->json(['sucesso' => true, 'protocolo' => $resultado['protocolo']]);
    }
}