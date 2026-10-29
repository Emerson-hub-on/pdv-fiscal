<?php

namespace App\Console\Commands;

use App\Models\EntradaNota;
use App\Services\CreditoPisCofinsEntrada;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Calcula (ou recalcula) o PIS/COFINS de entrada das notas já lançadas.
 * Use depois de criar as colunas, ou depois de mudar o regime na Configuração do SPED.
 * Itens com ajuste manual mantêm CST/base/alíquotas; só os valores são recalculados.
 */
class CalcularPisCofinsEntradas extends Command
{
    protected $signature = 'fiscal:pis-cofins-entradas
                            {--de= : Data de entrada inicial (AAAA-MM-DD)}
                            {--ate= : Data de entrada final (AAAA-MM-DD)}
                            {--simular : Mostra o que mudaria, sem gravar}';

    protected $description = 'Calcula o PIS/COFINS de entrada (crédito) das notas de entrada já lançadas';

    public function handle(CreditoPisCofinsEntrada $servico): int
    {
        $regime = CreditoPisCofinsEntrada::regime();

        if ($regime === null) {
            $this->error('Nada a fazer: a empresa não é CRT 3 ou o regime do PIS/COFINS não foi definido na Configuração do SPED.');
            return self::FAILURE;
        }

        $entradas = EntradaNota::query()
            ->where('status', '!=', 'cancelada')
            ->when($this->option('de'), fn ($q, $d) => $q->whereDate('data_entrada', '>=', $d))
            ->when($this->option('ate'), fn ($q, $d) => $q->whereDate('data_entrada', '<=', $d))
            ->orderBy('data_entrada')
            ->get();

        $simular = (bool) $this->option('simular');
        $this->info("Regime: {$regime}. Entradas: {$entradas->count()}" . ($simular ? ' (simulação, nada será gravado)' : ''));

        $linhas = [];
        $pendentes = [];

        foreach ($entradas as $entrada) {
            DB::beginTransaction();

            try {
                $servico->aplicar($entrada);

                $tot = DB::table('entrada_nota_itens')->where('entrada_nota_id', $entrada->id)
                    ->selectRaw('SUM(valor_pis) pis, SUM(valor_cofins) cofins, SUM(cst_pis_cofins IS NULL) sem_cst')
                    ->first();

                // por que ficou sem CST: produto sem classificação ou CFOP de entrada pendente
                $semCst = DB::table('entrada_nota_itens as i')
                    ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
                    ->where('i.entrada_nota_id', $entrada->id)
                    ->whereNull('i.cst_pis_cofins')
                    ->get(['i.id', 'i.descricao', 'i.cfop_entrada_id', 'p.pis_cofins_id']);

                foreach ($semCst as $s) {
                    $motivos = array_filter([
                        $s->pis_cofins_id ? null : 'produto sem classificação de PIS/COFINS',
                        $s->cfop_entrada_id ? null : 'CFOP de entrada pendente',
                    ]);
                    $pendentes[] = [$entrada->id, $s->id, mb_strimwidth((string) $s->descricao, 0, 50, '…'), implode(' e ', $motivos) ?: '—'];
                }
                $linhas[] = [
                    $entrada->id,
                    "{$entrada->numero}/{$entrada->serie}",
                    $entrada->data_entrada?->format('d/m/Y'),
                    number_format((float) $tot->pis, 2, ',', '.'),
                    number_format((float) $tot->cofins, 2, ',', '.'),
                    (int) $tot->sem_cst,
                ];

                $simular ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Entrada {$entrada->id}: {$e->getMessage()}");
            }
        }

        $this->table(['ID', 'Nota', 'Entrada', 'Crédito PIS', 'Crédito COFINS', 'Itens sem CST'], $linhas);

        if ($pendentes) {
            $this->warn(count($pendentes) . ' item(ns) sem CST de PIS/COFINS:');
            $this->table(['Entrada', 'Item', 'Descrição', 'Motivo'], $pendentes);
        }

        return self::SUCCESS;
    }
}