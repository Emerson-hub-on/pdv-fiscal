@extends('layouts.app')

@section('titulo', 'Pedidos')
@section('body-class', 'conteudo-largo')

@section('conteudo')
@php
    $podeAlterar = auth()->user()?->podeAlterar('comandas');
    $colunas = [
        'preparo'   => ['titulo' => 'Em preparo',        'borda' => 'border-t-amber-400', 'ponto' => 'bg-amber-400'],
        'pronto'    => ['titulo' => 'Pronto / A caminho', 'borda' => 'border-t-blue-500',  'ponto' => 'bg-blue-500'],
        'concluido' => ['titulo' => 'Concluído',         'borda' => 'border-t-green-500', 'ponto' => 'bg-green-500'],
    ];
@endphp

    <x-form.cabecalho solto rotulo="Comandas · Pedidos" titulo="Lista de Pedidos">
        <span class="inline-flex items-center gap-2 text-xs text-slate-300">
            <span class="relative flex w-2.5 h-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                <span class="relative inline-flex rounded-full w-2.5 h-2.5 bg-green-500"></span>
            </span>
            Atualiza sozinha
        </span>
        <button type="button" id="btn-som" onclick="alternarSom()"
                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-medium text-amber-50 ring-1 ring-amber-50/25 hover:bg-amber-50/10">
        </button>
        <button type="button" onclick="Impressoras.abrir()" title="Impressoras" aria-label="Impressoras"
                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-medium text-amber-50 ring-1 ring-amber-50/25 hover:bg-amber-50/10">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
            <span id="btn-impressoras-rotulo">Impressoras</span>
        </button>
    </x-form.cabecalho>

    {{-- Colunas com altura fixa (ocupam a tela até o rodapé, com ou sem pedido); a lista rola por dentro --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach ($colunas as $chave => $coluna)
            <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 border-t-4 {{ $coluna['borda'] }} flex flex-col h-[32rem] lg:h-[calc(100vh-11rem)] min-h-[28rem]">
                <header class="flex items-center gap-2 px-4 py-3">
                    <span class="w-2.5 h-2.5 rounded-full {{ $coluna['ponto'] }}"></span>
                    <h2 class="font-semibold text-slate-800">{{ $coluna['titulo'] }}</h2>
                    <span class="ml-auto rounded-full bg-slate-100 text-slate-500 px-2.5 py-0.5 text-xs font-semibold" data-total="{{ $chave }}">0</span>
                </header>
                <div class="flex-1 min-h-0 px-3 pb-3 flex flex-col gap-3 overflow-y-auto" data-coluna="{{ $chave }}"></div>
            </section>
        @endforeach
    </div>

    {{-- Aviso de pedido novo / erro --}}
    <div id="aviso-pedidos" class="hidden fixed right-4 top-16 z-50 max-w-sm rounded-xl bg-[#10284A] text-amber-50 px-4 py-3 text-sm font-medium shadow-2xl"></div>

    {{-- Detalhes do pedido (abre clicando no cartão) --}}
    <div id="modal-detalhe" class="fixed inset-0 z-40 bg-black/50 hidden items-center justify-center p-4"
         onclick="if (event.target === this) fecharDetalhe()">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl max-h-[90vh] flex flex-col">
            <div class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-slate-900 text-lg" data-detalhe="titulo"></h2>
                        <span data-detalhe="selo"></span>
                    </div>
                    <p class="text-sm text-slate-500 truncate" data-detalhe="sub"></p>
                </div>
                <button type="button" onclick="fecharDetalhe()" aria-label="Fechar"
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4" data-detalhe="corpo"></div>
            <div class="hidden px-6 py-4 border-t border-slate-100" data-detalhe="acoes-wrap"><div class="flex items-center gap-2" data-detalhe="acoes"></div></div>
        </div>
    </div>

    @include('comandas.pedidos._impressoras', ['podeAlterar' => $podeAlterar])
    @include('comandas.mesas._relogio')
    @include('comandas._fechar-conta', ['formas' => $formas])

<script>
    const STATUS_PEDIDO = @json(\App\Models\ComandaPedido::STATUS);
    const PODE_ALTERAR = @json((bool) $podeAlterar);
    const URL_DADOS = @json(route('comanda-pedidos.dados'));
    const URL_STATUS = @json(route('comanda-pedidos.status', ['pedido' => '__ID__']));
    const URL_CONTA = @json(route('comanda-pedidos.conta', ['comanda' => '__ID__']));
    const URL_FECHAR = @json(route('comanda-pedidos.conta.fechar', ['comanda' => '__ID__']));
    const CSRF_PEDIDOS = @json(csrf_token());

    let dados = @json($dados);
    let ultimoId = dados.ultimo_id;
    let detalheAberto = null;  // pedido com a janela de detalhes aberta (sobrevive à atualização)
    let somLigado = true;
    try { somLigado = localStorage.getItem('pedidos.som') !== '0'; } catch (e) {}

    const moeda = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dataHora = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';

    // ===== Desenho =====
    function cartao(p) {
        const finalizado = ['concluido', 'cancelado'].includes(p.status);

        const linhaTempo = (rotulo, iso, cor = 'text-slate-500', comRelogio = false) => iso ? `
            <p class="flex items-center gap-1.5 text-xs ${cor}">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                ${rotulo}: ${escHtml(dataHora(iso))}${comRelogio ? ` · <span class="font-semibold" data-desde="${escHtml(iso)}"></span>` : ''}
            </p>` : '';

        // A coluna já diz a situação: no cartão fica só a próxima ação (Em preparo → Pronto → Entregue)
        const proxima = { preparo: ['pronto', 'Marcar como pronto', 'bg-blue-600 hover:bg-blue-700'],
                          pronto: ['entregue', 'Marcar como entregue', 'bg-indigo-600 hover:bg-indigo-700'] }[p.status];
        // Cancelar à esquerda e próxima ação à direita (pedido ainda não concluído nem cancelado)
        const botoes = PODE_ALTERAR && !finalizado ? `
            <div class="mt-3 flex items-center gap-2">
                <button type="button" onclick="event.stopPropagation(); cancelarPedido(${p.id})"
                        class="rounded-xl px-3.5 py-2 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50 transition">Cancelar</button>
                ${proxima ? `<button type="button" onclick="event.stopPropagation(); mudarStatus(${p.id}, '${proxima[0]}')"
                        class="ml-auto rounded-xl ${proxima[2]} text-white px-3.5 py-2 text-sm font-semibold transition">${proxima[1]} →</button>` : ''}
            </div>` : '';

        // Situação que a coluna não mostra sozinha (entregue dentro de "Pronto / A caminho"; cancelado em "Concluído")
        const selo = ['entregue', 'cancelado'].includes(p.status)
            ? `<span class="inline-block mt-2 rounded-lg px-2 py-0.5 text-xs font-semibold ${STATUS_PEDIDO[p.status].selo}">${escHtml(STATUS_PEDIDO[p.status].rotulo)}</span>` : '';
        // Impressora não respondeu: avisa no cartão (o detalhe mostra o motivo e reimprime)
        const seloImpressao = p.impressao_erro && !finalizado
            ? `<span class="inline-flex items-center gap-1 mt-2 ml-1 rounded-lg bg-red-50 text-red-700 px-2 py-0.5 text-xs font-semibold">⚠ Falha na impressão</span>` : '';

        return `
            <article role="button" tabindex="0" onclick="abrirDetalhe(${p.id})" onkeydown="if (event.key === 'Enter') abrirDetalhe(${p.id})"
                     class="cursor-pointer rounded-xl ring-1 ring-slate-200 bg-white p-4 hover:ring-[#10284A]/40 hover:shadow-sm transition ${p.status === 'cancelado' ? 'opacity-70' : ''}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-900">Pedido #${p.id}</h3>
                        ${p.cliente ? `<p class="text-sm text-slate-600 truncate">Cliente: ${escHtml(p.cliente)}</p>` : ''}
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 rounded-lg bg-violet-50 text-violet-700 px-2 py-1 text-xs font-semibold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M6 9v10m12-10v10M3 9l2-4h14l2 4"/></svg>
                            ${escHtml(p.mesa || 'Mesa')}
                        </span>
                        ${PODE_ALTERAR && impressoras.length ? `
                            <button type="button" data-imprimir="${p.id}" onclick="event.stopPropagation(); imprimirPedido(${p.id}, null, this)"
                                    title="${p.impresso_em ? 'Reimprimir' : 'Imprimir'} nas impressoras do pedido" aria-label="${p.impresso_em ? 'Reimprimir' : 'Imprimir'} pedido #${p.id}"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center ring-1 transition disabled:opacity-50 disabled:cursor-wait ${p.impressao_erro && !finalizado ? 'text-red-600 ring-red-200 hover:bg-red-50' : 'text-slate-500 ring-slate-200 hover:text-[#10284A] hover:bg-slate-50'}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                            </button>` : ''}
                    </div>
                </div>
                <div class="mt-2 flex flex-col gap-0.5">
                    <p class="text-[11px] text-slate-400">Status time:</p>
                    ${linhaTempo('Pedido', p.enviado_em, 'text-slate-500', p.status === 'preparo')}
                    ${linhaTempo('Pronto', p.pronto_em, 'text-blue-600', p.status === 'pronto')}
                    ${linhaTempo('Entregue', p.entregue_em, 'text-indigo-600')}
                    ${linhaTempo('Concluído', p.concluido_em, 'text-green-700')}
                    ${linhaTempo('Cancelado', p.cancelado_em, 'text-red-600')}
                </div>
                <p class="mt-2 text-lg font-bold text-green-600">${escHtml(moeda(p.total))}</p>
                ${selo}${seloImpressao}
                ${botoes}
                <p class="mt-2 text-xs font-medium text-slate-400">Clique para ver os detalhes</p>
            </article>`;
    }

    function desenhar() {
        for (const [coluna, pedidos] of Object.entries(dados.colunas)) {
            const el = document.querySelector(`[data-coluna="${coluna}"]`);
            el.innerHTML = pedidos.length
                ? pedidos.map(cartao).join('')
                : '<p class="py-10 text-center text-sm text-slate-400">Nenhum pedido.</p>';
            document.querySelector(`[data-total="${coluna}"]`).textContent = dados.totais[coluna];
        }
        relogioMesas.atualizar();
        if (detalheAberto !== null) desenharDetalhe();
    }

    // ===== Janela de detalhes do pedido (clicando no cartão) =====
    function abrirDetalhe(id) {
        detalheAberto = id;
        if (!desenharDetalhe()) return;
        const modal = document.getElementById('modal-detalhe');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function fecharDetalhe() {
        detalheAberto = null;
        const modal = document.getElementById('modal-detalhe');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function desenharDetalhe() {
        const p = Object.values(dados.colunas).flat().find(x => x.id === detalheAberto);
        if (!p) { fecharDetalhe(); return false; }
        const el = (n) => document.querySelector(`[data-detalhe="${n}"]`);
        const finalizado = ['concluido', 'cancelado'].includes(p.status);
        const status = STATUS_PEDIDO[p.status] ?? { rotulo: p.status, selo: 'bg-slate-100 text-slate-600' };
        const secao = (titulo) => `<p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2">${titulo}</p>`;

        el('titulo').textContent = `Pedido #${p.id}`;
        el('sub').textContent = [p.mesa, p.cliente ? 'Cliente: ' + p.cliente : ''].filter(Boolean).join(' · ');
        el('selo').className = `shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold ${status.selo}`;
        el('selo').textContent = status.rotulo;

        // Garçom e horários
        const linha = (rotulo, valor) => valor ? `<div class="flex justify-between gap-3"><span class="text-slate-500">${rotulo}</span><span class="font-medium text-slate-800 text-right">${escHtml(valor)}</span></div>` : '';
        const info = `
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm flex flex-col gap-1">
                ${linha('Garçom', p.garcom || '—')}
                ${linha('Pedido', dataHora(p.enviado_em))}
                ${linha('Pronto', dataHora(p.pronto_em))}
                ${linha('Entregue', dataHora(p.entregue_em))}
                ${linha('Concluído', dataHora(p.concluido_em))}
                ${linha('Cancelado', dataHora(p.cancelado_em))}
            </div>`;

        const itens = `
            <div class="divide-y divide-slate-100">
                ${p.itens.map(i => `
                    <div class="py-2.5 flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="text-slate-900"><span class="font-bold">${i.quantidade}×</span> ${escHtml(i.nome)}</p>
                            <p class="text-xs text-slate-400">${escHtml(moeda(i.preco_unitario))} cada</p>
                            ${i.observacao ? `<p class="mt-0.5 text-xs text-amber-700">Obs.: ${escHtml(i.observacao)}</p>` : ''}
                        </div>
                        <span class="shrink-0 font-semibold text-slate-800">${escHtml(moeda(i.subtotal))}</span>
                    </div>`).join('')}
            </div>
            <div class="mt-1 pt-2 border-t-2 border-slate-200 flex justify-between items-center">
                <span class="font-semibold text-slate-600">Total do pedido</span>
                <span class="text-lg font-bold text-[#10284A]">${escHtml(moeda(p.total))}</span>
            </div>`;

        // Pagamento: só aparece depois que o pedido é finalizado (o cliente paga a conta da mesa no final)
        let pagamento;
        if (p.status === 'cancelado') {
            pagamento = '<p class="text-sm text-slate-400">Pedido cancelado: ficou fora da conta da mesa.</p>';
        } else if (p.status === 'concluido' && p.pagamentos.length) {
            pagamento = (p.conta_pedidos > 1
                    ? `<p class="text-xs text-slate-500 mb-2">Este pedido (${escHtml(moeda(p.total))}) foi pago junto com a conta da ${escHtml(p.mesa || 'mesa')}:
                           ${p.conta_pedidos} pedidos, total <span class="font-semibold text-slate-700">${escHtml(moeda(p.conta_total))}</span>.</p>` : '')
                + `<div class="rounded-xl bg-green-50 ring-1 ring-green-200 px-4 py-3 text-sm flex flex-col gap-1">`
                + p.pagamentos.map(x => `<div class="flex justify-between gap-3"><span class="text-slate-700">${escHtml(x.descricao)}</span><span class="font-semibold text-slate-900">${escHtml(moeda(x.valor))}</span></div>`).join('')
                + (p.troco > 0 ? `<div class="flex justify-between gap-3 text-slate-500"><span>Troco</span><span>${escHtml(moeda(p.troco))}</span></div>` : '')
                + `</div>`
                + (p.comanda_id && p.conta_pedidos > 1 ? `
                    <button type="button" onclick="verConta(${p.comanda_id})" class="mt-2 text-sm font-semibold text-[#10284A] hover:underline">Ver conta completa →</button>` : '');
        } else {
            pagamento = '<p class="text-sm text-slate-400">—</p>';
        }

        el('corpo').innerHTML = `
            ${info}
            <div class="mt-5">${secao('Itens')}${itens}</div>
            <div class="mt-5">${secao('Forma de pagamento')}${pagamento}</div>
            <div class="mt-5">${secao('Impressão')}${blocoImpressao(p)}</div>`;

        // Ações no rodapé: voltar etapa e fechar a conta da mesa (pedido ainda em aberto)
        const acoes = [];
        if (PODE_ALTERAR && !finalizado && p.status !== 'preparo') {
            acoes.push(`<button type="button" onclick="mudarStatus(${p.id}, 'preparo')"
                class="rounded-xl px-3.5 py-2.5 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Voltar para preparo</button>`);
        }
        if (PODE_ALTERAR && !finalizado && p.comanda_id && p.conta_status === 'aberta') {
            acoes.push(`<button type="button" onclick="fecharDetalhe(); fecharConta(${p.comanda_id})"
                class="ml-auto rounded-xl bg-[#10284A] text-amber-50 px-3.5 py-2.5 text-sm font-semibold hover:bg-[#163a68]">Fechar conta da ${escHtml(p.mesa || 'mesa')}</button>`);
        }
        el('acoes').innerHTML = acoes.join('');
        el('acoes-wrap').classList.toggle('hidden', !acoes.length);
        return true;
    }

    // ===== Impressão do pedido =====
    let impressoras = [];
    window.aoMudarImpressoras = (lista) => {
        impressoras = lista;
        const ativas = lista.filter(i => i.ativo).length;
        document.getElementById('btn-impressoras-rotulo').textContent = ativas ? `Impressoras (${ativas})` : 'Impressoras';
        desenhar(); // mostra/esconde o ícone de impressora nos cartões
    };

    function blocoImpressao(p) {
        const situacao = p.impressao_erro
            ? `<p class="rounded-xl bg-red-50 text-red-700 px-3 py-2 text-sm">Falha na impressão: ${escHtml(p.impressao_erro)}</p>`
            : p.impresso_em
                ? `<p class="text-sm text-slate-600">Impresso em ${escHtml(dataHora(p.impresso_em))}.</p>`
                : `<p class="text-sm text-slate-400">${impressoras.some(i => i.ativo) ? 'Ainda não impresso.' : 'Nenhuma impressora ativa (configure no ícone de impressora no topo).'}</p>`;

        if (!PODE_ALTERAR || !impressoras.length) return situacao;

        return situacao + `
            <div class="mt-2 flex items-center gap-2">
                <select id="detalhe-impressora" class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30">
                    <option value="">Todas as impressoras do pedido</option>
                    ${impressoras.map(i => `<option value="${i.id}">${escHtml(i.nome)}${i.ativo ? '' : ' (inativa)'}</option>`).join('')}
                </select>
                <button type="button" id="detalhe-imprimir" onclick="imprimirPedido(${p.id}, document.getElementById('detalhe-impressora').value || null, this)"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-[#10284A] ring-1 ring-[#10284A]/30 hover:bg-slate-50 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
                    ${p.impresso_em ? 'Reimprimir' : 'Imprimir'}
                </button>
            </div>`;
    }

    // Cartão: ícone de impressora (todas as impressoras do pedido). Detalhe: impressora escolhida na lista.
    async function imprimirPedido(id, impressoraId = null, botao = null) {
        if (botao) botao.disabled = true;
        try {
            const resp = await fetch(@json(route('comanda-pedidos.imprimir', ['pedido' => '__ID__'])).replace('__ID__', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF_PEDIDOS },
                body: JSON.stringify({ impressora_id: impressoraId }),
            });
            if (resp.redirected) return location.reload();
            const r = await resp.json().catch(() => ({}));
            if (!resp.ok) throw new Error(r.message || 'Não foi possível imprimir.');
            dados = r;
            desenhar();
            avisar(r.message, r.erro);
        } catch (e) {
            avisar(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message, true);
            if (botao) botao.disabled = false;
        }
    }

    // Captura (antes do Esc da janela de fechar conta): fecha esta só quando ela é a de cima
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && detalheAberto !== null && document.getElementById('modal-fechar-conta').classList.contains('hidden')) fecharDetalhe();
    }, true);

    // ===== Ações =====
    function avisar(texto, erro = false) {
        const aviso = document.getElementById('aviso-pedidos');
        aviso.textContent = texto;
        aviso.classList.toggle('bg-red-600', erro);
        aviso.classList.toggle('bg-[#10284A]', !erro);
        aviso.classList.remove('hidden');
        clearTimeout(avisar.timer);
        avisar.timer = setTimeout(() => aviso.classList.add('hidden'), 5000);
    }

    // Cancelado vai para a coluna Concluído (com o selo "Cancelado") e sai da conta da mesa
    function cancelarPedido(id) {
        const pedido = Object.values(dados.colunas).flat().find(p => p.id === id);
        const mesa = pedido?.mesa ? ` da ${pedido.mesa}` : '';
        if (!confirm(`Deseja cancelar o pedido #${id}${mesa}?\n\nEle vai para Concluído como cancelado e sai da conta da mesa.`)) return;
        mudarStatus(id, 'cancelado');
    }

    async function mudarStatus(id, status) {
        try {
            const resp = await fetch(URL_STATUS.replace('__ID__', id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF_PEDIDOS },
                body: JSON.stringify({ status }),
            });
            if (resp.redirected) return location.reload();
            const r = await resp.json().catch(() => ({}));
            if (!resp.ok) throw new Error(r.message || 'Não foi possível mudar a situação.');
            dados = r;
            desenhar();
            if (status === 'pronto') avisar(`Pedido #${id} pronto: o garçom foi avisado.`);
        } catch (e) {
            avisar(e.message, true);
        }
    }

    // Conta já paga: abre o resumo de todos os pedidos da mesa com o pagamento (só leitura)
    function verConta(comandaId) {
        FecharConta.abrir({ urlConta: URL_CONTA.replace('__ID__', comandaId), urlFechar: null, aoFechar: null });
    }

    function fecharConta(comandaId) {
        FecharConta.abrir({
            urlConta: URL_CONTA.replace('__ID__', comandaId),
            urlFechar: URL_FECHAR.replace('__ID__', comandaId),
            aoFechar: (r) => {
                dados = r;
                desenhar();
                avisar(`Conta da ${r.conta.mesa} fechada. A mesa está livre.`);
            },
        });
    }

    // ===== Atualização automática e aviso de pedido novo =====
    function bipe() {
        if (!somLigado) return;
        try {
            const ctx = bipe.ctx ??= new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.18].forEach(atraso => {
                const osc = ctx.createOscillator();
                const vol = ctx.createGain();
                osc.frequency.value = 880;
                vol.gain.setValueAtTime(0.15, ctx.currentTime + atraso);
                vol.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + atraso + 0.15);
                osc.connect(vol).connect(ctx.destination);
                osc.start(ctx.currentTime + atraso);
                osc.stop(ctx.currentTime + atraso + 0.16);
            });
        } catch (e) {}
    }

    function desenharBotaoSom() {
        document.getElementById('btn-som').textContent = somLigado ? '🔔 Som ligado' : '🔕 Som desligado';
    }
    function alternarSom() {
        somLigado = !somLigado;
        try { localStorage.setItem('pedidos.som', somLigado ? '1' : '0'); } catch (e) {}
        desenharBotaoSom();
        if (somLigado) bipe();
    }

    async function atualizar() {
        if (document.hidden) return;
        try {
            const resp = await fetch(URL_DADOS, { headers: { Accept: 'application/json' } });
            if (resp.redirected) return location.reload();
            if (!resp.ok) return;
            const novos = await resp.json();
            const chegaram = novos.colunas.preparo.filter(p => p.id > ultimoId);
            ultimoId = Math.max(ultimoId, novos.ultimo_id);
            dados = novos;
            desenhar();
            if (chegaram.length) {
                bipe();
                avisar(chegaram.length === 1
                    ? `Novo pedido #${chegaram[0].id} — ${chegaram[0].mesa ?? ''}`
                    : `${chegaram.length} pedidos novos chegaram.`);
            }
        } catch (e) { /* sem conexão: tenta de novo no próximo ciclo */ }
    }

    setInterval(atualizar, 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) atualizar(); });
    desenharBotaoSom();
    desenhar();
    Impressoras.carregar().catch(() => {}); // para o "Reimprimir" e o contador no ícone
</script>
@endsection
