<!-- Modal Categoria / Marca / Grupo -->
<div id="modal-catalogo" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 id="modal-catalogo-titulo" class="text-lg font-bold">Selecionar</h2>
            <button type="button" onclick="fecharModalCatalogo()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="catalogo-busca" placeholder="Buscar..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarCatalogo()">
            <button type="button" onclick="abrirFormNovoCatalogo()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo
            </button>
        </div>

        <div id="form-novo-catalogo" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <input type="text" id="novo-catalogo-nome" placeholder="Nome..."
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoCatalogo()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoCatalogo()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <ul id="catalogo-lista" class="divide-y divide-gray-100"></ul>
        <p id="catalogo-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhum resultado encontrado.</p>
    </div>
</div>

<!-- Modal NCM -->
<div id="modal-ncm" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Selecionar NCM</h2>
            <button type="button" onclick="fecharModalNcm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="ncm-busca" placeholder="Buscar por código ou descrição..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarNcm()">
            <button type="button" onclick="abrirFormNovoNcm()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo NCM
            </button>
        </div>

        <div id="form-novo-ncm" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" id="novo-ncm-codigo" placeholder="Código (8 dígitos)"
                       maxlength="8" class="border rounded px-3 py-2 text-sm">
                <input type="text" id="novo-ncm-descricao" placeholder="Descrição"
                       class="border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoNcm()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoNcm()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2 w-28">Código</th>
                    <th class="py-2">Descrição</th>
                </tr>
            </thead>
            <tbody id="ncm-lista"></tbody>
        </table>
        <p id="ncm-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhum NCM encontrado. Use "+ Novo NCM" para cadastrar.</p>
    </div>
</div>

<!-- Modal CEST -->
<div id="modal-cest" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Selecionar CEST</h2>
            <button type="button" onclick="fecharModalCest()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-2">
            <input type="text" id="cest-busca" placeholder="Buscar por código ou descrição..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarCest()">
            <button type="button" onclick="abrirFormNovoCest()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo CEST
            </button>
        </div>

        <div class="text-right mb-4">
            <button type="button" onclick="limparCest()" class="text-xs text-gray-500 hover:underline">
                Produto não sujeito a ICMS-ST (limpar seleção)
            </button>
        </div>

        <div id="form-novo-cest" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" id="novo-cest-codigo" placeholder="Código (7 dígitos)"
                       maxlength="7" class="border rounded px-3 py-2 text-sm">
                <input type="text" id="novo-cest-descricao" placeholder="Descrição"
                       class="border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoCest()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoCest()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2 w-28">Código</th>
                    <th class="py-2">Descrição</th>
                    <th class="py-2 w-28"></th>
                </tr>
            </thead>
            <tbody id="cest-lista"></tbody>
        </table>
        <p id="cest-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhum CEST encontrado. Use "+ Novo CEST" para cadastrar.</p>
    </div>
</div>

<!-- Modal Classificação Tributária IBS/CBS (cClassTrib) -->
<div id="modal-classtrib" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Selecionar Classificação Tributária (IBS/CBS)</h2>
            <button type="button" onclick="fecharModalClassTrib()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-2">
            <input type="text" id="classtrib-busca" placeholder="Buscar por código, CST ou descrição..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarClassTrib()">
            <button type="button" onclick="abrirFormNovoClassTrib()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Nova
            </button>
        </div>

        <div class="text-right mb-4">
            <button type="button" onclick="limparClassTrib()" class="text-xs text-gray-500 hover:underline">
                Produto sem classificação IBS/CBS específica (limpar seleção)
            </button>
        </div>

        <div id="form-novo-classtrib" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" id="novo-classtrib-codigo" placeholder="cClassTrib (6 dígitos)"
                       maxlength="6" class="border rounded px-3 py-2 text-sm">
                <input type="text" id="novo-classtrib-cst" placeholder="CST (3 dígitos)"
                       maxlength="3" class="border rounded px-3 py-2 text-sm">
            </div>
            <input type="text" id="novo-classtrib-descricao" placeholder="Descrição do cClassTrib"
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <input type="text" id="novo-classtrib-cst-descricao" placeholder="Descrição do CST (opcional)"
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="fecharFormNovoClassTrib()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button type="button" onclick="salvarNovoClassTrib()"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">Salvar</button>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2 w-24">cClassTrib</th>
                    <th class="py-2 w-16">CST</th>
                    <th class="py-2">Descrição</th>
                    <th class="py-2 w-28"></th>
                </tr>
            </thead>
            <tbody id="classtrib-lista"></tbody>
        </table>
        <p id="classtrib-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhuma classificação encontrada. Use "+ Nova" para cadastrar.</p>
    </div>
