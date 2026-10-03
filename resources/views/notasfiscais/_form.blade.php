@php
    $ehEdicao = isset($notaFiscal) && $notaFiscal !== null;

    $transportadorAtual = old('transportador_id')
    ? \App\Models\Transportador::find(old('transportador_id'))
    : ($ehEdicao ? $notaFiscal->transportador : null);

    $itensIniciais = $ehEdicao
        ? $notaFiscal->itens->map(function ($i) use ($notaFiscal, $ehEdicao) {
            $subtotalBruto = (float) $i->valor_unitario * (float) $i->quantidade;
            $freteItem = ($ehEdicao && $notaFiscal->frete_por_item) ? (float) $i->valor_frete : 0;
            $baseImpostos = $subtotalBruto + (float) $i->valor_outras_despesas + $freteItem;
            $cstsComBaseCalculo = ['00', '10', '20', '70', '90'];

            $trib = $i->tributacao;
            $cstOuCsosn = $trib?->csosn ?? $trib?->cst_icms ?? '—';

            // 1. ICMS: Prioriza os dados manuais se preenchidos, senão calcula pelo padrão
            if (!is_null($i->aliquota_icms_manual) || !is_null($i->bc_icms_manual)) {
                $bcIcms       = (float) ($i->bc_icms_manual ?? 0);
                $aliquotaIcms = (float) ($i->aliquota_icms_manual ?? 0);
                $valorIcms    = (float) ($i->valor_icms_manual ?? ($bcIcms * $aliquotaIcms / 100));
            } else {
                $bcIcms = 0; $valorIcms = 0; $aliquotaIcms = 0;

                if ($trib && in_array($trib->cst_icms, $cstsComBaseCalculo, true)) {
                    $bcIcms = $baseImpostos;
                    $aliquotaIcms = (float) $trib->aliquota_icms;
                    $valorIcms = $bcIcms * $aliquotaIcms / 100;
                }
            }

            // 2. IPI: Prioriza os dados manuais se preenchidos, senão calcula pelo padrão
            if (!is_null($i->aliquota_ipi_manual) || !is_null($i->valor_ipi_manual)) {
                $aliquotaIpi = (float) ($i->aliquota_ipi_manual ?? 0);
                $valorIpi    = (float) ($i->valor_ipi_manual ?? ($baseImpostos * $aliquotaIpi / 100));
            } else {
                $ipi = $i->ipi;
                $valorIpi = 0; $aliquotaIpi = 0;

                if ($ipi && $ipi->codigo === '50' && $ipi->aliquota) {
                    $aliquotaIpi = (float) $ipi->aliquota;
                    $valorIpi = $baseImpostos * $aliquotaIpi / 100;
                }
            }

            return [
                'produto_id'            => $i->produto_id,
                'codigo'                => $i->produto->codigo_interno,
                'produto_variante_id'   => $i->produto_variante_id,
                'codigo_barras'         => $i->produto->codigo_barras,
                'descricao'             => $i->descricao ?? $i->produto->nome,
                'cst_csosn'             => $cstOuCsosn,
                'quantidade'            => (float) $i->quantidade,
                'valor_unitario'        => (float) $i->valor_unitario,
                'valor_total'           => (float) $i->valor_total,
                'valor_desconto'        => (float) $i->valor_desconto,
                'valor_outras_despesas' => (float) $i->valor_outras_despesas,
                'valor_frete'           => $freteItem,
                'desconto_percentual'   => $subtotalBruto > 0 ? round(((float) $i->valor_desconto / $subtotalBruto) * 100, 2) : 0,
                'tributacao'            => $i->tributacao,
                'ipi'                   => $i->ipi,
                'bc_icms'               => $bcIcms,
                'valor_icms'            => $valorIcms,
                'aliquota_icms'         => $aliquotaIcms,
                'valor_ipi'             => $valorIpi,
                'aliquota_ipi'          => $aliquotaIpi,
                'ref_chave_acesso'      => $i->ref_chave_acesso,
                'ref_nitem'             => $i->ref_nitem,
                'bases_manuais'         => !is_null($i->bc_icms_manual) || !is_null($i->valor_ipi_manual),
            ];
        })->values()
        : collect();

    $motivoAtual = old('motivo_ajuste', $ehEdicao ? $notaFiscal->motivo_ajuste : null);
    $freteModoInicial = old('frete_modo', ($ehEdicao && $notaFiscal->frete_por_item) ? 'item' : 'global');
    $modFreteInicial = (string) old('mod_frete', $ehEdicao ? $notaFiscal->mod_frete : 9);
    $labelsFinalidade = [1 => 'Normal', 2 => 'Complementar', 3 => 'Ajuste', 4 => 'Devolução', 5 => 'Nota de Crédito', 6 => 'Nota de Débito'];
    $naturezaAtual = old('natureza_operacao', $ehEdicao ? $notaFiscal->natureza_operacao : null);
    $finalidadeAtual = old('finalidade', $ehEdicao ? $notaFiscal->finalidade : null);
    $notasReferenciadasIniciais = $ehEdicao ? ($notaFiscal->notas_referenciadas ?? []) : [];
@endphp

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            mostrarAviso({{ Illuminate\Support\Js::from($errors->all()) }}.join('\n'), 'erro');
        });
    </script>
@endif

