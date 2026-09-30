@php
    $ehEdicao = isset($notaFiscal) && $notaFiscal !== null;

    $itensIniciais = $ehEdicao
        ? $notaFiscal->itens->map(function ($i) {
            $subtotalBruto = (float) $i->valor_unitario * (float) $i->quantidade;
            $cstsComBaseCalculo = ['00', '10', '20', '70', '90'];

            // 1. ICMS: Prioriza os dados manuais se preenchidos, senão calcula pelo padrão
            if (!is_null($i->aliquota_icms_manual) || !is_null($i->bc_icms_manual)) {
                $bcIcms       = (float) ($i->bc_icms_manual ?? 0);
                $aliquotaIcms = (float) ($i->aliquota_icms_manual ?? 0);
                $valorIcms    = (float) ($i->valor_icms_manual ?? ($bcIcms * $aliquotaIcms / 100));
            } else {
                $trib = $i->tributacao;
                $bcIcms = 0; $valorIcms = 0; $aliquotaIcms = 0;

                if ($trib && in_array($trib->cst_icms, $cstsComBaseCalculo, true)) {
                    $bcIcms = $subtotalBruto;
                    $aliquotaIcms = (float) $trib->aliquota_icms;
                    $valorIcms = $bcIcms * $aliquotaIcms / 100;
                }
            }

            // 2. IPI: Prioriza os dados manuais se preenchidos, senão calcula pelo padrão
            if (!is_null($i->aliquota_ipi_manual) || !is_null($i->valor_ipi_manual)) {
                $aliquotaIpi = (float) ($i->aliquota_ipi_manual ?? 0);
                $valorIpi    = (float) ($i->valor_ipi_manual ?? ($subtotalBruto * $aliquotaIpi / 100));
            } else {
                $ipi = $i->ipi;
                $valorIpi = 0; $aliquotaIpi = 0;

                if ($ipi && $ipi->codigo === '50' && $ipi->aliquota) {
                    $aliquotaIpi = (float) $ipi->aliquota;
                    $valorIpi = $subtotalBruto * $aliquotaIpi / 100;
                }
            }

            return [
                'produto_id'          => $i->produto_id,
                'codigo'              => $i->produto->codigo_interno,
                'codigo_barras'       => $i->produto->codigo_barras,
                'descricao'           => $i->descricao ?? $i->produto->nome,
                'quantidade'          => (float) $i->quantidade,
                'valor_unitario'      => (float) $i->valor_unitario,
                'valor_total'         => (float) $i->valor_total,
                'valor_desconto'      => (float) $i->valor_desconto,
                'desconto_percentual' => $subtotalBruto > 0 ? round(((float) $i->valor_desconto / $subtotalBruto) * 100, 2) : 0,
                'bc_icms'             => $bcIcms,
                'valor_icms'          => $valorIcms,
                'aliquota_icms'       => $aliquotaIcms,
                'valor_ipi'           => $valorIpi,
                'aliquota_ipi'        => $aliquotaIpi,
                'ref_chave_acesso'    => $i->ref_chave_acesso,
                'ref_nitem'           => $i->ref_nitem,
                'bases_manuais'       => !is_null($i->bc_icms_manual) || !is_null($i->valor_ipi_manual),
            ];
        })->values()
        : collect();

    $motivoAtual = old('motivo_ajuste', $ehEdicao ? $notaFiscal->motivo_ajuste : null);
    $labelsFinalidade = [1 => 'Normal', 2 => 'Complementar', 3 => 'Ajuste', 4 => 'Devolução', 5 => 'Nota de Crédito', 6 => 'Nota de Débito'];
    $naturezaAtual = old('natureza_operacao', $ehEdicao ? $notaFiscal->natureza_operacao : null);
    $finalidadeAtual = old('finalidade', $ehEdicao ? $notaFiscal->finalidade : null);
    $notasReferenciadasIniciais = $ehEdicao ? ($notaFiscal->notas_referenciadas ?? []) : [];
@endphp

