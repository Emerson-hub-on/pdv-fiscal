{{-- Caixa: consulta dos convênios (vendas fiado) em aberto. Filtra por período da venda, nº do cupom e cliente.
     Lê o SQLite do caixa (lista trazida do servidor no Atualizar Caixa + vendas deste caixa ainda não enviadas). --}}
<div id="modal-convenios" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Convênios em aberto</h2>
                <p class="text-xs text-slate-300">Vendas no convênio (fiado) ainda não recebidas. Clique na dívida para dar baixa.</p>
            </div>
            <button type="button" onclick="fecharModalConvenios()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="p-6 flex flex-col gap-4 min-h-0">
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Venda de</label>
                    <input type="date" id="convenios-inicio" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">até</label>
                    <input type="date" id="convenios-fim" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Nº do cupom</label>
                    <input type="text" id="convenios-cupom" inputmode="numeric" placeholder="Ex.: 1234"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Cliente</label>
                    <input type="text" id="convenios-cliente" placeholder="Nome do cliente"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
                </div>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex gap-2">
                    <button type="button" onclick="filtroRapidoConvenios('hoje')" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 hover:ring-[#10284A]/40 hover:text-[#10284A] text-slate-700 transition">Hoje</button>
                    <button type="button" onclick="filtroRapidoConvenios('mes')" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 hover:ring-[#10284A]/40 hover:text-[#10284A] text-slate-700 transition">Este mês</button>
                    <button type="button" onclick="filtroRapidoConvenios('todos')" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 hover:ring-[#10284A]/40 hover:text-[#10284A] text-slate-700 transition">Todos em aberto</button>
                </div>
                <p id="convenios-resumo" class="text-sm text-slate-600"></p>
            </div>

            <div class="border border-gray-200 rounded-lg overflow-y-auto min-h-0 max-h-[50vh]">
                <table class="w-full text-sm">
                    <thead class="bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider sticky top-0">
                        <tr>
                            <th class="text-left px-3 py-2.5">Data</th>
                            <th class="text-left px-3 py-2.5">Cupom</th>
                            <th class="text-left px-3 py-2.5">Cliente</th>
                            <th class="text-left px-3 py-2.5">Vencimento</th>
                            <th class="text-right px-3 py-2.5">Em aberto</th>
                            <th class="text-left px-3 py-2.5">Situação</th>
                        </tr>
                    </thead>
                    <tbody id="linhas-convenios" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{-- Baixa de um convênio no caixa: valor pago (total ou parte) e forma de pagamento --}}
<div id="modal-baixa-convenio" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[55]">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Receber convênio</h2>
                <p id="baixa-convenio-subtitulo" class="text-xs text-slate-300"></p>
            </div>
            <button type="button" onclick="fecharModalBaixaConvenio()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-2 mb-4 text-center">
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-2.5">
                    <p class="text-[11px] text-gray-500 uppercase">Valor da venda</p>
                    <p id="baixa-convenio-valor-venda" class="font-bold text-slate-800"></p>
                </div>
                <div class="rounded-lg bg-[#10284A] p-2.5">
                    <p class="text-[11px] text-slate-300 uppercase">Em aberto</p>
                    <p id="baixa-convenio-saldo" class="font-bold text-amber-50"></p>
                </div>
            </div>

            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Valor pago (R$)</label>
            <input type="number" step="0.01" min="0.01" id="baixa-convenio-valor"
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition text-base font-semibold mb-3">

            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Forma de pagamento</label>
            <div id="baixa-convenio-formas" class="grid grid-cols-2 gap-2 mb-3">
                @foreach (\App\Services\ConvenioService::FORMAS_RECEBIMENTO as $chave => $rotulo)
                    <button type="button" data-forma="{{ $chave }}" onclick="escolherFormaBaixaConvenio('{{ $chave }}')"
                            class="forma-baixa-convenio border-2 border-gray-200 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:border-[#10284A] transition">
                        <span class="opacity-50 text-xs mr-1">{{ $loop->iteration }}</span>{{ $rotulo }}
                    </button>
                @endforeach
            </div>

            <p id="baixa-convenio-info" class="hidden text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5 mb-3"></p>
            <p id="baixa-convenio-erro" class="hidden text-sm text-red-600 mb-3"></p>

            <button type="button" id="btn-confirmar-baixa-convenio" onclick="confirmarBaixaConvenio()"
                    class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-lg font-semibold disabled:opacity-60">
                Confirmar recebimento <span class="opacity-70 text-xs">Enter</span>
            </button>
            <p class="text-xs text-gray-400 mt-3">Teclas 1 a 4 escolhem a forma. O valor recebido entra no fechamento deste caixa.</p>
        </div>
    </div>
</div>

<script>
    const URL_CONVENIOS_ABERTOS = @json(route('vendas.convenios-abertos'));
    let timeoutConvenios;
    const URL_RECEBER_CONVENIO = @json(route('vendas.convenio-receber'));
    let linhasConveniosAtuais = [];
    let convenioEmBaixa = null;
    let formaBaixaConvenio = 'dinheiro';
    let contadorConvenios = 0;

    const SITUACAO_CONVENIO = {
        aberto: ['Em aberto', 'bg-amber-100 text-amber-800'],
        vencido: ['Vencido', 'bg-red-100 text-red-700'],
        nao_sincronizado: ['Não sincronizada', 'bg-slate-200 text-slate-700'],
    };

    function dataIso(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function dataBr(iso) {
        return iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : '—';
    }

    function abrirModalConvenios() {
        if (typeof fecharTodosModais === 'function') fecharTodosModais();
        const modal = document.getElementById('modal-convenios');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('convenios-cupom').value = '';
        document.getElementById('convenios-cliente').value = '';
        filtroRapidoConvenios('todos');
        setTimeout(() => document.getElementById('convenios-cupom').focus(), 100);
    }

    function fecharModalConvenios() {
        const modal = document.getElementById('modal-convenios');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function filtroRapidoConvenios(tipo) {
        const hoje = new Date();
        const inicio = document.getElementById('convenios-inicio');
        const fim = document.getElementById('convenios-fim');

        if (tipo === 'hoje') {
            inicio.value = fim.value = dataIso(hoje);
        } else if (tipo === 'mes') {
            inicio.value = dataIso(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
            fim.value = dataIso(hoje);
        } else {
            inicio.value = fim.value = '';
        }
        carregarConvenios();
    }

    async function carregarConvenios() {
        const minhaBusca = ++contadorConvenios;
        const corpo = document.getElementById('linhas-convenios');
        const params = new URLSearchParams({
            data_inicio: document.getElementById('convenios-inicio').value,
            data_fim: document.getElementById('convenios-fim').value,
            cupom: document.getElementById('convenios-cupom').value.trim(),
            cliente: document.getElementById('convenios-cliente').value.trim(),
        });

        let dados;
        try {
            const resp = await fetch(`${URL_CONVENIOS_ABERTOS}?${params}`, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar os convênios.`);
            dados = await resp.json();
        } catch (e) {
            if (minhaBusca !== contadorConvenios) return;
            corpo.replaceChildren();
            const tr = corpo.insertRow();
            const td = tr.insertCell();
            td.colSpan = 6;
            td.className = 'px-3 py-6 text-center text-red-600';
            td.textContent = e.message;
            return;
        }
        if (minhaBusca !== contadorConvenios) return;

        document.getElementById('convenios-resumo').textContent =
            `${dados.quantidade} venda(s) · em aberto R$ ${Number(dados.total).toFixed(2).replace('.', ',')}`;

        corpo.replaceChildren();
        linhasConveniosAtuais = dados.linhas;
        if (dados.linhas.length === 0) {
            const tr = corpo.insertRow();
            const td = tr.insertCell();
            td.colSpan = 6;
            td.className = 'px-3 py-6 text-center text-gray-400';
            td.textContent = 'Nenhum convênio em aberto com esses filtros. A lista do servidor é atualizada no "Atualizar Caixa".';
            return;
        }

        // Texto do cliente vai como texto, nunca como HTML
        dados.linhas.forEach((l, indice) => {
            const tr = corpo.insertRow();
            tr.className = 'hover:bg-amber-50 cursor-pointer';
            tr.title = 'Clique para dar baixa';
            tr.addEventListener('click', () => abrirModalBaixaConvenio(indice));
            const celula = (texto, cls = '') => {
                const td = tr.insertCell();
                td.className = 'px-3 py-2.5 ' + cls;
                td.textContent = texto;
                return td;
            };
            celula(dataBr(l.data), 'whitespace-nowrap');
            celula(l.numero_nfce ? `${l.numero_nfce}${l.serie_nfce ? ' / ' + l.serie_nfce : ''}` : 'sem nº', 'font-mono');
            celula(l.cliente || '—');
            celula(dataBr(l.vencimento), 'whitespace-nowrap');
            celula('R$ ' + Number(l.saldo).toFixed(2).replace('.', ','), 'text-right font-semibold');
            const [rotulo, cor] = SITUACAO_CONVENIO[l.situacao] ?? [l.situacao, 'bg-slate-100 text-slate-700'];
            const badge = document.createElement('span');
            badge.className = 'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ' + cor;
            badge.textContent = rotulo;
            celula('').appendChild(badge);
        });
    }


    // ---------- Baixa do convênio no caixa ----------
    const moedaConvenio = v => 'R$ ' + Number(v).toFixed(2).replace('.', ',');

    function abrirModalBaixaConvenio(indice) {
        const l = linhasConveniosAtuais[indice];
        if (!l) return;
        convenioEmBaixa = l;

        document.getElementById('baixa-convenio-subtitulo').textContent =
            `${l.cliente || '—'} · cupom ${l.numero_nfce ?? 'sem nº'} · venda de ${dataBr(l.data)}`;
        document.getElementById('baixa-convenio-valor-venda').textContent = moedaConvenio(l.valor);
        document.getElementById('baixa-convenio-saldo').textContent = moedaConvenio(l.saldo);
        document.getElementById('baixa-convenio-erro').classList.add('hidden');
        document.getElementById('btn-confirmar-baixa-convenio').disabled = false;
        const campo = document.getElementById('baixa-convenio-valor');
        campo.value = Number(l.saldo).toFixed(2);
        escolherFormaBaixaConvenio('dinheiro');
        atualizarInfoBaixaConvenio();

        const modal = document.getElementById('modal-baixa-convenio');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => { campo.focus(); campo.select(); }, 100);
    }

    function fecharModalBaixaConvenio() {
        const modal = document.getElementById('modal-baixa-convenio');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        convenioEmBaixa = null;
    }

    function escolherFormaBaixaConvenio(forma) {
        formaBaixaConvenio = forma;
        document.querySelectorAll('.forma-baixa-convenio').forEach(btn => {
            const ativa = btn.dataset.forma === forma;
            btn.classList.toggle('border-[#10284A]', ativa);
            btn.classList.toggle('bg-[#10284A]', ativa);
            btn.classList.toggle('text-white', ativa);
            btn.classList.toggle('border-gray-200', !ativa);
            btn.classList.toggle('text-gray-700', !ativa);
        });
    }

    function atualizarInfoBaixaConvenio() {
        if (!convenioEmBaixa) return;
        const valor = parseFloat(document.getElementById('baixa-convenio-valor').value) || 0;
        const info = document.getElementById('baixa-convenio-info');
        const restante = Math.round((convenioEmBaixa.saldo - valor) * 100) / 100;

        if (valor > 0 && restante > 0.009) {
            info.textContent = `Pagamento parcial: ficam ${moedaConvenio(restante)} em aberto no convênio.`;
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
        }
    }

    async function confirmarBaixaConvenio() {
        if (!convenioEmBaixa) return;
        const erro = document.getElementById('baixa-convenio-erro');
        const botao = document.getElementById('btn-confirmar-baixa-convenio');
        const valor = Math.round((parseFloat(document.getElementById('baixa-convenio-valor').value) || 0) * 100) / 100;

        const mensagem = valor <= 0 ? 'Informe o valor pago.'
            : valor - convenioEmBaixa.saldo > 0.009 ? `O valor pago é maior que o saldo em aberto (${moedaConvenio(convenioEmBaixa.saldo)}).`
            : null;
        if (mensagem) {
            erro.textContent = mensagem;
            erro.classList.remove('hidden');
            return;
        }

        botao.disabled = true;
        let resultado;
        try {
            const resp = await fetch(URL_RECEBER_CONVENIO, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ venda_uuid: convenioEmBaixa.venda_uuid, valor, forma_pagamento: formaBaixaConvenio }),
            });
            resultado = await resp.json().catch(() => ({}));
            if (!resp.ok) throw new Error(resultado.erro || resultado.message || `Erro ${resp.status} ao registrar a baixa.`);
        } catch (e) {
            botao.disabled = false;
            erro.textContent = e.message;
            erro.classList.remove('hidden');
            return;
        }

        fecharModalBaixaConvenio();
        await carregarConvenios();
        alert(resultado.quitado
            ? 'Convênio quitado.'
            : `Baixa registrada. Ficam ${moedaConvenio(resultado.restante)} em aberto.`)
    }

    document.getElementById('baixa-convenio-valor').addEventListener('input', atualizarInfoBaixaConvenio);
    document.getElementById('modal-baixa-convenio').addEventListener('keydown', (e) => {
        // Só este modal responde ao teclado enquanto está aberto (os atalhos do PDV ficam de fora)
        e.stopPropagation();
        if (e.key === 'Escape') { e.preventDefault(); fecharModalBaixaConvenio(); return; }
        if (e.key === 'Enter') { e.preventDefault(); confirmarBaixaConvenio(); return; }
        const formas = Object.keys(@json(\App\Services\ConvenioService::FORMAS_RECEBIMENTO));
        const n = parseInt(e.key, 10);
        if (n >= 1 && n <= formas.length && e.target.id !== 'baixa-convenio-valor') {
            e.preventDefault();
            escolherFormaBaixaConvenio(formas[n - 1]);
        }
    });

    ['convenios-inicio', 'convenios-fim'].forEach(id =>
        document.getElementById(id).addEventListener('change', carregarConvenios));
    ['convenios-cupom', 'convenios-cliente'].forEach(id =>
        document.getElementById(id).addEventListener('input', () => {
            clearTimeout(timeoutConvenios);
            timeoutConvenios = setTimeout(carregarConvenios, 300);
        }));
    document.getElementById('modal-convenios').addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.getElementById('modal-baixa-convenio').classList.contains('hidden')) {
            e.preventDefault();
            fecharModalConvenios();
        }
    });
</script>