<form id="form-nota" method="POST"
      action="{{ $ehEdicao ? route('notasfiscais.update', $notaFiscal) : route('notasfiscais.store') }}"
      class="flex flex-col gap-6 w-fit max-w-full min-w-[min(64rem,100%)] mt-6 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif
    <input type="hidden" name="itens_json" id="itens_json">
    <input type="hidden" name="notas_referenciadas_json" id="campo-notas-referenciadas">
    <input type="hidden" name="informacoes_complementares" id="campo-informacoes-complementares"
        value="{{ old('informacoes_complementares', $ehEdicao ? $notaFiscal->informacoes_complementares : '') }}">

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            @if ($ehEdicao)
                <a href="{{ route('notasfiscais.index') }}" title="Voltar para Notas Fiscais"
                class="text-gray-500 hover:text-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif
            <h1 class="text-lg font-semibold">{{ $ehEdicao ? 'Editar Nota Fiscal (rascunho)' : 'Nova Nota Fiscal (Saída)' }}</h1>
            
        <div class="relative" id="menu-opcoes">
            <button type="button" id="btn-opcoes" onclick="alternarMenuOpcoes()"
                    class="flex items-center gap-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm hover:bg-gray-50">
                Opções
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="painel-opcoes"
                class="hidden absolute right-0 mt-1 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-40 p-4 text-sm">
                <p class="font-medium text-gray-700 mb-2">Modalidade do frete</p>
                @foreach (\App\Models\NotaFiscal::MODALIDADES_FRETE as $codigo => $nome)
                    <label class="flex items-center gap-2 mb-1 cursor-pointer">
                        <input type="radio" name="mod_frete_ui" value="{{ $codigo }}"
                            onchange="definirModFrete('{{ $codigo }}')"
                            {{ $modFreteInicial === (string) $codigo ? 'checked' : '' }}>
                        {{ $nome }}
                    </label>
                @endforeach

                <div id="opcoes-valor-frete" class="mt-3 pt-3 border-t border-gray-100">
                    <p class="font-medium text-gray-700 mb-2">Valor do frete</p>
                    <label class="flex items-center gap-2 mb-1 cursor-pointer">
                        <input type="radio" name="frete_modo_ui" value="item" onchange="definirFreteModo('item')"
                            {{ $freteModoInicial === 'item' ? 'checked' : '' }}> Item a item
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="frete_modo_ui" value="global" onchange="definirFreteModo('global')"
                            {{ $freteModoInicial === 'global' ? 'checked' : '' }}> Valor total (informado ao confirmar)
                    </label>
                </div>
            </div>
        </div>
        
        </div>


        <div class="grid grid-cols-2 gap-4 max-w-5xl">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Nota</label>
                <input type="hidden" name="cfop_saida_id" id="campo-cfop"
                    value="{{ old('cfop_saida_id', $ehEdicao ? $notaFiscal->cfop_saida_id : '') }}">
                <button type="button" onclick="abrirModalCfop()"
                        class="w-full text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                    <span id="texto-cfop-selecionado">
                        @if ($ehEdicao && $notaFiscal->cfopSaida)
                            {{ $notaFiscal->cfopSaida->codigo }} - {{ $notaFiscal->cfopSaida->descricao }}
                        @else
                            Selecionar tipo de nota...
                        @endif
                    </span>
                </button>
                @error('cfop_saida_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Pagamento</label>
                <input type="hidden" name="forma_pagamento_id" id="campo-pagamento"
                    value="{{ old('forma_pagamento_id', $ehEdicao ? $notaFiscal->forma_pagamento_id : '') }}">
                <button type="button" onclick="abrirModalPagamento()"
                        class="w-full text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                    <span id="texto-pagamento-selecionado">
                        @if ($ehEdicao && $notaFiscal->formaPagamento)
                            {{ $notaFiscal->formaPagamento->descricao }}
                        @else
                            Selecionar tipo de pagamento...
                        @endif
                    </span>
                </button>
                @error('forma_pagamento_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            @php
                $clienteAtual = old('cliente_id')
                    ? \App\Models\Cliente::find(old('cliente_id'))
                    : ($ehEdicao ? $notaFiscal->cliente : null);
            @endphp

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <input type="hidden" name="cliente_id" id="campo-cliente"
                    value="{{ old('cliente_id', $ehEdicao ? $notaFiscal->cliente_id : '') }}">
                <button type="button" onclick="abrirModalCliente()"
                        class="w-full text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                    <span id="texto-cliente-selecionado">
                        @if ($clienteAtual)
                            {{ $clienteAtual->nome }} — {{ $clienteAtual->cpf_cnpj_formatado }}
                        @else
                            Selecionar cliente...
                        @endif
                    </span>
                </button>
                @error('cliente_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Operador</label>
                <select name="operador_id" id="campo-operador" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}"
                            @selected(old('operador_id', $ehEdicao ? $notaFiscal->operador_id : auth()->id()) == $usuario->id)>
                            {{ $usuario->name }}
                        </option>
                    @endforeach
                </select>
                @error('operador_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <input type="hidden" name="natureza_operacao" id="campo-natureza" value="{{ $naturezaAtual }}">
            <input type="hidden" name="finalidade" id="campo-finalidade" value="{{ $finalidadeAtual }}">
            <input type="hidden" name="motivo_ajuste" id="campo-motivo-ajuste" value="{{ $motivoAtual }}">
            <input type="hidden" name="frete_modo" id="campo-frete-modo" value="{{ $freteModoInicial }}">
            <input type="hidden" name="frete_total" id="campo-frete-total" value="0">
            <input type="hidden" name="mod_frete" id="campo-mod-frete" value="{{ $modFreteInicial }}">
            <input type="hidden" name="transportador_id" id="campo-transportador" value="{{ $transportadorAtual?->id }}">

        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-3">Itens da nota</h2>

        <div id="aviso-cabecalho" class="bg-amber-50 text-amber-800 border border-amber-200 rounded-lg px-3 py-2 text-sm mb-4">
            Preencha cliente, natureza da operação e finalidade acima para liberar a adição de itens.
        </div>

        <input type="text" id="input-busca-item-nf" placeholder="Nome, código interno ou código de barras..."
               autocomplete="off" disabled
               class="w-full max-w-2xl border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed">

        <div id="editor-item" class="hidden flex flex-col gap-3 mb-4 bg-gray-50 rounded-lg p-4">
            <div class="text-sm font-semibold text-gray-800" id="editor-produto-nome"></div>

            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Código</label>
                    <input type="text" id="editor-codigo" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Cód. Barras</label>
                    <input type="text" id="editor-codigo-barras" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Cst/Csosn</label>
                    <input type="text" id="editor-cst-csosn" readonly
                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Descrição</label>
                    <input type="text" id="editor-descricao"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-6 gap-3">

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Qtd</label>
                    <input type="number" step="0.001" id="editor-quantidade" value="1"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Vl Unit</label>
                    <input type="number" step="0.0001" id="editor-valor-unitario"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Vl Total</label>
                    <input type="text" id="editor-valor-total" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desconto (R$)</label>
                    <input type="number" step="0.01" id="editor-desconto" value="0"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Outras despesas (R$)</label>
                    <input type="number" step="0.01" min="0" id="editor-outras-despesas" value="0"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <div id="editor-bloco-frete" class="{{ $freteModoInicial === 'item' ? '' : 'hidden' }}">
                    <label class="block text-xs text-gray-500 mb-1">Frete (R$)</label>
                    <input type="number" step="0.01" min="0" id="editor-frete" value="0"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>

                <div id="editor-referencia-devolucao" class="hidden grid grid-cols-2 gap-3 col-span-5">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Chave da NF-e original (devolução)</label>
                        <input type="text" id="editor-ref-chave" maxlength="44"
                            class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nº do item na nota original (opcional)</label>
                        <input type="number" id="editor-ref-nitem" min="1"
                            class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desconto %</label>
                    <input type="number" step="0.01" id="editor-desconto-percentual" value="0"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">BC ICMS</label>
                    <input type="text" id="editor-bc-icms" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Vlr. ICMS</label>
                    <input type="text" id="editor-valor-icms" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">% ICMS</label>
                    <input type="text" id="editor-aliquota-icms" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Vlr. IPI</label>
                    <input type="text" id="editor-valor-ipi" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">% IPI</label>
                    <input type="text" id="editor-aliquota-ipi" readonly
                        class="w-full border border-gray-200 bg-gray-100 rounded-lg px-2 py-1.5 text-sm text-gray-600">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" id="btn-editor-confirmar" onclick="adicionarLinhaNaGrid()"
                        class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700 w-fit">
                    Adicionar à nota
                </button>
                <button type="button" id="btn-editor-remover" onclick="removerOuCancelarEditor()"
                        class="border border-red-300 text-red-600 rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-red-50 w-fit">
                    Cancelar
                </button>
            </div>
        </div>

        @error('itens') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Cód. Barras</th>
                        <th class="text-left px-3 py-2">Descrição</th>
                        <th class="text-left px-3 py-2">CFOP</th>
                        <th class="text-left px-3 py-2">Cst/Csosn</th>
                        <th class="text-right px-3 py-2">Qtd</th>
                        <th class="text-right px-3 py-2">Vl Unit</th>
                        <th class="text-right px-3 py-2">Vl Total</th>
                        <th class="text-right px-3 py-2">Desconto</th>
                        <th class="text-right px-3 py-2">Desconto %</th>
                        <th class="text-right px-3 py-2">Outras desp.</th>
                        <th class="text-right px-3 py-2">Frete</th>
                        <th class="text-right px-3 py-2">BC ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. ICMS</th>
                        <th class="text-right px-3 py-2">% ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. IPI</th>
                        <th class="text-right px-3 py-2">% IPI</th>                       
                        <th class="text-left px-3 py-2">Ref. NF origem</th>
                    </tr>
                </thead>
                <tbody id="linhas-grid-itens" class="divide-y divide-gray-100"></tbody>
                <tfoot class="bg-gray-50 font-medium">
                    <tr>
                        <td colspan="15" class="px-3 py-1 text-right text-gray-500">Frete total</td>
                        <td class="px-3 py-1 text-right" id="total-frete-grid">R$ 0,00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="15" class="px-3 py-2 text-right">Total da nota</td>
                        <td class="px-3 py-2 text-right" id="total-grid-itens">R$ 0,00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <p id="grid-vazia" class="text-center text-gray-400 py-8">Nenhum item adicionado ainda.</p>
        </div>

        <div>
            <button type="button" onclick="abrirModalReferencia()"
                    class="bg-green-700 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-green-800">
                {{ $ehEdicao ? 'Salvar Alterações' : 'Salvar Nota' }}
            </button>
        </div>

        <div id="area-avisos" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]"></div>
</form>

<!-- Modal CFOP -->
<div id="modal-cfop" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Selecionar CFOP</h2>
            <button type="button" onclick="fecharModalCfop()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="cfop-busca" placeholder="Buscar por código ou descrição..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarCfop()">
            <button type="button" onclick="abrirFormNovoCfop()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo CFOP
            </button>
        </div>

        <div id="form-cfop" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <input type="hidden" id="cfop-form-id">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" id="cfop-form-codigo" placeholder="Código (4 dígitos)"
                       maxlength="4" class="border rounded px-3 py-2 text-sm">
                <input type="text" id="cfop-form-descricao" placeholder="Descrição"
                       class="border rounded px-3 py-2 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" id="cfop-form-natureza" placeholder="Natureza da operação padrão"
                    class="border rounded px-3 py-2 text-sm">
                <select id="cfop-form-finalidade" class="border rounded px-3 py-2 text-sm">
                    <option value="1">Normal</option>
                    <option value="2">Complementar</option>
                    <option value="3">Ajuste</option>
                    <option value="4">Devolução</option>
                    <option value="5">Nota de Crédito (IBS/CBS)</option>
                    <option value="6">Nota de Débito (IBS/CBS)</option>
                </select>
            </div>

            <div class="mb-2">
                <select id="cfop-form-tipo" class="border rounded px-3 py-2 text-sm w-full">
                    <option value="saida">Saída (CFOP 5xxx, 6xxx, 7xxx)</option>
                    <option value="entrada">Entrada (CFOP 1xxx, 2xxx, 3xxx)</option>
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 mb-2">
                <input type="checkbox" id="cfop-form-movimenta" checked>
                Movimenta estoque (diminui o estoque do produto na quantidade vendida)
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-700 mb-2">
                <input type="checkbox" id="cfop-form-destacar-bases">
                Destacar bases (permite editar manualmente BC, valor e % de ICMS e IPI ao adicionar item)
            </label>

            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormCfop()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarFormCfop(this)"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2 w-20">Código</th>
                    <th class="py-2">Descrição</th>
                    <th class="py-4 w-32 text-center">Tipo da operação</th>
                    <th class="py-2 w-32 text-center">Mov. estoque</th>
                </tr>
            </thead>
            <tbody id="cfop-lista"></tbody>
        </table>
        <p id="cfop-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhum CFOP encontrado. Use "+ Novo CFOP" para cadastrar.</p>
    </div>
</div>

<!-- Modal Forma de Pagamento -->
<div id="modal-pagamento" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Selecionar Tipo de Pagamento</h2>
            <button type="button" onclick="fecharModalPagamento()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="pagamento-busca" placeholder="Buscar..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarFormaPagamento()">
            <button type="button" onclick="abrirFormNovaFormaPagamento()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Nova forma
            </button>
        </div>

        <!-- O formulário de cadastro/edição começa aqui -->
        <div id="form-pagamento" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <input type="hidden" id="pagamento-form-id">
            
            <input type="text" id="pagamento-form-descricao" placeholder="Descrição (ex: A prazo 45 dias)"
                class="w-full border rounded px-3 py-2 text-sm mb-2">
            
            <!-- INCLUSÃO DOS NOVOS CAMPOS SEFAZ -->
            <div class="grid grid-cols-2 gap-2 mb-2">
                <select id="pagamento-form-meio" class="border rounded px-3 py-2 text-sm bg-white">
                    @foreach (\App\Models\FormaPagamento::MEIOS as $codigo => $nome)
                        <option value="{{ $codigo }}">{{ $codigo }} - {{ $nome }}</option>
                    @endforeach
                </select>
                <select id="pagamento-form-indpag" class="border rounded px-3 py-2 text-sm bg-white">
                    <option value="0">À vista</option>
                    <option value="1">A prazo</option>
                </select>
            </div>
            <!-- FIM DA INCLUSÃO -->

            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormPagamento()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarFormaPagamento(this)"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <tbody id="pagamento-lista"></tbody>
        </table>
        <p id="pagamento-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhuma forma encontrada.</p>
    </div>
</div>



<!-- Modal de busca de cliente -->
<div id="modal-busca-cliente" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Cliente</h2>
            <button type="button" onclick="fecharModalCliente()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-cliente-modal" placeholder="Buscar por nome ou CPF/CNPJ..."
                   autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Nome</th>
                        <th class="py-2">CPF/CNPJ</th>
                        <th class="py-2">UF</th>
                        <th class="py-2">Telefone</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-cliente"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<!-- Modal de busca de Transportador -->
<div id="modal-busca-transportador" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60]">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Transportador</h2>
            <button type="button" onclick="fecharModalTransportador()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-transportador-modal" autocomplete="off" placeholder="Buscar por nome ou CPF/CNPJ..."
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Nome</th><th class="py-2">CPF/CNPJ</th><th class="py-2">Município/UF</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-transportador"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<!-- Modal de busca de produto -->
<div id="modal-busca-produto-nf" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Produto</h2>
            <button type="button" onclick="fecharModalBuscaNf()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-produto-modal-nf" placeholder="Buscar por nome, código ou código de barras..."
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Código</th>
                        <th class="py-2">Produto</th>
                        <th class="py-2">Preço</th>
                        <th class="py-2">Estoque</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-produto-nf"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>


<!-- Modal de Confirmação — Notas Referenciadas + Informações Complementares -->
<div id="modal-referencia-nota" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Confirmar Nota Fiscal</h2>
            <button type="button" onclick="fecharModalReferencia()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div id="aviso-referencia-obrigatoria" class="hidden bg-amber-50 text-amber-800 border border-amber-200 rounded-lg px-3 py-2 text-sm mb-4">
            Esta finalidade exige a referência de ao menos uma nota fiscal.
        </div>

        <div id="bloco-motivo-ajuste" class="hidden mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do ajuste</label>
            <select id="modal-motivo-ajuste" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></select>
        </div>

        <div id="bloco-transportador-modal" class="hidden mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Transportador
                <span id="modal-transportador-obrigatorio" class="hidden text-red-500">*</span>
                <span id="modal-mod-frete-texto" class="text-xs text-gray-400 font-normal"></span>
            </label>

            <button type="button" onclick="abrirModalTransportador()"
                    class="w-full text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                <span id="texto-transportador-selecionado">
                    @if ($transportadorAtual)
                        {{ $transportadorAtual->nome }} — {{ $transportadorAtual->documento_formatado }}
                    @else
                        Selecionar transportador...
                    @endif
                </span>
            </button>

            <div class="flex justify-between mt-1 text-xs">
                <button type="button" onclick="limparTransportador()" class="text-red-600 hover:underline">remover</button>
                <a href="{{ route('transportadores.create') }}" target="_blank" class="text-blue-700 hover:underline">cadastrar novo</a>
            </div>
        </div>

        <div id="bloco-frete-modal" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Frete</label>
            <div id="bloco-frete-global">
                <input type="number" step="0.01" min="0" id="modal-frete-global" value="0"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">O valor é rateado entre os itens, proporcional ao valor de cada um.</p>
            </div>
            <p id="bloco-frete-itens" class="hidden text-sm text-gray-700">
                Soma dos fretes dos itens: <strong id="modal-frete-soma">R$ 0,00</strong>
                <span class="text-gray-400">(informado item a item)</span>
            </p>
        </div>

        <label class="block text-sm font-medium text-gray-700 mb-1">
            Nota(s) fiscal(is) referenciada(s)
            <span class="text-gray-400 font-normal">(chave de acesso, 44 dígitos)</span>
        </label>
        <div class="flex gap-2 mb-2">
            <input type="text" id="input-chave-referenciada" maxlength="44" placeholder="Chave de acesso da NF-e"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
            <button type="button" onclick="adicionarChaveReferenciada()"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm hover:bg-gray-700">Adicionar</button>
        </div>
        <ul id="lista-chaves-referenciadas" class="text-sm mb-1 space-y-1"></ul>
        <p id="chaves-vazio" class="text-xs text-gray-400 mb-4">Nenhuma nota referenciada ainda.</p>

        <label class="block text-sm font-medium text-gray-700 mb-1">Informações complementares (opcional)</label>
        <textarea id="modal-informacoes-complementares" rows="3" maxlength="2000"
                  placeholder="Observações adicionais que devem aparecer no DANFE..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-1"></textarea>
        <p class="text-xs text-gray-500 mb-4">
            Em notas de devolução, o sistema já destaca automaticamente o ICMS/IPI aqui — use este campo só para observações extras.
        </p>

        <div class="flex gap-2 justify-end">
            <button type="button" onclick="fecharModalReferencia()"
                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="confirmarESalvarNota()"
                    class="bg-green-700 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-green-800">Confirmar e Salvar</button>
        </div>
    </div>
</div>



<script>
window.crtEmpresa = {{ (int) $crtEmpresa }};
window.cfopDestacarBases = {{ ($ehEdicao && $notaFiscal->cfopSaida && $notaFiscal->cfopSaida->destacar_bases) ? 'true' : 'false' }};
window.cfopCodigoNota = @json($ehEdicao && $notaFiscal->cfopSaida ? (string) $notaFiscal->cfopSaida->codigo : '');
let itensNota = @json($itensIniciais);
let chavesReferenciadas = @json($notasReferenciadasIniciais);
let resultadosAtuaisNf = [];
let indiceSelecionadoNf = -1;
let timeoutBuscaNf;
let produtoSelecionadoParaEditor = null;
let indiceItemEmEdicao = null;
let cfopsCache = [];
let formasPagamentoCache = [];
let clientesAtuais = [];
let indiceClienteSelecionado = -1;
let timeoutBuscaCliente;
let contadorBuscaCliente = 0; // descarta respostas antigas se o operador digitar rápido
const finalidadesRefPorItem = @json(array_map('strval', config('fiscal.finalidades_referencia_por_item', [])));
const motivosAjuste = @json(config('fiscal.motivos_ajuste', []));
const inputBuscaCliente  = document.getElementById('busca-cliente-modal');
const linhasBuscaCliente = document.getElementById('linhas-busca-cliente');
const inputBuscaNf = document.getElementById('input-busca-item-nf');
const inputBuscaModalNf = document.getElementById('busca-produto-modal-nf');
const linhasBuscaDivNf = document.getElementById('linhas-busca-produto-nf');
const campoCliente = document.getElementById('campo-cliente');
const campoNatureza = document.getElementById('campo-natureza');
const campoFinalidade = document.getElementById('campo-finalidade');
const campoCfop = document.getElementById('campo-cfop');
const campoPagamento = document.getElementById('campo-pagamento');
const campoOperador = document.getElementById('campo-operador');
const cfopsVendaPorItem = @json(array_map('strval', config('fiscal.cfops_venda_por_item', [])));
const cfopInterestadual = @json(config('fiscal.cfop_interestadual', []));
let freteModo = @json($freteModoInicial);
let modFrete = @json($modFreteInicial);
let freteGlobal = {{ (float) old('frete_total', ($ehEdicao && !$notaFiscal->frete_por_item) ? $notaFiscal->valor_frete : 0) }};
let transportadoresAtuais = [];
let indiceTransportador = -1;
let timeoutBuscaTransportador;
let contadorBuscaTransportador = 0;
const rotulosModFrete = @json(\App\Models\NotaFiscal::MODALIDADES_FRETE);
const inputBuscaTransportador  = document.getElementById('busca-transportador-modal');
const linhasBuscaTransportador = document.getElementById('linhas-busca-transportador');
const campoTransportador       = document.getElementById('campo-transportador');


function abrirModalTransportador() {
    const modal = document.getElementById('modal-busca-transportador');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaTransportador.value = '';
    inputBuscaTransportador.focus();
    buscarTransportadores('');
}

function fecharModalTransportador() {
    const modal = document.getElementById('modal-busca-transportador');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    transportadoresAtuais = [];
    indiceTransportador = -1;
}

async function buscarTransportadores(termo) {
    const minhaBusca = ++contadorBuscaTransportador;
    let dados;

    try {
        dados = await requisicaoJson(`{{ route('transportadores.listar') }}?termo=${encodeURIComponent(termo)}`);
    } catch (e) {
        if (minhaBusca === contadorBuscaTransportador) {
            mostrarAviso('Falha ao buscar transportadores:\n' + e.message, 'erro');
            transportadoresAtuais = [];
            renderizarTransportadores();
        }
        return;
    }

    if (minhaBusca !== contadorBuscaTransportador) return;

    transportadoresAtuais = dados;
    indiceTransportador = dados.length > 0 ? 0 : -1;
    renderizarTransportadores();
}

function renderizarTransportadores() {
    if (transportadoresAtuais.length === 0) {
        linhasBuscaTransportador.innerHTML = '<tr><td colspan="3" class="p-3 text-sm text-gray-400 text-center">Nenhum transportador encontrado.</td></tr>';
        return;
    }

    linhasBuscaTransportador.innerHTML = transportadoresAtuais.map((t, index) => {
        const destacado = index === indiceTransportador;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarTransportador(${index})">
                <td class="py-3 font-medium">${escaparHtml(t.nome)}</td>
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${escaparHtml(t.documento_formatado)}</td>
                <td class="py-3">${escaparHtml(t.municipio)}/${escaparHtml(t.uf)}</td>
            </tr>`;
    }).join('');

    linhasBuscaTransportador.children[indiceTransportador]?.scrollIntoView({ block: 'nearest' });
}

function selecionarTransportador(index) {
    const t = transportadoresAtuais[index];
    if (!t) return;

    campoTransportador.value = t.id;
    document.getElementById('texto-transportador-selecionado').innerText = `${t.nome} — ${t.documento_formatado}`;
    fecharModalTransportador();
}

function limparTransportador() {
    campoTransportador.value = '';
    document.getElementById('texto-transportador-selecionado').innerText = 'Selecionar transportador...';
}

inputBuscaTransportador?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaTransportador);
    timeoutBuscaTransportador = setTimeout(() => buscarTransportadores(inputBuscaTransportador.value.trim()), 300);
});

inputBuscaTransportador?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalTransportador(); return; }
    if (transportadoresAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceTransportador = (indiceTransportador + 1) % transportadoresAtuais.length;
        renderizarTransportadores();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceTransportador = (indiceTransportador - 1 + transportadoresAtuais.length) % transportadoresAtuais.length;
        renderizarTransportadores();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceTransportador >= 0) selecionarTransportador(indiceTransportador);
    }
});

function alternarMenuOpcoes(forcarFechar = false) {
    const painel = document.getElementById('painel-opcoes');
    painel.classList.toggle('hidden', forcarFechar ? true : !painel.classList.contains('hidden'));
}

document.addEventListener('click', (e) => {
    if (!document.getElementById('menu-opcoes').contains(e.target)) alternarMenuOpcoes(true);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') alternarMenuOpcoes(true);
});

// Mostra/esconde o que depende da modalidade e do modo do frete
function aplicarVisibilidadeFrete() {
    const semFrete = modFrete === '9';
    document.getElementById('opcoes-valor-frete').classList.toggle('hidden', semFrete);
    document.getElementById('editor-bloco-frete').classList.toggle('hidden', semFrete || freteModo !== 'item');
}

function definirModFrete(valor) {
    if (valor === modFrete) return;

    if (valor === '9' && freteTotalNota() > 0
        && !confirm('Sem frete: o valor de frete informado será zerado. Continuar?')) {
        document.querySelector(`input[name="mod_frete_ui"][value="${modFrete}"]`).checked = true;
        
        return;
    }

    modFrete = valor;

    if (valor === '9') {
        itensNota.forEach(i => i.valor_frete = 0);
        freteGlobal = 0;
        freteModo = 'global';
        document.querySelector('input[name="frete_modo_ui"][value="global"]').checked = true;
        limparTransportador();
    }

    aplicarVisibilidadeFrete();
    renderizarGridItens();
}

function somaFreteItens() {
    return itensNota.reduce((s, i) => s + (Number(i.valor_frete) || 0), 0);
}

function freteTotalNota() {
    return freteModo === 'item' ? somaFreteItens() : freteGlobal;
}

function definirFreteModo(modo) {
    if (modo === freteModo) return;

    if (modo === 'global' && somaFreteItens() > 0
        && !confirm('Os fretes informados item a item serão descartados. Continuar?')) {
        document.querySelector('input[name="frete_modo_ui"][value="item"]').checked = true;
        return;
    }

    if (modo === 'global') itensNota.forEach(i => i.valor_frete = 0);

    freteModo = modo;
    aplicarVisibilidadeFrete();
    renderizarGridItens();
}

// Libera a largura total da tela (variante já prevista no layouts/app)
document.body.classList.add('conteudo-largo');

// Espelha NotaFiscalItem::cfopEfetivo() do backend
function cfopEfetivoItem(item) {
    const cfopNota = window.cfopCodigoNota ?? '';
    const cfopProduto = item.tributacao?.cfop ? String(item.tributacao.cfop) : null;

    if (!cfopProduto || !cfopsVendaPorItem.includes(cfopNota)) {
        return cfopNota || '—';
    }

    if (cfopNota.startsWith('6')) {
        return cfopInterestadual[cfopProduto] ?? ('6' + cfopProduto.substring(1));
    }

    return cfopProduto;
}


function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}

function abrirModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaCliente.value = '';
    inputBuscaCliente.focus();
    buscarClientes(''); // sem termo = 20 primeiros em ordem alfabética
}

function fecharModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    clientesAtuais = [];
    indiceClienteSelecionado = -1;
}

async function buscarClientes(termo) {
    const minhaBusca = ++contadorBuscaCliente;
    let dados;

    try {
        dados = await requisicaoJson(
            `{{ route('notasfiscais.buscar-cliente') }}?termo=${encodeURIComponent(termo)}`
        );
    } catch (e) {
        if (minhaBusca === contadorBuscaCliente) { // erro de busca antiga não interessa
            mostrarAviso('Falha ao buscar clientes:\n' + e.message, 'erro');
            clientesAtuais = [];
            indiceClienteSelecionado = -1;
            renderizarClientes();
        }
        return;
    }

    if (minhaBusca !== contadorBuscaCliente) return; // chegou uma busca mais nova, ignora esta

    clientesAtuais = dados;
    indiceClienteSelecionado = dados.length > 0 ? 0 : -1;
    renderizarClientes();
}

function renderizarClientes() {
    if (clientesAtuais.length === 0) {
        linhasBuscaCliente.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum cliente encontrado.</td></tr>';
        return;
    }

    linhasBuscaCliente.innerHTML = clientesAtuais.map((c, index) => {
        const destacado = index === indiceClienteSelecionado;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarCliente(${index})">
                <td class="py-3 font-medium">${escaparHtml(c.nome)}</td>
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${escaparHtml(c.cpf_cnpj)}</td>
                <td class="py-3">${escaparHtml(c.uf)}</td>
                <td class="py-3">${escaparHtml(c.telefone)}</td>
            </tr>
        `;
    }).join('');

    // mantém a linha destacada visível ao navegar com as setas
    linhasBuscaCliente.children[indiceClienteSelecionado]?.scrollIntoView({ block: 'nearest' });
}

function selecionarCliente(index) {
    const c = clientesAtuais[index];
    if (!c) return;

    campoCliente.value = c.id;
    document.getElementById('texto-cliente-selecionado').innerText = `${c.nome} — ${c.cpf_cnpj}`;

    atualizarTravaCabecalho(); // input hidden não dispara 'change', então chamamos na mão
    fecharModalCliente();
}

inputBuscaCliente?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaCliente);
    timeoutBuscaCliente = setTimeout(() => buscarClientes(inputBuscaCliente.value.trim()), 300);
});

