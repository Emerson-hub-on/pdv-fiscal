{{-- Caixa: consulta dos convênios (vendas fiado) em aberto. Filtra por período da venda, nº do cupom e cliente.
     Lê o SQLite do caixa (lista trazida do servidor no Atualizar Caixa + vendas deste caixa ainda não enviadas). --}}
<div id="modal-convenios" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
            <div>
                <h2 class="text-lg font-bold text-white">Convênios em aberto</h2>
                <p class="text-xs text-slate-300">Vendas no convênio (fiado) ainda não recebidas. A baixa é feita no painel, em Contas a receber.</p>
            </div>
            <button type="button" onclick="fecharModalConvenios()" class="text-slate-300 hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="p-6 flex flex-col gap-4 min-h-0">
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Venda de</label>
                    <input type="date" id="convenios-inicio" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">até</label>
                    <input type="date" id="convenios-fim" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nº do cupom</label>
                    <input type="text" id="convenios-cupom" inputmode="numeric" placeholder="Ex.: 1234"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cliente</label>
                    <input type="text" id="convenios-cliente" placeholder="Nome do cliente"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none">
                </div>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex gap-2">
                    <button type="button" onclick="filtroRapidoConvenios('hoje')" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Hoje</button>
                    <button type="button" onclick="filtroRapidoConvenios('mes')" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Este mês</button>
                    <button type="button" onclick="filtroRapidoConvenios('todos')" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Todos em aberto</button>
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

<script>
    const URL_CONVENIOS_ABERTOS = @json(route('vendas.convenios-abertos'));
    let timeoutConvenios;
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
        if (dados.linhas.length === 0) {
            const tr = corpo.insertRow();
            const td = tr.insertCell();
            td.colSpan = 6;
            td.className = 'px-3 py-6 text-center text-gray-400';
            td.textContent = 'Nenhum convênio em aberto com esses filtros. A lista do servidor é atualizada no "Atualizar Caixa".';
            return;
        }

        // Texto do cliente vai como texto, nunca como HTML
        dados.linhas.forEach(l => {
            const tr = corpo.insertRow();
            tr.className = 'hover:bg-gray-50';
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

    ['convenios-inicio', 'convenios-fim'].forEach(id =>
        document.getElementById(id).addEventListener('change', carregarConvenios));
    ['convenios-cupom', 'convenios-cliente'].forEach(id =>
        document.getElementById(id).addEventListener('input', () => {
            clearTimeout(timeoutConvenios);
            timeoutConvenios = setTimeout(carregarConvenios, 300);
        }));
    document.getElementById('modal-convenios').addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); fecharModalConvenios(); }
    });
</script>
