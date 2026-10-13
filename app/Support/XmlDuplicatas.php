<?php

namespace App\Support;

/**
 * Higieniza a lista de duplicatas (número, vencimento, valor) lida do XML pelo NfeXmlParser
 * antes de gravar no rascunho da entrada.
 */
class XmlDuplicatas
{
    /**
     * Mantém só itens com vencimento Y-m-d válido e valor positivo, ordenados por vencimento.
     *
     * @return array<int, array{numero: string, vencimento: string, valor: float}>
     */
    public static function normalizar(mixed $dados): array
    {
        if (! is_array($dados)) {
            return [];
        }

        $lista = [];
        foreach ($dados as $d) {
            if (! is_array($d)) {
                continue;
            }

            $venc  = (string) ($d['vencimento'] ?? '');
            $valor = round((float) ($d['valor'] ?? 0), 2);

            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $venc, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $valor <= 0) {
                continue;
            }

            $lista[] = [
                'numero'     => mb_substr((string) ($d['numero'] ?? ''), 0, 20),
                'vencimento' => $venc,
                'valor'      => $valor,
            ];
        }

        usort($lista, fn ($a, $b) => strcmp($a['vencimento'], $b['vencimento']));

        return $lista;
    }
}