inputBuscaCliente?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalCliente(); return; }
    if (clientesAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceClienteSelecionado = (indiceClienteSelecionado + 1) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceClienteSelecionado = (indiceClienteSelecionado - 1 + clientesAtuais.length) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceClienteSelecionado >= 0) selecionarCliente(indiceClienteSelecionado);
    }
});

function sugerirTipoCfop() {
    const primeiro = document.getElementById('cfop-form-codigo').value.charAt(0);
    if (['1', '2', '3'].includes(primeiro)) document.getElementById('cfop-form-tipo').value = 'entrada';
    else if (['5', '6', '7'].includes(primeiro)) document.getElementById('cfop-form-tipo').value = 'saida';
}

function cabecalhoValido() {
    const cliente = campoCliente?.value ?? '';
    const cfop = campoCfop?.value ?? '';
    const pagamento = campoPagamento?.value ?? '';
    const operador = campoOperador?.value ?? '';
    const natureza = campoNatureza?.value?.trim() ?? '';
    const finalidade = campoFinalidade?.value ?? '';

    return cliente !== '' && 
           cfop !== '' && 
           pagamento !== '' && 
           operador !== '' && 
           natureza !== '' && 
           finalidade !== '';
}

function atualizarTravaCabecalho() {
    const valido = cabecalhoValido();
    if (inputBuscaNf) {
        inputBuscaNf.disabled = !valido;
    }
    const aviso = document.getElementById('aviso-cabecalho');
    if (aviso) {
        aviso.classList.toggle('hidden', valido);
    }
}

