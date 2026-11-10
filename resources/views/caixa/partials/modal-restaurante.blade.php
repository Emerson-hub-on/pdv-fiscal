{{-- PDV → Puxar Pedido (F6) → Pedidos Restaurante: contas de mesa pagas e pedidos do iFood concluídos de UM dia,
     emitidos em NFC-e um por vez. "Pausar" deixa terminar a emissão em andamento e para antes da próxima. --}}
<div id="modal-restaurante" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 bg-[#10284A] shrink-0">
            <div>
                <h2 class="text-lg font-bold text-amber-50">Pedidos Restaurante</h2>
                <p class="text-xs text-slate-300">Contas de mesa pagas e pedidos do iFood concluídos. Emita as vendas de um dia por vez.</p>
            </div>
            <button type="button" data-rest="fechar" onclick="RestaurantePdv.fechar()"
                    class="text-slate-400 hover:text-white text-2xl leading-none transition disabled:opacity-30 disabled:cursor-not-allowed">&times;</button>
        </div>

        <div class="flex flex-wrap items-end gap-3 px-6 py-4 border-b border-slate-200 shrink-0">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                Dia das vendas
                <input type="date" data-rest="data" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}"
                       onchange="RestaurantePdv.carregar()"
                       class="mt-1 block bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 normal-case tracking-normal font-normal focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition disabled:opacity-60">
            </label>
            <button type="button" data-rest="atualizar" onclick="RestaurantePdv.carregar()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold px-4 py-2 rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                Atualizar lista
            </button>
            <div data-rest="resumo" class="flex flex-wrap gap-2 ml-auto text-xs"></div>
        </div>

        <div class="flex-1 overflow-y-auto px-6">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-white">
                    <tr class="text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-2 px-2 w-8">
                            <input type="checkbox" data-rest="todos" onchange="RestaurantePdv.marcarTodos(this.checked)"
                                   class="rounded border-slate-300 text-[#10284A] focus:ring-[#10284A]/30" title="Marcar todos a emitir">
                        </th>
                        <th class="py-2 px-2">Origem</th>
                        <th class="py-2 px-2">Pedido</th>
                        <th class="py-2 px-2">Hora</th>
                        <th class="py-2 px-2 text-center">Itens</th>
                        <th class="py-2 px-2">Pagamento</th>
                        <th class="py-2 px-2 text-right">Total</th>
                        <th class="py-2 px-2">Situação</th>
                    </tr>
                </thead>
                <tbody data-rest="linhas"></tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 shrink-0">
            <div data-rest="progresso" class="hidden mb-3">
                <div class="flex justify-between text-xs text-slate-600 mb-1">
                    <span data-rest="progresso-texto"></span>
                    <span data-rest="progresso-conta"></span>
                </div>
                <progress data-rest="barra" max="100" value="0"
                          class="w-full h-2 rounded-full overflow-hidden appearance-none bg-slate-200 [&::-webkit-progress-bar]:bg-slate-200 [&::-webkit-progress-value]:bg-green-600 [&::-webkit-progress-value]:transition-all [&::-moz-progress-bar]:bg-green-600"></progress>
            </div>
            <p data-rest="mensagem" class="hidden text-sm mb-3"></p>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <p class="text-xs text-slate-500 mr-auto">As NFC-e saem com a data e hora de agora. A taxa de entrega do iFood não entra na nota.</p>
                <button type="button" data-rest="pausar" onclick="RestaurantePdv.pausar()"
                        class="hidden bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition disabled:opacity-60 disabled:cursor-not-allowed">
                    Pausar
                </button>
                <button type="button" data-rest="emitir" onclick="RestaurantePdv.emitir()"
                        class="bg-[#10284A] hover:bg-[#1a3a66] text-amber-50 text-sm font-semibold px-5 py-2.5 rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Emitir vendas
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Pendência de um pedido: abre por cima da lista, pelo botão "Pendência" --}}
<div id="modal-pendencia-restaurante" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" role="dialog" aria-modal="true">
        <div class="flex justify-between items-start gap-3 px-6 py-4 bg-amber-50 border-b border-amber-200">
            <div>
                <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Pendência para emitir</p>
                <h3 data-pend="titulo" class="text-lg font-bold text-slate-800"></h3>
                <p data-pend="detalhe" class="text-xs text-slate-500"></p>
            </div>
            <button type="button" onclick="RestaurantePdv.fecharPendencia()"
                    class="text-slate-400 hover:text-slate-700 text-2xl leading-none transition">&times;</button>
        </div>
        <div class="px-6 py-4">
            <ul data-pend="lista" class="list-disc pl-5 space-y-2 text-sm text-slate-700"></ul>
            <p class="mt-4 text-xs text-slate-500">Resolva o que está acima e clique em "Atualizar lista" para o pedido ficar liberado para emitir.</p>
        </div>
        <div class="flex justify-end px-6 py-3 bg-slate-50 border-t border-slate-200">
            <button type="button" data-pend="ok" onclick="RestaurantePdv.fecharPendencia()"
                    class="bg-[#10284A] hover:bg-[#1a3a66] text-amber-50 text-sm font-semibold px-5 py-2 rounded-xl transition">
                Entendi
            </button>
        </div>
    </div>
