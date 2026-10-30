<?php

namespace App\Http\Controllers;

use App\Services\LicencaService;
use Illuminate\Http\Request;

// Tela aberta mesmo sem login: só aceita códigos assinados, então qualquer um pode colar a licença nova
class LicencaController extends Controller
{
    public function tela(LicencaService $licenca)
    {
        return view('licenca.tela', ['situacao' => $licenca->situacao()]);
    }

    // Configuração → Licença, dentro do painel administrativo
    public function painel(LicencaService $licenca)
    {
        return view('licenca.painel', ['situacao' => $licenca->situacao()]);
    }

    public function registrar(Request $request, LicencaService $licenca)
    {
        $request->validate(['codigo' => 'required|string|max:1000']);

        $erro = $licenca->registrar($request->input('codigo'));

        if ($erro) {
            return back()->withErrors(['codigo' => $erro])->withInput();
        }

        // Volta para a tela de onde veio (pública ou Configuração → Licença)
        return back()->with('sucesso', 'Licença registrada. ' . $licenca->situacao()['mensagem']);
    }
}
