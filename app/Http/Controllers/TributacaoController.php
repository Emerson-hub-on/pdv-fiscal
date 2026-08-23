<?php

namespace App\Http\Controllers;

use App\Models\Tributacao;
use App\Models\Empresa;
use Illuminate\Http\Request;

class TributacaoController extends Controller
{
    public function listar(Request $request)
    {
        $empresa = Empresa::first();
        $crt = $empresa?->crt ?? 1;
        $termo = $request->get('q', '');

        $tributacoes = Tributacao::where('crt', $crt)
            ->where('ativo', true)
            ->when($termo, fn($q) => $q->where('descricao', 'like', "%{$termo}%"))
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'cfop', 'csosn', 'cst_icms', 'aliquota_icms', 'observacao']);

        return response()->json($tributacoes);
    }
}