</div>

<script>
window.RestaurantePdv = (() => {
    const URL_LISTAR = @json(route('pdv.restaurante.listar'));
    const URL_EMITIR = @json(route('pdv.restaurante.emitir'));
    const CSRF = @json(csrf_token());

    const SITUACOES = {
        pendente:     { rotulo: 'A emitir',               classe: 'bg-slate-100 text-slate-700' },
        bloqueado:    { rotulo: 'Com pendência',          classe: 'bg-amber-50 text-amber-700' },
        emitindo:     { rotulo: 'Emitindo…',              classe: 'bg-blue-50 text-blue-700 animate-pulse' },
        emitida:      { rotulo: 'NFC-e',                  classe: 'bg-green-50 text-green-700' },
        contingencia: { rotulo: 'Contingência',           classe: 'bg-orange-50 text-orange-700' },
        nao_emitida:  { rotulo: 'Não emitida',            classe: 'bg-red-50 text-red-700' },
        erro:         { rotulo: 'Erro',                   classe: 'bg-red-50 text-red-700' },
        outro_caixa:  { rotulo: 'Emitida em outro caixa', classe: 'bg-indigo-50 text-indigo-700' },
        cancelada:    { rotulo: 'NFC-e cancelada',        classe: 'bg-slate-100 text-slate-500' },
    };

    let pedidos = [];
    let dataLista = null;
    let marcados = new Set();
    let emitindo = false;
    let pausaPedida = false;
    let carregando = 0;

    const modal = () => document.getElementById('modal-restaurante');
    const el = (n) => modal().querySelector(`[data-rest="${n}"]`);
    const chave = (p) => `${p.tipo}:${p.id}`;
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const moeda = (v) => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const aEmitir = () => pedidos.filter(p => p.situacao === 'pendente');

    function abrir() {
        modal().classList.remove('hidden');
        modal().classList.add('flex');
        if (!emitindo) carregar();
    }

    function fechar() {
        // Esc com a pendência aberta fecha só ela
        if (pendenciaAberta()) {
            fecharPendencia();
            return false;
        }
        if (emitindo) {
            mensagem('Aguarde: pause a emissão e espere a nota em andamento terminar para fechar.', 'aviso');
            return false;
        }
        modal().classList.add('hidden');
        modal().classList.remove('flex');
        if (typeof inputBusca !== 'undefined') inputBusca.focus();
        return true;
    }

    function aberto() {
        return !modal().classList.contains('hidden');
    }

    async function carregar() {
        if (emitindo) return;
        const data = el('data').value;
        const minha = ++carregando;
        mensagem('');
        el('linhas').innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-400">Carregando pedidos…</td></tr>';

        try {
            const resp = await fetch(`${URL_LISTAR}?data=${encodeURIComponent(data)}`, { headers: { 'Accept': 'application/json' } });
            const json = await resp.json().catch(() => ({}));
            if (minha !== carregando) return;
            if (!resp.ok) throw new Error(json.message || `Erro ${resp.status} ao carregar os pedidos.`);

            pedidos = json.pedidos || [];
            dataLista = json.data;
            marcados = new Set(aEmitir().map(chave));
            desenhar();
        } catch (e) {
            if (minha !== carregando) return;
            pedidos = [];
            marcados = new Set();
            desenhar();
            el('linhas').innerHTML = `<tr><td colspan="8" class="p-4 text-center text-red-600">${esc(e.message)}</td></tr>`;
        }
    }

    function seloSituacao(p) {
        const s = SITUACOES[p.situacao] || SITUACOES.pendente;
        let rotulo = s.rotulo;
        if ((p.situacao === 'emitida' || p.situacao === 'contingencia') && p.numero_nfce) {
            rotulo += ' nº ' + String(p.numero_nfce).padStart(6, '0');
        }
        let extra = '';
        if (p.situacao === 'bloqueado') {
            // Só o botão "Pendência"; o relato abre num modal ao clicar
            return `<button type="button" data-pendencia="${esc(chave(p))}" title="Ver o que falta para emitir"
                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100 cursor-pointer transition">
                        ⚠ Pendência
                    </button>`;
        } else if (['nao_emitida', 'erro'].includes(p.situacao) && p.motivo) {
            extra = `<p class="mt-1 text-xs text-red-600">${esc(p.motivo)}</p>`
                + (p.situacao === 'nao_emitida' ? '<p class="text-[11px] text-slate-500">Reenvie em Contingências (F1).</p>' : '');
        } else if (p.situacao === 'contingencia') {
            extra = '<p class="mt-1 text-[11px] text-slate-500">Documento válido; sobe para a SEFAZ em Contingências (F1).</p>';
        } else if (p.situacao === 'cancelada') {
            extra = '<p class="mt-1 text-[11px] text-slate-500">Fica livre para emitir de novo depois do "Atualizar Caixa".</p>';
        }
        return `<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${s.classe}">${esc(rotulo)}</span>${extra}`;
    }

    function desenhar() {
        const linhas = el('linhas');

        if (pedidos.length === 0) {
            linhas.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-400">Nenhuma conta de mesa paga ou pedido do iFood concluído neste dia.</td></tr>';
        } else {
            linhas.innerHTML = pedidos.map(p => {
                const k = chave(p);
                const podeMarcar = p.situacao === 'pendente' && !emitindo;
                const ifood = p.tipo === 'ifood';
                return `
                    <tr class="border-b border-slate-100 align-top ${p.situacao === 'emitindo' ? 'bg-blue-50/50' : ''}" data-pedido="${esc(k)}">
                        <td class="py-2.5 px-2">
                            <input type="checkbox" data-marcar="${esc(k)}" ${marcados.has(k) ? 'checked' : ''} ${podeMarcar ? '' : 'disabled'}
                                   class="rounded border-slate-300 text-[#10284A] focus:ring-[#10284A]/30 disabled:opacity-40">
                        </td>
                        <td class="py-2.5 px-2">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold ${ifood ? 'bg-red-50 text-red-700' : 'bg-sky-50 text-sky-700'}">${ifood ? 'iFood' : 'Mesa'}</span>
                        </td>
                        <td class="py-2.5 px-2 font-medium text-slate-800">${esc(p.rotulo)}</td>
                        <td class="py-2.5 px-2 whitespace-nowrap text-slate-600">${esc(p.hora || '')}</td>
                        <td class="py-2.5 px-2 text-center">${p.itens}</td>
                        <td class="py-2.5 px-2 text-slate-600 text-xs">${esc(p.pagamento || '—')}</td>
                        <td class="py-2.5 px-2 text-right font-semibold whitespace-nowrap">${moeda(p.total)}</td>
                        <td class="py-2.5 px-2">${seloSituacao(p)}</td>
                    </tr>`;
            }).join('');
        }

        desenharResumo();
        desenharBotoes();
    }

    function desenharResumo() {
        const conta = (s) => pedidos.filter(p => s.includes(p.situacao)).length;
        const partes = [
            [conta(['pendente']), 'a emitir', 'bg-slate-100 text-slate-700'],
            [conta(['emitida', 'contingencia', 'outro_caixa']), 'emitidas', 'bg-green-50 text-green-700'],
            [conta(['bloqueado']), 'com pendência', 'bg-amber-50 text-amber-700'],
            [conta(['nao_emitida', 'erro']), 'com erro', 'bg-red-50 text-red-700'],
        ].filter(([n], i) => n > 0 || i < 2);
        el('resumo').innerHTML = partes.map(([n, r, c]) => `<span class="px-2.5 py-1 rounded-full font-semibold ${c}">${n} ${r}</span>`).join('');

        const pendentes = aEmitir();
        const todos = el('todos');
        todos.checked = pendentes.length > 0 && pendentes.every(p => marcados.has(chave(p)));
        todos.disabled = emitindo || pendentes.length === 0;
    }

    function selecionados() {
        return aEmitir().filter(p => marcados.has(chave(p)));
    }

    function desenharBotoes() {
        const n = selecionados().length;
        const botao = el('emitir');
        botao.textContent = n > 0 ? `Emitir ${n} venda${n > 1 ? 's' : ''}` : 'Emitir vendas';
        botao.disabled = emitindo || n === 0;
        botao.classList.toggle('hidden', emitindo);

        const pausar = el('pausar');
        pausar.classList.toggle('hidden', !emitindo);
        pausar.disabled = pausaPedida;
        pausar.textContent = pausaPedida ? 'Pausando…' : 'Pausar';

        el('data').disabled = emitindo;
        el('atualizar').disabled = emitindo;
        el('fechar').disabled = emitindo;
    }

    function marcarTodos(sim) {
        marcados = sim ? new Set(aEmitir().map(chave)) : new Set();
        desenhar();
    }

    const CORES_MENSAGEM = { erro: 'text-red-600', ok: 'text-green-700', aviso: 'text-amber-700', info: 'text-slate-600' };

    function mensagem(texto, tipo = 'info') {
        const m = el('mensagem');
        m.textContent = texto;
        m.className = `text-sm mb-3 ${CORES_MENSAGEM[tipo] || CORES_MENSAGEM.info} ${texto ? '' : 'hidden'}`;
    }

    function progresso(feitos, total, texto) {
        el('progresso').classList.remove('hidden');
        el('progresso-texto').textContent = texto;
        el('progresso-conta').textContent = `${feitos} de ${total}`;
        el('barra').value = total ? Math.round(feitos * 100 / total) : 0;
    }

    function pausar() {
        if (!emitindo) return;
        pausaPedida = true;
        desenharBotoes();
        mensagem('Pausa pedida: a nota em andamento termina e a emissão para.', 'aviso');
    }

    async function emitir() {
        if (emitindo) return;
        const fila = selecionados();
        if (fila.length === 0) return;
        if (!dataLista || dataLista !== el('data').value) {
            mensagem('O dia mudou desde que a lista foi carregada. Atualize a lista antes de emitir.', 'aviso');
            return;
        }

        emitindo = true;
        pausaPedida = false;
        mensagem('');
        let feitos = 0, ok = 0, falhas = 0, parouPor = null;
        progresso(0, fila.length, 'Emitindo…');

        for (const p of fila) {
            if (pausaPedida) { parouPor = 'pausa'; break; }

            p.situacao = 'emitindo';
            desenhar();
            progresso(feitos, fila.length, `Emitindo ${p.rotulo}…`);

            try {
                const resp = await fetch(URL_EMITIR, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ tipo: p.tipo, id: p.id, data: dataLista }),
                });
                const json = await resp.json().catch(() => ({}));

                if (resp.status === 503) {
                    p.situacao = 'pendente';
                    parouPor = json.message || 'Servidor indisponível.';
                    break;
                }
                if (resp.status === 419) {
                    p.situacao = 'pendente';
                    parouPor = 'A sessão expirou. Recarregue a página (F5) e entre de novo.';
                    break;
                }
                if (!resp.ok) {
                    p.situacao = 'erro';
                    p.motivo = json.message || `Erro ${resp.status}.`;
                    falhas++;
                } else {
                    p.situacao = json.situacao;
                    p.numero_nfce = json.numero_nfce;
                    p.motivo = json.motivo;
                    p.venda_uuid = json.venda_uuid;
                    if (json.situacao === 'nao_emitida') falhas++; else ok++;
                }
            } catch (e) {
                p.situacao = 'pendente';
                parouPor = 'Falha de comunicação com o caixa: ' + e.message;
                break;
            }

            marcados.delete(chave(p));
            feitos++;
            progresso(feitos, fila.length, `${p.rotulo}: ${SITUACOES[p.situacao]?.rotulo || p.situacao}`);
        }

        emitindo = false;
        pausaPedida = false;
        desenhar();

        const resumo = `${ok} emitida${ok === 1 ? '' : 's'}` + (falhas ? `, ${falhas} com erro` : '');
        if (parouPor === 'pausa') {
            progresso(feitos, fila.length, 'Pausado');
            mensagem(`Emissão pausada. ${resumo}. Clique em "Emitir" para continuar de onde parou.`, 'aviso');
        } else if (parouPor) {
            progresso(feitos, fila.length, 'Parado');
            mensagem(`${parouPor} ${resumo}.`, 'erro');
        } else {
            progresso(feitos, fila.length, 'Concluído');
            mensagem(`Emissão concluída: ${resumo}.`, falhas ? 'aviso' : 'ok');
        }
    }

    document.addEventListener('change', (e) => {
        const k = e.target?.dataset?.marcar;
        if (!k || emitindo) return;
        if (e.target.checked) marcados.add(k); else marcados.delete(k);
        desenharResumo();
        desenharBotoes();
    });

    // Modal da pendência: o que falta para emitir o pedido
    const modalPendencia = () => document.getElementById('modal-pendencia-restaurante');
    const pend = (n) => modalPendencia().querySelector(`[data-pend="${n}"]`);

    function abrirPendencia(k) {
        const p = pedidos.find(x => chave(x) === k);
        if (!p) return;
        pend('titulo').textContent = p.rotulo;
        pend('detalhe').textContent = [p.tipo === 'ifood' ? 'iFood' : 'Mesa', p.hora, moeda(p.total)].filter(Boolean).join(' · ');
        const problemas = p.problemas?.length ? p.problemas : ['O pedido não pode ser emitido.'];
        pend('lista').innerHTML = problemas.map(x => `<li>${esc(x)}</li>`).join('');
        modalPendencia().classList.remove('hidden');
        modalPendencia().classList.add('flex');
        pend('ok').focus();
    }

    function fecharPendencia() {
        modalPendencia().classList.add('hidden');
        modalPendencia().classList.remove('flex');
    }

    const pendenciaAberta = () => !modalPendencia().classList.contains('hidden');

    document.addEventListener('click', (e) => {
        const botao = e.target.closest?.('#modal-restaurante [data-pendencia]');
        if (botao) {
            abrirPendencia(botao.dataset.pendencia);
        } else if (e.target === modalPendencia()) {
            fecharPendencia(); // clique fora da caixa
        }
    });

    // Não fecha a página no meio de uma emissão
    window.addEventListener('beforeunload', (e) => {
        if (emitindo) { e.preventDefault(); e.returnValue = ''; }
    });

    return { abrir, fechar, aberto, carregar, emitir, pausar, marcarTodos, fecharPendencia, emitindo: () => emitindo };
})();

function abrirModalRestaurante() { RestaurantePdv.abrir(); }
</script>
