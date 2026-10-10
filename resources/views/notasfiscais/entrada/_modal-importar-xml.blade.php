@php 
    $operacoesEntrada = \App\Models\OperacaoEntrada::where('ativo', true)
        ->orderBy('ordem')->get(); 
@endphp

{{-- 1) Envio do XML --}}
<div id="modal-importar-xml" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Importar XML da NF-e</h2>
            <button type="button" onclick="fecharModalXml()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div id="dropzone-xml"
             class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
            <p class="text-sm text-gray-600">Arraste o XML aqui ou <span class="text-blue-600 font-medium">clique para procurar</span></p>
            <p id="nome-arquivo-xml" class="text-xs text-gray-500 mt-2"></p>
            <input type="file" id="input-xml" accept=".xml,text/xml,application/xml" class="hidden">
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Operação da entrada <span class="text-red-500">*</span></label>
            <select id="xml-operacao"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none transition">
                @foreach ($operacoesEntrada as $op)
                    <option value="{{ $op->id }}">{{ $op->descricao }}</option>
                @endforeach
            </select>
        </div>

        <p class="text-xs text-gray-400 mt-3">
            Se o fornecedor da nota não estiver cadastrado, ele será cadastrado automaticamente com os dados do XML.
        </p>
        <p id="erro-upload-xml" class="hidden text-sm text-red-600 mt-3 whitespace-pre-line"></p>

        <div class="flex justify-end gap-2 mt-5">
            <button type="button" onclick="fecharModalXml()"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancelar</button>
            <button type="button" id="btn-enviar-xml" disabled onclick="analisarXml()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                Analisar XML
            </button>
        </div>
    </div>
</div>

{{-- 2) Conferência dos itens --}}
<div id="modal-conferencia-xml" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900 rounded-t-xl">
            <h2 class="text-lg font-bold text-white">Conferir itens da NF-e</h2>
            <button type="button" onclick="cancelarConferenciaXml()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="px-6 pt-4 text-sm text-gray-600" id="resumo-nota-xml"></div>

        <div class="p-6 overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 pr-3">Código de barras</th>
                        <th class="py-2 pr-3">Descrição</th>
                        <th class="py-2 pr-3">Valor de custo</th>
                        <th class="py-2">Produto no sistema</th>
                    </tr>
                </thead>
                <tbody id="linhas-conferencia-xml"></tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-3">
            <p id="contador-conferencia-xml" class="text-sm text-gray-500 mr-auto"></p>
            <button type="button" id="btn-cadastrar-pendentes-xml" onclick="cadastrarPendentesXml()"
                    class="hidden bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                Cadastrar produtos não encontrados
            </button>
            <p id="erro-conferencia-xml" class="hidden text-sm text-red-600 whitespace-pre-line"></p>
            <button type="button" onclick="cancelarConferenciaXml()"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancelar</button>
            <button type="button" id="btn-confirmar-xml" onclick="confirmarImportacaoXml()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                Importar para o rascunho
            </button>
        </div>
    </div>
</div>

