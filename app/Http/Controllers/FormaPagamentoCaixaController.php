<?php

namespace App\Http\Controllers;

use App\Models\FormaPagamentoCaixa;
use App\Support\FormasCaixa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cadastros → Caixa → Formas de pagamento: o que aparece na tela de pagamento do caixa.
 * As 6 formas "do sistema" (dinheiro, PIX, crédito, débito, crédito em loja e convênio) podem ser renomeadas,
 * reordenadas e inativadas, mas não excluídas nem ter o meio de pagamento trocado (o caixa depende delas).
 * O caixa recebe a lista no "Atualizar Caixa".
 */
class FormaPagamentoCaixaController extends Controller
{
    public function index()
    {
        $formas = FormaPagamentoCaixa::ordenadas()->get();
        $usos = DB::table('venda_pagamentos')
            ->whereIn('forma_pagamento', $formas->pluck('codigo'))
            ->selectRaw('forma_pagamento, COUNT(*) AS total')
            ->groupBy('forma_pagamento')
            ->pluck('total', 'forma_pagamento');

        return view('formas-pagamento-caixa.index', compact('formas', 'usos'));
    }

    public function create()
    {
        $forma = new FormaPagamentoCaixa([
            'ativo' => true,
            'meio_pagamento' => '99',
            'ordem' => (int) FormaPagamentoCaixa::max('ordem') + 10,
        ]);

        return view('formas-pagamento-caixa.create', compact('forma'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        FormaPagamentoCaixa::create($dados + [
            'codigo'  => $this->codigoUnico($dados['descricao']),
            'tipo'    => $this->tipoPeloMeio($dados['meio_pagamento']),
            'sistema' => false,
        ]);
        FormasCaixa::limpar();

        return redirect()->route('formas-pagamento-caixa.index')
            ->with('sucesso', 'Forma de pagamento cadastrada. Use "Atualizar Caixa" nos caixas para ela aparecer.');
    }

    public function edit(FormaPagamentoCaixa $forma)
    {
        $emUso = $forma->emUso();

        return view('formas-pagamento-caixa.edit', compact('forma', 'emUso'));
    }

    public function update(Request $request, FormaPagamentoCaixa $forma): RedirectResponse
    {
        $dados = $this->validar($request, $forma);

        // Forma do sistema: o caixa depende do meio e do comportamento dela; muda só nome, ordem e situação
        if ($forma->sistema) {
            $dados = array_intersect_key($dados, array_flip(['descricao', 'ordem', 'ativo']));
        } else {
            $dados['tipo'] = $this->tipoPeloMeio($dados['meio_pagamento']);
        }

        $this->garantirUmaAtiva($forma, (bool) ($dados['ativo'] ?? $forma->ativo));
        $forma->update($dados);
        FormasCaixa::limpar();

        return redirect()->route('formas-pagamento-caixa.index')
            ->with('sucesso', 'Forma de pagamento atualizada. Use "Atualizar Caixa" nos caixas para aplicar.');
    }

    public function toggleAtivo(FormaPagamentoCaixa $forma): RedirectResponse
    {
        $this->garantirUmaAtiva($forma, !$forma->ativo);
        $forma->update(['ativo' => !$forma->ativo]);
        FormasCaixa::limpar();

        return back()->with('sucesso', $forma->ativo ? "\"{$forma->descricao}\" reativada." : "\"{$forma->descricao}\" inativada: deixa de aparecer no caixa após o Atualizar Caixa.");
    }

    public function destroy(FormaPagamentoCaixa $forma): RedirectResponse
    {
        if ($forma->sistema) {
            return back()->with('erro', 'Formas do sistema não podem ser excluídas. Inative-a se não for usar.');
        }
        if ($forma->emUso()) {
            return back()->with('erro', "\"{$forma->descricao}\" já foi usada em vendas e não pode ser excluída. Inative-a.");
        }

        $this->garantirUmaAtiva($forma, false);
        $forma->delete();
        FormasCaixa::limpar();

        return redirect()->route('formas-pagamento-caixa.index')->with('sucesso', "\"{$forma->descricao}\" excluída.");
    }

    private function validar(Request $request, ?FormaPagamentoCaixa $forma = null): array
    {
        $request->merge([
            'ativo'         => $request->boolean('ativo'),
            'permite_troco' => $request->boolean('permite_troco'),
        ]);

        return $request->validate([
            'descricao'      => ['required', 'string', 'max:60', Rule::unique('formas_pagamento_caixa', 'descricao')->ignore($forma?->id)],
            'meio_pagamento' => [$forma?->sistema ? 'nullable' : 'required', Rule::in(array_keys(FormasCaixa::MEIOS))],
            'permite_troco'  => ['boolean'],
            'ativo'          => ['boolean'],
            'ordem'          => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'descricao.required'      => 'Informe a descrição que aparece no caixa.',
            'descricao.unique'        => 'Já existe uma forma de pagamento com essa descrição.',
            'meio_pagamento.required' => 'Escolha o meio de pagamento da NFC-e.',
            'meio_pagamento.in'       => 'Meio de pagamento inválido.',
        ]) + ['ordem' => (int) $request->input('ordem', 0)];
    }

    /** Comportamento no caixa pelo meio da SEFAZ (as especiais — crédito em loja e convênio — são só do sistema). */
    private function tipoPeloMeio(string $meio): string
    {
        return match ($meio) {
            '01' => 'dinheiro',
            '03' => 'credito',
            '04' => 'debito',
            '17', '20' => 'pix',
            default => 'outro',
        };
    }

    /** Código gravado nas vendas: derivado da descrição, único e com até 20 caracteres. */
    private function codigoUnico(string $descricao): string
    {
        $base = Str::limit(Str::slug($descricao, '_'), 16, '') ?: 'forma';
        $codigo = $base;
        $n = 2;

        while (FormaPagamentoCaixa::where('codigo', $codigo)->exists()) {
            $codigo = $base . '_' . $n++;
        }

        return $codigo;
    }

    /** O caixa não pode ficar sem nenhuma forma de pagamento ativa. */
    private function garantirUmaAtiva(FormaPagamentoCaixa $forma, bool $ficaAtiva): void
    {
        if ($ficaAtiva) {
            return;
        }

        if (!FormaPagamentoCaixa::ativas()->whereKeyNot($forma->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ativo' => 'O caixa precisa de pelo menos uma forma de pagamento ativa.',
            ]);
        }
    }
}
