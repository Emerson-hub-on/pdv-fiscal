<div id="modal-operacao-entrada" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[65]">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900 rounded-t-xl">
            <h2 class="text-lg font-bold text-white">CFOP / Operação da entrada</h2>
            <button type="button" onclick="fecharModalOperacao()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        {{-- Lista --}}
        <div id="op-view-lista" class="p-6 overflow-y-auto">
            <div class="flex items-center gap-3 mb-4">
                <input type="text" id="op-busca" placeholder="Buscar por CFOP ou natureza da operação..." autocomplete="off"
                       class="flex-1 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition">
                <button type="button" onclick="opMostrarCadastro()"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition whitespace-nowrap">
                    + Cadastrar novo CFOP de entrada
                </button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2 px-3 w-40">CFOP</th>
                        <th class="py-2 px-3">Natureza da Operação</th>
                    </tr>
                </thead>
                <tbody id="op-linhas"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar. O primeiro CFOP vale para compras dentro do estado e o segundo para outros estados.</p>
        </div>

        {{-- Cadastro --}}
        <div id="op-view-cadastro" class="hidden p-6 overflow-y-auto">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CFOP de entrada <span class="text-red-500">*</span></label>
                    <input type="text" id="op-cfop" maxlength="4" placeholder="Ex.: 1949"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Informe o 1xxx (dentro do estado). O 2xxx (outros estados) é criado automaticamente.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CFOP quando a mercadoria tem ST <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" id="op-cfop-st" maxlength="4" placeholder="Ex.: 1403"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Em branco: usa o mesmo CFOP da coluna ao lado.</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Natureza da operação <span class="text-red-500">*</span></label>
                    <input type="text" id="op-descricao" maxlength="60" placeholder="Ex.: Entrada de mercadoria em demonstração"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="col-span-2 flex items-start gap-3">
                    <input type="checkbox" id="op-movimenta" checked class="w-4 h-4 mt-0.5 text-blue-600 rounded">
                    <label for="op-movimenta" class="text-sm text-gray-700">
                        Movimenta estoque
                        <span class="block text-xs text-gray-400">Desmarque para uso e consumo, ativo imobilizado etc.: a entrada não soma no estoque de venda nem altera o custo.</span>
                    </label>
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-4">As regras de conversão serão criadas para os CFOPs de venda, ST, bonificação e outros do fornecedor (5xxx → 1xxx, 6xxx → 2xxx).</p>
            <p id="op-erro" class="hidden text-sm text-red-600 mt-3 whitespace-pre-line"></p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="opMostrarLista()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Voltar à lista</button>
                <button type="button" id="op-btn-salvar" onclick="opSalvar()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                    Cadastrar e selecionar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let opLista = @json($operacoes);
let opFiltrada = [];
let opIndice = 0;
let opContexto = 'cabecalho'; 

function opEsc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function opRotulo(o) { return `${o.cfops || '—'} — ${o.descricao}`; }

function abrirModalOperacao(contexto = 'cabecalho') {
    opContexto = contexto;
    const modal = document.getElementById('modal-operacao-entrada');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    opMostrarLista();
}

