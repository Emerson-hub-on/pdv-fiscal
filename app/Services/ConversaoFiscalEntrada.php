<?php

namespace App\Services;

use App\Models\CfopEntradaConversao;
use App\Models\CstEntradaConversao;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\OperacaoEntrada;
use Illuminate\Support\Collection;

class ConversaoFiscalEntrada
{
    private Collection $cfops;
    private Collection $csts;
    private string $regime;

    public function __construct(OperacaoEntrada $operacao)
    {
        $this->regime = Empresa::atual()->crt == 3 ? 'normal' : 'simples';

        // carrega as regras uma vez só (evita consulta por item)
        $this->cfops = CfopEntradaConversao::where('operacao_entrada_id', $operacao->id)
            ->pluck('cfop_entrada_id', 'cfop_origem');

        $this->csts = CstEntradaConversao::where('regime', $this->regime)->get()
            ->keyBy(fn ($c) => $c->tipo_origem . ':' . $c->codigo_origem);
    }

    public function regime(): string
    {
        return $this->regime;
    }

    public function converter(?string $cfopOrigem, ?string $cst, ?string $csosn): array
    {
        $cstConv = $csosn
            ? $this->csts->get("csosn:{$csosn}")
            : ($cst ? $this->csts->get("cst:{$cst}") : null);

        return [
            'cfop_entrada_id'   => $cfopOrigem ? $this->cfops->get($cfopOrigem) : null,
            'cst_csosn_entrada' => $cstConv?->codigo_entrada,
            'gera_credito'      => $cstConv?->gera_credito,
        ];
    }

    /**
     * Entrada digitada (sem XML): presume um CFOP de origem pela UF do fornecedor
     * (mesma UF da empresa = 5102, outra UF = 6102) para achar o CFOP de entrada.
     */
    public function cfopOrigemPresumido(?Fornecedor $fornecedor): ?string
    {
        $ufEmpresa = Empresa::atual()->getAttributes()['uf'] ?? null;

        if (! $fornecedor?->uf || ! $ufEmpresa) {
            return null;
        }

        return strtoupper($fornecedor->uf) === strtoupper($ufEmpresa) ? '5102' : '6102';
    }
}