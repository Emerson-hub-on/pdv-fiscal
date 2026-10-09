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

                @if (auth()->user()?->podeVer('produtos'))
                    <button type="button" onclick="xmlMostrarCadastro()" class="mt-4 text-sm text-blue-600 hover:underline">
                        + Cadastrar este produto no sistema
                    </button>
                @endif
            </div>

            <div id="view-cadastro-xml" class="hidden">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="cad-nome" maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código de barras</label>
                        <input type="text" id="cad-codigo-barras" maxlength="14" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade <span class="text-red-500">*</span></label>
                        <input type="text" id="cad-unidade" maxlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NCM</label>
                        <input type="text" id="cad-ncm" maxlength="8" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preço de custo <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" id="cad-custo" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preço de venda <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" id="cad-venda" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <p id="erro-cadastro-xml" class="hidden text-sm text-red-600 mt-3 whitespace-pre-line"></p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" onclick="xmlMostrarBusca()"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Voltar à busca</button>
                    <button type="button" id="btn-salvar-produto-xml" onclick="xmlSalvarProduto()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                        Cadastrar e assimilar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const XML_URLS = {
    analisar:  @json(route('entradas-nota.importar-xml.analisar')),
    confirmar: @json(route('entradas-nota.importar-xml.confirmar')),
    rapido:    @json(route('entradas-nota.importar-xml.produto-rapido')),
    produtos:  @json(route('entradas-nota.produtos')),
};

let xmlImportacao = null;   // { token, nota, itens: [...] }
let xmlItemAtual = null;    // índice do item sendo assimilado
let xmlResultados = [];
let xmlIndice = -1;
let xmlTimeout;

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

async function analisarXml() {
    const arquivo = inputXml.files[0];
    if (!arquivo) return;

    btnXml.disabled = true;
    btnXml.textContent = 'Analisando...';
    xmlLimparErro('erro-upload-xml');

    try {
        const fd = new FormData();
        fd.append('xml', arquivo);
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
        (nota.fornecedor_novo ? ' <span class="text-amber-600">(não cadastrado: será cadastrado automaticamente)</span>' : '');

    document.getElementById('linhas-conferencia-xml').innerHTML = itens.map((item, i) => {
        const p = item.produto;
        const celula = p
            ? `<span class="text-green-700 font-medium">${p.manual ? 'Assimilado manualmente' : 'Cadastro encontrado: ' + xmlEsc(p.por)}</span>
               <span class="block text-xs text-gray-500">${xmlEsc(p.nome)}</span>
               <button type="button" onclick="xmlAbrirAssimilar(${i})" class="text-xs text-blue-600 hover:underline">trocar</button>`
            : `<button type="button" onclick="xmlAbrirAssimilar(${i})" class="text-red-600 font-medium hover:underline text-left">
                   Cadastro não encontrado! (Clique aqui para assimilar)</button>`;

        return `
            <tr class="border-b border-gray-100 align-top">
                <td class="py-3 pr-3 font-mono text-xs text-gray-600">${xmlEsc(item.ean || '—')}</td>
                <td class="py-3 pr-3">${xmlEsc(item.descricao)}
                    <span class="block text-xs text-gray-400">Cód. fornecedor: ${xmlEsc(item.codigo || '—')}</span></td>
                <td class="py-3 pr-3">${xmlMoeda(item.valor_custo)}</td>
                <td class="py-3">${celula}</td>
            </tr>`;
    }).join('');

    const pendentes = itens.filter(i => !i.produto).length;
    document.getElementById('contador-conferencia-xml').textContent =
        pendentes ? `${pendentes} de ${itens.length} item(ns) sem produto` : `Todos os ${itens.length} itens assimilados`;
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
function xmlMostrarCadastro() {
    const item = xmlImportacao.itens[xmlItemAtual];

    document.getElementById('cad-nome').value = item.descricao;
    document.getElementById('cad-codigo-barras').value = item.ean || '';
    document.getElementById('cad-unidade').value = item.unidade || 'UN';
    document.getElementById('cad-ncm').value = item.ncm || '';
    document.getElementById('cad-custo').value = item.valor_custo;
    document.getElementById('cad-venda').value = item.valor_custo;
    xmlLimparErro('erro-cadastro-xml');

    document.getElementById('view-busca-xml').classList.add('hidden');
    document.getElementById('view-cadastro-xml').classList.remove('hidden');
    document.getElementById('cad-nome').focus();
}

async function xmlSalvarProduto() {
    const btn = document.getElementById('btn-salvar-produto-xml');
    btn.disabled = true;
    xmlLimparErro('erro-cadastro-xml');

    try {
        const produto = await xmlRequisicao(XML_URLS.rapido, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: document.getElementById('cad-nome').value,
                codigo_barras: document.getElementById('cad-codigo-barras').value || null,
                unidade_comercial: document.getElementById('cad-unidade').value,
                ncm: document.getElementById('cad-ncm').value || null,
                preco_custo: document.getElementById('cad-custo').value,
                preco_venda: document.getElementById('cad-venda').value,
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