</div>

<!-- Modal Tributação -->
<input type="hidden" name="tributacao_id" id="tributacao_id" value="{{ old('tributacao_id', $produto->tributacao_id ?? '') }}">

<div id="modal-tributacao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Classificação Tributária</h2>
            <button type="button" onclick="fecharModalTributacao()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <input type="text" id="tributacao-busca" placeholder="Buscar tributação..."
               class="w-full border rounded px-3 py-2 text-sm mb-4" oninput="buscarTributacao()">

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b">
                    <th class="py-2 pr-4">Descrição</th>
                    <th class="py-2 pr-4">CFOP</th>
                    <th class="py-2 pr-4">CSOSN/CST</th>
                    <th class="py-2">Alíquota</th>
                </tr>
            </thead>
            <tbody id="tributacao-lista"></tbody>
        </table>
        <p id="tributacao-vazio" class="text-sm text-gray-400 text-center py-4 hidden">Nenhuma tributação encontrada.</p>
    </div>
</div>



<script>
let tipoCatalogoAtivo = null;
let timeoutCatalogo;
let timeoutNcm;
let timeoutCest;
let timeoutClassTrib;
let editandoCatalogoId = null;
let editandoNcmId = null;
let editandoCestId = null;
let editandoClassTribId = null;
let timeoutTributacao;



// ===================== CATALOGO (categoria, marca, grupo) =====================

function abrirModalCatalogo(tipo) {
    tipoCatalogoAtivo = tipo;
    const titulos = { categoria: 'Categoria', marca: 'Marca', grupo: 'Grupo' };
    document.getElementById('modal-catalogo-titulo').innerText = titulos[tipo];
    document.getElementById('catalogo-busca').value = '';
    document.getElementById('form-novo-catalogo').classList.add('hidden');
    document.getElementById('novo-catalogo-nome').value = '';

    document.getElementById('modal-catalogo').classList.remove('hidden');
    document.getElementById('modal-catalogo').classList.add('flex');

    buscarCatalogo();
    setTimeout(() => document.getElementById('catalogo-busca').focus(), 100);
}

function fecharModalCatalogo() {
    document.getElementById('modal-catalogo').classList.add('hidden');
    document.getElementById('modal-catalogo').classList.remove('flex');
    tipoCatalogoAtivo = null;
}

