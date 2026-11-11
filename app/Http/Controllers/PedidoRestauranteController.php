<?php

namespace App\Http\Controllers;

use App\Models\CaixaLocal;
use App\Models\PdvCache;
use App\Services\EmissaoCaixaService;
use App\Services\VendaRestauranteService;
use App\Support\CentralStatus;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PDV → Puxar Pedido (F6) → Pedidos Restaurante: contas de mesa pagas e pedidos do iFood concluídos de um dia,
 * emitidos em NFC-e um a um pela tela (o operador pode pausar entre um e outro).
 * Os pedidos estão no servidor: sem conexão com ele, a tela avisa e não emite.
 */
class PedidoRestauranteController extends Controller
{
    public function __construct(private VendaRestauranteService $restaurante)
    {
    }

    public function listar(Request $request): JsonResponse
    {
        $data = $this->data($request->query('data'));

        if (!$data) {
            return response()->json(['message' => 'Escolha um dia até hoje.'], 422);
        }

        if (CentralStatus::fora()) {
            return response()->json(['message' => 'Servidor indisponível: os pedidos do restaurante ficam no servidor. Tente de novo quando a conexão voltar.'], 503);
        }

        try {
            return response()->json(['data' => $data, 'pedidos' => $this->restaurante->listar($data)]);
        } catch (\Throwable $e) {
            return $this->falha($e, 'Não foi possível carregar os pedidos do restaurante.');
        }
    }

