<?php

namespace App\Http\Controllers;

use App\Models\Condutor;
use App\Models\Empresa;
use App\Models\Mdfe;
use App\Models\Municipio;
use App\Models\SpedConfiguracao;
use App\Models\Transportador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpedConfiguracaoController extends Controller
{
    public function editar()
    {
        return view('sped.configuracoes', ['config' => SpedConfiguracao::atual()]);
    }

    public function salvar(Request $request)
    {
        $soDigitos = fn (string $campo) => preg_replace('/\D/', '', (string) $request->input($campo)) ?: null;

        $request->merge([
            'empresa_fone'   => $soDigitos('empresa_fone'),
            'contador_cpf'   => $soDigitos('contador_cpf'),
            'contador_cnpj'  => $soDigitos('contador_cnpj'),
            'contador_cep'   => $soDigitos('contador_cep'),
            'contador_fone'  => $soDigitos('contador_fone'),
            'contador_uf'    => strtoupper(trim((string) $request->input('contador_uf'))) ?: null,
        ]);

        $dados = $request->validate([
            'ind_ativ'      => ['required', Rule::in([SpedConfiguracao::ATIV_INDUSTRIAL, SpedConfiguracao::ATIV_OUTROS])],
            'ind_perfil'       => ['required', Rule::in(array_keys(SpedConfiguracao::PERFIS))],
            'clas_estab_ind'   => ['nullable', 'required_if:ind_ativ,0', Rule::in(array_keys(SpedConfiguracao::CLASSIFICACOES_INDUSTRIAIS))],
            'cod_receita_icms' => ['nullable', 'string', 'max:20'],
            'regime_pis_cofins' => [
                Rule::requiredIf((int) Empresa::atual()->crt === 3), 'nullable',
                Rule::in(array_keys(SpedConfiguracao::REGIMES_PIS_COFINS)),
            ],
            'cod_cta_receita'  => ['nullable', 'string', 'max:60', 'regex:/^[^|]+$/'],
            'nome_cta_receita' => ['nullable', 'required_with:cod_cta_receita', 'string', 'max:60'],
            'cod_cta_compras'  => ['nullable', 'string', 'max:60', 'regex:/^[^|]+$/'],
            'nome_cta_compras' => ['nullable', 'required_with:cod_cta_compras', 'string', 'max:60'],
            'empresa_fone'  => ['nullable', 'regex:/^\d{10,11}$/'],
            'empresa_email' => ['nullable', 'email', 'max:100'],

            'contador_nome' => ['required', 'string', 'min:2', 'max:100'],
            'contador_cpf'  => [
                'required',
                fn ($attr, $valor, $fail) => Condutor::cpfValido((string) $valor) || $fail('CPF do contador inválido.'),
            ],
            'contador_crc'  => ['required', 'string', 'max:15'],
            'contador_cnpj' => [
                'nullable',
                'regex:/^[0-9A-Za-z]{14}$/',
                fn ($attr, $valor, $fail) => $valor === null || Transportador::documentoValido(strtoupper($valor)) || $fail('CNPJ do escritório inválido.'),
            ],
            'contador_cep'         => ['required', 'regex:/^\d{8}$/'],
            'contador_endereco'    => ['required', 'string', 'max:60'],
            'contador_numero'      => ['nullable', 'string', 'max:10'],
            'contador_complemento' => ['nullable', 'string', 'max:60'],
            'contador_bairro'      => ['required', 'string', 'max:60'],
            'contador_municipio'   => ['required', 'string', 'max:100'],
            'contador_cod_municipio' => ['required', 'regex:/^\d{7}$/'],
            'contador_uf'          => ['required', Rule::in(Transportador::UFS)],
            'contador_fone'        => ['nullable', 'regex:/^\d{10,11}$/'],
            'contador_email'       => ['required', 'email', 'max:100'],
        ], [
            'empresa_fone.regex'  => 'Telefone da empresa: informe DDD + número (10 ou 11 dígitos).',
            'nome_cta_receita.required_with' => 'Informe o nome da conta de receita.',
            'nome_cta_compras.required_with' => 'Informe o nome da conta de compras.',
            'regime_pis_cofins.required' => 'Empresa do regime normal (CRT 3): informe o regime do PIS/COFINS.',
            'clas_estab_ind.required_if' => 'Para atividade industrial, informe a classificação do estabelecimento (registro 0002).',
            'contador_fone.regex' => 'Telefone do contador: informe DDD + número (10 ou 11 dígitos).',
            'contador_cep.regex'  => 'CEP do contador deve ter 8 dígitos.',
            'contador_cod_municipio.regex'    => 'Selecione o município do contador na lista (o código IBGE precisa ter 7 dígitos).',
            'contador_cod_municipio.required' => 'Selecione o município do contador na lista.',
        ]);

        // O código do município precisa existir e ser da UF informada
        $cod = $dados['contador_cod_municipio'];
        if (Mdfe::ufDoMunicipio($cod) !== $dados['contador_uf'] || (Municipio::exists() && !Municipio::where('codigo_ibge', $cod)->exists())) {
            return back()->withInput()->withErrors(['contador_cod_municipio' => 'O código IBGE do município do contador não confere com a UF. Selecione o município na lista.']);
        }

        // Simples Nacional não tem regime de PIS/COFINS próprio
        if ((int) Empresa::atual()->crt !== 3) {
            $dados['regime_pis_cofins'] = null;
            unset($dados['cod_cta_receita'], $dados['nome_cta_receita'], $dados['cod_cta_compras'], $dados['nome_cta_compras']);
        }

        // Comércio não informa o registro 0002
        if ((int) $dados['ind_ativ'] !== SpedConfiguracao::ATIV_INDUSTRIAL) {
            $dados['clas_estab_ind'] = null;
        }

        $config = SpedConfiguracao::first() ?? new SpedConfiguracao();
        $config->fill($dados)->save();

        return redirect()->route('sped-config.editar')->with('sucesso', 'Configuração do SPED salva.');
    }
}