[campoCliente, campoCfop, campoPagamento, campoOperador].forEach(campo => {
    campo.addEventListener('input', atualizarTravaCabecalho);
    campo.addEventListener('change', atualizarTravaCabecalho);
});

function abrirModalPagamento() {
    document.getElementById('modal-pagamento').classList.remove('hidden');
    document.getElementById('modal-pagamento').classList.add('flex');
    document.getElementById('pagamento-busca').value = '';
    buscarFormaPagamento();
}

function fecharModalPagamento() {
    document.getElementById('modal-pagamento').classList.add('hidden');
    document.getElementById('modal-pagamento').classList.remove('flex');
    fecharFormPagamento();
}

async function buscarFormaPagamento() {
    const termo = document.getElementById('pagamento-busca').value.trim();

    try {
        formasPagamentoCache = await requisicaoJson(
            `{{ route('formas-pagamento.listar') }}?termo=${encodeURIComponent(termo)}`
        );
    } catch (e) {
        mostrarAviso('Falha ao buscar formas de pagamento:\n' + e.message, 'erro');
        formasPagamentoCache = [];
    }

    renderizarListaPagamento();
}

function renderizarListaPagamento() {
    const tbody = document.getElementById('pagamento-lista');
    const vazio = document.getElementById('pagamento-vazio');

    if (formasPagamentoCache.length === 0) {
        tbody.innerHTML = '';
        vazio.classList.remove('hidden');
        return;
    }
    vazio.classList.add('hidden');

    tbody.innerHTML = formasPagamentoCache.map(f => `
        <tr class="border-b border-gray-100 hover:bg-gray-50">
            <td class="py-2 px-2 cursor-pointer" onclick="selecionarFormaPagamento(${f.id})">${f.descricao}</td>
            <td class="py-2 px-2 text-right">
                <button type="button" onclick="abrirFormEdicaoPagamento(${f.id})" class="text-blue-600 text-xs hover:underline">editar</button>
            </td>
        </tr>
    `).join('');
}


function selecionarFormaPagamento(id) {
    const forma = formasPagamentoCache.find(f => f.id === id);
    campoPagamento.value = forma.id;
    document.getElementById('texto-pagamento-selecionado').innerText = forma.descricao;
    atualizarTravaCabecalho();
    fecharModalPagamento();
}

function abrirFormNovaFormaPagamento() {
    document.getElementById('pagamento-form-id').value = '';
    document.getElementById('pagamento-form-descricao').value = '';
    document.getElementById('pagamento-form-meio').value = '01';
    document.getElementById('pagamento-form-indpag').value = '0';
    document.getElementById('form-pagamento').classList.remove('hidden');
}


