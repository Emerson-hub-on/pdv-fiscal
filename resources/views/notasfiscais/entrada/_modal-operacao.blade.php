<div id="modal-operacao-entrada" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[65]">
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        {{-- Aviso enquanto busca a operação no banco / monta a tabela --}}
        <div id="op-carregando" class="hidden absolute inset-0 z-20 rounded-xl bg-white/85 flex-col items-center justify-center gap-3">
            <svg class="w-9 h-9 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p id="op-carregando-texto" class="text-sm font-medium text-gray-700">Buscando informações no banco...</p>
        </div>

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
                        <th class="py-2 px-3 w-24"></th>
                    </tr>
                </thead>
                <tbody id="op-linhas"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar. O primeiro CFOP vale para compras dentro do estado e o segundo para outros estados.</p>
        </div>

        {{-- Cadastro / edição: natureza + tabela de conversão (cada CFOP x cada CST/CSOSN) --}}
        <div id="op-view-cadastro" class="hidden flex-1 min-h-0 flex flex-col">
            <div class="px-6 pt-5 pb-3 border-b border-gray-200">
                <h3 id="op-cadastro-titulo" class="text-base font-semibold text-gray-800 mb-3">Novo CFOP de entrada</h3>

                <div class="flex items-end gap-4 mb-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Natureza da operação <span class="text-red-500">*</span></label>
                        <input type="text" id="op-descricao" maxlength="60" placeholder="Ex.: Compra para comercialização"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 pb-2 whitespace-nowrap"
                           title="Desmarque para uso e consumo, ativo imobilizado etc.: a entrada não soma no estoque nem altera o custo.">
                        <input type="checkbox" id="op-movimenta" checked class="w-4 h-4 text-blue-600 rounded">
                        Movimenta estoque
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
                    <input type="text" id="op-filtro" placeholder="Filtrar por CFOP ou CST/CSOSN..." autocomplete="off"
                           class="w-56 border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <span class="flex items-center gap-1.5">
                        Preencher CFOP de entrada:
                        <input type="text" id="op-preencher" maxlength="4" placeholder="1102"
                               class="w-14 border border-gray-300 rounded px-1.5 py-1 font-mono outline-none focus:ring-2 focus:ring-blue-500">
                        com ST:
                        <input type="text" id="op-preencher-st" maxlength="4" placeholder="1403"
                               class="w-14 border border-gray-300 rounded px-1.5 py-1 font-mono outline-none focus:ring-2 focus:ring-blue-500">
                    </span>
                    <span class="flex items-center gap-1.5 ml-auto">
                        <input type="text" id="op-novo-cfop" maxlength="4" placeholder="5405"
                               class="w-14 border border-gray-300 rounded px-1.5 py-1 font-mono outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" onclick="opAdicionarCfop()"
                                class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-2.5 py-1 rounded-lg font-semibold transition whitespace-nowrap">
                            + Adicionar CFOP
                        </button>
                    </span>
                </div>
            </div>

            <div class="px-6 overflow-y-auto flex-1 min-h-0">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white z-10">
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                            <th class="py-2 px-1.5 w-24">CFOP origem</th>
                            <th class="py-2 px-1.5">CST/CSOSN origem</th>
                            <th class="py-2 px-1.5 w-24">CFOP entrada</th>
                            <th class="py-2 px-1.5"><span data-rotulo-cst></span> entrada</th>
                            <th class="py-2 px-1.5 w-16 op-col-credito">Crédito</th>
                            <th class="py-2 px-1.5 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="op-tabela"></tbody>
                </table>
                <datalist id="op-cfops-entrada"></datalist>
            </div>

            <div class="px-6 py-3 border-t border-gray-200">
                <p class="text-xs text-gray-400 mb-2">
                    O <span data-rotulo-cst></span> de entrada vem da tabela de conversão do seu regime; linhas alteradas ficam em amarelo.
                    O × remove o CFOP inteiro. 5xxx (dentro do estado) entra como 1xxx e 6xxx (fora do estado) como 2xxx.
                </p>
                <p id="op-erro" class="hidden text-sm text-red-600 mb-2 whitespace-pre-line max-h-24 overflow-y-auto"></p>
                <div class="flex items-center justify-end gap-2">
                    <span id="op-contador" class="text-xs text-gray-400 mr-auto"></span>
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
</div>

