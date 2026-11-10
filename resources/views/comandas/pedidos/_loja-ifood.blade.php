{{--
    Loja no iFood: aberta/fechada (e o motivo que o iFood informa), pausas e horário de funcionamento.
    Botão no cabeçalho da Lista de Pedidos (#btn-loja-ifood) mostra a situação e atualiza a cada minuto.
--}}
<div id="modal-loja-ifood" class="fixed inset-0 z-50 bg-black/50 hidden items-center justify-center p-4"
     onclick="if (event.target === this) LojaIfood.fechar()">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-5 pb-3 border-b border-slate-100 flex items-center gap-3">
            <span class="rounded bg-[#EA1D2C] px-1.5 py-0.5 text-[10px] font-bold text-white">iFood</span>
            <h2 class="flex-1 font-bold text-slate-900">Loja no iFood</h2>
            <button type="button" data-li="atualizar" onclick="LojaIfood.carregar(true)"
                    class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-50">Atualizar</button>
            <button type="button" onclick="LojaIfood.fechar()" aria-label="Fechar" class="w-10 h-10 rounded-xl text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
        </div>

        <div data-li="abas" class="hidden px-6 pt-3 flex gap-2">
            <button type="button" data-aba="situacao" onclick="LojaIfood.aba('situacao')"
                    class="rounded-xl px-4 py-2 text-sm font-semibold">Situação e pausas</button>
            <button type="button" data-aba="horarios" onclick="LojaIfood.aba('horarios')"
                    class="rounded-xl px-4 py-2 text-sm font-semibold">Horário de funcionamento</button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <p data-li="erro" class="hidden mb-3 rounded-xl bg-red-50 text-red-700 px-3 py-2 text-sm"></p>

            {{-- Situação e pausas --}}
            <div data-painel="situacao">
                <div data-li="situacao" class="text-sm text-slate-400 py-6 text-center">Consultando o iFood…</div>

                <div data-li="polling" class="hidden mt-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 text-amber-800 px-4 py-3 text-sm"></div>

                <section data-li="pausar" class="hidden mt-5">
                    <h3 class="text-sm font-bold text-slate-800">Pausar a loja agora</h3>
                    <p class="text-xs text-slate-500 mb-2">A loja para de receber pedidos no iFood durante a pausa e volta sozinha no fim.</p>
                    <input type="text" data-li="motivo" maxlength="200" placeholder="Motivo (opcional): cozinha cheia, falta de entregador…"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#10284A]/30 mb-2">
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ([15 => '15 min', 30 => '30 min', 60 => '1 hora', 120 => '2 horas'] as $min => $rotulo)
                            <button type="button" data-pausar="{{ $min }}" onclick="LojaIfood.pausar({{ $min }}, this)"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-amber-800 bg-amber-50 ring-1 ring-amber-200 hover:bg-amber-100 disabled:opacity-50">{{ $rotulo }}</button>
                        @endforeach
                        <span class="text-xs text-slate-400 mx-1">ou até</span>
                        <input type="time" data-li="ate"
                               class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#10284A]/30">
                        <button type="button" onclick="LojaIfood.pausarAte(this)"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-50">Pausar</button>
                    </div>
                </section>

                <section class="mt-5">
                    <h3 class="text-sm font-bold text-slate-800 mb-2">Pausas ativas e agendadas</h3>
                    <div data-li="pausas" class="flex flex-col gap-2"></div>
                </section>
            </div>

            {{-- Horário de funcionamento --}}
            <div data-painel="horarios" class="hidden">
                <p class="text-xs text-slate-500 mb-3">
                    O horário vale para a semana inteira: dia sem turno fica <strong>fechado</strong>. Fim menor que o início passa da meia-noite.
                    Para fechar por pouco tempo, use a pausa (não mude o horário).
                </p>
                <div data-li="horarios" class="flex flex-col divide-y divide-slate-100 text-sm"></div>
            </div>
        </div>

        <div data-li="rodape-horarios" class="hidden px-6 py-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <button type="button" data-li="copiar" onclick="LojaIfood.copiarSegunda()"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Copiar segunda para todos os dias</button>
            <button type="button" data-li="salvar-horarios" onclick="LojaIfood.salvarHorarios(this)"
                    class="ml-auto rounded-xl bg-[#10284A] hover:bg-[#1a3a66] text-white px-5 py-2.5 text-sm font-semibold disabled:opacity-50">Salvar no iFood</button>
        </div>
    </div>
</div>

<script>
    window.LojaIfood = (() => {
        const URLS = {
            situacao: @json(route('ifood.loja.situacao')),
            pausar: @json(route('ifood.loja.pausar')),
            remover: @json(route('ifood.loja.pausa.remover', ['pausa' => '__ID__'])),
            horarios: @json(route('ifood.loja.horarios')),
            salvarHorarios: @json(route('ifood.loja.horarios.salvar')),
        };
        const CSRF = @json(csrf_token());
        const modal = () => document.getElementById('modal-loja-ifood');
        const el = (n) => modal().querySelector(`[data-li="${n}"]`);
        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const horaMin = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const diaHora = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
        const mesmoDia = (a, b) => new Date(a).toDateString() === new Date(b).toDateString();

        let situacao = null;
        let dias = null;      // horário em edição
        let abaAtual = 'situacao';

        async function requisitar(url, metodo = 'GET', corpo = null) {
            const resp = await fetch(url, {
                method: metodo,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: corpo ? JSON.stringify(corpo) : null,
            });
            if (resp.redirected) { location.reload(); throw new Error('Sessão expirada.'); }
            const r = await resp.json().catch(() => ({}));
            if (!resp.ok) {
                const primeiroErro = r.errors ? Object.values(r.errors)[0]?.[0] : null;
                throw new Error(primeiroErro || r.message || 'Não foi possível concluir.');
            }
            return r;
        }

        const mensagemErro = (e) => e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message;

        function erro(texto) {
            el('erro').textContent = texto || '';
            el('erro').classList.toggle('hidden', !texto);
        }

        // ===== Botão do cabeçalho =====
        function resumo(s) {
            if (!s || s.configurado === false) return { rotulo: 'Loja iFood', ponto: 'bg-slate-400' };
            if (s.pausada) {
                const ativa = s.pausas.find(p => p.ativa);
                return { rotulo: 'Pausada' + (ativa?.fim ? ' até ' + horaMin(ativa.fim) : ''), ponto: 'bg-amber-400' };
            }
            return s.aberta ? { rotulo: 'Loja aberta', ponto: 'bg-green-500' } : { rotulo: 'Loja fechada', ponto: 'bg-red-500' };
        }

        function desenharBotao() {
            const botao = document.getElementById('btn-loja-ifood');
            if (!botao) return;
            const r = resumo(situacao);
            botao.querySelector('[data-li-ponto]').className = `w-2 h-2 rounded-full ${r.ponto}`;
            botao.querySelector('[data-li-rotulo]').textContent = r.rotulo;
            botao.title = situacao?.operacoes?.[0]?.titulo
                ? `${situacao.operacoes[0].titulo}${situacao.operacoes[0].subtitulo ? ' — ' + situacao.operacoes[0].subtitulo : ''}`
                : 'Situação da loja no iFood';
        }

        // ===== Situação =====
        function desenharSituacao() {
            const s = situacao;
            const caixa = el('situacao');

            if (s.configurado === false) {
                caixa.className = 'text-sm text-slate-500 py-6 text-center';
                caixa.textContent = 'Integração com o iFood desligada ou sem credenciais (Cadastros → iFood).';
                el('pausar').classList.add('hidden');
                el('pausas').innerHTML = '';
                return;
            }

            const r = resumo(s);
            const cor = s.pausada ? 'bg-amber-50 ring-amber-200' : s.aberta ? 'bg-green-50 ring-green-200' : 'bg-red-50 ring-red-200';
            const texto = s.pausada ? 'text-amber-800' : s.aberta ? 'text-green-800' : 'text-red-800';

            caixa.className = `rounded-2xl ring-1 px-4 py-4 ${cor}`;
            caixa.innerHTML = s.operacoes.map(o => `
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full ${r.ponto}"></span>
                    <p class="font-bold ${texto}">${esc(o.titulo || r.rotulo)}</p>
                    <span class="ml-auto text-[11px] uppercase tracking-wide text-slate-500">${esc(o.operacao)}</span>
                </div>
                ${o.subtitulo ? `<p class="mt-1 text-sm ${texto}">${esc(o.subtitulo)}</p>` : ''}
                ${o.descricao ? `<p class="mt-1 text-xs text-slate-600">${esc(o.descricao)}</p>` : ''}
                ${o.problemas.length ? `
                    <ul class="mt-3 flex flex-col gap-2">
                        ${o.problemas.map(p => `
                            <li class="rounded-xl bg-white/70 px-3 py-2">
                                <p class="text-sm font-semibold ${p.estado === 'WARNING' ? 'text-amber-800' : 'text-red-700'}">${esc(p.titulo || p.codigo)}</p>
                                ${p.subtitulo ? `<p class="text-xs text-slate-600">${esc(p.subtitulo)}</p>` : ''}
                            </li>`).join('')}
                    </ul>` : ''}
                ${o.horario ? `<p class="mt-3 text-xs text-slate-500">Horário de hoje: ${esc(o.horario)}</p>` : ''}
            `).join('<hr class="my-3 border-slate-200">') || '<p class="text-sm text-slate-500">O iFood não informou a situação.</p>';

            const polling = el('polling');
            polling.classList.toggle('hidden', !!s.polling?.em_dia);
            polling.innerHTML = `<p class="font-semibold">A integração não está consultando o iFood${s.polling?.ultimo_em ? ' desde ' + esc(diaHora(s.polling.ultimo_em)) : ''}.</p>
                <p class="text-xs mt-1">Sem a consulta a cada 30 segundos o iFood fecha a loja ("PDV desconectado"). Deixe esta tela aberta ou o <span class="font-mono">ifood:polling --sempre</span> rodando no servidor.</p>`;

            el('pausar').classList.toggle('hidden', !s.pode?.pausar);

            el('pausas').innerHTML = s.pausas.length ? s.pausas.map(p => `
                <div class="flex items-center gap-3 rounded-xl ring-1 ${p.ativa ? 'ring-amber-200 bg-amber-50' : 'ring-slate-200'} px-3 py-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 truncate">${esc(p.descricao || 'Pausa')}</p>
                        <p class="text-xs text-slate-500">${esc(diaHora(p.inicio))} até ${esc(p.fim && p.inicio && mesmoDia(p.inicio, p.fim) ? horaMin(p.fim) : diaHora(p.fim))}</p>
                    </div>
                    ${p.ativa ? '<span class="rounded-full bg-amber-200 text-amber-900 px-2 py-0.5 text-[11px] font-bold">Agora</span>' : '<span class="rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-[11px] font-bold">Agendada</span>'}
                    ${s.pode?.pausar ? `<button type="button" data-remover="${esc(p.id)}"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50 disabled:opacity-50">${p.ativa ? 'Reabrir agora' : 'Remover'}</button>` : ''}
                </div>`).join('') : '<p class="text-sm text-slate-400">Nenhuma pausa.</p>';
        }

        async function carregar(renovar = false) {
            if (renovar) el('atualizar').disabled = true;
            try {
                situacao = await requisitar(URLS.situacao + (renovar ? '?renovar=1' : ''));
                if (aberto()) { erro(''); desenharSituacao(); configurarAbas(); }
            } catch (e) {
                if (aberto()) erro(mensagemErro(e));
                const botao = document.getElementById('btn-loja-ifood');
                if (botao && !situacao) botao.querySelector('[data-li-rotulo]').textContent = 'Loja iFood ?';
            } finally {
                el('atualizar').disabled = false;
                desenharBotao();
            }
        }

        async function pausarMinutos(minutos, botao) {
            const motivo = el('motivo').value.trim();
            const fim = new Date(Date.now() + minutos * 60000);
            if (!confirm(`Pausar a loja no iFood até ${fim.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}${mesmoDia(new Date(), fim) ? '' : ' de ' + fim.toLocaleDateString('pt-BR')}? Os clientes não vão conseguir pedir até lá.`)) return;
            if (botao) botao.disabled = true;
            try {
                await requisitar(URLS.pausar, 'POST', { minutos, descricao: motivo });
                el('motivo').value = '';
                if (typeof avisar === 'function') avisar('Loja pausada no iFood. O iFood pode levar alguns minutos para mostrar a pausa.');
                await carregar(true);
            } catch (e) {
                erro(mensagemErro(e));
            } finally {
                if (botao) botao.disabled = false;
            }
        }

        function pausarAte(botao) {
            const valor = el('ate').value;
            if (!valor) { erro('Escolha até que horas a loja fica pausada.'); return; }
            const [h, m] = valor.split(':').map(Number);
            const fim = new Date(); fim.setHours(h, m, 0, 0);
            if (fim <= new Date()) fim.setDate(fim.getDate() + 1); // horário que já passou hoje: amanhã
            pausarMinutos(Math.max(1, Math.round((fim - new Date()) / 60000)), botao);
        }

        async function removerPausa(id, botao) {
            const pausa = situacao?.pausas.find(p => p.id === id);
            if (!confirm(pausa?.ativa ? 'Tirar a pausa e reabrir a loja no iFood agora?' : 'Remover esta pausa agendada?')) return;
            botao.disabled = true;
            try {
                await requisitar(URLS.remover.replace('__ID__', encodeURIComponent(id)), 'DELETE');
                if (typeof avisar === 'function') avisar('Pausa removida. O iFood pode levar alguns minutos para reabrir a loja.');
                await carregar(true);
            } catch (e) {
                erro(mensagemErro(e));
                botao.disabled = false;
            }
        }

        // ===== Horário de funcionamento =====
        function desenharHorarios() {
            const podeEditar = !!situacao?.pode?.horarios;
            el('horarios').innerHTML = dias.map((d, i) => `
                <div class="py-3 flex flex-wrap items-start gap-3">
                    <p class="w-20 pt-2 font-semibold text-slate-700">${esc(d.rotulo)}</p>
                    <div class="flex-1 flex flex-col gap-2">
                        ${d.turnos.length ? d.turnos.map((t, j) => `
                            <div class="flex items-center gap-2">
                                <input type="time" value="${esc(t.inicio)}" data-turno="${i}:${j}:inicio" ${podeEditar ? '' : 'disabled'}
                                       class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm disabled:bg-slate-50">
                                <span class="text-slate-400">às</span>
                                <input type="time" value="${esc(t.fim)}" data-turno="${i}:${j}:fim" ${podeEditar ? '' : 'disabled'}
                                       class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm disabled:bg-slate-50">
                                ${podeEditar ? `<button type="button" data-tirar="${i}:${j}" aria-label="Tirar turno" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none">&times;</button>` : ''}
                            </div>`).join('') : '<p class="pt-2 text-sm font-semibold text-red-600">Fechado</p>'}
                    </div>
                    ${podeEditar ? `<button type="button" data-turno-novo="${i}" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-[#10284A] ring-1 ring-slate-200 hover:bg-slate-50">+ turno</button>` : ''}
                </div>`).join('');

            el('rodape-horarios').classList.toggle('hidden', !podeEditar || abaAtual !== 'horarios');
        }

        async function carregarHorarios() {
            el('horarios').innerHTML = '<p class="py-6 text-center text-slate-400">Consultando o iFood…</p>';
            try {
                dias = (await requisitar(URLS.horarios)).dias;
                desenharHorarios();
            } catch (e) {
                el('horarios').innerHTML = '';
                erro(mensagemErro(e));
            }
        }

        function copiarSegunda() {
            if (!dias) return;
            const base = dias[0].turnos;
            dias.forEach((d, i) => { if (i > 0) d.turnos = base.map(t => ({ ...t })); });
            desenharHorarios();
        }

        async function salvarHorarios(botao) {
            if (!dias) return;
            const fechados = dias.filter(d => !d.turnos.length).map(d => d.rotulo);
            if (dias.some(d => d.turnos.some(t => !t.inicio || !t.fim))) { erro('Preencha a hora de início e de fim de todos os turnos.'); return; }
            if (!confirm('Gravar este horário no iFood? Ele substitui o horário da semana inteira.'
                + (fechados.length ? `\n\nFica FECHADO: ${fechados.join(', ')}.` : ''))) return;
            botao.disabled = true;
            erro('');
            try {
                dias = (await requisitar(URLS.salvarHorarios, 'PUT', { dias: dias.map(d => ({ dia: d.dia, turnos: d.turnos })) })).dias;
                desenharHorarios();
                if (typeof avisar === 'function') avisar('Horário gravado no iFood. Pode levar alguns minutos para aparecer no app.');
                carregar(true);
            } catch (e) {
                erro(mensagemErro(e));
            } finally {
                botao.disabled = false;
            }
        }

        // ===== Janela e abas =====
        function configurarAbas() {
            const comHorarios = !!situacao?.pode?.ver_horarios && situacao?.configurado !== false;
            el('abas').classList.toggle('hidden', !comHorarios);
            if (!comHorarios && abaAtual === 'horarios') aba('situacao');
        }

        function aba(nome) {
            abaAtual = nome;
            modal().querySelectorAll('[data-aba]').forEach(b => {
                const ativa = b.dataset.aba === nome;
                b.classList.toggle('bg-[#10284A]', ativa);
                b.classList.toggle('text-white', ativa);
                b.classList.toggle('text-slate-600', !ativa);
                b.classList.toggle('ring-1', !ativa);
                b.classList.toggle('ring-slate-200', !ativa);
            });
            modal().querySelectorAll('[data-painel]').forEach(p => p.classList.toggle('hidden', p.dataset.painel !== nome));
            el('rodape-horarios').classList.toggle('hidden', nome !== 'horarios' || !situacao?.pode?.horarios || !dias);
            erro('');
            if (nome === 'horarios' && !dias) carregarHorarios();
        }

        function abrir() {
            modal().classList.remove('hidden');
            modal().classList.add('flex');
            dias = null;
            aba('situacao');
            if (situacao) { desenharSituacao(); configurarAbas(); }
            carregar(true);
        }

        function fechar() {
            modal().classList.add('hidden');
            modal().classList.remove('flex');
        }

        const aberto = () => !modal().classList.contains('hidden');

        modal().addEventListener('click', (e) => {
            const remover = e.target.closest('[data-remover]');
            if (remover) return removerPausa(remover.dataset.remover, remover);

            const novo = e.target.closest('[data-turno-novo]');
            if (novo) {
                const d = dias[Number(novo.dataset.turnoNovo)];
                const ultimo = d.turnos.at(-1);
                d.turnos.push(ultimo ? { inicio: ultimo.fim, fim: '23:59' } : { inicio: '11:00', fim: '23:00' });
                return desenharHorarios();
            }

            const tirar = e.target.closest('[data-tirar]');
            if (tirar) {
                const [i, j] = tirar.dataset.tirar.split(':').map(Number);
                dias[i].turnos.splice(j, 1);
                return desenharHorarios();
            }
        });

        modal().addEventListener('change', (e) => {
            const campo = e.target.dataset?.turno;
            if (!campo || !dias) return;
            const [i, j, lado] = campo.split(':');
            dias[Number(i)].turnos[Number(j)][lado] = e.target.value;
        });

        // Esc fecha esta janela antes das outras da tela
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && aberto()) { e.stopImmediatePropagation(); fechar(); }
        }, true);

        // Botão do cabeçalho: situação a cada minuto (o servidor guarda 20 s em cache)
        carregar();
        setInterval(() => { if (!document.hidden) carregar(); }, 60000);

        return { abrir, fechar, aberto, carregar, aba, pausar: pausarMinutos, pausarAte, salvarHorarios, copiarSegunda, estado: () => situacao };
    })();
</script>
