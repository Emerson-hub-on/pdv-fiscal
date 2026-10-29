<?php

namespace App\Http\Controllers;

use App\Models\Mdfe;
use App\Models\SerieMdfe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Faturamento → MDF-e → Última numeração.
 * Define a série em uso e o último número já emitido (ex.: empresa que vem de outro sistema).
 * O próximo MDF-e sai com número = último + 1 (ver MdfeController::emitir()).
 */
class SerieMdfeController extends Controller
{
    /** Maior número aceito no nMDF do leiaute (9 dígitos). */
    private const NUMERO_MAXIMO = 999999999;

    public function editar()
    {
        $serieAtiva = SerieMdfe::ativas()->orderBy('id')->first();

        return view('mdfes.numeracao', [
            'serieAtiva' => $serieAtiva,
            // Maior número já usado em cada série (inclui rascunho que já recebeu número, cancelado e encerrado)
            'usados'     => Mdfe::query()
                ->whereNotNull('numero')
                ->selectRaw('serie, MAX(numero) as maior, COUNT(*) as total')
                ->groupBy('serie')
                ->orderBy('serie')
                ->get(),
        ]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'serie'        => ['required', 'integer', 'min:0', 'max:999'],
            'numero_atual' => ['required', 'integer', 'min:0', 'max:' . (self::NUMERO_MAXIMO - 1)],
        ], [
            'serie.max'        => 'A série do MDF-e vai de 0 a 999.',
            'numero_atual.max' => 'O último número precisa ser menor que 999.999.999 (o próximo MDF-e ainda tem que caber em 9 dígitos).',
        ], [
            'serie'        => 'série',
            'numero_atual' => 'último número emitido',
        ]);

        $serie = (int) $dados['serie'];
        $numero = (int) $dados['numero_atual'];

        // Nunca deixar o próximo número repetir um MDF-e que já existe nesta série
        $maiorUsado = (int) Mdfe::where('serie', $serie)->whereNotNull('numero')->max('numero');

        if ($numero < $maiorUsado) {
            throw ValidationException::withMessages([
                'numero_atual' => "Na série {$serie} o sistema já tem o MDF-e nº {$maiorUsado}. "
                    . "O último número não pode ser menor que ele, senão o próximo MDF-e repetiria uma numeração já usada.",
            ]);
        }

        DB::transaction(function () use ($serie, $numero) {
            // Uma série ativa por vez: é dela que o emitir() tira o próximo número
            SerieMdfe::where('serie', '!=', $serie)->lockForUpdate()->update(['ativo' => false]);

            $registro = SerieMdfe::where('serie', $serie)->lockForUpdate()->first() ?? new SerieMdfe(['serie' => $serie]);
            $registro->fill(['numero_atual' => $numero, 'ativo' => true])->save();
        });

        return redirect()->route('mdfes.numeracao.editar')
            ->with('sucesso', "Numeração do MDF-e salva: série {$serie}, próximo MDF-e nº " . number_format($numero + 1, 0, ',', '.') . '.');
    }
}