function fecharModalOperacao() {
    const modal = document.getElementById('modal-operacao-entrada');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function opMostrarLista() {
    document.getElementById('op-view-cadastro').classList.add('hidden');
    document.getElementById('op-view-lista').classList.remove('hidden');

    const busca = document.getElementById('op-busca');
    busca.value = '';
    opFiltrar();
    busca.focus();
}

function opFiltrar() {
    const termo = document.getElementById('op-busca').value.trim().toLowerCase();
    opFiltrada = opLista.filter(o => !termo || (o.cfops || '').toLowerCase().includes(termo) || o.descricao.toLowerCase().includes(termo));
    opIndice = 0;
    opRender();
}

function opRender() {
    const campo = document.getElementById(opContexto === 'import' ? 'xml-operacao' : 'operacao_entrada_id');
    const atual = String(campo?.value ?? '');
    const tbody = document.getElementById('op-linhas');

    if (!opFiltrada.length) {
        tbody.innerHTML = '<tr><td colspan="2" class="p-4 text-center text-sm text-gray-400">Nenhum CFOP encontrado.</td></tr>';
        return;
    }

    tbody.innerHTML = opFiltrada.map((o, i) => {
        const dest = i === opIndice;
        const marcado = String(o.id) === atual ? ' <span class="' + (dest ? 'text-emerald-300' : 'text-blue-600') + '">✓</span>' : '';
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${dest ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="opSelecionar(${o.id})">
                <td class="py-3 px-3 font-mono ${dest ? 'text-slate-300' : 'text-gray-600'}">${opEsc(o.cfops || '—')}</td>
                <td class="py-3 px-3 font-medium">${opEsc(o.descricao)}${marcado}</td>
            </tr>`;
    }).join('');
}

document.getElementById('op-busca').addEventListener('input', opFiltrar);

document.getElementById('op-busca').addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalOperacao(); return; }
    if (!opFiltrada.length) return;

    if (e.key === 'ArrowDown') { e.preventDefault(); opIndice = (opIndice + 1) % opFiltrada.length; opRender(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); opIndice = (opIndice - 1 + opFiltrada.length) % opFiltrada.length; opRender(); }
    else if (e.key === 'Enter') { e.preventDefault(); opSelecionar(opFiltrada[opIndice].id); }
});

function opAplicarNoImport(o) {
    const campo = document.getElementById('xml-operacao');
    if (!campo) return;

    campo.value = o.id;
    const label = document.getElementById('xml-operacao-label');
    label.textContent = opRotulo(o);
    label.className = 'text-gray-800';

    if (typeof xmlAtualizarBotaoImportar === 'function') xmlAtualizarBotaoImportar();
}

function opSelecionar(id) {
    const o = opLista.find(x => String(x.id) === String(id));
    if (!o) return;

    // escolha feita no cabeçalho também atualiza o cabeçalho; no modal de importação só vale para a importação
    if (opContexto !== 'import') {
        const cabecalho = document.getElementById('operacao_entrada_id');
        if (cabecalho) {
            cabecalho.value = o.id;
            const label = document.getElementById('operacao_entrada_label');
            label.textContent = opRotulo(o);
            label.className = 'text-gray-800';
        }
    }

    opAplicarNoImport(o);
    fecharModalOperacao();
}

function opMostrarCadastro() {
    ['op-cfop', 'op-cfop-st', 'op-descricao'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('op-movimenta').checked = true;
    document.getElementById('op-erro').classList.add('hidden');

    document.getElementById('op-view-lista').classList.add('hidden');
    document.getElementById('op-view-cadastro').classList.remove('hidden');
    document.getElementById('op-cfop').focus();
}

async function opSalvar() {
    const erro = document.getElementById('op-erro');
    const btn = document.getElementById('op-btn-salvar');
    erro.classList.add('hidden');

    const cfop = document.getElementById('op-cfop').value.trim();
    const cfopSt = document.getElementById('op-cfop-st').value.trim();
    const descricao = document.getElementById('op-descricao').value.trim();

    let mensagem = null;
    if (!/^[12]\d{3}$/.test(cfop)) mensagem = 'Informe o CFOP com 4 dígitos, começando por 1 ou 2.';
    else if (cfopSt && !/^[12]\d{3}$/.test(cfopSt)) mensagem = 'O CFOP de ST deve ter 4 dígitos, começando por 1 ou 2.';
    else if (!descricao) mensagem = 'Informe a natureza da operação.';

    if (mensagem) { erro.textContent = mensagem; erro.classList.remove('hidden'); return; }

    btn.disabled = true;

    try {
        const resp = await fetch(@json(route('entradas-nota.operacoes.store')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                cfop,
                cfop_st: cfopSt || null,
                descricao,
                movimenta_estoque: document.getElementById('op-movimenta').checked,
            }),
        });

        const dados = (resp.headers.get('content-type') || '').includes('application/json') ? await resp.json() : null;

        if (!resp.ok) {
            if (resp.status === 419) throw new Error('Sua sessão expirou. Recarregue a página (F5) e tente de novo.');
            if (dados?.errors) throw new Error(Object.values(dados.errors).flat().join('\n'));
            throw new Error(dados?.message ?? `O servidor retornou erro ${resp.status}.`);
        }

        opLista.push(dados);
        opSelecionar(dados.id);
    } catch (e) {
        erro.textContent = e.message;
        erro.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}
</script>