function abrirFormEdicaoPagamento(id) {
    const forma = formasPagamentoCache.find(f => f.id === id);
    document.getElementById('pagamento-form-id').value = forma.id;
    document.getElementById('pagamento-form-descricao').value = forma.descricao;
    document.getElementById('pagamento-form-meio').value = forma.meio_pagamento ?? '99';
    document.getElementById('pagamento-form-indpag').value = forma.ind_pag ?? 0;
    document.getElementById('form-pagamento').classList.remove('hidden');
}


function fecharFormPagamento() {
    document.getElementById('form-pagamento').classList.add('hidden');
}

async function salvarFormaPagamento(botao) {
    const id = document.getElementById('pagamento-form-id').value;
    const descricao = document.getElementById('pagamento-form-descricao').value.trim();
    const meio_pagamento = document.getElementById('pagamento-form-meio').value;
    const ind_pag = document.getElementById('pagamento-form-indpag').value;

    if (descricao.length < 2) {
        mostrarAviso('Informe uma descrição válida.', 'erro');
        return;
    }

    const rota = id ? `{{ route('formas-pagamento.editar') }}` : `{{ route('formas-pagamento.criar') }}`;
    const payload = id
    ? { id, descricao, meio_pagamento, ind_pag }
    : { descricao, meio_pagamento, ind_pag };

    await comCarregando(botao, 'Salvando...', async () => {
        try {
            const formaSalva = await postJson(rota, payload);

            fecharFormPagamento();
            await buscarFormaPagamento();

            // Se a forma editada é a que já estava selecionada no cabeçalho, atualiza o texto exibido
            if (campoPagamento.value == formaSalva.id) {
                document.getElementById('texto-pagamento-selecionado').innerText = formaSalva.descricao;
            }

            mostrarAviso('Forma de pagamento salva.', 'sucesso');
        } catch (e) {
            mostrarAviso('Não foi possível salvar a forma de pagamento:\n' + e.message, 'erro');
        }
    });
}

campoCfop.addEventListener('change', atualizarTravaCabecalho);


function abrirModalCfop() {
    document.getElementById('modal-cfop').classList.remove('hidden');
    document.getElementById('modal-cfop').classList.add('flex');
    document.getElementById('cfop-busca').value = '';
    buscarCfop();
}

function fecharModalCfop() {
    document.getElementById('modal-cfop').classList.add('hidden');
    document.getElementById('modal-cfop').classList.remove('flex');
    fecharFormCfop();
}

async function buscarCfop() {
    const termo = document.getElementById('cfop-busca').value.trim();
    try {
        cfopsCache = await requisicaoJson(`{{ route('cfop-saida.listar') }}?termo=${encodeURIComponent(termo)}`);
    } catch (e) {
        mostrarAviso('Falha ao buscar CFOPs:\n' + e.message, 'erro');
        cfopsCache = [];
    }
    renderizarListaCfop();
}

function renderizarListaCfop() {
    const tbody = document.getElementById('cfop-lista');
    const vazio = document.getElementById('cfop-vazio');

    if (cfopsCache.length === 0) {
        tbody.innerHTML = '';
        vazio.classList.remove('hidden');
        return;
    }
    vazio.classList.add('hidden');

    tbody.innerHTML = cfopsCache.map(c => `
        <tr class="border-b border-gray-100 hover:bg-gray-50">
            <td class="py-2 font-mono cursor-pointer" onclick="selecionarCfop(${c.id})">${c.codigo}</td>
            <td class="py-2 cursor-pointer" onclick="selecionarCfop(${c.id})">${c.descricao}</td>
            <td class="py-2 text-center cursor-pointer text-xs" onclick="selecionarCfop(${c.id})">${c.tipo_operacao === 'entrada' ? 'Entrada' : 'Saída'}</td>
            <td class="py-2 text-center cursor-pointer" onclick="selecionarCfop(${c.id})">${c.movimenta_estoque ? 'Sim' : 'Não'}</td>
            <td class="py-2 text-right">
                <button type="button" onclick="abrirFormEdicaoCfop(${c.id})" class="text-blue-600 text-xs hover:underline">editar</button>
            </td>
            

        </tr>
    `).join('');
}


// Aqui está o auto-preenchimento pedido: escolher o CFOP já preenche natureza e finalidade
function selecionarCfop(id) {
    const cfop = cfopsCache.find(c => c.id === id);
    window.cfopDestacarBases = !!cfop.destacar_bases;

    campoCfop.value = cfop.id;
    window.cfopCodigoNota = String(cfop.codigo);
    document.getElementById('texto-cfop-selecionado').innerText = `${cfop.codigo} - ${cfop.descricao}`;

    campoNatureza.value = cfop.natureza_operacao_padrao ?? '';
    campoFinalidade.value = cfop.finalidade_padrao ?? 1;

    
    atualizarTravaCabecalho();
    renderizarGridItens(); // o CFOP efetivo dos itens depende do CFOP da nota
    fecharModalCfop();
}


function abrirFormNovoCfop() {
    document.getElementById('cfop-form-id').value = '';
    document.getElementById('cfop-form-codigo').value = '';
    document.getElementById('cfop-form-descricao').value = '';
    document.getElementById('cfop-form-movimenta').checked = true;
    document.getElementById('cfop-form-natureza').value = '';
    document.getElementById('cfop-form-finalidade').value = '1';
    document.getElementById('form-cfop').classList.remove('hidden');
    document.getElementById('cfop-form-tipo').value = 'saida';
    document.getElementById('cfop-form-destacar-bases').checked = false;
}

function abrirFormEdicaoCfop(id) {
    const cfop = cfopsCache.find(c => c.id === id);
    document.getElementById('cfop-form-id').value = cfop.id;
    document.getElementById('cfop-form-codigo').value = cfop.codigo;
    document.getElementById('cfop-form-descricao').value = cfop.descricao;
    document.getElementById('cfop-form-movimenta').checked = cfop.movimenta_estoque;
    document.getElementById('cfop-form-natureza').value = cfop.natureza_operacao_padrao ?? '';
    document.getElementById('cfop-form-finalidade').value = cfop.finalidade_padrao ?? 1;
    document.getElementById('form-cfop').classList.remove('hidden');
    document.getElementById('cfop-form-tipo').value = cfop.tipo_operacao ?? 'saida';
    document.getElementById('cfop-form-destacar-bases').checked = cfop.destacar_bases ?? false;


}

function fecharFormCfop() {
    document.getElementById('form-cfop').classList.add('hidden');
}

async function salvarFormCfop(botao) {
    const id = document.getElementById('cfop-form-id').value;
    const codigo = document.getElementById('cfop-form-codigo').value.trim();
    const descricao = document.getElementById('cfop-form-descricao').value.trim();
    const movimentaEstoque = document.getElementById('cfop-form-movimenta').checked;
    const naturezaPadrao = document.getElementById('cfop-form-natureza').value.trim();
    const finalidadePadrao = document.getElementById('cfop-form-finalidade').value;

    if (codigo.length !== 4 || descricao.length < 3) {
        mostrarAviso('Informe um código de 4 dígitos e uma descrição válida.', 'erro');
        return;
    }

    const rota = id ? `{{ route('cfop-saida.editar') }}` : `{{ route('cfop-saida.criar') }}`;
    const payload = {
        codigo, descricao,
        tipo_operacao: document.getElementById('cfop-form-tipo').value,
        movimenta_estoque: movimentaEstoque,
        natureza_operacao_padrao: naturezaPadrao,
        finalidade_padrao: finalidadePadrao,
        destacar_bases: document.getElementById('cfop-form-destacar-bases').checked,
    };

    if (id) payload.id = id;

    await comCarregando(botao, 'Salvando...', async () => {
        try {
            const cfopSalvo = await postJson(rota, payload);

            fecharFormCfop();
            await buscarCfop();

            if (campoCfop.value == cfopSalvo.id) {
                window.cfopDestacarBases = !!cfopSalvo.destacar_bases;
                window.cfopCodigoNota = String(cfopSalvo.codigo);

                document.getElementById('texto-cfop-selecionado').innerText = `${cfopSalvo.codigo} - ${cfopSalvo.descricao}`;
                campoNatureza.value = cfopSalvo.natureza_operacao_padrao ?? '';
                campoFinalidade.value = cfopSalvo.finalidade_padrao ?? 1;
                renderizarGridItens();
            }

            mostrarAviso('CFOP salvo.', 'sucesso');
        } catch (e) {
            mostrarAviso('Não foi possível salvar o CFOP:\n' + e.message, 'erro');
        }
    });
}



function atualizarTravaCabecalho() {
    const valido = cabecalhoValido();
    inputBuscaNf.disabled = !valido;
    document.getElementById('aviso-cabecalho').classList.toggle('hidden', valido);
}

[campoCliente, campoCfop].forEach(campo => {
    campo.addEventListener('input', atualizarTravaCabecalho);
    campo.addEventListener('change', atualizarTravaCabecalho);
});

inputBuscaNf?.addEventListener('input', () => {
    if (!document.getElementById('modal-busca-produto-nf').classList.contains('hidden')) return;
    clearTimeout(timeoutBuscaNf);
    const valor = inputBuscaNf.value.trim();
    if (/^\d+$/.test(valor)) return;
    if (valor.length < 1) return;
    timeoutBuscaNf = setTimeout(() => buscarProdutoNf(valor), 300);
});

