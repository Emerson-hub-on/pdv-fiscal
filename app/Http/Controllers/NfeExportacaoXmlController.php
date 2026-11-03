<?php

namespace App\Http\Controllers;

use App\Models\NotaFiscal;
use App\Services\ExportacaoXmlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Painel → Faturamento → Nota Fiscal → Exportar XML NF-e: o mesmo padrão do caixa (período + pasta, .zip).
 *
 * - NF-e autorizadas (inclusive as canceladas depois): o XML com protocolo guardado na própria nota
 *   (notas_fiscais.xml), pela data de autorização. No disco, autorizada e rejeitada usam o mesmo nome
 *   ({chave}.xml), por isso o banco é a fonte.
 * - Cancelamentos e inutilizações: os arquivos de storage/app/XML_nfe/aa/mm/dd, pelo dia do evento.
 * Rejeitadas e rascunhos não vão (não são documento fiscal).
 *
 * Além de gravar na pasta (computador do servidor), dá para baixar o .zip pelo navegador — útil quando o
 * painel é aberto em outro computador da rede.
 */
class NfeExportacaoXmlController extends Controller
{
    public function __construct(private ExportacaoXmlService $exportacao)
    {
    }

    public function tela()
    {
        return view('notasfiscais.exportar-xml');
    }

    public function pastas(Request $request)
    {
        return response()->json($this->exportacao->pastas($request->query('caminho')));
    }

    public function resumo(Request $request)
    {
        [$de, $ate] = $this->exportacao->periodo($request);
        $dados = $this->dadosDoPeriodo($de, $ate);

        return response()->json($this->contagem($dados));
    }

    public function exportar(Request $request)
    {
        [$de, $ate] = $this->exportacao->periodo($request);
        $request->validate(['destino' => 'required|string|max:500'], [], ['destino' => 'pasta de destino']);

        [$zip, $nome, $dados] = $this->montar($de, $ate);
        $arquivoZip = $this->exportacao->gravarNaPasta($zip['temporario'], $request->input('destino'), $nome);

        $this->registrar($de, $ate, $arquivoZip, $zip);

        return response()->json($this->contagem($dados) + [
            'pasta' => $arquivoZip,
            'copiados' => $zip['copiados'],
            'falhas' => array_slice($zip['falhas'], 0, 10),
            'qtd_falhas' => count($zip['falhas']),
        ]);
    }

    /** Mesmo .zip, entregue como download do navegador (painel aberto em outro computador). */
    public function baixar(Request $request)
    {
        [$de, $ate] = $this->exportacao->periodo($request);

        [$zip, $nome] = $this->montar($de, $ate);
        $this->registrar($de, $ate, 'download', $zip);

        return response()->download($zip['temporario'], $nome . '.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    // ---------------------------------------------------------------------

    private function montar(Carbon $de, Carbon $ate): array
    {
        $dados = $this->dadosDoPeriodo($de, $ate);

        $entradas = $dados['notas']->map(fn ($n) => ['nome' => $n->chave_acesso . '-nfe.xml', 'conteudo' => $n->xml])->all();
        foreach (array_merge($dados['cancelamentos'], $dados['inutilizadas']) as $arquivo) {
            $entradas[] = ['nome' => basename($arquivo), 'arquivo' => $arquivo];
        }

        if (!$entradas) {
            abort(response()->json(['erro' => 'Nenhum XML de NF-e neste período.'], 422));
        }

        $nome = 'XML NF-e ' . $de->format('d-m-Y') . ' a ' . $ate->format('d-m-Y');

        return [$this->exportacao->montarZip($nome, $entradas), $nome, $dados];
    }

    private function dadosDoPeriodo(Carbon $de, Carbon $ate): array
    {
        $notas = NotaFiscal::query()
            ->whereIn('status', ['emitida', 'cancelada'])
            ->whereNotNull('xml')
            ->whereNotNull('chave_acesso')
            ->whereBetween('emitida_em', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('emitida_em')
            ->get(['id', 'status', 'chave_acesso', 'xml']);

        $cancelamentos = [];
        $inutilizadas = [];
        foreach ($this->exportacao->arquivosPorDia(storage_path('app/XML_nfe'), $de, $ate) as $arquivo) {
            $nome = basename($arquivo);

            if (str_ends_with($nome, '-cancelamento.xml')) {
                $cancelamentos[] = $arquivo;
            } elseif (str_ends_with($nome, '-inutilizada.xml') || str_ends_with($nome, '-inutilizado.xml')) { // -inutilizado: nome antigo
                $inutilizadas[] = $arquivo;
            }
        }

        return compact('notas', 'cancelamentos', 'inutilizadas');
    }

    private function contagem(array $dados): array
    {
        return [
            'autorizadas' => $dados['notas']->count(),
            'canceladas_depois' => $dados['notas']->where('status', 'cancelada')->count(),
            'cancelamentos' => count($dados['cancelamentos']),
            'inutilizadas' => count($dados['inutilizadas']),
        ];
    }

    private function registrar(Carbon $de, Carbon $ate, string $destino, array $zip): void
    {
        Log::info('Exportação de XMLs de NF-e', [
            'usuario_id' => Auth::id(),
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'destino' => $destino,
            'copiados' => $zip['copiados'],
            'falhas' => count($zip['falhas']),
        ]);
    }
}
