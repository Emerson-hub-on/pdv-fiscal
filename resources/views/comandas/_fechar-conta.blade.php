{{-- Janela "Fechar conta" (garçom na mesa e operador no balcão), em duas etapas:
       1) Resumo: todos os pedidos da mesa com os itens → "Confirmar e ir para o pagamento";
       2) Pagamento: uma ou mais formas → "Finalizar conta" (a mesa fica disponível).
     Uso: FecharConta.abrir({ urlConta, urlFechar, aoFechar(dados) }).
     Precisa de $formas (ComandaService::formasPagamento) e do escHtml (partials.escape-html). --}}
<div id="modal-fechar-conta" class="fixed inset-0 z-50 bg-black/50 hidden items-end sm:items-center justify-center"
     onclick="if (event.target === this) FecharConta.fechar()">
    <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[94vh] flex flex-col">
        <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-slate-400" data-fc="etapa">Fechar conta</p>
                <h2 class="font-bold text-slate-900 text-lg truncate" data-fc="titulo">Carregando...</h2>
            </div>
            <button type="button" onclick="FecharConta.fechar()" aria-label="Fechar"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-3">
            {{-- Etapa 1: resumo de todos os pedidos --}}
            <div data-fc="area-resumo">
                <div data-fc="resumo" class="flex flex-col gap-3"></div>
                <div class="flex items-center justify-between py-3 border-t-2 border-slate-200 mt-3">
                    <span class="font-semibold text-slate-600">Total da conta</span>
                    <span class="text-2xl font-bold text-[#10284A]" data-fc="total"></span>
                </div>
                {{-- Conta já paga (aberta de novo pela lista) --}}
                <div data-fc="area-paga" class="hidden mt-1 rounded-2xl bg-green-50 ring-1 ring-green-200 px-4 py-3 text-sm text-green-800"></div>
            </div>

            {{-- Etapa 2: forma(s) de pagamento --}}
            <div data-fc="area-pagamento" class="hidden">
                <div class="rounded-2xl bg-[#10284A] text-amber-50 px-4 py-3 flex items-center justify-between">
                    <span class="text-sm font-medium">Total a pagar</span>
                    <span class="text-2xl font-bold" data-fc="total-pagar"></span>
                </div>

                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mt-4 mb-2">Forma de pagamento <span class="font-normal normal-case tracking-normal text-slate-400">(pode usar mais de uma)</span></p>
                <div class="flex flex-wrap gap-2" data-fc="formas">
                    @foreach ($formas as $forma)
                        <button type="button" onclick="FecharConta.adicionarForma('{{ $forma->codigo }}')"
                                class="rounded-xl px-3.5 py-2.5 text-sm font-semibold ring-1 ring-slate-200 bg-white text-slate-700 active:bg-slate-100 hover:ring-[#10284A]/40">
                            + {{ $forma->descricao }}
                        </button>
                    @endforeach
                </div>
                <div data-fc="pagamentos" class="mt-3 flex flex-col gap-2"></div>

                <div class="mt-3 rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm flex flex-col gap-1">
                    <div class="flex justify-between"><span class="text-slate-500">Pago</span><span class="font-semibold" data-fc="pago"></span></div>
                    <div class="flex justify-between" data-fc="linha-restante"><span class="text-slate-500">Falta</span><span class="font-bold text-red-600" data-fc="restante"></span></div>
                    <div class="flex justify-between" data-fc="linha-troco"><span class="text-slate-500">Troco</span><span class="font-bold text-green-700" data-fc="troco"></span></div>
                </div>
            </div>
        </div>

        <div class="px-5 pt-3 pb-8 sm:pb-5 border-t border-slate-100">
            <p data-fc="erro" class="hidden mb-3 rounded-xl bg-red-50 text-red-600 px-3 py-2 text-sm"></p>
            <button type="button" onclick="FecharConta.irParaPagamento()" data-fc="avancar"
                    class="w-full rounded-2xl bg-[#10284A] text-amber-50 px-4 py-4 text-base font-bold active:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                Confirmar e ir para o pagamento →
            </button>
            <div data-fc="botoes-pagamento" class="hidden"><div class="flex items-center gap-2">
                <button type="button" onclick="FecharConta.voltarResumo()" data-fc="voltar"
                        class="rounded-2xl px-4 py-4 text-base font-semibold text-slate-600 ring-1 ring-slate-200 active:bg-slate-100">← Voltar</button>
                <button type="button" onclick="FecharConta.finalizar()" data-fc="finalizar"
                        class="flex-1 rounded-2xl bg-[#10284A] text-amber-50 px-4 py-4 text-base font-bold active:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    Finalizar conta
                </button>
            </div></div>
        </div>
    </div>
</div>

