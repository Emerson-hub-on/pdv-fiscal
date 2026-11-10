<?php

namespace App\Http\Controllers;

use App\Models\IfoodConfiguracao;
use App\Services\IfoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Loja no iFood (janela do botão "iFood" na Lista de Pedidos): aberta/fechada e por quê, pausas e horário de funcionamento.
 * Situação e pausas ficam no módulo Comandas (é o dia a dia da cozinha); o horário, no módulo iFood (cadastro).
 */
class IfoodLojaController extends Controller
{
    public function __construct(private IfoodService $ifood)
    {
    }

    public function situacao(Request $request): JsonResponse
    {
        $cfg = IfoodConfiguracao::atual();
        $usuario = $request->user();

        $base = [
            'polling' => [
                'ultimo_em' => $cfg->ultimo_polling_em?->toIso8601String(),
                // Sem polling a cada 30 s o iFood fecha a loja ("Gestor de Pedidos ou PDV desconectado")
                'em_dia'    => $cfg->pollingEmDia(),
            ],
            'pode' => [
                'pausar'       => $usuario->podeAlterar('comandas'),
                'ver_horarios' => $usuario->podeVer('ifood'),
                'horarios'     => $usuario->podeAlterar('ifood'),
            ],
        ];

        if (!$cfg->ativo || !$cfg->credenciaisPreenchidas()) {
            return response()->json($base + ['configurado' => false]);
        }

        return $this->tentar(fn () => $base + ['configurado' => true]
            + $this->ifood->situacaoLoja($cfg, $request->boolean('renovar')));
    }

    public function pausar(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'minutos'   => ['required', 'integer', 'min:1', 'max:' . IfoodService::PAUSA_MAXIMA_MINUTOS],
            'descricao' => ['nullable', 'string', 'max:200'],
        ], [
            'minutos.max' => 'A pausa pode ter no máximo 7 dias.',
        ]);
        if ($v->fails()) {
            return $this->invalido($v);
        }
        $dados = $v->validated();

        return $this->tentar(function () use ($dados, $request) {
            $pausa = $this->ifood->pausarLoja(IfoodConfiguracao::atual(), (int) $dados['minutos'], (string) ($dados['descricao'] ?? ''));
            Log::info('iFood: loja pausada pelo sistema', ['usuario' => $request->user()->id, 'pausa' => $pausa]);

            return ['ok' => true, 'pausa' => $pausa];
        });
    }

    public function removerPausa(Request $request, string $pausa): JsonResponse
    {
        return $this->tentar(function () use ($pausa, $request) {
            $this->ifood->removerPausa(IfoodConfiguracao::atual(), $pausa);
            Log::info('iFood: pausa removida pelo sistema', ['usuario' => $request->user()->id, 'pausa' => $pausa]);

            return ['ok' => true];
        });
    }

    public function horarios(): JsonResponse
    {
        return $this->tentar(fn () => ['dias' => $this->ifood->horariosLoja(IfoodConfiguracao::atual())]);
    }

    public function salvarHorarios(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'dias'                  => ['required', 'array', 'size:7'],
            'dias.*.dia'            => ['required', 'string'],
            'dias.*.turnos'         => ['present', 'array', 'max:6'],
            'dias.*.turnos.*.inicio' => ['required', 'date_format:H:i'],
            'dias.*.turnos.*.fim'    => ['required', 'date_format:H:i'],
        ], [
            'dias.*.turnos.max'       => 'No máximo 6 turnos por dia.',
            'dias.*.turnos.*.*'       => 'Preencha a hora de início e de fim de todos os turnos (HH:MM).',
        ]);
        if ($v->fails()) {
            return $this->invalido($v);
        }
        $dados = $v->validated();

        return $this->tentar(function () use ($dados, $request) {
            $cfg = IfoodConfiguracao::atual();
            $this->ifood->salvarHorariosLoja($cfg, $dados['dias']);
            Log::info('iFood: horário de funcionamento alterado', ['usuario' => $request->user()->id]);

            return ['ok' => true, 'dias' => $this->ifood->horariosLoja($cfg)];
        });
    }

    /** 422 em JSON (o tratamento padrão do sistema redireciona fora de api/*, e a janela recarregaria). */
    private function invalido(\Illuminate\Validation\Validator $v): JsonResponse
    {
        return response()->json(['message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
    }

    private function tentar(callable $acao): JsonResponse
    {
        try {
            return response()->json($acao());
        } catch (Throwable $e) {
            $conexao = str_contains($e->getMessage(), 'Sem conexão com o iFood');

            return response()->json(['message' => $e->getMessage()], $conexao ? 503 : 422);
        }
    }
}
