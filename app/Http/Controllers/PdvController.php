<?php

namespace App\Http\Controllers;

use App\Models\Pdv;
use Illuminate\Http\Request;

class PdvController extends Controller
{
    public function index()
    {
        $pdvs = Pdv::orderBy('nome')->get();
        return view('pdvs.index', compact('pdvs'));
    }

    public function create()
    {
        return view('pdvs.create');
    }

    public function store(Request $request)
    {
        $validado = $this->validarPdv($request);
        Pdv::create($validado);

        return redirect()->route('pdvs.index')->with('sucesso', 'PDV cadastrado com sucesso.');
    }

    public function edit(Pdv $pdv)
    {
        return view('pdvs.edit', compact('pdv'));
    }

    public function update(Request $request, Pdv $pdv)
    {
        $validado = $this->validarPdv($request, $pdv->id);
        $pdv->update($validado);

        return redirect()->route('pdvs.index')->with('sucesso', 'PDV atualizado com sucesso.');
    }

    public function toggleAtivo(Pdv $pdv)
    {
        $pdv->update(['ativo' => !$pdv->ativo]);

        return redirect()->route('pdvs.index')
            ->with('sucesso', $pdv->ativo ? 'PDV reativado.' : 'PDV inativado.');
    }

    private function validarPdv(Request $request, $idAtual = null): array
    {
        return $request->validate([
            'nome' => 'required|string|max:100',
            'serie_nfce' => 'required|integer|min:1|unique:pdvs,serie_nfce,' . $idAtual,
            'numero_atual_nfce' => 'required|integer|min:0',
            'csc' => 'required|string|max:100',
            'csc_id' => 'required|string|max:10',
        ]);
    }
}