inputBuscaNf?.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    if (!document.getElementById('modal-busca-produto-nf').classList.contains('hidden')) return;
    e.preventDefault();
    const termo = inputBuscaNf.value.trim();
    if (termo.length < 1) return;

    const resp = await fetch(`{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    const produtos = await resp.json();
    
    const exatos = produtos.filter(p => p.codigo_barras === termo || p.codigo_interno === termo);
    if (exatos.length === 1) {
        abrirEditorItem(exatos[0]);
        inputBuscaNf.value = '';
        return;
    }

    resultadosAtuaisNf = produtos;
    indiceSelecionadoNf = produtos.length > 0 ? 0 : -1;
    abrirModalBuscaNf(termo);
});

function abrirModalBuscaNf(termoInicial) {
    const modal = document.getElementById('modal-busca-produto-nf');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaModalNf.value = termoInicial ?? inputBuscaNf.value;
    inputBuscaNf.value = '';
    renderizarResultadosNf();
    inputBuscaModalNf.focus();
}

function fecharModalBuscaNf() {
    document.getElementById('modal-busca-produto-nf').classList.add('hidden');
    document.getElementById('modal-busca-produto-nf').classList.remove('flex');
    inputBuscaModalNf.value = '';
    resultadosAtuaisNf = [];
    indiceSelecionadoNf = -1;
    inputBuscaNf.focus();
}

async function buscarProdutoNf(termo) {
    try {
        resultadosAtuaisNf = await requisicaoJson(
            `{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`
        );
    } catch (e) {
        mostrarAviso('Falha ao buscar produtos:\n' + e.message, 'erro');
        return; // não abre o modal com resultado antigo ou vazio
    }

    indiceSelecionadoNf = resultadosAtuaisNf.length > 0 ? 0 : -1;
    abrirModalBuscaNf(termo);
}

inputBuscaModalNf?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaNf);
    const valor = inputBuscaModalNf.value.trim();
    if (valor.length < 1) { resultadosAtuaisNf = []; renderizarResultadosNf(); return; }
    timeoutBuscaNf = setTimeout(() => buscarProdutoNf(valor), 300);
});

inputBuscaModalNf?.addEventListener('keydown', (e) => {
    if (resultadosAtuaisNf.length === 0) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); indiceSelecionadoNf = (indiceSelecionadoNf + 1) % resultadosAtuaisNf.length; renderizarResultadosNf(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); indiceSelecionadoNf = (indiceSelecionadoNf - 1 + resultadosAtuaisNf.length) % resultadosAtuaisNf.length; renderizarResultadosNf(); }
    else if (e.key === 'Enter') { e.preventDefault(); if (indiceSelecionadoNf >= 0) selecionarResultadoNf(indiceSelecionadoNf); }
    else if (e.key === 'Escape') { fecharModalBuscaNf(); }
});

function renderizarResultadosNf() {
    if (resultadosAtuaisNf.length === 0) {
        linhasBuscaDivNf.innerHTML = '<tr><td colspan="3" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }
    linhasBuscaDivNf.innerHTML = resultadosAtuaisNf.map((p, index) => {
        const destacado = index === indiceSelecionadoNf;
        const codigo = p.codigo_barras || p.codigo_interno;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarResultadoNf(${index})">
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${codigo}</td>
                <td class="py-3 font-medium">${p.nome}</td>
                <td class="py-3 ${destacado ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">R$ ${Number(p.preco_venda).toFixed(2)}</td>
                <td class="py-3 font-medium">${p.estoque}</td>
            </tr>
        `;
    }).join('');
}

function selecionarResultadoNf(index) {
    const produto = resultadosAtuaisNf[index];
    fecharModalBuscaNf();
    abrirEditorItem(produto);
}

function abrirEditorItem(produto) {
    produtoSelecionadoParaEditor = produto;
    indiceItemEmEdicao = null;
    atualizarBotoesEditor();
    renderizarGridItens(); 
    document.getElementById('editor-produto-nome').innerText = produto.nome;
    document.getElementById('editor-codigo').value = produto.codigo_interno ?? '';
    document.getElementById('editor-codigo-barras').value = produto.codigo_barras ?? '';
    document.getElementById('editor-cst-csosn').value = produto.tributacao?.cst_icms ?? produto.tributacao?.csosn ?? '';
    document.getElementById('editor-descricao').value = produto.nome;
    document.getElementById('editor-quantidade').value = 1;
    document.getElementById('editor-valor-unitario').value = produto.preco_venda;
    document.getElementById('editor-desconto').value = 0;
    document.getElementById('editor-outras-despesas').value = 0;
    document.getElementById('editor-frete').value = 0;
    document.getElementById('editor-desconto-percentual').value = 0;
    document.getElementById('editor-item').classList.remove('hidden');
    atualizarCalculosEditor('valor');

    const bases = window.cfopDestacarBases;
    ['editor-bc-icms', 'editor-valor-icms', 'editor-aliquota-icms', 'editor-valor-ipi', 'editor-aliquota-ipi']
    .forEach(id => document.getElementById(id).readOnly = !bases);

    document.getElementById('editor-quantidade').focus();
    document.getElementById('editor-referencia-devolucao').classList
    .toggle('hidden', !finalidadesRefPorItem.includes(String(campoFinalidade.value)));
    document.getElementById('editor-ref-chave').value = '';
    document.getElementById('editor-ref-nitem').value = '';
}

function atualizarBotoesEditor() {
    const editando = indiceItemEmEdicao !== null;
    document.getElementById('btn-editor-confirmar').innerText = editando ? 'Atualizar item' : 'Adicionar à nota';
    document.getElementById('btn-editor-remover').innerText  = editando ? 'Remover item' : 'Cancelar';
}

function fecharEditorItem() {
    document.getElementById('editor-item').classList.add('hidden');
    produtoSelecionadoParaEditor = null;
    indiceItemEmEdicao = null;
    renderizarGridItens();
    inputBuscaNf.focus();
}

// Item na nota => remove. Produto ainda não adicionado => só cancela a operação.
function removerOuCancelarEditor() {
    if (indiceItemEmEdicao !== null) {
        itensNota.splice(indiceItemEmEdicao, 1);
    }
    fecharEditorItem();
}

function editarItemDaGrid(index) {
    const item = itensNota[index];
    if (!item) return;

    // o editor lê tributação/IPI daqui para recalcular ICMS e IPI
    produtoSelecionadoParaEditor = {
        produto_id:          item.produto_id,
        produto_variante_id: item.produto_variante_id ?? null,
        codigo_interno:      item.codigo,
        codigo_barras:       item.codigo_barras,
        tributacao:          item.tributacao ?? null,
        ipi:                 item.ipi ?? null,
    };
    indiceItemEmEdicao = index;

    document.getElementById('editor-produto-nome').innerText = item.descricao;
    document.getElementById('editor-codigo').value           = item.codigo ?? '';
    document.getElementById('editor-codigo-barras').value    = item.codigo_barras ?? '';
    document.getElementById('editor-cst-csosn').value        = item.cst_csosn ?? '';
    document.getElementById('editor-descricao').value        = item.descricao;
    document.getElementById('editor-quantidade').value       = item.quantidade;
    document.getElementById('editor-valor-unitario').value   = item.valor_unitario;
    document.getElementById('editor-desconto').value         = item.valor_desconto;
    document.getElementById('editor-desconto-percentual').value = item.desconto_percentual;
    document.getElementById('editor-outras-despesas').value = item.valor_outras_despesas ?? 0;
    document.getElementById('editor-frete').value = item.valor_frete ?? 0;

    atualizarCalculosEditor('valor');

    // CFOP que destaca bases: devolve ao editor os valores manuais que o operador tinha digitado
    const bases = !!window.cfopDestacarBases;
    ['editor-bc-icms', 'editor-valor-icms', 'editor-aliquota-icms', 'editor-valor-ipi', 'editor-aliquota-ipi']
        .forEach(id => document.getElementById(id).readOnly = !bases);

    if (bases && item.bases_manuais) {
        document.getElementById('editor-bc-icms').value        = 'R$ ' + Number(item.bc_icms).toFixed(2);
        document.getElementById('editor-valor-icms').value     = 'R$ ' + Number(item.valor_icms).toFixed(2);
        document.getElementById('editor-aliquota-icms').value  = Number(item.aliquota_icms).toFixed(2) + '%';
        document.getElementById('editor-valor-ipi').value      = 'R$ ' + Number(item.valor_ipi).toFixed(2);
        document.getElementById('editor-aliquota-ipi').value   = Number(item.aliquota_ipi).toFixed(2) + '%';
    }

    document.getElementById('editor-referencia-devolucao').classList
        .toggle('hidden', !finalidadesRefPorItem.includes(String(campoFinalidade.value)));
    document.getElementById('editor-ref-chave').value = item.ref_chave_acesso ?? '';
    document.getElementById('editor-ref-nitem').value = item.ref_nitem ?? '';

    const editor = document.getElementById('editor-item');
    editor.classList.remove('hidden');
    atualizarBotoesEditor();
    renderizarGridItens(); // destaca a linha em edição
    editor.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('editor-quantidade').focus();
}

/**
 * Recalcula Vl Total, BC ICMS, Vlr. ICMS e % ICMS em tempo real, além de manter
 * Desconto (R$) e Desconto % sincronizados entre si. origem indica qual dos dois
 * campos de desconto foi editado por último, pra saber qual recalcular a partir do outro.
 */
