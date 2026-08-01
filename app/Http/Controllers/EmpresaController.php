<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function editar()
    {
        $empresa = Empresa::first() ?? new Empresa();
        return view('empresa.form', compact('empresa'));
    }

    public function salvar(Request $request)
    {
        $validado = $request->validate([
            'cnpj' => 'required|string|size:14',
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'ie' => 'nullable|string|max:20',
            'im' => 'nullable|string|max:20',
            'crt' => 'required|integer|in:1,2,3',

            'logradouro' => 'required|string|max:255',
            'numero' => 'required|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'required|string|max:100',
            'cep' => 'required|string|size:8',
            'municipio' => 'required|string|max:100',
            'cod_municipio' => 'required|string|size:7',
            'uf' => 'required|string|size:2',

            'certificado' => ['nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value && !in_array(strtolower($value->getClientOriginalExtension()), ['pfx', 'p12'])) {
                    $fail('O certificado deve ser um arquivo .pfx ou .p12.');
                }
            }],
            'certificado_senha' => 'nullable|string',
            'certificado_validade' => 'nullable|date',

            'ambiente' => 'required|integer|in:1,2',
            // REMOVIDO: csc, csc_id, serie_nfce, serie_nfe — agora ficam em pdvs, não em empresa
        ]);

        $empresa = Empresa::first() ?? new Empresa();

        // Se um novo certificado foi enviado, converte pra base64 e substitui
        if ($request->hasFile('certificado')) {
            $validado['certificado_base64'] = base64_encode(
                file_get_contents($request->file('certificado')->getRealPath())
            );
        } else {
            unset($validado['certificado']);
        }

        $empresa->fill($validado);
        $empresa->save();

        return redirect()->route('empresa.editar')
            ->with('sucesso', 'Dados da empresa salvos com sucesso.');
    }
}