<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Models\Pdv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        // Só barra a inativação; reativar é sempre permitido
        if ($pdv->ativo && Caixa::where('pdv_id', $pdv->id)->where('status', 'aberto')->exists()) {
            return redirect()->route('pdvs.index')
                ->withErrors(['pdv' => 'Este PDV tem caixa aberto. Feche o caixa antes de inativá-lo.']);
        }

        $pdv->update(['ativo' => !$pdv->ativo]);

        return redirect()->route('pdvs.index')
            ->with('sucesso', $pdv->ativo ? 'PDV reativado.' : 'PDV inativado.');
    }

    private function validarPdv(Request $request, $idAtual = null): array
    {
        $maquina = strtoupper(trim((string) $request->input('maquina')));
        $request->merge(['maquina' => $maquina !== '' ? $maquina : null]);

        return $request->validate([
            'nome' => 'required|string|max:100',
            'maquina' => ['nullable', 'string', 'max:60', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('pdvs', 'maquina')->ignore($idAtual)],
            'serie_nfce' => 'required|integer|min:1|unique:pdvs,serie_nfce,' . $idAtual,
            'numero_atual_nfce' => 'required|integer|min:0',
            'csc' => 'required|string|max:100',
            'csc_id' => 'required|string|max:10',
        ]);
    }
}