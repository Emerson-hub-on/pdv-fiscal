<?php

namespace App\Http\Controllers;

use App\Models\CreditoLoja;
use Illuminate\Http\Request;

/** Financeiro → Créditos em loja (haver do cliente / vale-troca gerado pelas trocas). */
class CreditoLojaController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->get('busca', ''));
        $situacao = $request->get('situacao', 'com_saldo');
        $digitos = preg_replace('/\D/', '', $busca);

        $creditos = CreditoLoja::with(['cliente', 'notaFiscal', 'movimentos.notaFiscal'])
            ->when($situacao === 'com_saldo', fn ($q) => $q->disponiveis())
            ->when(in_array($situacao, ['esgotado', 'cancelado'], true), fn ($q) => $q->where('status', $situacao))
            ->when($busca !== '', function ($q) use ($busca, $digitos) {
                $q->where(function ($q) use ($busca, $digitos) {
                    $q->where('codigo', 'like', '%' . strtoupper($busca) . '%')
                      ->orWhereHas('cliente', function ($c) use ($busca, $digitos) {
                          $c->where('nome', 'like', "%{$busca}%");
                          if ($digitos !== '') {
                              $c->orWhere('cpf_cnpj', 'like', "%{$digitos}%");
                          }
                      });
                });
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $totalEmAberto = (float) CreditoLoja::disponiveis()->sum('saldo');

        return view('financeiro.creditos-loja.index', compact('creditos', 'busca', 'situacao', 'totalEmAberto'));
    }
}