function buscarCatalogo() {
    clearTimeout(timeoutCatalogo);
    timeoutCatalogo = setTimeout(async () => {
        const termo = document.getElementById('catalogo-busca').value;
        const resp = await fetch(`{{ route('catalogo.listar') }}?tipo=${tipoCatalogoAtivo}&q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('catalogo-lista');
        const vazio = document.getElementById('catalogo-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        lista.innerHTML = items.map(i => `
            <li class="py-2 px-1 hover:bg-blue-50 rounded text-sm flex justify-between items-center gap-2 group">
                <span class="flex-1 cursor-pointer" onclick="selecionarCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')">
                    ${i.nome}
                </span>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                    <button type="button" onclick="editarCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')"
                            class="text-xs text-blue-500 hover:underline px-1">Editar</button>
                    <button type="button" onclick="excluirCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')"
                            class="text-xs text-red-500 hover:underline px-1">Excluir</button>
                </div>
            </li>
        `).join('');
    }, 200);
}

function selecionarCatalogo(id, nome) {
    document.getElementById(`${tipoCatalogoAtivo}_id`).value = id;
    document.getElementById(`${tipoCatalogoAtivo}_label`).innerText = nome;
    fecharModalCatalogo();
}

function abrirFormNovoCatalogo() {
    document.getElementById('form-novo-catalogo').classList.remove('hidden');
    document.getElementById('novo-catalogo-nome').focus();
}

function fecharFormNovoCatalogo() {
    document.getElementById('form-novo-catalogo').classList.add('hidden');
    document.getElementById('novo-catalogo-nome').value = '';
    editandoCatalogoId = null;
}


function editarCatalogo(id, nomeAtual) {
    editandoCatalogoId = id;
    document.getElementById('form-novo-catalogo').classList.remove('hidden');
    document.getElementById('novo-catalogo-nome').value = nomeAtual;
    document.getElementById('novo-catalogo-nome').focus();
}


async function excluirCatalogo(id, nome) {
    if (!confirm(`Excluir "${nome}"? Esta ação não pode ser desfeita.`)) return;

    const resp = await fetch('{{ route("catalogo.excluir") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ tipo: tipoCatalogoAtivo, id }),
    });

    const resultado = await resp.json();

    if (resultado.erro) {
        alert(resultado.erro);
        return;
    }

    // Se o excluído era o selecionado atualmente, limpa o campo
    const idAtual = document.getElementById(`${tipoCatalogoAtivo}_id`).value;
    if (idAtual == id) {
        document.getElementById(`${tipoCatalogoAtivo}_id`).value = '';
        document.getElementById(`${tipoCatalogoAtivo}_label`).innerText = 'Clique para selecionar...';
    }

    buscarCatalogo();
}


function abrirModalTributacao() {
    document.getElementById('tributacao-busca').value = '';
    document.getElementById('modal-tributacao').classList.remove('hidden');
    document.getElementById('modal-tributacao').classList.add('flex');
    buscarTributacao();
    setTimeout(() => document.getElementById('tributacao-busca').focus(), 100);
}

function fecharModalTributacao() {
    document.getElementById('modal-tributacao').classList.add('hidden');
    document.getElementById('modal-tributacao').classList.remove('flex');
}

function buscarTributacao() {
    clearTimeout(timeoutTributacao);
    timeoutTributacao = setTimeout(async () => {
        const termo = document.getElementById('tributacao-busca').value;
        const resp = await fetch(`{{ route('tributacao.listar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('tributacao-lista');
        const vazio = document.getElementById('tributacao-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        lista.innerHTML = items.map(i => `
            <tr class="border-b hover:bg-blue-50 cursor-pointer"
                onclick="selecionarTributacao(${i.id}, '${i.descricao.replace(/'/g, "\\'")}', '${i.cfop}', '${i.csosn ?? i.cst_icms}', ${i.aliquota_icms})">
                <td class="py-2 pr-4">
                    <p class="font-medium">${i.descricao}</p>
                    ${i.observacao ? `<p class="text-xs text-gray-400">${i.observacao}</p>` : ''}
                </td>
                <td class="py-2 pr-4 font-mono">${i.cfop}</td>
                <td class="py-2 pr-4 font-mono">0${i.csosn ?? i.cst_icms ?? '-'}</td>
                <td class="py-2">${i.aliquota_icms > 0 ? i.aliquota_icms + '%' : '-'}</td>
            </tr>
        `).join('');
    }, 200);
}

function selecionarTributacao(id, descricao, cfop, codigoSit, aliquota) {
    document.getElementById('tributacao_id').value = id;

    const aliqLabel = aliquota > 0 ? ` (${aliquota}%)` : '';
    document.getElementById('tributacao_label').innerText =
        `${descricao} — CFOP ${cfop} / ${codigoSit}${aliqLabel}`;

    fecharModalTributacao();
}


async function salvarNovoCatalogo() {
    const nome = document.getElementById('novo-catalogo-nome').value.trim();
    if (!nome) return;

    let url, body;

    if (editandoCatalogoId) {
        url = '{{ route("catalogo.editar") }}';
        body = JSON.stringify({ tipo: tipoCatalogoAtivo, id: editandoCatalogoId, nome });
    } else {
        url = '{{ route("catalogo.criar") }}';
        body = JSON.stringify({ tipo: tipoCatalogoAtivo, nome });
    }

    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body,
    });

    const item = await resp.json();
    editandoCatalogoId = null;
    fecharFormNovoCatalogo();
    buscarCatalogo();

    // Se estava editando o item atualmente selecionado, atualiza o label
    const idAtual = document.getElementById(`${tipoCatalogoAtivo}_id`).value;
    if (idAtual == item.id) {
        document.getElementById(`${tipoCatalogoAtivo}_label`).innerText = item.nome;
    }
}

// ===================== NCM =====================

function abrirModalNcm() {
    document.getElementById('ncm-busca').value = '';
    document.getElementById('form-novo-ncm').classList.add('hidden');
    document.getElementById('modal-ncm').classList.remove('hidden');
    document.getElementById('modal-ncm').classList.add('flex');

    buscarNcm();
    setTimeout(() => document.getElementById('ncm-busca').focus(), 100);
}

function fecharModalNcm() {
    document.getElementById('modal-ncm').classList.add('hidden');
    document.getElementById('modal-ncm').classList.remove('flex');
}

function buscarNcm() {
    clearTimeout(timeoutNcm);
    timeoutNcm = setTimeout(async () => {
        const termo = document.getElementById('ncm-busca').value;
        const resp = await fetch(`{{ route('ncm.listar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('ncm-lista');
        const vazio = document.getElementById('ncm-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');

        const highlight = (texto, termo) => {
            if (!termo) return texto;
            const regex = new RegExp(`(${termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return texto.replace(regex, '<mark class="bg-yellow-200 rounded-sm">$1</mark>');
        };

        lista.innerHTML = items.map(i => `
            <tr class="border-b hover:bg-blue-50 group">
                <td class="py-2 font-mono text-xs cursor-pointer"
                    onclick="selecionarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.codigo, termo)}
                </td>
                <td class="py-2 text-sm cursor-pointer"
                    onclick="selecionarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.descricao, termo)}
                </td>
                <td class="py-2 w-28">
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition justify-end">
                        <button type="button" onclick="editarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')"
                                class="text-xs text-blue-500 hover:underline">Editar</button>
                        <button type="button" onclick="excluirNcm(${i.id})"
                                class="text-xs text-red-500 hover:underline">Excluir</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }, 200);
}

function selecionarNcm(id, codigo, descricao) {
    document.getElementById('ncm_id').value = id;
    document.getElementById('ncm_label').innerText = `${codigo} — ${descricao}`;
    fecharModalNcm();
}

function abrirFormNovoNcm() {
    document.getElementById('form-novo-ncm').classList.remove('hidden');
    document.getElementById('novo-ncm-codigo').focus();
}

function fecharFormNovoNcm() {
    document.getElementById('form-novo-ncm').classList.add('hidden');
    document.getElementById('novo-ncm-codigo').value = '';
    document.getElementById('novo-ncm-descricao').value = '';
    editandoNcmId = null;
}

function editarNcm(id, codigo, descricao) {
    editandoNcmId = id;
    document.getElementById('form-novo-ncm').classList.remove('hidden');
    document.getElementById('novo-ncm-codigo').value = codigo;
    document.getElementById('novo-ncm-descricao').value = descricao;
    document.getElementById('novo-ncm-codigo').focus();
}

async function salvarNovoNcm() {
    const codigo = document.getElementById('novo-ncm-codigo').value.trim();
    const descricao = document.getElementById('novo-ncm-descricao').value.trim();

    if (codigo.length !== 8) { alert('O código NCM deve ter exatamente 8 dígitos.'); return; }
    if (!descricao) { alert('Informe uma descrição.'); return; }

    let url, body;

    if (editandoNcmId) {
        url = '{{ route("ncm.editar") }}';
        body = JSON.stringify({ id: editandoNcmId, codigo, descricao });
    } else {
        url = '{{ route("ncm.criar") }}';
        body = JSON.stringify({ codigo, descricao });
    }

    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body,
    });

    const item = await resp.json();
    editandoNcmId = null;
    fecharFormNovoNcm();
    buscarNcm();

    // Atualiza o label se estava editando o NCM selecionado
    const idAtual = document.getElementById('ncm_id').value;
    if (idAtual == item.id) {
        document.getElementById('ncm_label').innerText = `${item.codigo} — ${item.descricao}`;
    }
}

async function excluirNcm(id) {
    if (!confirm('Excluir este NCM? Esta ação não pode ser desfeita.')) return;

    const resp = await fetch('{{ route("ncm.excluir") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ id }),
    });

    const resultado = await resp.json();

    if (resultado.erro) {
        alert(resultado.erro);
        return;
    }

    const idAtual = document.getElementById('ncm_id').value;
    if (idAtual == id) {
        document.getElementById('ncm_id').value = '';
        document.getElementById('ncm_label').innerText = 'Clique para selecionar...';
    }

    buscarNcm();
}

// ===================== CEST =====================

function abrirModalCest() {
    document.getElementById('cest-busca').value = '';
    document.getElementById('form-novo-cest').classList.add('hidden');
    document.getElementById('modal-cest').classList.remove('hidden');
    document.getElementById('modal-cest').classList.add('flex');

    buscarCest();
    setTimeout(() => document.getElementById('cest-busca').focus(), 100);
}

function fecharModalCest() {
    document.getElementById('modal-cest').classList.add('hidden');
    document.getElementById('modal-cest').classList.remove('flex');
}

function buscarCest() {
    clearTimeout(timeoutCest);
    timeoutCest = setTimeout(async () => {
        const termo = document.getElementById('cest-busca').value;
        const resp = await fetch(`{{ route('cest.listar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('cest-lista');
        const vazio = document.getElementById('cest-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');

        const highlight = (texto, termo) => {
            if (!termo) return texto;
            const regex = new RegExp(`(${termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return texto.replace(regex, '<mark class="bg-yellow-200 rounded-sm">$1</mark>');
        };

        lista.innerHTML = items.map(i => `
            <tr class="border-b hover:bg-blue-50 group">
                <td class="py-2 font-mono text-xs cursor-pointer"
                    onclick="selecionarCest(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.codigo, termo)}
                </td>
                <td class="py-2 text-sm cursor-pointer"
                    onclick="selecionarCest(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.descricao, termo)}
                </td>
                <td class="py-2 w-28">
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition justify-end">
                        <button type="button" onclick="editarCest(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')"
                                class="text-xs text-blue-500 hover:underline">Editar</button>
                        <button type="button" onclick="excluirCest(${i.id})"
                                class="text-xs text-red-500 hover:underline">Excluir</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }, 200);
}

function selecionarCest(id, codigo, descricao) {
    document.getElementById('cest_id').value = id;
    document.getElementById('cest_label').innerText = `${codigo} — ${descricao}`;
    fecharModalCest();
}

function limparCest() {
    document.getElementById('cest_id').value = '';
    document.getElementById('cest_label').innerText = 'Clique para selecionar (opcional)...';
    fecharModalCest();
}

function abrirFormNovoCest() {
    document.getElementById('form-novo-cest').classList.remove('hidden');
    document.getElementById('novo-cest-codigo').focus();
}

function fecharFormNovoCest() {
    document.getElementById('form-novo-cest').classList.add('hidden');
    document.getElementById('novo-cest-codigo').value = '';
    document.getElementById('novo-cest-descricao').value = '';
    editandoCestId = null;
}

function editarCest(id, codigo, descricao) {
    editandoCestId = id;
    document.getElementById('form-novo-cest').classList.remove('hidden');
    document.getElementById('novo-cest-codigo').value = codigo;
    document.getElementById('novo-cest-descricao').value = descricao;
    document.getElementById('novo-cest-codigo').focus();
}

async function salvarNovoCest() {
    const codigo = document.getElementById('novo-cest-codigo').value.trim();
    const descricao = document.getElementById('novo-cest-descricao').value.trim();

    if (codigo.length !== 7) { alert('O código CEST deve ter exatamente 7 dígitos.'); return; }
    if (!descricao) { alert('Informe uma descrição.'); return; }

    let url, body;

    if (editandoCestId) {
        url = '{{ route("cest.editar") }}';
        body = JSON.stringify({ id: editandoCestId, codigo, descricao });
    } else {
        url = '{{ route("cest.criar") }}';
        body = JSON.stringify({ codigo, descricao });
    }

    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body,
    });

    const item = await resp.json();

    if (item.errors) {
        alert(Object.values(item.errors).flat().join('\n'));
        return;
    }

    editandoCestId = null;
    fecharFormNovoCest();
    buscarCest();

    // Atualiza o label se estava editando o CEST selecionado
    const idAtual = document.getElementById('cest_id').value;
    if (idAtual == item.id) {
        document.getElementById('cest_label').innerText = `${item.codigo} — ${item.descricao}`;
    }
}

async function excluirCest(id) {
    if (!confirm('Excluir este CEST? Esta ação não pode ser desfeita.')) return;

    const resp = await fetch('{{ route("cest.excluir") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ id }),
    });

    const resultado = await resp.json();

    if (resultado.erro) {
        alert(resultado.erro);
        return;
    }

    const idAtual = document.getElementById('cest_id').value;
    if (idAtual == id) {
        document.getElementById('cest_id').value = '';
        document.getElementById('cest_label').innerText = 'Clique para selecionar (opcional)...';
    }

    buscarCest();
}

// ===================== CLASSIFICAÇÃO TRIBUTÁRIA (IBS/CBS) =====================

function abrirModalClassTrib() {
    document.getElementById('classtrib-busca').value = '';
    document.getElementById('form-novo-classtrib').classList.add('hidden');
    document.getElementById('modal-classtrib').classList.remove('hidden');
    document.getElementById('modal-classtrib').classList.add('flex');

    buscarClassTrib();
    setTimeout(() => document.getElementById('classtrib-busca').focus(), 100);
}

function fecharModalClassTrib() {
    document.getElementById('modal-classtrib').classList.add('hidden');
    document.getElementById('modal-classtrib').classList.remove('flex');
}

function buscarClassTrib() {
    clearTimeout(timeoutClassTrib);
    timeoutClassTrib = setTimeout(async () => {
        const termo = document.getElementById('classtrib-busca').value;
        const resp = await fetch(`{{ route('classtrib.listar') }}?q=${encodeURIComponent(termo)}`);
        const items = await resp.json();

        const lista = document.getElementById('classtrib-lista');
        const vazio = document.getElementById('classtrib-vazio');

        if (items.length === 0) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');

        const highlight = (texto, termo) => {
            if (!termo || !texto) return texto ?? '';
            const regex = new RegExp(`(${termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return texto.replace(regex, '<mark class="bg-yellow-200 rounded-sm">$1</mark>');
        };

        lista.innerHTML = items.map(i => `
            <tr class="border-b hover:bg-blue-50 group">
                <td class="py-2 font-mono text-xs cursor-pointer"
                    onclick="selecionarClassTrib(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.codigo, termo)}
                </td>
                <td class="py-2 font-mono text-xs text-gray-500 cursor-pointer"
                    onclick="selecionarClassTrib(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.cst_codigo, termo)}
                </td>
                <td class="py-2 text-sm cursor-pointer"
                    onclick="selecionarClassTrib(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${highlight(i.descricao, termo)}
                    ${i.cst_descricao ? `<p class="text-xs text-gray-400">${i.cst_descricao}</p>` : ''}
                </td>
                <td class="py-2 w-28">
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition justify-end">
                        <button type="button" onclick="editarClassTrib(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}', '${i.cst_codigo}', '${(i.cst_descricao ?? '').replace(/'/g, "\\'")}')"
                                class="text-xs text-blue-500 hover:underline">Editar</button>
                        <button type="button" onclick="excluirClassTrib(${i.id})"
                                class="text-xs text-red-500 hover:underline">Excluir</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }, 200);
}

function selecionarClassTrib(id, codigo, descricao) {
    document.getElementById('class_trib_ibs_cbs_id').value = id;
    document.getElementById('class_trib_ibs_cbs_label').innerText = `${codigo} — ${descricao}`;
    fecharModalClassTrib();
}

function limparClassTrib() {
    document.getElementById('class_trib_ibs_cbs_id').value = '';
    document.getElementById('class_trib_ibs_cbs_label').innerText = 'Clique para selecionar (opcional)...';
    fecharModalClassTrib();
}

function abrirFormNovoClassTrib() {
    document.getElementById('form-novo-classtrib').classList.remove('hidden');
    document.getElementById('novo-classtrib-codigo').focus();
}

function fecharFormNovoClassTrib() {
    document.getElementById('form-novo-classtrib').classList.add('hidden');
    document.getElementById('novo-classtrib-codigo').value = '';
    document.getElementById('novo-classtrib-cst').value = '';
    document.getElementById('novo-classtrib-descricao').value = '';
    document.getElementById('novo-classtrib-cst-descricao').value = '';
    editandoClassTribId = null;
}

function editarClassTrib(id, codigo, descricao, cstCodigo, cstDescricao) {
    editandoClassTribId = id;
    document.getElementById('form-novo-classtrib').classList.remove('hidden');
    document.getElementById('novo-classtrib-codigo').value = codigo;
    document.getElementById('novo-classtrib-cst').value = cstCodigo;
    document.getElementById('novo-classtrib-descricao').value = descricao;
    document.getElementById('novo-classtrib-cst-descricao').value = cstDescricao;
    document.getElementById('novo-classtrib-codigo').focus();
}

async function salvarNovoClassTrib() {
    const codigo = document.getElementById('novo-classtrib-codigo').value.trim();
    const cstCodigo = document.getElementById('novo-classtrib-cst').value.trim();
    const descricao = document.getElementById('novo-classtrib-descricao').value.trim();
    const cstDescricao = document.getElementById('novo-classtrib-cst-descricao').value.trim();

    if (codigo.length !== 6) { alert('O código cClassTrib deve ter exatamente 6 dígitos.'); return; }
    if (cstCodigo.length !== 3) { alert('O código CST deve ter exatamente 3 dígitos.'); return; }
    if (!descricao) { alert('Informe uma descrição.'); return; }

    let url, body;

    if (editandoClassTribId) {
        url = '{{ route("classtrib.editar") }}';
        body = JSON.stringify({ id: editandoClassTribId, codigo, cst_codigo: cstCodigo, descricao, cst_descricao: cstDescricao || null });
    } else {
        url = '{{ route("classtrib.criar") }}';
        body = JSON.stringify({ codigo, cst_codigo: cstCodigo, descricao, cst_descricao: cstDescricao || null });
    }

    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body,
    });

    const item = await resp.json();

    if (item.erro) {
        alert(item.erro);
        return;
    }
    if (item.errors) {
        alert(Object.values(item.errors).flat().join('\n'));
        return;
    }

    editandoClassTribId = null;
    fecharFormNovoClassTrib();
    buscarClassTrib();

    const idAtual = document.getElementById('class_trib_ibs_cbs_id').value;
    if (idAtual == item.id) {
        document.getElementById('class_trib_ibs_cbs_label').innerText = `${item.codigo} — ${item.descricao}`;
    }
}

async function excluirClassTrib(id) {
    if (!confirm('Excluir esta Classificação Tributária? Esta ação não pode ser desfeita.')) return;

    const resp = await fetch('{{ route("classtrib.excluir") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ id }),
    });

    const resultado = await resp.json();

    if (resultado.erro) {
        alert(resultado.erro);
        return;
    }

    const idAtual = document.getElementById('class_trib_ibs_cbs_id').value;
    if (idAtual == id) {
        document.getElementById('class_trib_ibs_cbs_id').value = '';
        document.getElementById('class_trib_ibs_cbs_label').innerText = 'Clique para selecionar (opcional)...';
    }

    buscarClassTrib();
}

</script>