<?php

namespace App\Console\Commands;

use App\Models\NotaFiscal;
use App\Models\Venda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa CFOP, CST/CSOSN, NCM e ICMS dos itens de notas antigas, emitidas antes de o sistema
 * gravar esses dados no banco. A fonte é o XML AUTORIZADO de cada nota (o que a SEFAZ aceitou),
 * casando o item do XML (nItem) com o item do banco pela ordem de gravação.
 *
 * Só preenche o que está vazio: nada já gravado é sobrescrito.
 */
class CompletarItensFiscais extends Command
{
    protected $signature = 'fiscal:completar-itens
        {--de= : Data inicial (AAAA-MM-DD) da emissão}
        {--ate= : Data final (AAAA-MM-DD) da emissão}
        {--simular : Só mostra o que seria alterado, sem gravar}';

    protected $description = 'Completa CFOP, CST/CSOSN, NCM e ICMS dos itens de NF-e e NFC-e antigas a partir do XML autorizado';

    private array $resumo = [];
    private array $problemas = [];
    private ?array $indiceXmlNfce = null;

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        if ($simular) {
            $this->warn('Modo simulação: nada será gravado.');
        }

        $this->processarNfe($simular);
        $this->processarNfce($simular);

        $this->newLine();
        $this->table(['Documento', 'Notas completadas', 'Itens completados', 'Sem XML', 'Não conferiram'],
            collect($this->resumo)->map(fn ($r, $doc) => [$doc, $r['notas'], $r['itens'], $r['sem_xml'], $r['divergentes']])->values()->all());