    /** CPF e endereço do cliente de um pedido de entrega do iFood, informados na janela da pendência. */
    public function destinatario(Request $request): JsonResponse
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'id'          => 'required|integer|min:1',
            'cpf'         => 'required|string|max:20',
            'nome'        => 'nullable|string|max:60',
            'logradouro'  => 'required|string|max:60',
            'numero'      => 'nullable|string|max:60',
            'complemento' => 'nullable|string|max:60',
            'bairro'      => 'required|string|max:60',
            'cep'         => 'required|string|max:10',
            'cidade'      => 'required|string|max:60',
            'uf'          => 'required|string|size:2',
        ], [
            'cpf.required'        => 'Informe o CPF do cliente.',
            'logradouro.required' => 'Informe a rua.',
            'bairro.required'     => 'Informe o bairro.',
            'cep.required'        => 'Informe o CEP.',
            'cidade.required'     => 'Informe a cidade.',
            'uf.*'                => 'Informe a UF (2 letras).',
        ]);

        // JSON também no erro de validação (fora de api/* o padrão do sistema redireciona)
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        if (CentralStatus::fora()) {
            return response()->json(['message' => 'Servidor indisponível: os pedidos do restaurante ficam no servidor.'], 503);
        }

        try {
            $faltando = $this->restaurante->salvarDestinatario((int) $request->input('id'), $v->validated());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->falha($e, 'Não foi possível gravar os dados do cliente.');
        }

        return response()->json(['ok' => $faltando === [], 'faltando' => $faltando]);
    }

    /** Emite a NFC-e de um pedido (a tela chama um por vez, na ordem, até acabar ou o operador pausar). */
    public function emitir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => 'required|in:mesa,ifood',
            'id'   => 'required|integer|min:1',
            'data' => 'required|date_format:Y-m-d',
        ]);

        $caixa = CaixaLocal::aberto(Auth::id());

        if (!$caixa) {
            return response()->json(['message' => 'Nenhum caixa aberto.'], 422);
        }

        $pdv = PdvCache::find($caixa->pdv_id);

        if ($pdv && !$pdv->ativo) {
            return response()->json(['message' => 'Este PDV está inativo e não pode emitir.'], 422);
        }

        if (CentralStatus::fora()) {
            return response()->json(['message' => 'Servidor indisponível: a emissão dos pedidos do restaurante ficou pausada.'], 503);
        }

        $tipo = $dados['tipo'];
        $id = (int) $dados['id'];
        $db = DB::connection('sqlite_local');

        $jaAqui = $db->table('vendas_pendentes')
            ->where('restaurante_origem', $tipo)->where('restaurante_ref_id', $id)
            ->where(fn ($q) => $q->whereNull('status_fiscal')->orWhere('status_fiscal', '!=', 'cancelada'))
            ->exists();

        if ($jaAqui) {
            return response()->json(['message' => 'Este pedido já foi emitido neste caixa.'], 409);
        }

        $uuid = (string) Str::uuid();

        try {
            $venda = $this->restaurante->montar($tipo, $id);

            // Uma emissão é sempre de um dia só: o pedido precisa ser do dia escolhido na tela
            if (!$venda['momento'] || $venda['momento']->format('Y-m-d') !== $dados['data']) {
                throw new DomainException('Este pedido não é do dia escolhido. Atualize a lista.');
            }

            $this->restaurante->reservar($tipo, $id, $uuid);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->falha($e, 'Não foi possível preparar a venda deste pedido.');
        }

        try {
            $db->table('vendas_pendentes')->insert([
                'uuid' => $uuid,
                'caixa_id_central' => $caixa->id_central,
                'caixa_uuid' => $caixa->uuid,
                'operador_id_central' => Auth::id(),
                'cliente_id' => null,
                'cpf_na_nota' => $venda['cpf'],
                'total' => $venda['total'],
                'troco' => $venda['troco'],
                'desconto' => round(array_sum(array_column($venda['itens'], 'desconto')), 2),
                'forma_pagamento' => null,
                'pagamentos' => json_encode($venda['pagamentos']),
                'itens' => json_encode($venda['itens']),
                'status' => 'pendente_sync',
                'restaurante_origem' => $tipo,
                'restaurante_ref_id' => $id,
                'restaurante_rotulo' => $venda['rotulo'],
                'ind_pres' => $venda['ind_pres'],
                'destinatario' => $venda['destinatario'] ? json_encode($venda['destinatario'], JSON_UNESCAPED_UNICODE) : null,
                'intermediador_cnpj' => $venda['intermediador_cnpj'],
                'intermediador_id' => $venda['intermediador_id'],
                'vendida_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A venda não foi gravada: o pedido volta a ficar livre para emitir
            try {
                VendaRestauranteService::liberar($tipo, $id, $uuid);
            } catch (\Throwable) {
            }

            return $this->falha($e, 'Não foi possível gravar a venda no caixa.');
        }

        $emissao = app(EmissaoCaixaService::class)->emitir($uuid, $caixa);
        $local = $db->table('vendas_pendentes')->where('uuid', $uuid)->first(['status_fiscal', 'numero_nfce', 'motivo_rejeicao']);

        return response()->json([
            'venda_uuid'  => $uuid,
            'emissao'     => $emissao,
            'situacao'    => match ($local?->status_fiscal) {
                'emitida' => 'emitida',
                'contingencia' => 'contingencia',
                default => 'nao_emitida',
            },
            'numero_nfce' => $local?->numero_nfce,
            'motivo'      => $emissao['sucesso'] ? null : ($emissao['erro'] ?? $local?->motivo_rejeicao),
            'offline'     => CentralStatus::fora(),
        ]);
    }

    private function data(?string $valor): ?string
    {
        try {
            $data = Carbon::createFromFormat('Y-m-d', (string) $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $data->isAfter(today()) ? null : $data->format('Y-m-d');
    }

    private function falha(\Throwable $e, string $mensagem): JsonResponse
    {
        if (CentralStatus::erroDeConexao($e)) {
            CentralStatus::marcarFora();

            return response()->json(['message' => 'Servidor indisponível: a emissão dos pedidos do restaurante ficou pausada.'], 503);
        }

        Log::error($mensagem, ['erro' => $e->getMessage()]);

        return response()->json(['message' => $mensagem . ' ' . $e->getMessage()], 500);
    }
}
