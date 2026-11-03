<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Caixa → Opções (F8) → Exportar XMLs: copia os XMLs das NFC-e deste computador, de um período,
 * para uma pasta escolhida pelo operador (pen drive, área de trabalho, pasta da contabilidade...).
 *
 * Os XMLs ficam em storage/app/XML_nfce/aa/mm/dd (dia em que foram gravados). Vão na exportação:
 * NFC-e autorizadas (*-nfe.xml) e inutilizações (*-inutilizado.xml). As que ainda estão em
 * contingência (sem autorização da SEFAZ) ficam de fora e só são contadas.
 *
 * O caixa roda neste mesmo computador, então o PHP enxerga as pastas do operador: a escolha da
 * pasta é feita por aqui (o navegador não informa o caminho de uma pasta).
 */
class ExportacaoXmlController extends Controller
{
    private const MAX_DIAS = 366;
    private const MAX_PASTAS = 500;

    /** Subpastas de um caminho; sem caminho, os atalhos (Área de trabalho, Documentos...) e as unidades. */
    public function pastas(Request $request)
    {
        $caminho = trim((string) $request->query('caminho', ''));

        if ($caminho === '') {
            return response()->json([
                'caminho' => null,
                'pai' => null,
                'pastas' => array_merge($this->atalhos(), $this->unidades()),
            ]);
        }

        $real = realpath($caminho);
        if ($real === false || !is_dir($real)) {
            return response()->json(['erro' => 'Pasta não encontrada.'], 422);
        }

        $pastas = [];
        foreach (@scandir($real) ?: [] as $nome) {
            if ($nome === '.' || $nome === '..' || str_starts_with($nome, '.') || str_starts_with($nome, '$')) {
                continue;
            }

            $completo = $this->juntar($real, $nome);
            if (@is_dir($completo) && !is_link($completo)) {
                $pastas[] = ['nome' => $nome, 'caminho' => $completo];
            }

            if (count($pastas) >= self::MAX_PASTAS) {
                break;
            }
        }

        usort($pastas, fn ($a, $b) => strnatcasecmp($a['nome'], $b['nome']));

        $pai = dirname($real);

        return response()->json([
            'caminho' => $real,
            'pai' => $pai !== $real ? $pai : '',   // '' = voltar para "Este computador"
            'pastas' => $pastas,
            'gravavel' => is_writable($real),
        ]);
    }

    /** Quantos XMLs o período tem (o modal mostra antes de exportar). */
    public function resumo(Request $request)
    {
        [$de, $ate] = $this->periodo($request);

        $arquivos = $this->arquivosDoPeriodo($de, $ate);

        return response()->json([
            'autorizadas' => count($arquivos['autorizadas']),
            'inutilizadas' => count($arquivos['inutilizadas']),
            'contingencia' => $arquivos['contingencia'],
        ]);
    }

