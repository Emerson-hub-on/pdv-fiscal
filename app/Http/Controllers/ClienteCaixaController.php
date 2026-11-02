<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modal "Adicionar consumidor" do caixa. Só usa o SQLite local:
 * - a busca lê o espelho clientes_cache (preenchido pelo SyncService::puxarClientes()) e os
 *   clientes cadastrados neste caixa que ainda não subiram;
 * - o cadastro rápido grava em clientes_pendentes, que o SyncService::enviarClientesPendentes()
 *   leva ao servidor antes das vendas.
 * Cliente ainda não enviado vai para a tela com id negativo (-id de clientes_pendentes).
 */
class ClienteCaixaController extends Controller
{
    /**
     * GET /clientes/buscar?q=termo
     * Sem termo, devolve os primeiros em ordem alfabética. Com termo, filtra
     * por nomes que COMEÇAM com o texto digitado (não "contém") ou pelo CPF/CNPJ.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));
        $termoNumerico = preg_replace('/\D/', '', $termo);
        $db = DB::connection('sqlite_local');

        $filtro = function ($q) use ($termo, $termoNumerico) {
            if ($termo === '') {
                return;
            }
            $q->where(function ($q) use ($termo, $termoNumerico) {
                $q->where('nome', 'like', "{$termo}%");
                if ($termoNumerico !== '') {
                    $q->orWhere('cpf_cnpj', 'like', "{$termoNumerico}%");
                }
            });
        };

        $cadastrados = $db->table('clientes_cache')
            ->where('ativo', true)
            ->where($filtro)
            ->orderBy('nome')->limit(20)
            ->get(['id', 'nome', 'cpf_cnpj'])
            ->map(fn ($c) => $this->item($c->id, $c->nome, $c->cpf_cnpj));

        // Os que já subiram estão no clientes_cache com o id do servidor
        $pendentes = $db->table('clientes_pendentes')
            ->whereNull('id_central')
            ->where($filtro)
            ->orderBy('nome')->limit(20)
            ->get(['id', 'nome', 'cpf_cnpj'])
            ->map(fn ($c) => $this->item(-$c->id, $c->nome, $c->cpf_cnpj, true));

        return response()->json(
            $cadastrados->concat($pendentes)->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)->take(20)->values()
        );
    }

    /**
     * POST /clientes/criar-rapido
     * Cadastro rápido sem sair da tela de pagamento: só nome + CPF/CNPJ. Fica no caixa
     * e sobe na próxima sincronização; o endereço pode ser completado depois no painel.
     */
    public function criarRapido(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_pessoa' => ['required', 'in:fisica,juridica'],
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => ['required', 'string'],
        ]);

        $data['nome'] = trim($data['nome']);
        $data['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);

        $tamanhoEsperado = $data['tipo_pessoa'] === 'fisica' ? 11 : 14;
        if (strlen($data['cpf_cnpj']) !== $tamanhoEsperado) {
            return $this->erroDocumento('Documento inválido para ' . ($data['tipo_pessoa'] === 'fisica' ? 'CPF (11 dígitos)' : 'CNPJ (14 dígitos)'));
        }

        $db = DB::connection('sqlite_local');

        $existente = $db->table('clientes_cache')->where('cpf_cnpj', $data['cpf_cnpj'])->value('nome')
            ?? $db->table('clientes_pendentes')->where('cpf_cnpj', $data['cpf_cnpj'])->value('nome');

        if ($existente) {
            return $this->erroDocumento("Já existe cliente com este documento: {$existente}. Busque pelo nome ou pelo CPF/CNPJ.");
        }

        $id = $db->table('clientes_pendentes')->insertGetId([
            'tipo_pessoa' => $data['tipo_pessoa'],
            'nome' => $data['nome'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'operador_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json($this->item(-$id, $data['nome'], $data['cpf_cnpj'], true));
    }

    private function item(int $id, string $nome, string $cpfCnpj, bool $pendente = false): array
    {
        $formatado = (new Cliente(['cpf_cnpj' => $cpfCnpj]))->cpf_cnpj_formatado;

        return [
            'id' => $id,
            'nome' => $nome,
            'cpf_cnpj' => $cpfCnpj,
            'cpf_cnpj_formatado' => $formatado,
            'label' => "{$nome} — {$formatado}" . ($pendente ? ' (cadastrado no caixa)' : ''),
        ];
    }

    private function erroDocumento(string $mensagem): JsonResponse
    {
        return response()->json(['errors' => ['cpf_cnpj' => [$mensagem]]], 422);
    }
}
