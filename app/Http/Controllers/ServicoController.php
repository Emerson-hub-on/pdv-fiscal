<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $busca  = trim((string) $request->get('busca', ''));
        $status = $request->get('status');

        $servicos = Servico::query()
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%");

                    $digitos = preg_replace('/\D/', '', $busca);
                    if ($digitos !== '') {
                        $q->orWhere('codigo', (int) $digitos)
                          ->orWhere('codigo_tributacao_nacional', 'like', "{$digitos}%");
                    }
                });
            })
            ->when($status === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($status === 'inativos', fn ($q) => $q->where('ativo', false))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return view('servicos._tabela', compact('servicos'));
        }

        return view('servicos.index', compact('servicos'));
    }

    public function create()
    {
        $servico = new Servico(['tributacao_issqn' => 1, 'valor_padrao' => 0]);

        return view('servicos.create', compact('servico'));
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        DB::transaction(function () use ($dados) {
            $servico = new Servico($dados);
            $servico->codigo = ((int) Servico::lockForUpdate()->max('codigo')) + 1;
            $servico->ativo  = true;
            $servico->save();
        });

        return redirect()->route('servicos.index')->with('sucesso', 'Serviço cadastrado.');
    }

    public function edit(Servico $servico)
    {
        return view('servicos.edit', compact('servico'));
    }

    public function update(Request $request, Servico $servico)
    {
        $servico->update($this->validar($request));

        return redirect()->route('servicos.index')->with('sucesso', 'Serviço atualizado.');
    }

    /** Nunca exclui de verdade: só inativa/reativa (o serviço pode estar em notas já emitidas). */
    public function toggleAtivo(Servico $servico)
    {
        $servico->update(['ativo' => ! $servico->ativo]);

        return back()->with('sucesso', $servico->ativo ? 'Serviço reativado.' : 'Serviço inativado.');
    }

    private function validar(Request $request): array
    {
        // aceita "14.01.01" ou "140101", "9511-8/00" ou "9511800"
        $request->merge([
            'codigo_tributacao_nacional' => preg_replace('/\D/', '', (string) $request->input('codigo_tributacao_nacional')),
            'cnae'                       => preg_replace('/\D/', '', (string) $request->input('cnae')) ?: null,
        ]);

        $dados = $request->validate([
            'nome'                        => ['required', 'string', 'min:2', 'max:150'],
            'descricao'                   => ['nullable', 'string', 'max:2000'],
            'valor_padrao'                => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'codigo_tributacao_nacional'  => ['required', 'digits:6'],
            'codigo_tributacao_municipal' => ['nullable', 'string', 'max:20'],
            'cnae'                        => ['nullable', 'digits:7'],
            'tributacao_issqn'            => ['required', Rule::in(array_keys(Servico::TRIBUTACAO_ISSQN))],
            'aliquota_iss'                => ['nullable', 'numeric', 'between:0,5'],
        ], [
            'codigo_tributacao_nacional.digits' => 'O código de tributação nacional deve ter 6 dígitos (ex.: 14.01.01).',
            'cnae.digits'                       => 'O CNAE deve ter 7 dígitos.',
            'aliquota_iss.between'              => 'A alíquota de ISS deve ficar entre 0% e 5%.',
        ], [
            'nome'                        => 'nome',
            'valor_padrao'                => 'valor padrão',
            'codigo_tributacao_nacional'  => 'código de tributação nacional',
            'codigo_tributacao_municipal' => 'código de tributação municipal',
            'tributacao_issqn'            => 'tributação do ISSQN',
            'aliquota_iss'                => 'alíquota de ISS',
        ]);

        $dados['nome']         = trim($dados['nome']);
        $dados['valor_padrao'] = round((float) ($dados['valor_padrao'] ?? 0), 2);

        return $dados;
    }
}