@php
    // CFOPs que uma operação nova já traz (os mesmos que a conversão automática criava)
    $opOrigensPadrao = collect(\App\Models\CfopEntradaConversao::ORIGENS)
        ->flatMap(fn ($cfops, $grupo) => collect($cfops)->map(fn ($c) => ['cfop' => $c, 'st' => $grupo === 'st']))
        ->unique('cfop')->sortBy('cfop')->values();

    $opDados = [
        'regime'       => $fiscal['regime'],
        'origens'      => $fiscal['origensCst'],
        'entrada'      => $fiscal['opcoesCst'],
        'cfopsEntrada' => $fiscal['cfopsEntrada'],
        'csts'         => $fiscal['csts'],
        'padrao'       => $opOrigensPadrao,
    ];
@endphp

<script>
let opLista = @json($operacoes);
let opFiltrada = [];
let opIndice = 0;
let opContexto = 'cabecalho';
let opEditandoId = null;        // null = cadastrando; id = editando
let opLinhas = [];              // tabela de conversão em edição
const OP_DADOS = @json($opDados);
const OP_ROTULO_CST = OP_DADOS.regime === 'normal' ? 'CST' : 'CSOSN';
const OP_URL_ATUALIZAR = @json(route('entradas-nota.operacoes.update', '__ID__'));

document.querySelectorAll('[data-rotulo-cst]').forEach(el => el.textContent = OP_ROTULO_CST);
document.querySelectorAll('.op-col-credito').forEach(el => el.classList.toggle('hidden', OP_DADOS.regime !== 'normal'));
document.getElementById('op-cfops-entrada').innerHTML = OP_DADOS.cfopsEntrada
    .map(c => `<option value="${escHtml(c.codigo)}">${escHtml(c.codigo)} — ${escHtml(c.descricao)}</option>`).join('');

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
        tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-sm text-gray-400">Nenhum CFOP encontrado.</td></tr>';
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
                <td class="py-3 px-3 text-right">
                    <button type="button" onclick="event.stopPropagation(); opMostrarEdicao(${o.id})"
                            class="text-xs font-medium px-2.5 py-1 rounded-lg border transition ${dest ? 'border-slate-500 text-slate-200 hover:bg-slate-700' : 'border-gray-300 text-gray-600 hover:bg-gray-100'}">
                        Editar
                    </button>
                </td>
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
        if (typeof renderizarGrid === 'function') renderizarGrid();
    }

    opAplicarNoImport(o);
    fecharModalOperacao();
}

// ---------------------------------------------------------------
// Cadastro / edição com a tabela de conversão (cada CFOP x cada CST/CSOSN de origem)
// ---------------------------------------------------------------
// Todas as origens possíveis, na ordem da tabela: CST 00..90 e CSOSN 101..900
const OP_ORIGENS = [
    ...OP_DADOS.origens.cst.map(o => ({ chave: `cst:${o.codigo}`, rotulo: `CST ${o.codigo}`, descricao: o.descricao })),
    ...OP_DADOS.origens.csosn.map(o => ({ chave: `csosn:${o.codigo}`, rotulo: `CSOSN ${o.codigo}`, descricao: o.descricao })),
];
const OP_ROTULO_ORIGEM = Object.fromEntries(OP_ORIGENS.map(o => [o.chave, o]));

function opPadraoCst(origem) {
    const p = OP_DADOS.csts?.[origem];
    return { codigo: p?.cst ?? '', credito: !!p?.credito };
}