function atualizarCalculosEditor(origemDesconto, manterTributosManuais = false) {
    const quantidade = parseFloat(document.getElementById('editor-quantidade').value) || 0;
    const valorUnitario = parseFloat(document.getElementById('editor-valor-unitario').value) || 0;
    const subtotalBruto = quantidade * valorUnitario;

    let desconto = parseFloat(document.getElementById('editor-desconto').value) || 0;
    let descontoPercentual = parseFloat(document.getElementById('editor-desconto-percentual').value) || 0;

    if (origemDesconto === 'percentual') {
        desconto = subtotalBruto > 0 ? Math.round((subtotalBruto * descontoPercentual / 100) * 100) / 100 : 0;
        document.getElementById('editor-desconto').value = desconto.toFixed(2);
    } else {
        descontoPercentual = subtotalBruto > 0 ? Math.round((desconto / subtotalBruto) * 10000) / 100 : 0;
        document.getElementById('editor-desconto-percentual').value = descontoPercentual.toFixed(2);
    }

    const valorTotal = Math.max(subtotalBruto - desconto, 0);
    const outrasDespesas = parseFloat(document.getElementById('editor-outras-despesas').value) || 0;
    const frete = freteModo === 'item' ? (parseFloat(document.getElementById('editor-frete').value) || 0) : 0;
    const baseImpostos = subtotalBruto + outrasDespesas + frete;
    document.getElementById('editor-valor-total').value = 'R$ ' + valorTotal.toFixed(2);

    // ICMS — mesma regra usada no NotaFiscalService::montarItens() na emissão real
    const trib = produtoSelecionadoParaEditor?.tributacao;
    const cstsComBaseCalculo = ['00', '10', '20', '70', '90'];
    // No Simples só existe grupo de base de cálculo para CSOSN 500 e 900 (igual ao serviço)
    const csosnComBaseCalculo = ['500', '900'];
    const simplesDestacando = window.crtEmpresa <= 2 && !!window.cfopDestacarBases;
    let bcIcms = 0, valorIcms = 0, aliquotaIcms = 0;

    if (window.crtEmpresa > 2 && trib && cstsComBaseCalculo.includes(trib.cst_icms)) {
        // Regime normal: calcula sempre
        bcIcms = baseImpostos;
        aliquotaIcms = parseFloat(trib.aliquota_icms) || 0;
        valorIcms = bcIcms * aliquotaIcms / 100;
    } else if (simplesDestacando && trib && csosnComBaseCalculo.includes(String(trib.csosn))) {
        // Simples Nacional: só sugere quando o CFOP destaca bases (ex.: devolução);
        // os campos continuam editáveis para o operador ajustar
        bcIcms = baseImpostos;
        aliquotaIcms = parseFloat(trib.aliquota_icms) || 0;
        valorIcms = bcIcms * aliquotaIcms / 100;
    }

    // Só atualiza os inputs de ICMS se NÃO estiver configurado para destacar bases OU se NÃO for para manter as edições manuais
    if (!window.cfopDestacarBases || !manterTributosManuais) {
        document.getElementById('editor-bc-icms').value = 'R$ ' + bcIcms.toFixed(2);
        document.getElementById('editor-valor-icms').value = 'R$ ' + valorIcms.toFixed(2);
        document.getElementById('editor-aliquota-icms').value = aliquotaIcms.toFixed(2) + '%';
    }

    // IPI — só CST 50 (Saída Tributada) gera valor
    const ipi = produtoSelecionadoParaEditor?.ipi;
    let valorIpi = 0, aliquotaIpi = 0;

    if (ipi && ipi.codigo === '50' && ipi.aliquota) {
        aliquotaIpi = parseFloat(ipi.aliquota) || 0;
        valorIpi = baseImpostos * aliquotaIpi / 100;
    }

    // Só atualiza os inputs de IPI se NÃO estiver configurado para destacar bases OU se NÃO for para manter as edições manuais
    if (!window.cfopDestacarBases || !manterTributosManuais) {
        document.getElementById('editor-valor-ipi').value = 'R$ ' + valorIpi.toFixed(2);
        document.getElementById('editor-aliquota-ipi').value = aliquotaIpi.toFixed(2) + '%';
    }
}


document.getElementById('editor-quantidade').addEventListener('input', () => atualizarCalculosEditor('valor'));
document.getElementById('editor-valor-unitario').addEventListener('input', () => atualizarCalculosEditor('valor'));
document.getElementById('editor-desconto').addEventListener('input', () => atualizarCalculosEditor('valor'));
document.getElementById('editor-desconto-percentual').addEventListener('input', () => atualizarCalculosEditor('percentual'));
document.getElementById('editor-outras-despesas').addEventListener('input', () => atualizarCalculosEditor('valor'));
document.getElementById('editor-frete').addEventListener('input', () => atualizarCalculosEditor('valor'));


function adicionarLinhaNaGrid() {
    const quantidade = parseFloat(document.getElementById('editor-quantidade').value) || 0;
    const valorUnitario = parseFloat(document.getElementById('editor-valor-unitario').value) || 0;
    const valorDesconto = parseFloat(document.getElementById('editor-desconto').value) || 0;
    const descontoPercentual = parseFloat(document.getElementById('editor-desconto-percentual').value) || 0;
    const outrasDespesas = parseFloat(document.getElementById('editor-outras-despesas').value) || 0;
    const descricao = document.getElementById('editor-descricao').value.trim();
    const refChave = document.getElementById('editor-ref-chave').value.replace(/\D/g, '');
    const refNitem = document.getElementById('editor-ref-nitem').value || null;
    const frete = freteModo === 'item' ? (parseFloat(document.getElementById('editor-frete').value) || 0) : 0;

    if (finalidadesRefPorItem.includes(String(campoFinalidade.value)) && refChave.length !== 44) {
        alert('Informe a chave de acesso (44 dígitos) da nota de origem deste item.');
        return;
    }

    if (quantidade <= 0 || valorUnitario < 0 || descricao.length < 1 || outrasDespesas < 0 || frete < 0) {
        alert('Preencha quantidade, valor unitário e descrição corretamente.');
        return;
    }

    const subtotalBruto = quantidade * valorUnitario;
    const valorTotal = Math.max(subtotalBruto - valorDesconto, 0);

    // Declaração da tributação e do CST/CSOSN do produto selecionado
    const trib = produtoSelecionadoParaEditor?.tributacao;
    const cstOuCsosn = trib?.csosn ?? trib?.cst_icms ?? '—';

    let bcIcms, valorIcms, aliquotaIcms, valorIpi, aliquotaIpi;
    const baseImpostos = subtotalBruto + outrasDespesas + frete;

    if (window.cfopDestacarBases) {
        bcIcms = parseFloat(document.getElementById('editor-bc-icms').value.replace('R$', '').replace(',', '.')) || 0;
        valorIcms = parseFloat(document.getElementById('editor-valor-icms').value.replace('R$', '').replace(',', '.')) || 0;
        aliquotaIcms = parseFloat(document.getElementById('editor-aliquota-icms').value.replace('%', '').replace(',', '.')) || 0;
        valorIpi = parseFloat(document.getElementById('editor-valor-ipi').value.replace('R$', '').replace(',', '.')) || 0;
        aliquotaIpi = parseFloat(document.getElementById('editor-aliquota-ipi').value.replace('%', '').replace(',', '.')) || 0;
    } else {
        const cstsComBaseCalculo = ['00', '10', '20', '70', '90'];
        bcIcms = 0; valorIcms = 0; aliquotaIcms = 0;

        if (window.crtEmpresa > 2 && trib && cstsComBaseCalculo.includes(trib.cst_icms)) {
            bcIcms = baseImpostos;
            aliquotaIcms = parseFloat(trib.aliquota_icms) || 0;
            valorIcms = bcIcms * aliquotaIcms / 100;
        }

        const ipi = produtoSelecionadoParaEditor?.ipi;
        valorIpi = 0; aliquotaIpi = 0;

        if (ipi && ipi.codigo === '50' && ipi.aliquota) {
            aliquotaIpi = parseFloat(ipi.aliquota) || 0;
            valorIpi = baseImpostos * aliquotaIpi / 100;
        }
    }

    const itemNovo = {
        produto_id: produtoSelecionadoParaEditor.produto_id,
        codigo: produtoSelecionadoParaEditor.codigo_interno,
        produto_variante_id: produtoSelecionadoParaEditor.produto_variante_id ?? null,
        codigo_barras: produtoSelecionadoParaEditor.codigo_barras,
        descricao,
        cst_csosn: cstOuCsosn,
        quantidade,
        valor_unitario: valorUnitario,
        valor_total: valorTotal,
        valor_desconto: valorDesconto,
        valor_outras_despesas: outrasDespesas,
        valor_frete: frete,
        desconto_percentual: descontoPercentual,
        bc_icms: bcIcms,
        valor_icms: valorIcms,
        aliquota_icms: aliquotaIcms,
        valor_ipi: valorIpi,
        aliquota_ipi: aliquotaIpi,
        bases_manuais: !!window.cfopDestacarBases,
        ref_chave_acesso: refChave || null,
        ref_nitem: refNitem,
        // guardados para permitir reeditar o item depois
        tributacao: produtoSelecionadoParaEditor.tributacao ?? null,
        ipi: produtoSelecionadoParaEditor.ipi ?? null,
    };

    if (indiceItemEmEdicao !== null) {
        itensNota[indiceItemEmEdicao] = itemNovo; // atualiza no mesmo lugar, mantém a ordem
    } else {
        itensNota.push(itemNovo);
    }

    fecharEditorItem(); // esconde o editor, zera estado, re-renderiza e foca na busca
}



function prepararBlocoMotivoAjuste() {
    const bloco = document.getElementById('bloco-motivo-ajuste');
    const select = document.getElementById('modal-motivo-ajuste');
    const opcoes = motivosAjuste[String(campoFinalidade.value)];

    if (!opcoes) {
        bloco.classList.add('hidden');
        select.innerHTML = '';
        return;
    }

    select.innerHTML = '<option value="">Selecione...</option>' +
        Object.entries(opcoes).map(([codigo, texto]) => `<option value="${codigo}">${codigo} - ${texto}</option>`).join('');
    select.value = document.getElementById('campo-motivo-ajuste').value;
    bloco.classList.remove('hidden');
}