<form id="form-nota" method="POST"
      action="{{ $ehEdicao ? route('notasfiscais.update', $notaFiscal) : route('notasfiscais.store') }}"
      class="flex flex-col gap-6 w-full max-w-5xl mx-auto mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif
    <input type="hidden" name="itens_json" id="itens_json">
    <input type="hidden" name="notas_referenciadas_json" id="campo-notas-referenciadas">
    <input type="hidden" name="informacoes_complementares" id="campo-informacoes-complementares"
        value="{{ old('informacoes_complementares', $ehEdicao ? $notaFiscal->informacoes_complementares : '') }}">

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-2 mb-4">
            @if ($ehEdicao)
                <a href="{{ route('notasfiscais.index') }}" title="Voltar para Notas Fiscais"
                class="text-gray-500 hover:text-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif
            <h1 class="text-lg font-semibold">{{ $ehEdicao ? 'Editar Nota Fiscal (rascunho)' : 'Nova Nota Fiscal (Saída)' }}</h1>
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

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="cliente_id" id="campo-cliente" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}"
                            @selected(old('cliente_id', $ehEdicao ? $notaFiscal->cliente_id : null) == $cliente->id)>
                            {{ $cliente->nome }} — {{ $cliente->cpf_cnpj_formatado }}
                        </option>
                    @endforeach
                </select>
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
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Descrição</label>
                    <input type="text" id="editor-descricao"
                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3">
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

                <div id="editor-referencia-devolucao" class="hidden grid grid-cols-2 gap-3">
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

            <button type="button" onclick="adicionarLinhaNaGrid()"
                    class="bg-gray-800 text-white rounded-lg px-3 py-1.5 text-sm h-fit hover:bg-gray-700 w-fit">
                Adicionar à nota
            </button>
        </div>

        @error('itens') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Cód. Barras</th>
                        <th class="text-left px-3 py-2">Descrição</th>
                        <th class="text-right px-3 py-2">Qtd</th>
                        <th class="text-right px-3 py-2">Vl Unit</th>
                        <th class="text-right px-3 py-2">Vl Total</th>
                        <th class="text-right px-3 py-2">Desconto</th>
                        <th class="text-right px-3 py-2">Desconto %</th>
                        <th class="text-right px-3 py-2">BC ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. ICMS</th>
                        <th class="text-right px-3 py-2">% ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. IPI</th>
                        <th class="text-right px-3 py-2">% IPI</th>
                        <th></th>
                        <th class="text-left px-3 py-2">Ref. NF origem</th>
                    </tr>
                </thead>
                <tbody id="linhas-grid-itens" class="divide-y divide-gray-100"></tbody>
                <tfoot class="bg-gray-50 font-medium">
                    <tr>
                        <td colspan="13" class="px-3 py-2 text-right">Total da nota</td>
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
                <button type="button" onclick="salvarFormCfop()"
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

        <div id="form-pagamento" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <input type="hidden" id="pagamento-form-id">
            <input type="text" id="pagamento-form-descricao" placeholder="Descrição (ex: A prazo 45 dias)"
                class="w-full border rounded px-3 py-2 text-sm mb-2">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormPagamento()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarFormaPagamento()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <tbody id="pagamento-lista"></tbody>
        </table>
        <p id="pagamento-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhuma forma encontrada.</p>
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
let itensNota = @json($itensIniciais);
const finalidadesRefPorItem = @json(array_map('strval', config('fiscal.finalidades_referencia_por_item', [])));
const motivosAjuste = @json(config('fiscal.motivos_ajuste', []));
let chavesReferenciadas = @json($notasReferenciadasIniciais);
let resultadosAtuaisNf = [];
let indiceSelecionadoNf = -1;
let timeoutBuscaNf;
let produtoSelecionadoParaEditor = null;
let cfopsCache = [];
let formasPagamentoCache = [];
const campoCfop = document.getElementById('campo-cfop');

const inputBuscaNf = document.getElementById('input-busca-item-nf');
const inputBuscaModalNf = document.getElementById('busca-produto-modal-nf');
const linhasBuscaDivNf = document.getElementById('linhas-busca-produto-nf');
const campoCliente = document.getElementById('campo-cliente');
const campoNatureza = document.getElementById('campo-natureza');
const campoFinalidade = document.getElementById('campo-finalidade');

const campoPagamento = document.getElementById('campo-pagamento');
const campoOperador = document.getElementById('campo-operador');



