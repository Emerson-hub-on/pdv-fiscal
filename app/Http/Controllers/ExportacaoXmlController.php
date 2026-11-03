<?php

namespace App\Http\Controllers;

use App\Services\ExportacaoXmlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Caixa → Opções (F8) → Exportar XMLs: grava os XMLs das NFC-e deste computador, de um período,
 * num .zip na pasta escolhida pelo operador (pen drive, área de trabalho, pasta da contabilidade...).
 *
 * Os XMLs ficam em storage/app/XML_nfce/aa/mm/dd (dia em que foram gravados). Vão na exportação:
 * NFC-e autorizadas (*-nfe.xml), eventos de cancelamento (*-cancelamento.xml, gravados no dia do
 * cancelamento), inutilizações (*-inutilizada.xml) e NFC-e emitidas em contingência off-line
 * (*-contingencia.xml com tpEmis=9: documento válido, ainda aguardando transmissão).
 * Notas REJEITADAS ou com erro de dados (*-rejeitada.xml; antes da separação também ficavam em
 * *-contingencia.xml com tpEmis=1) não são documento fiscal válido: ficam de fora e só são contadas.
 *
 * Período, pastas e .zip: App\Services\ExportacaoXmlService (o mesmo da exportação de NF-e do painel).
 */
class ExportacaoXmlController extends Controller
{
    public function __construct(private ExportacaoXmlService $exportacao)
    {
    }

    /** Subpastas de um caminho; sem caminho, os atalhos (Área de trabalho, Documentos...) e as unidades. */
    public function pastas(Request $request)
    {
        return response()->json($this->exportacao->pastas($request->query('caminho')));
    }

    /** Quantos XMLs o período tem (o modal mostra antes de exportar). */
    public function resumo(Request $request)
    {
        [$de, $ate] = $this->exportacao->periodo($request);

        $arquivos = $this->arquivosDoPeriodo($de, $ate);

        return response()->json([
            'autorizadas' => count($arquivos['autorizadas']),
            'cancelamentos' => count($arquivos['cancelamentos']),
            'inutilizadas' => count($arquivos['inutilizadas']),
            'contingencia' => count($arquivos['contingencia']),
            'nao_autorizadas' => $arquivos['nao_autorizadas'],
        ]);
    }

    public function exportar(Request $request)
    {
        [$de, $ate] = $this->exportacao->periodo($request);

        $request->validate(['destino' => 'required|string|max:500'], [], ['destino' => 'pasta de destino']);

        $arquivos = $this->arquivosDoPeriodo($de, $ate);
        $lista = array_merge($arquivos['autorizadas'], $arquivos['contingencia'], $arquivos['cancelamentos'], $arquivos['inutilizadas']);

        if (!$lista) {
            return response()->json(['erro' => 'Nenhum XML encontrado neste período.'], 422);
        }

        // Um .zip por exportação, com o período no nome; dentro, uma pasta com o mesmo nome
        $nome = 'XML NFC-e ' . $de->format('d-m-Y') . ' a ' . $ate->format('d-m-Y');
        $entradas = array_map(fn ($arquivo) => ['nome' => basename($arquivo), 'arquivo' => $arquivo], $lista);

        $zip = $this->exportacao->montarZip($nome, $entradas);
        $arquivoZip = $this->exportacao->gravarNaPasta($zip['temporario'], $request->input('destino'), $nome);

        Log::info('Exportação de XMLs do caixa', [
            'operador_id' => Auth::id(),
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'arquivo' => $arquivoZip,
            'copiados' => $zip['copiados'],
            'falhas' => count($zip['falhas']),
        ]);

        return response()->json([
            'pasta' => $arquivoZip,
            'copiados' => $zip['copiados'],
            'autorizadas' => count($arquivos['autorizadas']),
            'cancelamentos' => count($arquivos['cancelamentos']),
            'inutilizadas' => count($arquivos['inutilizadas']),
            'contingencia' => count($arquivos['contingencia']),
            'nao_autorizadas' => $arquivos['nao_autorizadas'],
            'falhas' => array_slice($zip['falhas'], 0, 10),
            'qtd_falhas' => count($zip['falhas']),
        ]);
    }

    /** @return array{autorizadas: string[], contingencia: string[], cancelamentos: string[], inutilizadas: string[], nao_autorizadas: int} */
    private function arquivosDoPeriodo(Carbon $de, Carbon $ate): array
    {
        $resultado = ['autorizadas' => [], 'contingencia' => [], 'cancelamentos' => [], 'inutilizadas' => [], 'nao_autorizadas' => 0];

        foreach ($this->exportacao->arquivosPorDia(storage_path('app/XML_nfce'), $de, $ate) as $arquivo) {
            $nome = basename($arquivo);

            if (str_ends_with($nome, '-nfe.xml')) {
                $resultado['autorizadas'][] = $arquivo;
            } elseif (str_ends_with($nome, '-cancelamento.xml')) {
                $resultado['cancelamentos'][] = $arquivo;
            } elseif (str_ends_with($nome, '-inutilizada.xml') || str_ends_with($nome, '-inutilizado.xml')) { // -inutilizado: nome antigo
                $resultado['inutilizadas'][] = $arquivo;
            } elseif (str_ends_with($nome, '-contingencia.xml') && $this->exportacao->contingenciaOffline($arquivo)) {
                // Contingência off-line (tpEmis=9): documento válido, entregue ao consumidor; vai junto
                $resultado['contingencia'][] = $arquivo;
            } elseif (str_ends_with($nome, '-rejeitada.xml') || str_contains($nome, '-contingencia')) {
                // -rejeitada.xml: SEFAZ recusou ou erro de dados. -contingencia com tpEmis=1 ou -contingencia-HHMMSS:
                // rejeitadas gravadas antes da separação e cópias antigas. Nenhum é documento válido: não vão para o contador
                $resultado['nao_autorizadas']++;
            }
        }

        return $resultado;
    }
}