        if ($this->problemas) {
            $this->newLine();
            $this->warn('Notas que precisam de atenção (não foram alteradas):');
            foreach (array_slice($this->problemas, 0, 50) as $p) {
                $this->line(" - {$p}");
            }
            if (count($this->problemas) > 50) {
                $this->line(' ... e mais ' . (count($this->problemas) - 50));
            }
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // NF-e (modelo 55) — XML na coluna notas_fiscais.xml
    // ------------------------------------------------------------------

    private function processarNfe(bool $simular): void
    {
        $this->resumo['NF-e'] = ['notas' => 0, 'itens' => 0, 'sem_xml' => 0, 'divergentes' => 0];

        $notas = NotaFiscal::query()
            ->whereIn('status', ['emitida', 'cancelada'])
            ->whereHas('itens', fn ($q) => $q->where(fn ($q) => $q->whereNull('cfop')->orWhere('cfop', '')
                ->orWhereNull('cst_csosn')->orWhere('cst_csosn', '')))
            ->when($this->option('de'), fn ($q, $d) => $q->whereDate('emitida_em', '>=', $d))
            ->when($this->option('ate'), fn ($q, $d) => $q->whereDate('emitida_em', '<=', $d))
            ->with(['itens' => fn ($q) => $q->orderBy('id')])
            ->get();

        foreach ($notas as $nota) {
            $rotulo = "NF-e {$nota->numero}/{$nota->serie}";

            if (!$nota->xml) {
                $this->resumo['NF-e']['sem_xml']++;
                $this->problemas[] = "{$rotulo}: sem XML gravado.";
                continue;
            }

            $this->completar('NF-e', $rotulo, $nota->xml, $nota->itens, $simular, 'ncm_id');
        }
    }

    // ------------------------------------------------------------------
    // NFC-e (modelo 65) — XML indicado em vendas.ultimo_arquivo_xml
    // ------------------------------------------------------------------

    private function processarNfce(bool $simular): void
    {
        $this->resumo['NFC-e'] = ['notas' => 0, 'itens' => 0, 'sem_xml' => 0, 'divergentes' => 0];

        $vendas = Venda::query()
            ->whereNotNull('chave_nfe')
            ->whereHas('itens', fn ($q) => $q->where(fn ($q) => $q->whereNull('cfop')->orWhere('cfop', '')
                ->orWhereNull('cst_csosn')->orWhere('cst_csosn', '')
                ->orWhereNull('ncm')->orWhere('ncm', '')))
            ->when($this->option('de'), fn ($q, $d) => $q->whereDate('emitida_em', '>=', $d))
            ->when($this->option('ate'), fn ($q, $d) => $q->whereDate('emitida_em', '<=', $d))
            ->with(['itens' => fn ($q) => $q->orderBy('id')])
            ->get();

        foreach ($vendas as $venda) {
            $rotulo = "NFC-e {$venda->numero_nfce}/{$venda->serie_nfce}";
            $xml = $this->xmlDaVenda($venda);

            if (!$xml) {
                $this->resumo['NFC-e']['sem_xml']++;
                $this->problemas[] = "{$rotulo}: XML não encontrado (ultimo_arquivo_xml: " . ($venda->ultimo_arquivo_xml ?: 'vazio') . ').';
                continue;
            }

            $this->completar('NFC-e', $rotulo, $xml, $venda->itens, $simular, 'ncm');
        }
    }

    /** ultimo_arquivo_xml pode ser o próprio XML ou um caminho; sem isso, procura pela chave em storage/app. */
    private function xmlDaVenda(Venda $venda): ?string
    {
        $valor = trim((string) $venda->ultimo_arquivo_xml);

        if (str_starts_with($valor, '<')) {
            return $valor;
        }

        foreach (array_filter([$valor, $valor ? storage_path($valor) : null, $valor ? storage_path('app/' . ltrim($valor, '/\\')) : null]) as $caminho) {
            if (is_file($caminho)) {
                return file_get_contents($caminho);
            }
        }

        $arquivo = $this->indiceXmlNfce()[$venda->chave_nfe] ?? null;

        return $arquivo ? file_get_contents($arquivo) : null;
    }

    /** Índice chave => arquivo de todos os XML em storage/app (montado uma vez só). */
    private function indiceXmlNfce(): array
    {
        if ($this->indiceXmlNfce !== null) {
            return $this->indiceXmlNfce;
        }

        $this->indiceXmlNfce = [];
        $base = storage_path('app');

        if (!is_dir($base)) {
            return $this->indiceXmlNfce;
        }

        $arquivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

        foreach ($arquivos as $arquivo) {
            if (strtolower($arquivo->getExtension()) === 'xml' && preg_match('/(\d{44})/', $arquivo->getFilename(), $m)) {
                // Prefere o arquivo sem sufixo (o autorizado), e não o de cancelamento/evento
                if (!isset($this->indiceXmlNfce[$m[1]]) || !str_contains($arquivo->getFilename(), '-')) {
                    $this->indiceXmlNfce[$m[1]] = $arquivo->getPathname();
                }
            }
        }

        return $this->indiceXmlNfce;
    }

    // ------------------------------------------------------------------
    // Leitura do XML e gravação
    // ------------------------------------------------------------------

    private function completar(string $doc, string $rotulo, string $xml, $itens, bool $simular, string $campoNcm): void
    {
        $detalhes = $this->lerItensDoXml($xml);

        if ($detalhes === null) {
            $this->resumo[$doc]['divergentes']++;
            $this->problemas[] = "{$rotulo}: XML ilegível.";
            return;
        }

        // Sem a mesma quantidade de itens não dá para casar com segurança
        if (count($detalhes) !== $itens->count()) {
            $this->resumo[$doc]['divergentes']++;
            $this->problemas[] = "{$rotulo}: o XML tem " . count($detalhes) . " item(ns) e o banco tem {$itens->count()}.";
            return;
        }

        $alterados = 0;

        DB::transaction(function () use ($itens, $detalhes, $simular, $campoNcm, &$alterados) {
            foreach ($itens->values() as $i => $item) {
                $xml = $detalhes[$i];
                $dados = [];

                if (blank($item->cfop) && $xml['cfop'])           $dados['cfop'] = $xml['cfop'];
                if (blank($item->cst_csosn) && $xml['cst_csosn']) $dados['cst_csosn'] = $xml['cst_csosn'];

                // Na NFC-e o NCM fica no próprio item (texto); na NF-e é uma referência (ncm_id) e não é mexida aqui
                if ($campoNcm === 'ncm' && blank($item->ncm) && $xml['ncm']) $dados['ncm'] = $xml['ncm'];

                // ICMS só quando o item nunca teve os impostos registrados
                if (blank($item->impostos_registrados_em) && (float) $item->valor_icms == 0.0 && $xml['vicms'] > 0) {
                    $dados['bc_icms']       = $xml['vbc'];
                    $dados['aliquota_icms'] = $xml['picms'];
                    $dados['valor_icms']    = $xml['vicms'];
                }

                if ($dados) {
                    $alterados++;

                    if (!$simular) {
                        // Grava direto (sem eventos/updated_at): é correção de histórico, não alteração da nota
                        DB::table($item->getTable())->where('id', $item->id)->update($dados);
                    }
                }
            }
        });

        if ($alterados) {
            $this->resumo[$doc]['notas']++;
            $this->resumo[$doc]['itens'] += $alterados;
            $this->line("{$rotulo}: {$alterados} item(ns)" . ($simular ? ' seriam completados' : ' completados'));
        }
    }

    /** Lê os <det> do XML (com ou sem nfeProc), na ordem de nItem. */
    private function lerItensDoXml(string $xml): ?array
    {
        $dom = new \DOMDocument();

        if (!@$dom->loadXML($xml)) {
            return null;
        }

        $itens = [];

        foreach ($dom->getElementsByTagName('det') as $det) {
            $prod = $det->getElementsByTagName('prod')->item(0);
            $icms = $det->getElementsByTagName('ICMS')->item(0);

            // O grupo do ICMS é o primeiro filho elemento de <ICMS> (ICMS00, ICMSSN102, ...)
            $grupo = null;
            foreach ($icms?->childNodes ?? [] as $no) {
                if ($no instanceof \DOMElement) { $grupo = $no; break; }
            }

            $valor = fn ($pai, $tag) => $pai?->getElementsByTagName($tag)->item(0)?->nodeValue;

            $itens[(int) $det->getAttribute('nItem')] = [
                'cfop'      => $valor($prod, 'CFOP'),
                'ncm'       => $valor($prod, 'NCM'),
                'cst_csosn' => $valor($grupo, 'CST') ?? $valor($grupo, 'CSOSN'),
                'vbc'       => round((float) $valor($grupo, 'vBC'), 2),
                'picms'     => round((float) $valor($grupo, 'pICMS'), 4),
                'vicms'     => round((float) $valor($grupo, 'vICMS'), 2),
            ];
        }

        ksort($itens);

        return array_values($itens);
    }
}