function sugerirTipoCfop() {
    const primeiro = document.getElementById('cfop-form-codigo').value.charAt(0);
    if (['1', '2', '3'].includes(primeiro)) document.getElementById('cfop-form-tipo').value = 'entrada';
    else if (['5', '6', '7'].includes(primeiro)) document.getElementById('cfop-form-tipo').value = 'saida';
}

function cabecalhoValido() {
    return campoCliente.value !== '' && campoNatureza.value.trim() !== '' && campoFinalidade.value !== ''
        && campoCfop.value !== '' && campoPagamento.value !== '' && campoOperador.value !== '';
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
    const resp = await fetch(`{{ route('formas-pagamento.listar') }}?termo=${encodeURIComponent(termo)}`);
    formasPagamentoCache = await resp.json();
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
    document.getElementById('form-pagamento').classList.remove('hidden');
}


function abrirFormEdicaoPagamento(id) {
    const forma = formasPagamentoCache.find(f => f.id === id);
    document.getElementById('pagamento-form-id').value = forma.id;
    document.getElementById('pagamento-form-descricao').value = forma.descricao;
    document.getElementById('form-pagamento').classList.remove('hidden');
}


function fecharFormPagamento() {
    document.getElementById('form-pagamento').classList.add('hidden');
}

async function salvarFormaPagamento() {
    const id = document.getElementById('pagamento-form-id').value;
    const descricao = document.getElementById('pagamento-form-descricao').value.trim();

    if (descricao.length < 2) {
        alert('Informe uma descrição válida.');
        return;
    }

    const rota = id ? `{{ route('formas-pagamento.editar') }}` : `{{ route('formas-pagamento.criar') }}`;
    const payload = id ? { id, descricao } : { descricao };

    const resp = await fetch(rota, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    });

    if (!resp.ok) {
        const erro = await resp.json();
        alert('Erro ao salvar: ' + (erro.message || 'verifique os dados.'));
        return;
    }

    const formaSalva = await resp.json();
    fecharFormPagamento();
    await buscarFormaPagamento();

    // Se a forma editada é a que já estava selecionada no cabeçalho, atualiza o texto exibido
    if (campoPagamento.value == formaSalva.id) {
        document.getElementById('texto-pagamento-selecionado').innerText = formaSalva.descricao;
    }
}
/**
 * Trava a área de itens até cliente + natureza + finalidade estarem preenchidos.
 * Evita o cenário de o operador montar a nota inteira e perder tudo por causa
 * de um erro de validação no cabeçalho — porque agora o cabeçalho é obrigatoriamente
 * válido ANTES de ele conseguir sequer buscar o primeiro produto.
 */