function renderizarGridItens() {
    const tbody = document.getElementById('linhas-grid-itens');
    const vazia = document.getElementById('grid-vazia');
    

    if (itensNota.length === 0) {
        tbody.innerHTML = '';
        vazia.classList.remove('hidden');
        document.getElementById('total-grid-itens').innerText = 'R$ 0,00';
        document.getElementById('total-frete-grid').innerText = 'R$ 0,00';
        return;
    }
    vazia.classList.add('hidden');

    let totalNota = 0;

    tbody.innerHTML = itensNota.map((item, index) => {
        totalNota += item.valor_total + Number(item.valor_outras_despesas ?? 0);
        const emEdicao = index === indiceItemEmEdicao;
        const refOrigem = item.ref_chave_acesso
            ? item.ref_chave_acesso.slice(-8) + (item.ref_nitem ? ' (item ' + item.ref_nitem + ')' : '')
            : '—';

        return `
            <tr class="cursor-pointer transition ${emEdicao ? 'bg-slate-100' : 'hover:bg-gray-50'}"
                onclick="editarItemDaGrid(${index})" title="Clique para editar">
                <td class="px-3 py-2">${item.codigo ?? '—'}</td>
                <td class="px-3 py-2">${item.codigo_barras ?? '—'}</td>
                <td class="px-3 py-2 whitespace-normal min-w-48">${item.descricao}</td>
                <td class="px-3 py-2 font-mono">${cfopEfetivoItem(item)}</td>
                <td class="px-3 py-2 font-mono">${item.cst_csosn ?? '—'}</td>
                <td class="px-3 py-2 text-right">${item.quantidade}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_unitario.toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium">R$ ${item.valor_total.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_desconto.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.desconto_percentual.toFixed(2)}%</td>
                <td class="px-3 py-2 text-right">R$ ${Number(item.valor_outras_despesas ?? 0).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${freteModo === 'item' ? 'R$ ' + Number(item.valor_frete ?? 0).toFixed(2) : '—'}</td>
                <td class="px-3 py-2 text-right">R$ ${item.bc_icms.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_icms.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.aliquota_icms.toFixed(2)}%</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_ipi.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.aliquota_ipi.toFixed(2)}%</td>
                <td class="px-3 py-2 text-xs font-mono">${refOrigem}</td>
            </tr>
        `;
    }).join('');

    const frete = freteTotalNota();
    document.getElementById('total-frete-grid').innerText = 'R$ ' + frete.toFixed(2).replace('.', ',');
    document.getElementById('total-grid-itens').innerText = 'R$ ' + (totalNota + frete).toFixed(2).replace('.', ',');
}

document.getElementById('form-nota').addEventListener('submit', function (e) {
    if (itensNota.length === 0) {
        e.preventDefault();
        alert('Adicione ao menos um item antes de salvar a nota.');
        return;
    }
    document.getElementById('itens_json').value = JSON.stringify(itensNota);
});


function abrirModalReferencia() {
    const modoItem = freteModo === 'item';
    const mostrarTransportador = modFrete !== '9';
    document.getElementById('bloco-transportador-modal').classList.toggle('hidden', !mostrarTransportador);
    document.getElementById('modal-transportador-obrigatorio').classList.toggle('hidden', modFrete !== '2');
    document.getElementById('modal-mod-frete-texto').innerText = mostrarTransportador
        ? '(' + rotulosModFrete[modFrete] + ')'
        : '';
    document.getElementById('bloco-frete-global').classList.toggle('hidden', modoItem);
    document.getElementById('bloco-frete-itens').classList.toggle('hidden', !modoItem);
    document.getElementById('modal-frete-global').value = freteGlobal.toFixed(2);
    document.getElementById('modal-frete-soma').innerText = 'R$ ' + somaFreteItens().toFixed(2).replace('.', ',');
    document.getElementById('bloco-frete-modal').classList.toggle('hidden', modFrete === '9');
    renderizarChavesReferenciadas();
    document.getElementById('modal-informacoes-complementares').value =
        document.getElementById('campo-informacoes-complementares').value;

    prepararBlocoMotivoAjuste();

    const obrigatorio = ['2', '5', '6'].includes(String(campoFinalidade.value));
    document.getElementById('aviso-referencia-obrigatoria').classList.toggle('hidden', !obrigatorio);

    document.getElementById('modal-referencia-nota').classList.remove('hidden');
    document.getElementById('modal-referencia-nota').classList.add('flex');
}

function fecharModalReferencia() {
    document.getElementById('modal-referencia-nota').classList.add('hidden');
    document.getElementById('modal-referencia-nota').classList.remove('flex');
}

function adicionarChaveReferenciada() {
    const input = document.getElementById('input-chave-referenciada');
    const chave = input.value.replace(/\D/g, '');

    if (chave.length !== 44) {
        alert('A chave de acesso deve ter 44 dígitos numéricos.');
        return;
    }
    if (chavesReferenciadas.includes(chave)) {
        alert('Essa chave já foi adicionada.');
        return;
    }

    chavesReferenciadas.push(chave);
    input.value = '';
    renderizarChavesReferenciadas();
}

function removerChaveReferenciada(index) {
    chavesReferenciadas.splice(index, 1);
    renderizarChavesReferenciadas();
}

function renderizarChavesReferenciadas() {
    const lista = document.getElementById('lista-chaves-referenciadas');
    const vazio = document.getElementById('chaves-vazio');

    if (chavesReferenciadas.length === 0) {
        lista.innerHTML = '';
        vazio.classList.remove('hidden');
        return;
    }
    vazio.classList.add('hidden');

    lista.innerHTML = chavesReferenciadas.map((chave, index) => `
        <li class="flex justify-between items-center bg-gray-50 rounded px-2 py-1">
            <span class="font-mono text-xs">${chave}</span>
            <button type="button" onclick="removerChaveReferenciada(${index})" class="text-red-600 text-xs hover:underline">remover</button>
        </li>
    `).join('');
}

function confirmarESalvarNota() {
    const finalidade = String(campoFinalidade.value);
    const ehAjuste = ['5', '6'].includes(finalidade);
    const motivo = document.getElementById('modal-motivo-ajuste').value;

    if (['2', '5', '6'].includes(finalidade) && chavesReferenciadas.length === 0) {
        alert('Esta finalidade exige ao menos uma nota fiscal referenciada.');
        return;
    }

    if (ehAjuste && !motivo) {
        alert('Selecione o motivo do ajuste.');
        return;
    }

    if (finalidadesRefPorItem.includes(finalidade)) {
        const itemSemReferencia = itensNota.find(i => !i.ref_chave_acesso);
        if (itemSemReferencia) {
            alert(`O item "${itemSemReferencia.descricao}" está sem a chave da nota de origem.`);
            return;
        }
    }

    if (freteModo === 'global' && modFrete !== '9') {
        const valor = parseFloat(document.getElementById('modal-frete-global').value) || 0;
        if (valor < 0) {
                mostrarAviso('O frete não pode ser negativo.', 'erro');
                return;
            }
        freteGlobal = Math.round(valor * 100) / 100;
    }
    document.getElementById('campo-frete-modo').value = freteModo;
    document.getElementById('campo-frete-total').value = freteTotalNota().toFixed(2);

    document.getElementById('campo-motivo-ajuste').value = ehAjuste ? motivo : '';
    document.getElementById('campo-notas-referenciadas').value = JSON.stringify(chavesReferenciadas);
    document.getElementById('campo-informacoes-complementares').value =
    document.getElementById('modal-informacoes-complementares').value;
    document.getElementById('campo-mod-frete').value = modFrete;
    if (modFrete === '2' && !campoTransportador.value) {
        mostrarAviso('Informe o transportador: o frete é por conta de terceiros.', 'erro');
        return;
    }
    fecharModalReferencia();
    document.getElementById('form-nota').requestSubmit();
}

function mostrarAviso(mensagem, tipo = 'erro') {
    const cores = {
        erro:    'bg-red-50 border-red-300 text-red-800',
        sucesso: 'bg-green-50 border-green-300 text-green-800',
        info:    'bg-blue-50 border-blue-300 text-blue-800',
    };

    const aviso = document.createElement('div');
    aviso.className = `border rounded-lg shadow-lg px-4 py-3 text-sm flex gap-3 items-start ${cores[tipo] ?? cores.info}`;
    aviso.setAttribute('role', 'alert');

    const texto = document.createElement('div');
    texto.className = 'flex-1 whitespace-pre-line';
    texto.textContent = mensagem; // textContent: mensagem do servidor nunca vira HTML

    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'text-lg leading-none opacity-60 hover:opacity-100';
    fechar.innerHTML = '&times;';
    fechar.onclick = () => aviso.remove();

    aviso.append(texto, fechar);
    document.getElementById('area-avisos').appendChild(aviso);

    setTimeout(() => aviso.remove(), tipo === 'erro' ? 10000 : 4000);
}

// Desabilita o botão e mostra "Salvando..." enquanto a ação roda
async function comCarregando(botao, textoCarregando, acao) {
    if (!botao || botao.disabled) return;
    const textoOriginal = botao.innerText;
    botao.disabled = true;
    botao.innerText = textoCarregando;
    botao.classList.add('opacity-60', 'cursor-wait');
    try {
        return await acao();
    } finally {
        botao.disabled = false;
        botao.innerText = textoOriginal;
        botao.classList.remove('opacity-60', 'cursor-wait');
    }
}

async function requisicaoJson(url, opcoes = {}) {
    let resp;
    try {
        resp = await fetch(url, {
            ...opcoes,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                ...(opcoes.headers ?? {}),
            },
        });
    } catch (e) {
        throw new Error('Sem conexão com o servidor. Verifique se o sistema local está em execução.');
    }

    const ehJson = (resp.headers.get('content-type') || '').includes('application/json');
    const dados = ehJson ? await resp.json() : null;

    if (!resp.ok) {
        if (resp.status === 419) throw new Error('Sua sessão expirou. Recarregue a página (F5) e tente de novo.');
        if (dados?.errors) throw new Error(Object.values(dados.errors).flat().join('\n'));
        throw new Error(dados?.message ?? `O servidor retornou erro ${resp.status}.`);
    }

    if (dados === null) throw new Error('Resposta inesperada do servidor. Tente novamente.');
    return dados;
}

function postJson(url, payload) {
    return requisicaoJson(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
}

// Inicialização: se vier preenchido (edição) ou old() de uma tentativa anterior (criação), já libera e renderiza
atualizarTravaCabecalho();
renderizarGridItens();
aplicarVisibilidadeFrete();
</script>