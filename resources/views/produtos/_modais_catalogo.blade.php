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
            <li class="py-2 px-1 hover:bg-blue-50 cursor-pointer rounded text-sm flex justify-between items-center"
                onclick="selecionarCatalogo(${i.id}, '${i.nome.replace(/'/g, "\\'")}')">
                <span>${i.nome}</span>
                <span class="text-blue-500 text-xs">Selecionar</span>
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
}

async function salvarNovoCatalogo() {
    const nome = document.getElementById('novo-catalogo-nome').value.trim();
    if (!nome) return;

    const resp = await fetch('{{ route('catalogo.criar') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ tipo: tipoCatalogoAtivo, nome }),
    });

    const item = await resp.json();
    selecionarCatalogo(item.id, item.nome);
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
            <tr class="hover:bg-blue-50 cursor-pointer border-b"
                onclick="selecionarNcm(${i.id}, '${i.codigo}', '${i.descricao.replace(/'/g, "\\'")}')">
                <td class="py-2 font-mono text-xs">${i.codigo}</td>
                <td class="py-2 text-sm">${i.descricao}</td>
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
}

async function salvarNovoNcm() {
    const codigo = document.getElementById('novo-ncm-codigo').value.trim();
    const descricao = document.getElementById('novo-ncm-descricao').value.trim();

    if (codigo.length !== 8) {
        alert('O código NCM deve ter exatamente 8 dígitos.');
        return;
    }

    if (!descricao) {
        alert('Informe uma descrição para o NCM.');
        return;
    }

    const resp = await fetch('{{ route('ncm.criar') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ codigo, descricao }),
    });

    const item = await resp.json();
    selecionarNcm(item.id, item.codigo, item.descricao);
    fecharFormNovoNcm();
}
</script>