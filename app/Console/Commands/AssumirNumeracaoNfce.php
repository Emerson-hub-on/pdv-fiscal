<?php

namespace App\Console\Commands;

use App\Models\Pdv;
use App\Models\Venda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssumirNumeracaoNfce extends Command
{
    protected $signature = 'nfce:assumir-numeracao
        {--pdv= : id do PDV (padrão: o PDV desta máquina)}
        {--forcar : segue mesmo com vendas ainda não emitidas no servidor}
        {--desfazer : devolve a emissão ao servidor}';

    protected $description = 'Passa a numeração e a emissão de NFC-e de um PDV para o caixa (precisa do servidor no ar).';

    public function handle(): int
    {
        $pdvId = (int) ($this->option('pdv') ?: config('app.pdv_id'));
        $pdv = Pdv::find($pdvId);

        if (!$pdv) {
            $this->error("PDV {$pdvId} não encontrado no servidor.");

            return self::FAILURE;
        }

        $local = DB::connection('sqlite_local');
        $contador = $local->table('numeracao_nfce')
            ->where('pdv_id', $pdvId)->where('serie', (string) $pdv->serie_nfce)->first();
        $ultimo = max((int) $pdv->numero_atual_nfce, (int) ($contador->ultimo_numero ?? 0));

        if ($this->option('desfazer')) {
            $pdv->forceFill(['numero_atual_nfce' => $ultimo, 'emissao_local' => false])->save();
            $local->table('pdvs_cache')->where('id', $pdvId)->update(['emissao_local' => false]);

            $this->info("Emissão devolvida ao servidor. Contador do servidor ajustado para {$ultimo}.");

            return self::SUCCESS;
        }

        // Vendas que o servidor ainda tem que emitir seriam bloqueadas depois da troca
        $naoEmitidas = Venda::whereIn('status', ['pendente', 'contingencia'])
            ->whereHas('caixa', fn ($q) => $q->where('pdv_id', $pdvId))
            ->count();

        if ($naoEmitidas > 0 && !$this->option('forcar')) {
            $this->error("Há {$naoEmitidas} venda(s) deste PDV ainda não emitidas no servidor (pendentes ou em contingência).");
            $this->line('Emita-as antes (F1) e rode de novo, ou use --forcar (elas passarão a ser emitidas pelo caixa).');

            return self::FAILURE;
        }

        $local->table('numeracao_nfce')->updateOrInsert(
            ['pdv_id' => $pdvId],
            ['serie' => (string) $pdv->serie_nfce, 'ultimo_numero' => $ultimo, 'created_at' => now(), 'updated_at' => now()]
        );

        $pdv->forceFill(['numero_atual_nfce' => $ultimo, 'emissao_local' => true])->save();
        $local->table('pdvs_cache')->where('id', $pdvId)->update(['emissao_local' => true]);

        $this->info("PDV {$pdv->nome} agora emite pelo caixa. Série {$pdv->serie_nfce}, último número usado: {$ultimo}.");

        return self::SUCCESS;
    }
}