<script>
    window.FecharConta = (function () {
        const FORMAS = Object.fromEntries(@json($formas).map(f => [f.codigo, f]));
        const CSRF = @json(csrf_token());
        const modal = document.getElementById('modal-fechar-conta');
        const el = (nome) => modal.querySelector(`[data-fc="${nome}"]`);
        const moeda = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const hora = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const centavos = (v) => Math.round(Number(v) * 100);
        // "10,50", "10.50", "1.234,56" → 10.5 / 1234.56
        const lerValor = (texto) => {
            let t = String(texto ?? '').replace(/[^\d,.]/g, '');
            if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
            const n = parseFloat(t);
            return Number.isFinite(n) && n > 0 ? Math.round(n * 100) / 100 : 0;
        };
        const formatarCampo = (v) => v ? Number(v).toFixed(2).replace('.', ',') : '';

        let estado = null; // { conta, etapa: 'resumo'|'pagamento', pagamentos: [{ forma, valor }], urlFechar, aoFechar }

        function abrirModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        function fechar() { modal.classList.add('hidden'); modal.classList.remove('flex'); estado = null; }

        function erro(texto) {
            el('erro').textContent = texto || '';
            el('erro').classList.toggle('hidden', !texto);
        }

        async function abrir({ urlConta, urlFechar, aoFechar }) {
            estado = { conta: null, etapa: 'resumo', pagamentos: [], urlFechar, aoFechar };
            el('titulo').textContent = 'Carregando...';
            el('resumo').innerHTML = '';
            el('total').textContent = '';
            el('pagamentos').innerHTML = '';
            el('area-paga').classList.add('hidden');
            erro('');
            mostrarEtapa();
            el('avancar').disabled = true;
            abrirModal();

            try {
                const resp = await fetch(urlConta, { headers: { Accept: 'application/json' } });
                if (resp.redirected) return location.reload(); // sessão expirou
                const dados = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(dados.message || 'Não foi possível carregar a conta.');
                estado.conta = dados.conta;
                desenharResumo();
            } catch (e) {
                el('titulo').textContent = 'Conta';
                erro(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message);
            }
        }

        // Mostra a etapa atual (resumo ou pagamento) e os botões dela
        function mostrarEtapa() {
            const pagamento = estado.etapa === 'pagamento';
            const paga = estado.conta && estado.conta.status !== 'aberta';
            el('etapa').textContent = paga ? 'Conta fechada'
                : pagamento ? 'Fechar conta · 2 de 2 · Pagamento' : 'Fechar conta · 1 de 2 · Resumo dos pedidos';
            el('area-resumo').classList.toggle('hidden', pagamento);
            el('area-pagamento').classList.toggle('hidden', !pagamento);
            el('avancar').classList.toggle('hidden', pagamento || paga);
            el('botoes-pagamento').classList.toggle('hidden', !pagamento);
            modal.querySelector('.overflow-y-auto').scrollTop = 0;
        }

        function desenharResumo() {
            const c = estado.conta;
            const paga = c.status !== 'aberta';
            el('titulo').textContent = c.mesa + (c.clientes.length ? ' · ' + c.clientes.join(', ') : '');

            el('resumo').innerHTML = (c.resumo || []).map(p => `
                <div class="rounded-2xl ring-1 ring-slate-200 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-50 flex items-center justify-between gap-2 text-sm">
                        <span class="font-semibold text-slate-800">${p.numero}º pedido <span class="font-normal text-slate-400">#${p.id} · ${escHtml(hora(p.enviado_em))}</span></span>
                        <span class="text-xs text-slate-500 truncate">${escHtml(p.status)}${p.garcom ? ' · ' + escHtml(p.garcom) : ''}</span>
                    </div>
                    ${p.cliente ? `<p class="px-4 pt-2 text-xs text-slate-500">Cliente: ${escHtml(p.cliente)}</p>` : ''}
                    <div class="px-4 divide-y divide-slate-100">
                        ${p.itens.map(i => `
                            <div class="py-2 flex items-start justify-between gap-3 text-sm">
                                <span class="min-w-0"><span class="font-semibold text-slate-900">${i.quantidade}×</span> ${escHtml(i.nome)}
                                    <span class="block text-xs text-slate-400">${escHtml(moeda(i.preco_unitario))} cada</span>
                                    ${i.observacao ? `<span class="block text-xs text-amber-700">Obs.: ${escHtml(i.observacao)}</span>` : ''}</span>
                                <span class="shrink-0 font-semibold text-slate-800">${escHtml(moeda(i.subtotal))}</span>
                            </div>`).join('')}
                    </div>
                    <div class="px-4 py-2 border-t border-slate-100 flex justify-between text-sm">
                        <span class="text-slate-500">Subtotal do pedido</span>
                        <span class="font-semibold text-slate-800">${escHtml(moeda(p.total))}</span>
                    </div>
                </div>`).join('') || '<p class="py-4 text-sm text-slate-400">Nenhum pedido nesta conta.</p>';
            el('total').textContent = moeda(c.total);
            el('total-pagar').textContent = moeda(c.total);

            el('area-paga').classList.toggle('hidden', !paga);
            if (paga) {
                el('area-paga').innerHTML = c.status === 'paga'
                    ? `<p class="font-semibold">Conta paga</p>${c.pagamentos.map(p => `<p>${escHtml(p.descricao)}: ${escHtml(moeda(p.valor))}</p>`).join('')}${c.troco > 0 ? `<p>Troco: ${escHtml(moeda(c.troco))}</p>` : ''}`
                    : '<p class="font-semibold">Conta encerrada sem cobrança.</p>';
            }
            el('avancar').disabled = paga || !(c.resumo || []).length;
            mostrarEtapa();
        }

        function irParaPagamento() {
            if (!estado?.conta || estado.conta.status !== 'aberta' || el('avancar').disabled) return;
            estado.etapa = 'pagamento';
            erro('');
            mostrarEtapa();
            desenharPagamentos();
        }

        function voltarResumo() {
            if (!estado) return;
            estado.etapa = 'resumo';
            erro('');
            mostrarEtapa();
        }

        function desenharPagamentos() {
            el('pagamentos').innerHTML = estado.pagamentos.map((p, i) => `
                <div class="flex items-center gap-2">
                    <span class="flex-1 min-w-0 font-semibold text-slate-800 truncate">${escHtml(FORMAS[p.forma].descricao)}</span>
                    <span class="text-slate-400 text-sm">R$</span>
                    <input type="text" inputmode="decimal" value="${formatarCampo(p.valor)}" data-indice="${i}"
                           class="w-32 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-base text-right font-semibold focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none">
                    <button type="button" onclick="FecharConta.removerForma(${i})" aria-label="Remover"
                            class="w-10 h-10 rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600 text-xl leading-none">&times;</button>
                </div>`).join('')
                || '<p class="text-sm text-slate-400">Toque em uma forma de pagamento acima.</p>';

            el('pagamentos').querySelectorAll('input').forEach(input => {
                input.addEventListener('input', () => {
                    estado.pagamentos[input.dataset.indice].valor = lerValor(input.value);
                    resumir();
                });
                input.addEventListener('blur', () => { input.value = formatarCampo(estado.pagamentos[input.dataset.indice].valor); });
            });
            resumir();
        }

        function resumir() {
            const total = centavos(estado.conta.total);
            const pago = estado.pagamentos.reduce((t, p) => t + centavos(p.valor), 0);
            const comTroco = estado.pagamentos.filter(p => FORMAS[p.forma].permite_troco).reduce((t, p) => t + centavos(p.valor), 0);
            const restante = Math.max(0, total - pago);
            const troco = Math.max(0, pago - total);

            el('pago').textContent = moeda(pago / 100);
            el('restante').textContent = moeda(restante / 100);
            el('troco').textContent = moeda(troco / 100);
            el('linha-restante').classList.toggle('hidden', restante === 0);
            el('linha-troco').classList.toggle('hidden', troco === 0);

            let problema = '';
            if (troco > comTroco) problema = 'O valor passou do total, e só dinheiro dá troco.';
            const pronto = estado.pagamentos.length > 0 && restante === 0 && !problema
                && estado.pagamentos.every(p => p.valor > 0);
            el('finalizar').disabled = !pronto;
            erro(problema);
        }

        function adicionarForma(codigo) {
            if (!estado?.conta || estado.etapa !== 'pagamento') return;
            const existente = estado.pagamentos.findIndex(p => p.forma === codigo);
            if (existente >= 0) {
                el('pagamentos').querySelector(`[data-indice="${existente}"]`)?.focus();
                return;
            }
            const total = centavos(estado.conta.total);
            const pago = estado.pagamentos.reduce((t, p) => t + centavos(p.valor), 0);
            estado.pagamentos.push({ forma: codigo, valor: Math.max(0, total - pago) / 100 });
            desenharPagamentos();
        }

        function removerForma(indice) {
            estado.pagamentos.splice(indice, 1);
            desenharPagamentos();
        }

        async function finalizar() {
            if (!estado?.conta || estado.etapa !== 'pagamento' || el('finalizar').disabled) return;
            const botao = el('finalizar');
            botao.disabled = true;
            el('voltar').disabled = true;
            botao.textContent = 'Finalizando...';
            erro('');

            try {
                const resp = await fetch(estado.urlFechar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ conta_id: estado.conta.id, pagamentos: estado.pagamentos }),
                });
                if (resp.redirected) return location.reload();
                const dados = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(dados.message || 'Não foi possível fechar a conta.');

                const aoFechar = estado.aoFechar;
                fechar();
                aoFechar?.(dados);
            } catch (e) {
                erro(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor. Tente de novo.' : e.message);
                botao.disabled = false;
            } finally {
                botao.textContent = 'Finalizar conta';
                el('voltar').disabled = false;
            }
        }

        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && estado) fechar(); });

        return { abrir, fechar, irParaPagamento, voltarResumo, adicionarForma, removerForma, finalizar };
    })();
</script>