function opLinhaPadrao(cfopOrigem, origem, cfopEntrada) {
    const p = opPadraoCst(origem);
    return { cfop_origem: cfopOrigem, origem, cfop_entrada: cfopEntrada || '', codigo_entrada: p.codigo, gera_credito: p.credito };
}

// Monta a tabela inteira da operação: CFOP base + exceções gravadas; o resto vem do padrão do regime
function opExpandir(base, regras) {
    const excecoes = Object.fromEntries((regras ?? []).map(r => [`${r.cfop_origem}|${r.origem}`, r]));
    const cfops = [...new Set([...Object.keys(base ?? {}), ...(regras ?? []).map(r => r.cfop_origem)])].sort();

    return cfops.flatMap(cfop => OP_ORIGENS.map(o => {
        const r = excecoes[`${cfop}|${o.chave}`];
        return r ? { ...r, gera_credito: !!r.gera_credito } : opLinhaPadrao(cfop, o.chave, base?.[cfop]);
    }));
}

function opAbrirFormulario(o) {
    opEditandoId = o?.id ?? null;
    opLinhas = o
        ? opExpandir(o.base, o.regras)
        : opExpandir(Object.fromEntries(OP_DADOS.padrao.map(p => [p.cfop, ''])), []);

    document.getElementById('op-descricao').value = o?.descricao ?? '';
    document.getElementById('op-movimenta').checked = o ? !!o.movimenta_estoque : true;
    ['op-preencher', 'op-preencher-st', 'op-filtro', 'op-novo-cfop'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('op-erro').classList.add('hidden');
    document.getElementById('op-cadastro-titulo').textContent = o ? 'Editar CFOP de entrada' : 'Novo CFOP de entrada';
    document.getElementById('op-btn-salvar').textContent = o ? 'Salvar alterações' : 'Cadastrar e selecionar';

    document.getElementById('op-view-lista').classList.add('hidden');
    document.getElementById('op-view-cadastro').classList.remove('hidden');
    opAbertos = new Set();
    opTabelaRender();
    document.getElementById(o ? 'op-descricao' : 'op-preencher').focus();
}

// Aviso "buscando..." por cima do modal; espera o navegador desenhá-lo antes de seguir
function opCarregando(mostrar, texto = 'Buscando informações no banco...') {
    const aviso = document.getElementById('op-carregando');
    document.getElementById('op-carregando-texto').textContent = texto;
    aviso.classList.toggle('hidden', !mostrar);
    aviso.classList.toggle('flex', mostrar);
    return new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
}

async function opMostrarCadastro() {
    await opCarregando(true, 'Montando a tabela de conversão...');
    try {
        opAbrirFormulario(null);
    } finally {
        opCarregando(false);
    }
}

// Editar: busca a operação no banco (pega alterações feitas em outro computador) e abre a tabela
async function opMostrarEdicao(id) {
    await opCarregando(true);
    try {
        const resp = await fetch(@json(route('entradas-nota.operacoes.show', '__ID__')).replace('__ID__', id), {
            headers: { 'Accept': 'application/json' },
        });
        const dados = (resp.headers.get('content-type') || '').includes('application/json') ? await resp.json() : null;
        if (!resp.ok || !dados) throw new Error(dados?.message || `O servidor retornou erro ${resp.status}.`);

        const i = opLista.findIndex(x => String(x.id) === String(dados.id));
        if (i >= 0) opLista[i] = dados;
        opAtualizarRegrasDaGrade(dados);
        opAbrirFormulario(dados);
    } catch (e) {
        alert('Não foi possível carregar a operação: ' + e.message);
    } finally {
        opCarregando(false);
    }
}

function opOpcoesEntrada(valor) {
    return '<option value="">— escolha —</option>' + OP_DADOS.entrada.map(o =>
        `<option value="${o.codigo}" ${o.codigo === valor ? 'selected' : ''}>${o.codigo} — ${opEsc(o.descricao)}</option>`).join('');
}

// Linha diferente do padrão do regime (CST/CSOSN de entrada ou crédito): fica destacada
function opAlterada(l) {
    const p = opPadraoCst(l.origem);
    return l.codigo_entrada !== p.codigo || (OP_DADOS.regime === 'normal' && !!l.gera_credito !== p.credito);
}

let opAbertos = new Set();      // CFOPs com as linhas de CST/CSOSN abertas

// CFOP de entrada mais usado no grupo (é o que vira a regra base ao salvar)
function opCfopDoGrupo(linhas) {
    const contagem = {};
    linhas.forEach(l => { contagem[l.cfop_entrada] = (contagem[l.cfop_entrada] || 0) + 1; });
    return Object.keys(contagem).sort((a, b) => contagem[b] - contagem[a])[0] ?? '';
}

function opAlternarCfop(cfop) {
    opAbertos.has(cfop) ? opAbertos.delete(cfop) : opAbertos.add(cfop);
    opTabelaRender();
}

function opTabelaRender() {
    const tbody = document.getElementById('op-tabela');
    const normal = OP_DADOS.regime === 'normal';
    const termo = document.getElementById('op-filtro').value.trim().toLowerCase();
    const campo = 'w-full border border-gray-300 rounded px-1.5 py-0.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 bg-white';

    // agrupa por CFOP de origem, guardando o índice de cada linha em opLinhas
    const grupos = [];
    opLinhas.forEach((l, i) => {
        let g = grupos[grupos.length - 1];
        if (!g || g.cfop !== l.cfop_origem) grupos.push(g = { cfop: l.cfop_origem, linhas: [] });
        g.linhas.push({ l, i });
    });

    document.getElementById('op-contador').textContent = `${grupos.length} CFOP(s) · ${opLinhas.length} linhas`;

    // filtro: por CFOP mostra o grupo; por CST/CSOSN abre os grupos só com as linhas que batem
    const casaCst = l => OP_ROTULO_ORIGEM[l.origem]?.rotulo.toLowerCase().includes(termo);
    const visiveis = grupos
        .map(g => {
            if (!termo || g.cfop.includes(termo)) return { ...g, abrir: opAbertos.has(g.cfop), detalhes: g.linhas };
            const batem = g.linhas.filter(({ l }) => casaCst(l));
            return batem.length ? { ...g, abrir: true, detalhes: batem } : null;
        })
        .filter(Boolean);

    if (!visiveis.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-4 text-center text-xs text-gray-400">${opLinhas.length ? 'Nenhum CFOP para o filtro.' : 'Nenhum CFOP. Use "+ Adicionar CFOP".'}</td></tr>`;
        return;
    }

    tbody.innerHTML = visiveis.map(g => {
        const linhas = g.linhas.map(x => x.l);
        const cfopGrupo = opCfopDoGrupo(linhas);
        const alteradas = linhas.filter(opAlterada).length;
        const variado = linhas.some(l => l.cfop_entrada !== cfopGrupo);
        const c = opEsc(g.cfop);

        const cabecalho = `
        <tr class="border-t-2 border-gray-300 bg-gray-50 hover:bg-gray-100 cursor-pointer select-none" onclick="opAlternarCfop('${c}')">
            <td class="py-2 px-1.5 font-mono font-semibold text-gray-800 whitespace-nowrap">
                <span class="inline-block w-4 text-gray-400">${g.abrir ? '▾' : '▸'}</span>${c}
            </td>
            <td class="py-2 px-1.5 text-xs text-gray-500 whitespace-nowrap">
                ${g.linhas.length} CST/CSOSN
                <span data-alteradas="${c}" class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-medium ${alteradas ? '' : 'hidden'}">${alteradas} alterada(s)</span>
            </td>
            <td class="py-1.5 px-1.5" onclick="event.stopPropagation()">
                <input type="text" maxlength="4" list="op-cfops-entrada" value="${opEsc(cfopGrupo)}" data-grupo="${c}"
                       title="CFOP de entrada de todas as linhas deste CFOP" class="${campo} font-mono font-semibold">
            </td>
            <td class="py-2 px-1.5 text-xs text-gray-400" colspan="${normal ? 2 : 1}">
                <span data-variado="${c}" class="text-amber-700 ${variado ? '' : 'hidden'}">CFOP de entrada diferente em algumas linhas · </span>${g.abrir ? 'clique para fechar' : 'clique para ver cada CST/CSOSN'}
            </td>
            <td class="py-2 px-1.5 text-right" onclick="event.stopPropagation()">
                <button type="button" onclick="opRemoverCfop('${c}')" title="Remover o CFOP ${c} (todas as linhas)"
                        class="text-gray-400 hover:text-red-600 text-lg leading-none transition">&times;</button>
            </td>
        </tr>`;

        if (!g.abrir) return cabecalho;

        return cabecalho + g.detalhes.map(({ l, i }) => {
            const o = OP_ROTULO_ORIGEM[l.origem];
            return `
            <tr class="border-t border-gray-100 ${opAlterada(l) ? 'bg-amber-50' : ''}">
                <td class="py-0.5 px-1.5"></td>
                <td class="py-0.5 px-1.5 pl-4 font-mono text-gray-700 whitespace-nowrap" title="${opEsc(o?.descricao)}">${opEsc(o?.rotulo ?? l.origem)}</td>
                <td class="py-0.5 px-1.5">
                    <input type="text" maxlength="4" list="op-cfops-entrada" value="${opEsc(l.cfop_entrada)}" data-i="${i}" data-campo="cfop_entrada"
                           class="${campo} font-mono">
                </td>
                <td class="py-0.5 px-1.5">
                    <select data-i="${i}" data-campo="codigo_entrada" class="${campo}">${opOpcoesEntrada(l.codigo_entrada)}</select>
                </td>
                <td class="py-0.5 px-1.5 text-center ${normal ? '' : 'hidden'}">
                    <input type="checkbox" data-i="${i}" data-campo="gera_credito" ${l.gera_credito ? 'checked' : ''} class="w-4 h-4 text-blue-600 rounded">
                </td>
                <td></td>
            </tr>`;
        }).join('');
    }).join('');
}

// Edição dos campos: grava no estado sem redesenhar (para não perder o foco)
document.getElementById('op-tabela').addEventListener('input', (e) => {
    // CFOP de entrada do grupo: vale para todas as linhas daquele CFOP
    const grupo = e.target.dataset.grupo;
    if (grupo) {
        const valor = e.target.value.trim();
        opLinhas.forEach((l, i) => {
            if (l.cfop_origem !== grupo) return;
            l.cfop_entrada = valor;
            const campoLinha = document.querySelector(`#op-tabela [data-i="${i}"][data-campo="cfop_entrada"]`);
            if (campoLinha) campoLinha.value = valor;
        });
        return;
    }

    const i = e.target.dataset.i;
    const campo = e.target.dataset.campo;
    if (i === undefined || !campo) return;

    const linha = opLinhas[i];
    linha[campo] = campo === 'gera_credito' ? e.target.checked : e.target.value.trim();
    e.target.closest('tr').classList.toggle('bg-amber-50', opAlterada(linha));
});

// ao sair de um campo, atualiza só o resumo do grupo (sem redesenhar, para o Tab seguir normal)
function opAtualizarResumo(cfop) {
    const linhas = opLinhas.filter(l => l.cfop_origem === cfop);
    const cfopGrupo = opCfopDoGrupo(linhas);
    const alteradas = linhas.filter(opAlterada).length;
    const sel = v => document.querySelector(`#op-tabela [${v}="${cfop}"]`);

    const badge = sel('data-alteradas');
    if (badge) { badge.textContent = `${alteradas} alterada(s)`; badge.classList.toggle('hidden', !alteradas); }
    sel('data-variado')?.classList.toggle('hidden', !linhas.some(l => l.cfop_entrada !== cfopGrupo));
    const grupo = sel('data-grupo');
    if (grupo && document.activeElement !== grupo) grupo.value = cfopGrupo;
}

document.getElementById('op-tabela').addEventListener('change', (e) => {
    const cfop = e.target.dataset.grupo ?? opLinhas[e.target.dataset.i]?.cfop_origem;
    if (cfop) opAtualizarResumo(cfop);
});

document.getElementById('op-filtro').addEventListener('input', opTabelaRender);

function opAdicionarCfop() {
    const campo = document.getElementById('op-novo-cfop');
    const cfop = campo.value.trim();
    const erro = document.getElementById('op-erro');

    if (!/^[56]\d{3}$/.test(cfop)) {
        erro.textContent = 'Informe o CFOP do fornecedor com 4 dígitos, começando por 5 ou 6 (ex.: 5405).';
        erro.classList.remove('hidden');
        campo.focus();
        return;
    }
    if (opLinhas.some(l => l.cfop_origem === cfop)) {
        erro.textContent = `O CFOP ${cfop} já está na tabela.`;
        erro.classList.remove('hidden');
        opAbertos.add(cfop);
        document.getElementById('op-filtro').value = cfop;
        opTabelaRender();
        return;
    }

    erro.classList.add('hidden');
    opLinhas = [...opLinhas, ...OP_ORIGENS.map(o => opLinhaPadrao(cfop, o.chave, opCfopSugerido(cfop)))]
        .sort((a, b) => a.cfop_origem.localeCompare(b.cfop_origem));
    campo.value = '';
    opAbertos.add(cfop);
    document.getElementById('op-filtro').value = cfop;
    opTabelaRender();
}

function opRemoverCfop(cfop) {
    if (!confirm(`Remover o CFOP ${cfop} desta operação (todas as ${OP_ORIGENS.length} linhas)?`)) return;
    opLinhas = opLinhas.filter(l => l.cfop_origem !== cfop);
    opTabelaRender();
}

// CFOP de entrada pelo atalho "Preencher": 5xxx -> 1xxx, 6xxx -> 2xxx; CFOP de ST usa o "com ST"
function opCfopSugerido(cfopOrigem) {
    const cfop = document.getElementById('op-preencher').value.trim();
    const cfopSt = document.getElementById('op-preencher-st').value.trim();
    if (!/^[12]\d{3}$/.test(cfop)) return '';

    const ehSt = /^[56]4/.test(cfopOrigem) || OP_DADOS.padrao.some(p => p.cfop === cfopOrigem && p.st);
    const sufixo = (ehSt && /^[12]\d{3}$/.test(cfopSt) ? cfopSt : cfop).slice(1);
    return (cfopOrigem[0] === '5' ? '1' : '2') + sufixo;
}

function opPreencherTabela() {
    if (!/^[12]\d{3}$/.test(document.getElementById('op-preencher').value.trim())) return;
    opLinhas.forEach(l => { l.cfop_entrada = opCfopSugerido(l.cfop_origem); });
    opTabelaRender();
}
['op-preencher', 'op-preencher-st'].forEach(id => document.getElementById(id).addEventListener('input', opPreencherTabela));

// depois de editar, atualiza o texto da operação já escolhida no cabeçalho / na importação
function opAtualizarRotulosSelecionados(o) {
    [['operacao_entrada_id', 'operacao_entrada_label'], ['xml-operacao', 'xml-operacao-label']].forEach(([campoId, labelId]) => {
        const campo = document.getElementById(campoId);
        const label = document.getElementById(labelId);
        if (campo && label && String(campo.value) === String(o.id)) label.textContent = opRotulo(o);
    });
}

// A grade da nota calcula a conversão ao vivo: leva para ela a tabela que acabou de ser salva
function opAtualizarRegrasDaGrade(o) {
    if (typeof regrasFiscais === 'undefined') return;

    regrasFiscais.cfops = regrasFiscais.cfops || {};
    regrasFiscais.regras = regrasFiscais.regras || {};
    regrasFiscais.cfops[o.id] = { ...o.base };
    regrasFiscais.regras[o.id] = Object.fromEntries((o.regras ?? []).map(r => [
        `${r.cfop_origem}|${r.origem}`, { cfop: r.cfop_entrada, cst: r.codigo_entrada, credito: !!r.gera_credito },
    ]));

    if (regrasFiscais.descricoes) {
        [...Object.values(o.base ?? {}), ...(o.regras ?? []).map(r => r.cfop_entrada)].forEach(c => {
            if (c && !regrasFiscais.descricoes[c]) regrasFiscais.descricoes[c] = o.descricao;
        });
    }
}

function opValidarTabela() {
    const erros = [];

    opLinhas.forEach(l => {
        const onde = `CFOP ${l.cfop_origem} / ${OP_ROTULO_ORIGEM[l.origem]?.rotulo ?? l.origem}`;
        if (!/^[12]\d{3}$/.test(l.cfop_entrada)) { erros.push(`${onde}: CFOP de entrada com 4 dígitos, começando por 1 ou 2.`); return; }

        const area = l.cfop_origem[0] === '5' ? '1' : '2';
        if (l.cfop_entrada[0] !== area) erros.push(`${onde}: o CFOP de entrada deve começar por ${area} (${area === '1' ? 'dentro' : 'fora'} do estado).`);
        if (!l.codigo_entrada) erros.push(`${onde}: escolha o ${OP_ROTULO_CST} de entrada.`);
    });

    if (!opLinhas.length) erros.push('Inclua ao menos um CFOP na tabela de conversão.');
    if (erros.length > 10) return [...erros.slice(0, 10), `... e mais ${erros.length - 10} linha(s) com problema. Dica: use "Preencher CFOP de entrada".`];
    return erros;
}

async function opSalvar() {
    const erro = document.getElementById('op-erro');
    const btn = document.getElementById('op-btn-salvar');
    erro.classList.add('hidden');

    const descricao = document.getElementById('op-descricao').value.trim();
    const erros = descricao ? opValidarTabela() : ['Informe a natureza da operação.'];

    if (erros.length) { erro.textContent = erros.join('\n'); erro.classList.remove('hidden'); return; }

    const editando = opEditandoId !== null;
    btn.disabled = true;

    try {
        const resp = await fetch(editando ? OP_URL_ATUALIZAR.replace('__ID__', opEditandoId) : @json(route('entradas-nota.operacoes.store')), {
            method: editando ? 'PUT' : 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                descricao,
                movimenta_estoque: document.getElementById('op-movimenta').checked,
                linhas: opLinhas.map(l => ({
                    cfop_origem: l.cfop_origem,
                    origem: l.origem,
                    cfop_entrada: l.cfop_entrada,
                    codigo_entrada: l.codigo_entrada,
                    gera_credito: !!l.gera_credito,
                })),
            }),
        });

        const dados = (resp.headers.get('content-type') || '').includes('application/json') ? await resp.json() : null;

        if (!resp.ok) {
            if (resp.status === 419) throw new Error('Sua sessão expirou. Recarregue a página (F5) e tente de novo.');
            if (dados?.errors) throw new Error(Object.values(dados.errors).flat().join('\n'));
            throw new Error(dados?.message ?? `O servidor retornou erro ${resp.status}.`);
        }

        opAtualizarRegrasDaGrade(dados);

        if (editando) {
            const i = opLista.findIndex(x => String(x.id) === String(dados.id));
            if (i >= 0) opLista[i] = dados;
            opAtualizarRotulosSelecionados(dados);
            if (typeof renderizarGrid === 'function') renderizarGrid();
            opMostrarLista();
        } else {
            opLista.push(dados);
            opSelecionar(dados.id);
        }
    } catch (e) {
        erro.textContent = e.message;
        erro.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}
</script>