    public function exportar(Request $request)
    {
        [$de, $ate] = $this->periodo($request);

        $request->validate(['destino' => 'required|string|max:500'], [], ['destino' => 'pasta de destino']);

        $destino = realpath(trim($request->input('destino')));
        if ($destino === false || !is_dir($destino)) {
            return response()->json(['erro' => 'A pasta de destino não existe mais. Escolha outra.'], 422);
        }

        $arquivos = $this->arquivosDoPeriodo($de, $ate);
        $lista = array_merge($arquivos['autorizadas'], $arquivos['inutilizadas']);

        if (!$lista) {
            return response()->json(['erro' => 'Nenhum XML encontrado neste período.'], 422);
        }

        // Uma subpasta por exportação, com o período no nome
        $pasta = $this->juntar($destino, 'XML NFC-e ' . $de->format('d-m-Y') . ' a ' . $ate->format('d-m-Y'));

        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true)) {
            return response()->json(['erro' => 'Não foi possível criar a pasta em "' . $destino . '". Confira se ela permite gravação (pen drive protegido, pasta do sistema...).'], 422);
        }

        $copiados = 0;
        $falhas = [];
        foreach ($lista as $origem) {
            if (@copy($origem, $this->juntar($pasta, basename($origem)))) {
                $copiados++;
            } else {
                $falhas[] = basename($origem);
            }
        }

        Log::info('Exportação de XMLs do caixa', [
            'operador_id' => Auth::id(),
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'pasta' => $pasta,
            'copiados' => $copiados,
            'falhas' => count($falhas),
        ]);

        return response()->json([
            'pasta' => $pasta,
            'copiados' => $copiados,
            'autorizadas' => count($arquivos['autorizadas']),
            'inutilizadas' => count($arquivos['inutilizadas']),
            'contingencia' => $arquivos['contingencia'],
            'falhas' => array_slice($falhas, 0, 10),
            'qtd_falhas' => count($falhas),
        ]);
    }

    // ---------------------------------------------------------------------

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodo(Request $request): array
    {
        $validado = $request->validate([
            'de' => 'required|date_format:Y-m-d',
            'ate' => 'required|date_format:Y-m-d|after_or_equal:de',
        ], [
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ], [
            'de' => 'data inicial',
            'ate' => 'data final',
        ]);

        $de = Carbon::createFromFormat('!Y-m-d', $validado['de']);
        $ate = Carbon::createFromFormat('!Y-m-d', $validado['ate']);

        if ($de->diffInDays($ate) >= self::MAX_DIAS) {
            abort(response()->json(['erro' => 'Escolha um período de até 1 ano.'], 422));
        }

        return [$de, $ate];
    }

    /** @return array{autorizadas: string[], inutilizadas: string[], contingencia: int} */
    private function arquivosDoPeriodo(Carbon $de, Carbon $ate): array
    {
        $base = storage_path('app/XML_nfce');
        $resultado = ['autorizadas' => [], 'inutilizadas' => [], 'contingencia' => 0];

        for ($dia = $de->copy(); $dia->lte($ate); $dia->addDay()) {
            $pasta = $base . DIRECTORY_SEPARATOR . $dia->format('y') . DIRECTORY_SEPARATOR . $dia->format('m') . DIRECTORY_SEPARATOR . $dia->format('d');

            foreach (glob($pasta . DIRECTORY_SEPARATOR . '*.xml') ?: [] as $arquivo) {
                $nome = basename($arquivo);

                if (str_ends_with($nome, '-nfe.xml')) {
                    $resultado['autorizadas'][] = $arquivo;
                } elseif (str_ends_with($nome, '-inutilizado.xml')) {
                    $resultado['inutilizadas'][] = $arquivo;
                } elseif (str_contains($nome, '-contingencia')) {
                    $resultado['contingencia']++;
                }
            }
        }

        return $resultado;
    }

    private function atalhos(): array
    {
        $perfil = getenv('USERPROFILE') ?: getenv('HOME');
        if (!$perfil) {
            return [];
        }

        $atalhos = [];
        $opcoes = [
            'Área de trabalho' => ['Desktop', 'OneDrive' . DIRECTORY_SEPARATOR . 'Desktop', 'OneDrive' . DIRECTORY_SEPARATOR . 'Área de Trabalho'],
            'Documentos' => ['Documents', 'OneDrive' . DIRECTORY_SEPARATOR . 'Documents', 'OneDrive' . DIRECTORY_SEPARATOR . 'Documentos'],
            'Downloads' => ['Downloads'],
        ];

        foreach ($opcoes as $rotulo => $candidatas) {
            foreach ($candidatas as $relativo) {
                $completo = $this->juntar($perfil, $relativo);
                if (is_dir($completo)) {
                    $atalhos[] = ['nome' => $rotulo, 'caminho' => $completo, 'tipo' => 'atalho'];
                    break;
                }
            }
        }

        return $atalhos;
    }

    // Unidades do Windows (C:, D:, pen drive...); em outro sistema, a raiz
    private function unidades(): array
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return [['nome' => '/', 'caminho' => '/', 'tipo' => 'unidade']];
        }

        $unidades = [];
        foreach (range('C', 'Z') as $letra) {
            if (@is_dir("{$letra}:\\")) {
                $unidades[] = ['nome' => "Unidade {$letra}:", 'caminho' => "{$letra}:\\", 'tipo' => 'unidade'];
            }
        }

        return $unidades;
    }

    private function juntar(string $pasta, string $nome): string
    {
        return rtrim($pasta, '\\/') . DIRECTORY_SEPARATOR . $nome;
    }
}
