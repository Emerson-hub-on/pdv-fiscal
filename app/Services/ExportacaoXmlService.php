<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Peças comuns das exportações de XML (caixa: NFC-e; painel: NF-e):
 * período, navegador de pastas deste computador e gravação do .zip na pasta escolhida.
 *
 * O PHP roda no computador onde o sistema está instalado, então ele enxerga e grava nas pastas
 * dessa máquina (o navegador não informa o caminho de uma pasta).
 */
class ExportacaoXmlService
{
    public const MAX_DIAS = 366;
    private const MAX_PASTAS = 500;

    /** @return array{0: Carbon, 1: Carbon} */
    public function periodo(Request $request): array
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

    /**
     * Arquivos .xml das pastas por dia (base/aa/mm/dd) do período.
     *
     * @return string[]
     */
    public function arquivosPorDia(string $base, Carbon $de, Carbon $ate): array
    {
        $arquivos = [];

        for ($dia = $de->copy(); $dia->lte($ate); $dia->addDay()) {
            $pasta = $base . DIRECTORY_SEPARATOR . $dia->format('y') . DIRECTORY_SEPARATOR . $dia->format('m') . DIRECTORY_SEPARATOR . $dia->format('d');

            foreach (glob($pasta . DIRECTORY_SEPARATOR . '*.xml') ?: [] as $arquivo) {
                $arquivos[] = $arquivo;
            }
        }

        return $arquivos;
    }

    /** Subpastas de um caminho; sem caminho, os atalhos (Área de trabalho, Documentos...) e as unidades. */
    public function pastas(?string $caminho): array
    {
        $caminho = trim((string) $caminho);

        if ($caminho === '') {
            return [
                'caminho' => null,
                'pai' => null,
                'pastas' => array_merge($this->atalhos(), $this->unidades()),
            ];
        }

        $real = realpath($caminho);
        if ($real === false || !is_dir($real)) {
            abort(response()->json(['erro' => 'Pasta não encontrada.'], 422));
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

        return [
            'caminho' => $real,
            'pai' => $pai !== $real ? $pai : '',   // '' = voltar para "Este computador"
            'pastas' => $pastas,
            'gravavel' => is_writable($real),
        ];
    }

    /**
     * Monta o .zip numa pasta temporária. Cada entrada: ['nome' => 'arquivo.xml', 'arquivo' => caminho]
     * ou ['nome' => 'arquivo.xml', 'conteudo' => texto]. Dentro do zip, tudo fica na pasta $nome.
     *
     * @return array{temporario: string, copiados: int, falhas: string[]}
     */
    public function montarZip(string $nome, array $entradas): array
    {
        $temporario = storage_path('app/tmp-exportacao-' . uniqid() . '.zip');
        if (!is_dir(dirname($temporario))) {
            @mkdir(dirname($temporario), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($temporario, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(response()->json(['erro' => 'Não foi possível montar o arquivo .zip.'], 500));
        }

        $copiados = 0;
        $falhas = [];
        $usados = [];

        foreach ($entradas as $entrada) {
            $nomeArquivo = $entrada['nome'];
            if (isset($usados[$nomeArquivo])) {
                continue; // o mesmo XML não entra duas vezes
            }

            $ok = isset($entrada['conteudo'])
                ? $zip->addFromString($nome . '/' . $nomeArquivo, $entrada['conteudo'])
                : (is_readable($entrada['arquivo']) && $zip->addFile($entrada['arquivo'], $nome . '/' . $nomeArquivo));

            if ($ok) {
                $copiados++;
                $usados[$nomeArquivo] = true;
            } else {
                $falhas[] = $nomeArquivo;
            }
        }

        if (!$zip->close()) {
            @unlink($temporario);
            abort(response()->json(['erro' => 'Não foi possível montar o arquivo .zip.'], 500));
        }

        return ['temporario' => $temporario, 'copiados' => $copiados, 'falhas' => $falhas];
    }

    /**
     * Copia o zip montado para a pasta escolhida (montar fora e copiar no fim: um pen drive retirado
     * no meio não fica com zip pela metade). Devolve o caminho final.
     */
    public function gravarNaPasta(string $temporario, string $destino, string $nome): string
    {
        $pasta = realpath(trim($destino));
        if ($pasta === false || !is_dir($pasta)) {
            @unlink($temporario);
            abort(response()->json(['erro' => 'A pasta de destino não existe mais. Escolha outra.'], 422));
        }

        $arquivoZip = $this->juntar($pasta, $nome . '.zip');
        $gravou = @copy($temporario, $arquivoZip);
        @unlink($temporario);

        if (!$gravou) {
            abort(response()->json(['erro' => 'Não foi possível gravar o arquivo em "' . $pasta . '". Confira se a pasta permite gravação (pen drive protegido, pasta do sistema...).'], 422));
        }

        return $arquivoZip;
    }

    // tpEmis=9 = NFC-e emitida em contingência off-line (o tpEmis fica no começo do XML, dentro de <ide>)
    public function contingenciaOffline(string $arquivo): bool
    {
        $inicio = @file_get_contents($arquivo, false, null, 0, 4096);

        return is_string($inicio) && str_contains($inicio, '<tpEmis>9</tpEmis>');
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

    public function juntar(string $pasta, string $nome): string
    {
        return rtrim($pasta, '\\/') . DIRECTORY_SEPARATOR . $nome;
    }
}
