<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Venda;
use App\Services\MontagemNfeService;
use Illuminate\Http\Request;

/** Faturamento → Montagem NF-e → A partir de NFC-e */
class MontagemNfeController extends Controller
{
    public function __construct(private MontagemNfeService $montagem)
    {
    }

    public function index(Request $request)
    {
        $modo = $request->get('modo') === 'data' ? 'data' : 'cupom';
        $data = $request->filled('data') && strtotime($request->get('data'))
            ? date('Y-m-d', strtotime($request->get('data')))
            : now()->toDateString();

        $vendas = collect();
        $pendentes = collect();
        $resultadoCupom = null;

        if ($modo === 'cupom') {
            if ($request->filled('serie') || $request->filled('numero')) {
                $dados = $request->validate([
                    'serie'  => ['required', 'integer', 'min:0', 'max:999'],
                    'numero' => ['required', 'integer', 'min:1', 'max:999999999'],
                ], [], ['serie' => 'série', 'numero' => 'número do cupom']);

                $resultadoCupom = $this->montagem->buscarPorCupom((int) $dados['serie'], (int) $dados['numero']);

                if ($resultadoCupom['venda']) {
                    $vendas = collect([$resultadoCupom['venda']]);
                }
            }
        } else {
            $vendas = $this->montagem->buscarPorData($data);
            $pendentes = $this->montagem->pendentesPorData($data);
        }

        // Uma única série de NFC-e nos PDVs ativos: já vem preenchida
        $seriesPdv = \App\Models\Pdv::where('ativo', true)->distinct()->pluck('serie_nfce');
        $seriePadrao = $seriesPdv->count() === 1 ? $seriesPdv->first() : null;

        $bloqueios = $vendas->mapWithKeys(fn (Venda $v) => [$v->id => MontagemNfeService::erroVendaParaMontagem($v)]);
        $nfesGeradas = $vendas->mapWithKeys(fn (Venda $v) => [$v->id => MontagemNfeService::nfeJaGerada($v)]);

        return view('faturamento.montagem-nfe.nfce', compact(
            'modo', 'data', 'seriePadrao', 'vendas', 'pendentes', 'resultadoCupom', 'bloqueios', 'nfesGeradas'
        ));
    }

    /** Abre a tela da NF-e já preenchida com os dados da NFC-e; ao salvar vira rascunho. */
    public function montar(Venda $venda)
    {
        $erro = MontagemNfeService::erroVendaParaMontagem($venda);

        if ($erro) {
            return redirect()
                ->route('montagem-nfe.nfce.index', ['modo' => 'cupom', 'serie' => $venda->serie_nfce, 'numero' => $venda->numero_nfce])
                ->withErrors(['venda' => $erro]);
        }

        [$nota, $avisos] = $this->montagem->montar($venda);

        return view('notasfiscais.create', [
            'clientes'       => Cliente::ativos()->orderBy('nome')->get(),
            'crtEmpresa'     => Empresa::first()->crt,
            'notaFiscal'     => null,
            'notaPreMontada' => $nota,
            'avisosMontagem' => $avisos,
        ]);
    }
}
