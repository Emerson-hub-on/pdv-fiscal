<!-- Modal Categoria / Marca / Grupo -->
<div id="modal-catalogo" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 id="modal-catalogo-titulo" class="text-lg font-bold">Selecionar</h2>
            <button onclick="fecharModalCatalogo()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="catalogo-busca" placeholder="Buscar..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarCatalogo()">
            <button onclick="abrirFormNovoCatalogo()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded whitespace-nowrap">
                + Novo
            </button>
        </div>

        <div id="form-novo-catalogo" class="hidden bg-gray-50 rounded-lg p-3 mb-3">
            <input type="text" id="novo-catalogo-nome" placeholder="Nome..."
                   class="w-full border rounded px-3 py-2 text-sm mb-2">
            <div class="flex gap-2 justify-end">
                <button onclick="fecharFormNovoCatalogo()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button onclick="salvarNovoCatalogo()"
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
            <button onclick="fecharModalNcm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex gap-2 mb-4">
            <input type="text" id="ncm-busca" placeholder="Buscar por código ou descrição..."
                   class="flex-1 border rounded px-3 py-2 text-sm" oninput="buscarNcm()">
            <button onclick="abrirFormNovoNcm()"
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
                <button onclick="fecharFormNovoNcm()" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                <button onclick="salvarNovoNcm()"
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

<script>
let tipoCatalogoAtivo = null;
let timeoutCatalogo;
let timeoutNcm;
let editandoCatalogoId = null;
let editandoNcmId = null;

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
                    <button onclick="editarCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')"
                            class="text-xs text-blue-500 hover:underline px-1">Editar</button>
                    <button onclick="excluirCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')"
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
        lista.innerHTML = items.map(i => `
            <tr class="border-b hover:bg-blue-50 group">
                <td class="py-2 font-mono text-xs cursor-pointer"
                    onclick="selecionarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${i.codigo}
                </td>
                <td class="py-2 text-sm cursor-pointer"
                    onclick="selecionarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                    ${i.descricao}
                </td>
                <td class="py-2 w-28">
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition justify-end">
                        <button onclick="editarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')"
                                class="text-xs text-blue-500 hover:underline">Editar</button>
                        <button onclick="excluirNcm(${i.id})"
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


</script>