{{-- 3) Assimilar produto: busca + cadastro rápido --}}
<div id="modal-assimilar-xml" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60]">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Assimilar produto</h2>
            <button type="button" onclick="xmlFecharAssimilar()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6">
            <p id="assimilar-item-xml" class="text-sm text-gray-600 mb-4"></p>

            <div id="view-busca-xml">
                <label class="block text-sm font-medium text-gray-700 mb-1">Produto no sistema</label>
                <input type="text" id="busca-assimilar-xml" placeholder="Buscar por nome, referência ou código de barras..." autocomplete="off"
                       class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                            <th class="py-2">Código</th>
                            <th class="py-2">Produto</th>
                            <th class="py-2">Custo</th>
                            <th class="py-2">Estoque</th>
                        </tr>
                    </thead>
                    <tbody id="linhas-assimilar-xml"></tbody>
                </table>
                <p id="erro-assimilar-xml" class="hidden text-sm text-red-600 mt-3 whitespace-pre-line"></p>
                <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
            </div>

            @if (auth()->user()?->podeVer('produtos'))
            @php
                $pisCofinsObrigatorio = \App\Models\Empresa::atual()->crt == 3;
                $cls = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
                $clsBtn = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-left text-sm bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none transition';
                $abas = ['geral' => 'Dados Gerais', 'fiscal' => 'Dados Fiscais', 'preco' => 'Preço e Estoque', 'atacado' => 'Atacado'];
            @endphp

            <div id="view-cadastro-xml" class="hidden">

                {{-- ids usados pelos modais de catálogo (tributacao_id já vem do partial) --}}
                @foreach (['categoria_id', 'marca_id', 'grupo_id', 'ncm_id', 'cest_id', 'class_trib_ibs_cbs_id', 'pis_cofins_id', 'ipi_id'] as $idOculto)
                    <input type="hidden" id="{{ $idOculto }}">
                @endforeach

                <div class="flex border-b border-gray-200 mb-5">
                    @foreach ($abas as $chave => $titulo)
                        <button type="button" onclick="cadTab('{{ $chave }}')" id="cad-tab-btn-{{ $chave }}"
                                class="cad-tab-btn px-5 py-3 text-sm font-medium border-b-2 transition {{ $loop->first ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            {{ $titulo }}
                        </button>
                    @endforeach
                </div>

                {{-- Dados Gerais --}}
                <div id="cad-painel-geral" class="cad-painel grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="cad-nome" maxlength="255" class="{{ $cls }}">
                    </div>

                    <div class="col-span-2 grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código de barras (EAN)</label>
                            <input type="text" id="cad-codigo-barras" maxlength="50" placeholder="Deixe em branco se não tiver" class="{{ $cls }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código interno <span class="text-xs text-gray-400 font-normal">(automático)</span></label>
                            <input type="text" readonly value="Gerado ao salvar" class="w-full border rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referência</label>
                            <input type="text" id="cad-referencia" maxlength="100" class="{{ $cls }}">
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="cad-descricao" rows="2" class="{{ $cls }} resize-none"></textarea>
                    </div>

                    @foreach (['categoria' => 'Categoria', 'marca' => 'Marca', 'grupo' => 'Grupo'] as $tipo => $rotulo)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $rotulo }}</label>
                            <button type="button" onclick="abrirModalCatalogo('{{ $tipo }}')" class="{{ $clsBtn }}">
                                <span id="{{ $tipo }}_label" class="text-gray-600">Clique para selecionar...</span>
                            </button>
                        </div>
                    @endforeach

                    <div class="flex flex-col justify-center">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Produto de balança</label>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="cad-balanca" class="sr-only peer" onchange="cadValidarBalanca(this)">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <span class="text-sm text-gray-600">Pesado em KG</span>
                            <span id="cad-aviso-balanca" class="text-xs text-orange-500 hidden">⚠️ Unidade comercial deve ser KG</span>
                        </div>
                    </div>
                </div>

                {{-- Dados Fiscais --}}
                <div id="cad-painel-fiscal" class="cad-painel grid grid-cols-2 gap-4 hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NCM <span class="text-red-500">*</span></label>
                        <button type="button" onclick="abrirModalNcm()" class="{{ $clsBtn }}">
                            <span id="ncm_label" class="text-gray-600">Clique para selecionar...</span>
                        </button>
                        <p id="cad-ncm-dica" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CEST</label>
                        <button type="button" onclick="abrirModalCest()" class="{{ $clsBtn }}">
                            <span id="cest_label" class="text-gray-600">Clique para selecionar (opcional)...</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            PIS/COFINS
                            @if ($pisCofinsObrigatorio)
                                <span class="text-red-500">*</span>
                            @else
                                <span class="text-xs text-gray-400 font-normal">(obrigatório apenas no Lucro Presumido/Real)</span>
                            @endif
                        </label>
                        <button type="button" onclick="abrirModalPisCofins()" class="{{ $clsBtn }}">
                            <span id="pis_cofins_label" class="text-gray-600">Clique para selecionar...</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">IPI <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                        <button type="button" onclick="abrirModalIpi()" class="{{ $clsBtn }}">
                            <span id="ipi_label" class="text-gray-600">Clique para selecionar (opcional)...</span>
                        </button>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Classificação Tributária <span class="text-red-500">*</span></label>
                        <button type="button" onclick="abrirModalTributacao()" class="{{ $clsBtn }}">
                            <span id="tributacao_label" class="text-gray-600">Clique para selecionar...</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade comercial <span class="text-red-500">*</span></label>
                        <input type="text" id="cad-unidade" maxlength="6" class="{{ $cls }}" oninput="cadUnidadeMudou()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade tributável <span class="text-red-500">*</span></label>
                        <input type="text" id="cad-unidade-trib" maxlength="6" class="{{ $cls }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Origem da mercadoria <span class="text-red-500">*</span></label>
                        <select id="cad-origem" class="{{ $cls }} bg-white">
                            @foreach ([0 => '0 - Nacional', 1 => '1 - Importado (importação direta)', 2 => '2 - Importado (mercado interno)', 3 => '3 - Nacional (importação 40% a 70%)', 4 => '4 - Nacional (processos produtivos básicos)', 5 => '5 - Nacional (importação até 40%)', 6 => '6 - Importado (direta, sem similar)', 7 => '7 - Importado (mercado interno, sem similar)', 8 => '8 - Nacional (importação acima de 70%)'] as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Classificação IBS/CBS</label>
                        <button type="button" onclick="abrirModalClassTrib()" class="{{ $clsBtn }}">
                            <span id="class_trib_ibs_cbs_label" class="text-gray-600">Clique para selecionar (opcional)...</span>
                        </button>
                    </div>
                </div>

                {{-- Preço e Estoque --}}
                <div id="cad-painel-preco" class="cad-painel grid grid-cols-2 gap-4 hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preço de venda <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                            <input type="number" step="0.01" min="0" id="cad-venda" class="{{ $cls }} pl-9">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preço de custo</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                            <input type="number" step="0.01" min="0" id="cad-custo" class="{{ $cls }} pl-9">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estoque</label>
                        <input type="number" readonly value="0" class="w-full border rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        <p class="text-xs text-gray-400 mt-1">A quantidade da nota entra quando a entrada for finalizada.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estoque mínimo</label>
                        <input type="number" min="0" id="cad-estoque-min" value="0" class="{{ $cls }}">
                    </div>
                    <p class="col-span-2 text-xs text-gray-400">Produtos com variação (cor/tamanho) ainda não são suportados na entrada de nota.</p>
                </div>

                {{-- Atacado --}}
                <div id="cad-painel-atacado" class="cad-painel hidden">
                    <div class="flex items-center gap-3 py-1 mb-4">
                        <input type="checkbox" id="cad-tem-atacado" onchange="cadToggleAtacado(this.checked)" class="w-4 h-4 text-blue-600 rounded">
                        <label for="cad-tem-atacado" class="text-sm font-medium text-gray-700">Este produto tem preço de atacado</label>
                    </div>

                    <div id="cad-bloco-atacado" class="grid grid-cols-2 gap-4 hidden">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor original</label>
                            <input type="text" id="cad-atacado-original" readonly class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                            <p class="text-xs text-gray-400 mt-1">Vem do preço de venda em <em>Preço e Estoque</em>.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor de atacado</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                                <input type="number" step="0.01" min="0" id="cad-preco-atacado" class="{{ $cls }} pl-9">
                            </div>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">A partir de <span id="cad-unidade-atacado" class="text-gray-400 font-normal">(UN)</span></label>
                            <input type="number" step="0.001" min="0" id="cad-qtd-atacado" placeholder="Ex: 10" class="{{ $cls }} max-w-xs">
                        </div>
                        <div class="col-span-2 border-t pt-3 flex items-center gap-3">
                            <input type="checkbox" id="cad-atacado-prazo" onchange="cadTogglePrazo(this.checked)" class="w-4 h-4 text-blue-600 rounded">
                            <label for="cad-atacado-prazo" class="text-sm font-medium text-gray-700">Ativar prazo do atacado</label>
                        </div>
                        <div id="cad-bloco-prazo" class="col-span-2 grid grid-cols-2 gap-4 hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data inicial</label>
                                <input type="date" id="cad-atacado-inicio" class="{{ $cls }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data final</label>
                                <input type="date" id="cad-atacado-fim" class="{{ $cls }}">
                            </div>
                        </div>
                    </div>
                </div>

                <p id="erro-cadastro-xml" class="hidden text-sm text-red-600 mt-4 whitespace-pre-line"></p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" onclick="xmlVoltarBusca()"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Voltar à busca</button>
                    <button type="button" id="btn-salvar-produto-xml" onclick="xmlSalvarProduto()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                        Cadastrar e assimilar
                    </button>
                </div>
            </div>
            @endif

<script>
try {
    const salva = localStorage.getItem('entrada_xml_operacao');
    const selOperacao = document.getElementById('xml-operacao');
    if (salva && selOperacao.querySelector(`option[value="${salva}"]`)) {
        selOperacao.value = salva;
    }
} catch (e) {}

const XML_URLS = {
    analisar:  @json(route('entradas-nota.importar-xml.analisar')),
    confirmar: @json(route('entradas-nota.importar-xml.confirmar')),
    rapido: @json(route('entradas-nota.importar-xml.produto')),
    produtos:  @json(route('entradas-nota.produtos')),
    pendentes: @json(route('entradas-nota.importar-xml.cadastrar-pendentes')),
};
const XML_CHAVE_AUTO = 'entrada_xml_auto_cadastro';
let xmlImportacao = null;   // { token, nota, itens: [...] }
let xmlItemAtual = null;    // índice do item sendo assimilado
let xmlResultados = [];
let xmlIndice = -1;
let xmlTimeout;
const XML_PODE_CADASTRAR = @json((bool) auth()->user()?->podeVer('produtos'));

function xmlAutoCadastroAtivo() {
    try { return localStorage.getItem(XML_CHAVE_AUTO) === '1'; } catch (e) { return false; }
}

function xmlDefinirAutoCadastro(ligado) {
    try { localStorage.setItem(XML_CHAVE_AUTO, ligado ? '1' : '0'); } catch (e) {}
    if (xmlImportacao) renderizarConferenciaXml();
}

const optAuto = document.getElementById('opt-auto-cadastro');
if (optAuto) optAuto.checked = xmlAutoCadastroAtivo();

async function cadastrarPendentesXml() {
    const indices = xmlImportacao.itens.map((it, i) => it.produto ? null : i).filter(i => i !== null);
    if (!indices.length) return;

    if (!confirm(`Cadastrar ${indices.length} produto(s) automaticamente com os padrões definidos em Opções?`)) return;

    const btn = document.getElementById('btn-cadastrar-pendentes-xml');
    btn.disabled = true;
    btn.textContent = 'Cadastrando...';
    xmlLimparErro('erro-conferencia-xml');

    try {
        const r = await xmlRequisicao(XML_URLS.pendentes, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: xmlImportacao.token, indices }),
        });

        r.criados.forEach(c => {
            xmlImportacao.itens[c.indice].produto = { ...c.produto, manual: true, auto: true };
        });
        renderizarConferenciaXml();

        if (r.falhas.length) {
            xmlErro('erro-conferencia-xml',
                r.falhas.map(f => `Item ${f.indice + 1}: ${f.motivo}`).join('\n'));
        }
    } catch (e) {
        xmlErro('erro-conferencia-xml', e.message);
    } finally {
        btn.disabled = false;
        renderizarConferenciaXml();
    }
}
// ---------- utilitários ----------
function xmlEsc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function xmlMoeda(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function xmlAbrir(id)  { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function xmlFechar(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
function xmlErro(id, msg) { const el = document.getElementById(id); el.textContent = msg; el.classList.remove('hidden'); }
function xmlLimparErro(id) { document.getElementById(id).classList.add('hidden'); }

async function xmlRequisicao(url, opcoes = {}) {
    let resp;
    try {
        resp = await fetch(url, {
            ...opcoes,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', ...(opcoes.headers ?? {}) },
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
    if (dados === null) throw new Error('Resposta inesperada do servidor.');
    return dados;
}

// ---------- 1) envio do XML ----------
const dropzoneXml = document.getElementById('dropzone-xml');
const inputXml = document.getElementById('input-xml');
const nomeXml = document.getElementById('nome-arquivo-xml');
const btnXml = document.getElementById('btn-enviar-xml');

function abrirModalXml() { xmlAbrir('modal-importar-xml'); }
function fecharModalXml() {
    xmlFechar('modal-importar-xml');
    inputXml.value = '';
    nomeXml.textContent = '';
    btnXml.disabled = true;
    xmlLimparErro('erro-upload-xml');
}

function atualizarArquivoXml() {
    const arquivo = inputXml.files[0];
    xmlLimparErro('erro-upload-xml');

    if (!arquivo) { nomeXml.textContent = ''; btnXml.disabled = true; return; }

    if (!arquivo.name.toLowerCase().endsWith('.xml')) {
        nomeXml.textContent = 'Selecione um arquivo .xml';
        inputXml.value = '';
        btnXml.disabled = true;
        return;
    }
    nomeXml.textContent = arquivo.name;
    btnXml.disabled = false;
}

inputXml.addEventListener('click', (e) => e.stopPropagation());
inputXml.addEventListener('change', atualizarArquivoXml);
dropzoneXml.addEventListener('click', () => inputXml.click());

['dragenter', 'dragover'].forEach(ev => dropzoneXml.addEventListener(ev, (e) => {
    e.preventDefault();
    dropzoneXml.classList.add('border-blue-500', 'bg-blue-50');
}));
['dragleave', 'drop'].forEach(ev => dropzoneXml.addEventListener(ev, (e) => {
    e.preventDefault();
    dropzoneXml.classList.remove('border-blue-500', 'bg-blue-50');
}));
dropzoneXml.addEventListener('drop', (e) => {
    if (e.dataTransfer.files.length) { inputXml.files = e.dataTransfer.files; atualizarArquivoXml(); }
});


function xmlLinhaFiscal(item) {
    const c = item.conversao;
    if (!c) return '';

    if (!c.cfop_entrada) {
        return `<span class="block text-xs text-amber-600">Conversão pendente: CFOP ${xmlEsc(c.cfop_origem || '—')} sem regra para esta operação</span>`;
    }

    return `<span class="block text-xs text-gray-500">CFOP ${xmlEsc(c.cfop_origem || '—')} → <strong>${xmlEsc(c.cfop_entrada)}</strong>
        · ${c.tipo_origem} ${xmlEsc(c.cst_origem || '—')} → <strong>${c.tipo_entrada} ${xmlEsc(c.cst_entrada || 'pendente')}</strong></span>`;
}


async function analisarXml() {
    const arquivo = inputXml.files[0];
    if (!arquivo) return;

    btnXml.disabled = true;
    btnXml.textContent = 'Analisando...';
    xmlLimparErro('erro-upload-xml');

    try {
        const fd = new FormData();
        fd.append('xml', arquivo);
        const operacao = document.getElementById('xml-operacao').value;
        fd.append('operacao_entrada_id', operacao);
        try { localStorage.setItem('entrada_xml_operacao', operacao); } catch (e) {}
        xmlImportacao = await xmlRequisicao(XML_URLS.analisar, { method: 'POST', body: fd });

        fecharModalXml();
        renderizarConferenciaXml();
        xmlAbrir('modal-conferencia-xml');
    } catch (e) {
        xmlErro('erro-upload-xml', e.message);
    } finally {
        btnXml.textContent = 'Analisar XML';
        btnXml.disabled = !inputXml.files[0];
    }
}

// ---------- 2) conferência ----------
function renderizarConferenciaXml() {
    const { nota, itens } = xmlImportacao;

    document.getElementById('resumo-nota-xml').innerHTML =
        `NF-e nº <strong>${xmlEsc(nota.numero)}</strong>${nota.serie ? ' / série ' + xmlEsc(nota.serie) : ''} — ` +
        `Fornecedor: <strong>${xmlEsc(nota.fornecedor_nome)}</strong>` +
        (nota.operacao ? ` — Operação: <strong>${xmlEsc(nota.operacao)}</strong>` : '') +
        (nota.fornecedor_novo ? ' <span class="text-amber-600">(não cadastrado: será cadastrado automaticamente)</span>' : '');

    document.getElementById('linhas-conferencia-xml').innerHTML = itens.map((item, i) => {
        const p = item.produto;

        const botaoCadastrar = XML_PODE_CADASTRAR
            ? `<button type="button" onclick="xmlAbrirCadastro(${i})"
                       class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                   + Cadastrar produto no sistema</button>`
            : '';

        const titulo = p
            ? (p.auto ? 'Cadastrado automaticamente'
                : p.manual ? 'Assimilado manualmente'
                : 'Cadastro encontrado: ' + xmlEsc(p.por))
            : '';

        const celula = p
            ? `<span class="text-green-700 font-medium">${titulo}</span>
               <span class="block text-xs text-gray-500">${xmlEsc(p.nome)}</span>
               <button type="button" onclick="xmlAbrirAssimilar(${i})" class="text-xs text-blue-600 hover:underline">trocar</button>`
            : `<div class="flex flex-col items-start gap-1.5">
                   ${botaoCadastrar}
                   <button type="button" onclick="xmlAbrirAssimilar(${i})" class="text-red-600 font-medium hover:underline text-left">
                       Cadastro não encontrado! (Clique aqui para assimilar)</button>
               </div>`;

        return `
            <tr class="border-b border-gray-100 align-top">
                <td class="py-3 pr-3 font-mono text-xs text-gray-600">${xmlEsc(item.ean || '—')}</td>
                <td class="py-3 pr-3">${xmlEsc(item.descricao)}
                    <span class="block text-xs text-gray-400">Cód. fornecedor: ${xmlEsc(item.codigo || '—')}</span></td>
                    ${xmlLinhaFiscal(item)}
                <td class="py-3 pr-3">${xmlMoeda(item.valor_custo)}</td>
                <td class="py-3">${celula}</td>
            </tr>`;
    }).join('');

    const pendentes = itens.filter(i => !i.produto).length;
    document.getElementById('contador-conferencia-xml').textContent =
        pendentes ? `${pendentes} de ${itens.length} item(ns) sem produto` : `Todos os ${itens.length} itens assimilados`;

    const btnAuto = document.getElementById('btn-cadastrar-pendentes-xml');
    btnAuto.classList.toggle('hidden', !(XML_PODE_CADASTRAR && xmlAutoCadastroAtivo() && pendentes > 0));
    btnAuto.textContent = `Cadastrar produtos não encontrados (${pendentes})`;
}

function cancelarConferenciaXml() {
    xmlFechar('modal-conferencia-xml');
    xmlImportacao = null;
    xmlLimparErro('erro-conferencia-xml');
}

async function confirmarImportacaoXml() {
    const itens = xmlImportacao.itens;
    const pendentes = itens.filter(i => !i.produto).length;

    if (pendentes && !confirm(`${pendentes} item(ns) sem produto vão ficar de fora do rascunho. Continuar?`)) return;

    const produtos = {};
    itens.forEach((it, i) => { if (it.produto) produtos[i] = it.produto.id; });

    const btn = document.getElementById('btn-confirmar-xml');
    btn.disabled = true;
    btn.textContent = 'Importando...';
    xmlLimparErro('erro-conferencia-xml');

    try {
        const r = await xmlRequisicao(XML_URLS.confirmar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: xmlImportacao.token, produtos }),
        });
        window.location.href = r.redirect;
    } catch (e) {
        xmlErro('erro-conferencia-xml', e.message);
        btn.disabled = false;
        btn.textContent = 'Importar para o rascunho';
    }
}

// ---------- 3) assimilar: busca ----------
const inputBuscaXml = document.getElementById('busca-assimilar-xml');

function xmlAbrirAssimilar(i) {
    xmlItemAtual = i;
    const item = xmlImportacao.itens[i];

    document.getElementById('assimilar-item-xml').innerHTML =
        `Item da nota: <strong>${xmlEsc(item.descricao)}</strong> — código de barras: ${xmlEsc(item.ean || '—')}`;

    xmlMostrarBusca();
    xmlAbrir('modal-assimilar-xml');

    inputBuscaXml.value = item.ean || item.descricao;
    xmlBuscar(inputBuscaXml.value);
    inputBuscaXml.focus();
    inputBuscaXml.select();
}

// Abre o modal direto na tela de cadastro (botão da conferência)
function xmlAbrirCadastro(i) {
    xmlItemAtual = i;
    const item = xmlImportacao.itens[i];

    document.getElementById('assimilar-item-xml').innerHTML =
        `Item da nota: <strong>${xmlEsc(item.descricao)}</strong> — código de barras: ${xmlEsc(item.ean || '—')}`;

    xmlAbrir('modal-assimilar-xml');
    xmlMostrarCadastro();
}

// Volta do cadastro para a busca já com o item pesquisado
function xmlVoltarBusca() {
    const item = xmlImportacao.itens[xmlItemAtual];

    xmlMostrarBusca();
    if (!inputBuscaXml.value.trim()) inputBuscaXml.value = item.ean || item.descricao;
    xmlBuscar(inputBuscaXml.value);
    inputBuscaXml.focus();
}

function xmlFecharAssimilar() {
    xmlFechar('modal-assimilar-xml');
    xmlResultados = [];
    xmlIndice = -1;
}

function xmlMostrarBusca() {
    document.getElementById('view-cadastro-xml').classList.add('hidden');
    document.getElementById('view-busca-xml').classList.remove('hidden');
}

async function xmlBuscar(termo) {
    termo = termo.trim();
    xmlLimparErro('erro-assimilar-xml');

    if (termo.length < 2) { xmlResultados = []; xmlIndice = -1; xmlRenderResultados(); return; }

    try {
        xmlResultados = await xmlRequisicao(`${XML_URLS.produtos}?q=${encodeURIComponent(termo)}`);
    } catch (e) {
        xmlResultados = [];
        xmlErro('erro-assimilar-xml', 'Falha ao buscar produtos:\n' + e.message);
    }
    xmlIndice = xmlResultados.length ? 0 : -1;
    xmlRenderResultados();
}

function xmlRenderResultados() {
    const tbody = document.getElementById('linhas-assimilar-xml');

    if (!xmlResultados.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
        return;
    }

    tbody.innerHTML = xmlResultados.map((p, i) => {
        const dest = i === xmlIndice;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${dest ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="xmlSelecionar(${i})">
                <td class="py-3 font-mono text-sm ${dest ? 'text-slate-300' : 'text-gray-500'}">${xmlEsc(p.codigo_barras || p.codigo_interno || '—')}</td>
                <td class="py-3 font-medium">${xmlEsc(p.nome)}</td>
                <td class="py-3 ${dest ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">${xmlMoeda(p.preco_custo)}</td>
                <td class="py-3 font-medium">${xmlEsc(p.estoque)}</td>
            </tr>`;
    }).join('');
}

inputBuscaXml.addEventListener('input', () => {
    clearTimeout(xmlTimeout);
    xmlTimeout = setTimeout(() => xmlBuscar(inputBuscaXml.value), 300);
});

inputBuscaXml.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { xmlFecharAssimilar(); return; }
    if (!xmlResultados.length) return;

    if (e.key === 'ArrowDown') { e.preventDefault(); xmlIndice = (xmlIndice + 1) % xmlResultados.length; xmlRenderResultados(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); xmlIndice = (xmlIndice - 1 + xmlResultados.length) % xmlResultados.length; xmlRenderResultados(); }
    else if (e.key === 'Enter') { e.preventDefault(); if (xmlIndice >= 0) xmlSelecionar(xmlIndice); }
});

function xmlSelecionar(i) { xmlAtribuir(xmlResultados[i]); }

function xmlAtribuir(produto) {
    xmlImportacao.itens[xmlItemAtual].produto = {
        id: produto.id,
        nome: produto.nome,
        codigo_interno: produto.codigo_interno,
        codigo_barras: produto.codigo_barras,
        manual: true,
    };
    xmlFecharAssimilar();
    renderizarConferenciaXml();
}

// ---------- 3b) assimilar: cadastro rápido ----------
const CAD_PIS_COFINS_OBRIGATORIO = {{ !empty($pisCofinsObrigatorio) ? 'true' : 'false' }};

const CAD_SELETORES = {
    categoria: 'Clique para selecionar...',
    marca: 'Clique para selecionar...',
    grupo: 'Clique para selecionar...',
    ncm: 'Clique para selecionar...',
    cest: 'Clique para selecionar (opcional)...',
    pis_cofins: 'Clique para selecionar...',
    ipi: 'Clique para selecionar (opcional)...',
    tributacao: 'Clique para selecionar...',
    class_trib_ibs_cbs: 'Clique para selecionar (opcional)...',
};

function cadTab(tab) {
    document.querySelectorAll('.cad-painel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.cad-tab-btn').forEach(b => {
        b.classList.remove('border-blue-600', 'text-blue-600');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('cad-painel-' + tab).classList.remove('hidden');
    const btn = document.getElementById('cad-tab-btn-' + tab);
    btn.classList.add('border-blue-600', 'text-blue-600');
    btn.classList.remove('border-transparent', 'text-gray-500');

    if (tab === 'atacado') cadAtualizarOriginalAtacado();
}

function cadAtualizarOriginalAtacado() {
    const venda = parseFloat(document.getElementById('cad-venda').value) || 0;
    document.getElementById('cad-atacado-original').value =
        venda.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('cad-unidade-atacado').textContent =
        `(${document.getElementById('cad-unidade').value.toUpperCase() || 'UN'})`;
}

function cadToggleAtacado(ligado) {
    document.getElementById('cad-bloco-atacado').classList.toggle('hidden', !ligado);
    cadAtualizarOriginalAtacado();
}

function cadTogglePrazo(ligado) {
    document.getElementById('cad-bloco-prazo').classList.toggle('hidden', !ligado);
}

function cadValidarBalanca(checkbox) {
    const unidade = document.getElementById('cad-unidade').value.toUpperCase().trim();
    if (checkbox.checked && unidade !== 'KG') {
        checkbox.checked = false;
        const aviso = document.getElementById('cad-aviso-balanca');
        aviso.classList.remove('hidden');
        setTimeout(() => aviso.classList.add('hidden'), 3000);
    }
}

function cadUnidadeMudou() {
    const box = document.getElementById('cad-balanca');
    if (box.checked) cadValidarBalanca(box);
}

function xmlMostrarCadastro() {
    const item = xmlImportacao.itens[xmlItemAtual];
    const set = (id, valor) => { document.getElementById(id).value = valor ?? ''; };

    Object.entries(CAD_SELETORES).forEach(([chave, texto]) => {
        set(chave + '_id', '');
        document.getElementById(chave + '_label').innerText = texto;
    });

    if (item.ncm_registro) {
        set('ncm_id', item.ncm_registro.id);
        document.getElementById('ncm_label').innerText = `${item.ncm_registro.codigo} — ${item.ncm_registro.descricao}`;
    }
    document.getElementById('cad-ncm-dica').textContent = !item.ncm ? ''
        : (item.ncm_registro ? `NCM do XML: ${item.ncm}`
                             : `NCM do XML: ${item.ncm} — não cadastrado. Use a janela do NCM para cadastrá-lo.`);

    set('cad-nome', item.descricao);
    set('cad-codigo-barras', item.ean);
    set('cad-referencia', '');
    set('cad-descricao', '');
    document.getElementById('cad-balanca').checked = false;

    set('cad-unidade', item.unidade || 'UN');
    set('cad-unidade-trib', item.unidade_tributavel || item.unidade || 'UN');
    set('cad-origem', item.origem ?? 0);

    set('cad-custo', item.valor_custo);
    set('cad-venda', item.valor_custo);
    set('cad-estoque-min', 0);

    document.getElementById('cad-tem-atacado').checked = false;
    document.getElementById('cad-atacado-prazo').checked = false;
    ['cad-preco-atacado', 'cad-qtd-atacado', 'cad-atacado-inicio', 'cad-atacado-fim'].forEach(id => set(id, ''));
    cadToggleAtacado(false);
    cadTogglePrazo(false);

    xmlLimparErro('erro-cadastro-xml');
    document.getElementById('view-busca-xml').classList.add('hidden');
    document.getElementById('view-cadastro-xml').classList.remove('hidden');
    cadTab('geral');
    document.getElementById('cad-nome').focus();
}

function cadValidar() {
    const v = (id) => document.getElementById(id).value.trim();

    const regras = [
        ['O campo Nome é obrigatório.', 'geral', () => !v('cad-nome')],
        ['O campo NCM é obrigatório. Selecione em Dados Fiscais.', 'fiscal', () => !v('ncm_id')],
        ['O campo Unidade comercial é obrigatório.', 'fiscal', () => !v('cad-unidade')],
        ['O campo Unidade tributável é obrigatório.', 'fiscal', () => !v('cad-unidade-trib')],
        ['O campo Classificação Tributária é obrigatório. Selecione em Dados Fiscais.', 'fiscal', () => !v('tributacao_id')],
        ['O campo Preço de venda é obrigatório. Preencha em Preço e Estoque.', 'preco', () => v('cad-venda') === ''],
    ];

    if (CAD_PIS_COFINS_OBRIGATORIO) {
        regras.splice(5, 0, ['O campo PIS/COFINS é obrigatório no seu regime tributário. Selecione em Dados Fiscais.',
            'fiscal', () => !v('pis_cofins_id')]);
    }

    for (const [mensagem, tab, falhou] of regras) {
        if (falhou()) {
            cadTab(tab);
            xmlErro('erro-cadastro-xml', mensagem);
            return false;
        }
    }
    return true;
}

async function xmlSalvarProduto() {
    xmlLimparErro('erro-cadastro-xml');
    if (!cadValidar()) return;

    const v = (id) => document.getElementById(id).value.trim();
    const ou = (id) => v(id) || null;
    const flag = (id) => document.getElementById(id).checked ? '1' : '0';
    const temAtacado = document.getElementById('cad-tem-atacado').checked;

    const btn = document.getElementById('btn-salvar-produto-xml');
    btn.disabled = true;

    try {
        const produto = await xmlRequisicao(XML_URLS.rapido, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: v('cad-nome'),
                codigo_barras: v('cad-codigo-barras'),
                referencia: ou('cad-referencia'),
                descricao: ou('cad-descricao'),
                categoria_id: ou('categoria_id'),
                marca_id: ou('marca_id'),
                grupo_id: ou('grupo_id'),
                produto_balanca: flag('cad-balanca'),

                ncm_id: ou('ncm_id'),
                cest_id: ou('cest_id'),
                pis_cofins_id: ou('pis_cofins_id'),
                ipi_id: ou('ipi_id'),
                tributacao_id: ou('tributacao_id'),
                class_trib_ibs_cbs_id: ou('class_trib_ibs_cbs_id'),
                unidade_comercial: v('cad-unidade').toUpperCase(),
                unidade_tributavel: v('cad-unidade-trib').toUpperCase(),
                origem_mercadoria: v('cad-origem'),

                preco_venda: v('cad-venda'),
                preco_custo: ou('cad-custo'),
                estoque: 0,
                estoque_minimo: v('cad-estoque-min') || 0,

                tem_preco_atacado: temAtacado ? '1' : '0',
                preco_atacado: temAtacado ? ou('cad-preco-atacado') : null,
                quantidade_minima_atacado: temAtacado ? ou('cad-qtd-atacado') : null,
                atacado_tem_prazo: temAtacado ? flag('cad-atacado-prazo') : '0',
                atacado_data_inicio: temAtacado ? ou('cad-atacado-inicio') : null,
                atacado_data_fim: temAtacado ? ou('cad-atacado-fim') : null,
            }),
        });
        xmlAtribuir(produto);
    } catch (e) {
        xmlErro('erro-cadastro-xml', e.message);
    } finally {
        btn.disabled = false;
    }
}
</script>