function cabecalhoValido() {
    return campoCliente.value !== '' && campoNatureza.value.trim() !== '' && campoFinalidade.value !== '' && campoCfop.value !== '';
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
    const resp = await fetch(`{{ route('cfop-saida.listar') }}?termo=${encodeURIComponent(termo)}`);
    cfopsCache = await resp.json();
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
    document.getElementById('texto-cfop-selecionado').innerText = `${cfop.codigo} - ${cfop.descricao}`;

    campoNatureza.value = cfop.natureza_operacao_padrao ?? '';
    campoFinalidade.value = cfop.finalidade_padrao ?? 1;

    
    atualizarTravaCabecalho();
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

async function salvarFormCfop() {
    const id = document.getElementById('cfop-form-id').value;
    const codigo = document.getElementById('cfop-form-codigo').value.trim();
    const descricao = document.getElementById('cfop-form-descricao').value.trim();
    const movimentaEstoque = document.getElementById('cfop-form-movimenta').checked;
    const naturezaPadrao = document.getElementById('cfop-form-natureza').value.trim();
    const finalidadePadrao = document.getElementById('cfop-form-finalidade').value;

    if (codigo.length !== 4 || descricao.length < 3) {
        alert('Informe um código de 4 dígitos e uma descrição válida.');
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

    const resp = await fetch(rota, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    });

    if (!resp.ok) {
        const erro = await resp.json();
        alert('Erro ao salvar CFOP: ' + (erro.message || 'verifique os dados.'));
        return;
    }

    const cfopSalvo = await resp.json();
    fecharFormCfop();
    await buscarCfop();

    if (campoCfop.value == cfopSalvo.id) {
        window.cfopDestacarBases = !!cfopSalvo.destacar_bases;

        document.getElementById('texto-cfop-selecionado').innerText = `${cfopSalvo.codigo} - ${cfopSalvo.descricao}`;
        campoNatureza.value = cfopSalvo.natureza_operacao_padrao ?? '';
        campoFinalidade.value = cfopSalvo.finalidade_padrao ?? 1;
    }
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
    const exato = produtos.find(p => p.codigo_barras === termo || p.codigo_interno === termo);

    if (exato) {
        abrirEditorItem(exato);
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
    const resp = await fetch(`{{ route('notasfiscais.buscar-produto') }}?termo=${encodeURIComponent(termo)}`);
    resultadosAtuaisNf = await resp.json();
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
    document.getElementById('editor-produto-nome').innerText = produto.nome;
    document.getElementById('editor-codigo').value = produto.codigo_interno ?? '';
    document.getElementById('editor-codigo-barras').value = produto.codigo_barras ?? '';
    document.getElementById('editor-descricao').value = produto.nome;
    document.getElementById('editor-quantidade').value = 1;
    document.getElementById('editor-valor-unitario').value = produto.preco_venda;
    document.getElementById('editor-desconto').value = 0;
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
    document.getElementById('editor-valor-total').value = 'R$ ' + valorTotal.toFixed(2);

    // ICMS — mesma regra usada no NotaFiscalService::montarItens() na emissão real
    const trib = produtoSelecionadoParaEditor?.tributacao;
    const cstsComBaseCalculo = ['00', '10', '20', '70', '90'];
    let bcIcms = 0, valorIcms = 0, aliquotaIcms = 0;

    if (window.crtEmpresa > 2 && trib && cstsComBaseCalculo.includes(trib.cst_icms)) {
        bcIcms = subtotalBruto;
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
        valorIpi = subtotalBruto * aliquotaIpi / 100;
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


function adicionarLinhaNaGrid() {
    const quantidade = parseFloat(document.getElementById('editor-quantidade').value) || 0;
    const valorUnitario = parseFloat(document.getElementById('editor-valor-unitario').value) || 0;
    const valorDesconto = parseFloat(document.getElementById('editor-desconto').value) || 0;
    const descontoPercentual = parseFloat(document.getElementById('editor-desconto-percentual').value) || 0;
    const descricao = document.getElementById('editor-descricao').value.trim();
    const refChave = document.getElementById('editor-ref-chave').value.replace(/\D/g, '');
    const refNitem = document.getElementById('editor-ref-nitem').value || null;

    if (finalidadesRefPorItem.includes(String(campoFinalidade.value)) && refChave.length !== 44) {
        alert('Informe a chave de acesso (44 dígitos) da nota de origem deste item.');
        return;
    }

    if (quantidade <= 0 || valorUnitario < 0 || descricao.length < 1) {
        alert('Preencha quantidade, valor unitário e descrição corretamente.');
        return;
    }

    const subtotalBruto = quantidade * valorUnitario;
    const valorTotal = Math.max(subtotalBruto - valorDesconto, 0);

    // INÍCIO DO NOVO BLOCO: Condicional para ler campos manuais ou calcular automaticamente
    let bcIcms, valorIcms, aliquotaIcms, valorIpi, aliquotaIpi;

    if (window.cfopDestacarBases) {
        bcIcms = parseFloat(document.getElementById('editor-bc-icms').value.replace('R$', '').replace(',', '.')) || 0;
        valorIcms = parseFloat(document.getElementById('editor-valor-icms').value.replace('R$', '').replace(',', '.')) || 0;
        aliquotaIcms = parseFloat(document.getElementById('editor-aliquota-icms').value.replace('%', '').replace(',', '.')) || 0;
        valorIpi = parseFloat(document.getElementById('editor-valor-ipi').value.replace('R$', '').replace(',', '.')) || 0;
        aliquotaIpi = parseFloat(document.getElementById('editor-aliquota-ipi').value.replace('%', '').replace(',', '.')) || 0;
    } else {
        const trib = produtoSelecionadoParaEditor?.tributacao;
        const cstsComBaseCalculo = ['00', '10', '20', '70', '90'];
        bcIcms = 0; valorIcms = 0; aliquotaIcms = 0;

        if (window.crtEmpresa > 2 && trib && cstsComBaseCalculo.includes(trib.cst_icms)) {
            bcIcms = subtotalBruto;
            aliquotaIcms = parseFloat(trib.aliquota_icms) || 0;
            valorIcms = bcIcms * aliquotaIcms / 100;
        }

        const ipi = produtoSelecionadoParaEditor?.ipi;
        valorIpi = 0; aliquotaIpi = 0;

        if (ipi && ipi.codigo === '50' && ipi.aliquota) {
            aliquotaIpi = parseFloat(ipi.aliquota) || 0;
            valorIpi = subtotalBruto * aliquotaIpi / 100;
        }
    }
    // FIM DO NOVO BLOCO

    itensNota.push({
        produto_id: produtoSelecionadoParaEditor.id,
        codigo: produtoSelecionadoParaEditor.codigo_interno,
        codigo_barras: produtoSelecionadoParaEditor.codigo_barras,
        descricao,
        quantidade,
        valor_unitario: valorUnitario,
        valor_total: valorTotal,
        valor_desconto: valorDesconto,
        desconto_percentual: descontoPercentual,
        bc_icms: bcIcms,
        valor_icms: valorIcms,
        aliquota_icms: aliquotaIcms,
        valor_ipi: valorIpi,
        aliquota_ipi: aliquotaIpi,
        bases_manuais: !!window.cfopDestacarBases,
        ref_chave_acesso: refChave || null,
        ref_nitem: refNitem,
    });

    document.getElementById('editor-item').classList.add('hidden');
    produtoSelecionadoParaEditor = null;
    renderizarGridItens();
    inputBuscaNf.focus();
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


function removerLinhaDaGrid(index) {
    itensNota.splice(index, 1);
    renderizarGridItens();
}

function renderizarGridItens() {
    const tbody = document.getElementById('linhas-grid-itens');
    const vazia = document.getElementById('grid-vazia');

    if (itensNota.length === 0) {
        tbody.innerHTML = '';
        vazia.classList.remove('hidden');
        document.getElementById('total-grid-itens').innerText = 'R$ 0,00';
        return;
    }
    vazia.classList.add('hidden');

    let totalNota = 0;

    tbody.innerHTML = itensNota.map((item, index) => {
        totalNota += item.valor_total;
        return `
            <tr>
                <td class="px-3 py-2">${item.codigo ?? '—'}</td>
                <td class="px-3 py-2">${item.codigo_barras ?? '—'}</td>
                <td class="px-3 py-2">${item.descricao}</td>
                <td class="px-3 py-2 text-right">${item.quantidade}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_unitario.toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium">R$ ${item.valor_total.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_desconto.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.desconto_percentual.toFixed(2)}%</td>
                <td class="px-3 py-2 text-right">R$ ${item.bc_icms.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_icms.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.aliquota_icms.toFixed(2)}%</td>
                <td class="px-3 py-2 text-right">R$ ${item.valor_ipi.toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${item.aliquota_ipi.toFixed(2)}%</td>
                <td class="px-3 py-2 text-right">
                    <button type="button" onclick="removerLinhaDaGrid(${index})" class="text-red-600 text-xs hover:underline">remover</button>
                </td>
                <td class="px-3 py-2 text-xs font-mono">${item.ref_chave_acesso ? item.ref_chave_acesso.slice(-8) + (item.ref_nitem ? ' (item ' + item.ref_nitem + ')' : '') : '—'}</td>
            </tr>
        `;
    }).join('');

    document.getElementById('total-grid-itens').innerText = 'R$ ' + totalNota.toFixed(2).replace('.', ',');
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

    document.getElementById('campo-motivo-ajuste').value = ehAjuste ? motivo : '';
    document.getElementById('campo-notas-referenciadas').value = JSON.stringify(chavesReferenciadas);
    document.getElementById('campo-informacoes-complementares').value =
        document.getElementById('modal-informacoes-complementares').value;

    fecharModalReferencia();
    document.getElementById('form-nota').requestSubmit();
}


// Inicialização: se vier preenchido (edição) ou old() de uma tentativa anterior (criação), já libera e renderiza
atualizarTravaCabecalho();
renderizarGridItens();
</script>