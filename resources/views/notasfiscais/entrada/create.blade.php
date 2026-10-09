@extends('layouts.app')

@section('titulo', 'Nova Entrada de Nota')
@section('body-class', 'sidebar-oculta conteudo-largo')

@section('conteudo')
    @if (session('erro_xml'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('erro_xml') }}</div>
    @endif

    @error('xml')
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $message }}</div>
    @enderror

    @php
        $podeProdutos = (bool) auth()->user()?->podeVer('produtos');
        $empresa = $podeProdutos ? \App\Models\Empresa::atual() : null;
        $tributacoes = $podeProdutos ? \App\Models\Tributacao::orderBy('descricao')->get() : collect();
        $pisCofinsLista = $podeProdutos ? \App\Models\ClassificacaoPisCofins::orderBy('codigo')->get() : collect();
        $clsSel = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500';
    @endphp

    <div class="flex justify-end items-center gap-2 mb-4">
        <div class="relative" id="menu-opcoes-xml">
            <button type="button" onclick="document.getElementById('painel-opcoes-xml').classList.toggle('hidden')"
                    class="flex items-center gap-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white hover:bg-gray-50 text-gray-700 transition">
                Opções
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="painel-opcoes-xml"
                 class="hidden absolute right-0 mt-1 w-96 bg-white border border-gray-200 rounded-lg shadow-lg z-40 p-4 text-sm">
                <p class="font-medium text-gray-700 mb-3">Importação de XML</p>

                <label class="flex items-start gap-3 cursor-pointer">
                    <span class="relative inline-flex items-center mt-0.5">
                        <input type="checkbox" id="opt-auto-cadastro" class="sr-only peer"
                               onchange="xmlDefinirAutoCadastro(this.checked)">
                        <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></span>
                    </span>
                    <span>
                        <span class="font-medium text-gray-800">Cadastrar produtos automaticamente</span>
                        <span class="block text-xs text-gray-500">Na conferência aparece o botão para cadastrar de uma vez os produtos não encontrados.</span>
                    </span>
                </label>

                @if ($podeProdutos)
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                        <p class="font-medium text-gray-700">Padrões do cadastro automático</p>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Tributação padrão <span class="text-red-500">*</span></label>
                            <select id="opt-trib" class="{{ $clsSel }}">
                                <option value="">Selecione...</option>
                                @foreach ($tributacoes as $t)
                                    <option value="{{ $t->id }}" @selected($empresa->entrada_tributacao_padrao_id == $t->id)>{{ $t->labelCompleto() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Tributação quando a nota vem com ST <span class="text-gray-400">(opcional)</span></label>
                            <select id="opt-trib-st" class="{{ $clsSel }}">
                                <option value="">Usar a tributação padrão</option>
                                @foreach ($tributacoes as $t)
                                    <option value="{{ $t->id }}" @selected($empresa->entrada_tributacao_st_padrao_id == $t->id)>{{ $t->labelCompleto() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">
                                PIS/COFINS padrão
                                @if ($empresa->crt == 3) <span class="text-red-500">*</span> @else <span class="text-gray-400">(opcional)</span> @endif
                            </label>
                            <select id="opt-pis" class="{{ $clsSel }}">
                                <option value="">Nenhum</option>
                                @foreach ($pisCofinsLista as $p)
                                    <option value="{{ $p->id }}" @selected($empresa->entrada_pis_cofins_padrao_id == $p->id)>CST {{ $p->codigo }} — {{ $p->descricao }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Margem sobre o custo para o preço de venda (%)</label>
                            <input type="number" step="0.01" min="0" id="opt-margem" value="{{ $empresa->entrada_margem_padrao }}" class="{{ $clsSel }}">
                        </div>

                        <p id="opt-msg" class="hidden text-xs"></p>

                        <button type="button" id="opt-salvar" onclick="xmlSalvarPadroes()"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                            Salvar padrões
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <button type="button" onclick="abrirModalXml()"
                class="bg-gray-700 hover:bg-gray-500 text-amber-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            Importar XML
        </button>
    </div>

    @include('notasfiscais.entrada._form')
    @include('notasfiscais.entrada._modal-importar-xml')

    @if ($podeProdutos)
        @include('produtos._modais_catalogo')
        <style>
            #modal-catalogo, #modal-ncm, #modal-cest, #modal-classtrib,
            #modal-piscofins, #modal-ipi, #modal-tributacao { z-index: 70; }
        </style>
    @endif

    <script>
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('menu-opcoes-xml');
            if (menu && !menu.contains(e.target)) {
                document.getElementById('painel-opcoes-xml').classList.add('hidden');
            }
        });

        async function xmlSalvarPadroes() {
            const btn = document.getElementById('opt-salvar');
            const msg = document.getElementById('opt-msg');
            const ou = (id) => document.getElementById(id).value || null;

            btn.disabled = true;
            msg.classList.add('hidden');

            try {
                await xmlRequisicao(@json(route('entradas-nota.importar-xml.opcoes')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tributacao_padrao_id: ou('opt-trib'),
                        tributacao_st_padrao_id: ou('opt-trib-st'),
                        pis_cofins_padrao_id: ou('opt-pis'),
                        margem_padrao: document.getElementById('opt-margem').value || 0,
                    }),
                });
                msg.textContent = 'Padrões salvos.';
                msg.className = 'text-xs text-green-600';
            } catch (e) {
                msg.textContent = e.message;
                msg.className = 'text-xs text-red-600 whitespace-pre-line';
            } finally {
                btn.disabled = false;
            }
        }
    </script>
@endsection