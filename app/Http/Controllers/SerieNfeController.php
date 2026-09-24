<?php

namespace App\Http\Controllers;

use App\Models\SerieNfe;
use Illuminate\Http\Request;

class SerieNfeController extends Controller
{
    public function index()
    {
        $series = SerieNfe::orderBy('serie')->get();

        return view('series-nfe.index', compact('series'));
    }

    public function edit(SerieNfe $serieNfe)
    {
        return view('series-nfe.edit', compact('serieNfe'));
    }

    public function update(Request $request, SerieNfe $serieNfe)
    {
        $dados = $request->validate([
            'serie'        => ['required', 'integer', 'min:1'],
            'numero_atual' => ['required', 'integer', 'min:0'],
            'descricao'    => ['nullable', 'string', 'max:255'],
            'ativa'        => ['sometimes', 'boolean'],
        ]);

        $serieNfe->update([
            'serie'        => $dados['serie'],
            'numero_atual' => $dados['numero_atual'],
            'descricao'    => $dados['descricao'] ?? null,
            'ativa'        => $dados['ativa'] ?? false,
        ]);

        return redirect()->route('series-nfe.index')->with('sucesso', 'Série de NF-e atualizada com sucesso.');
    }
}