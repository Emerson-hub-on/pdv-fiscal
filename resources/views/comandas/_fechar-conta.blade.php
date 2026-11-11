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

                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mt-4 mb-2">Pagamento <span class="font-normal normal-case tracking-normal text-slate-400">(pode usar mais de uma forma)</span></p>
                {{-- Valor primeiro, forma ao lado → "Adicionar pagamento" --}}
                <div class="flex items-stretch gap-2">
                    <label class="w-36 shrink-0 flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-3 focus-within:bg-white focus-within:border-[#10284A]/40 focus-within:ring-2 focus-within:ring-[#10284A]/30">
                        <span class="text-slate-400 text-sm">R$</span>
                        <input type="text" inputmode="decimal" data-fc="valor" placeholder="0,00" autocomplete="off" aria-label="Valor"
                               class="w-full min-w-0 bg-transparent py-3 text-base text-right font-semibold outline-none">
                    </label>
                    <select data-fc="forma" aria-label="Forma de pagamento"
                            class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-base text-slate-800 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none">
                        <option value="">Forma de pagamento</option>
                        @foreach ($formas as $forma)
                            <option value="{{ $forma->codigo }}">{{ $forma->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" onclick="FecharConta.adicionarPagamento()" data-fc="adicionar"
                        class="mt-2 w-full rounded-xl px-4 py-3 text-sm font-bold ring-1 ring-[#10284A]/30 bg-white text-[#10284A] active:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed">
                    + Adicionar pagamento
                </button>
                <div data-fc="pagamentos" class="mt-3 flex flex-col gap-2"></div>

                <div class="mt-3 rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm flex flex-col gap-1">
                    <div class="flex justify-between"><span class="text-slate-500">Pago</span><span class="font-semibold" data-fc="pago"></span></div>
                    <div class="flex justify-between" data-fc="linha-restante"><span class="text-slate-500">Falta</span><span class="font-bold text-red-600" data-fc="restante"></span></div>
                    <div class="flex justify-between" data-fc="linha-troco"><span class="text-slate-500">Troco</span><span class="font-bold text-green-700" data-fc="troco"></span></div>
                </div>

                {{-- Conta acima do limite: aviso; o nome e o CPF são informados numa janelinha (pode finalizar sem, com confirmação) --}}
                <div data-fc="area-cliente" class="hidden mt-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">Dados do cliente para a nota fiscal</p>
                    <div data-fc="cliente-vazio">
                        <p class="text-xs text-amber-800" data-fc="aviso-cliente"></p>
                        <button type="button" onclick="FecharConta.abrirCliente()" data-fc="abrir-cliente"
                                class="mt-1 text-sm font-bold text-[#10284A] underline underline-offset-2 active:opacity-70">→ Clique aqui para adicionar.</button>
                    </div>
                    <div data-fc="cliente-preenchido" class="hidden"><div class="flex items-center justify-between gap-3 mt-1">
                        <p class="min-w-0 text-sm text-slate-800">
                            <span class="block font-semibold truncate" data-fc="cliente-resumo-nome"></span>
                            <span class="block text-xs text-slate-600" data-fc="cliente-resumo-cpf"></span>
                        </p>
                        <button type="button" onclick="FecharConta.abrirCliente()"
                                class="shrink-0 rounded-xl px-3 py-2 text-sm font-semibold ring-1 ring-amber-300 bg-white text-amber-900 active:bg-amber-100">Alterar</button>
                    </div></div>
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

    {{-- Janelinha: nome e CPF do cliente para a NFC-e --}}
    <div data-fc="modal-cliente" class="fixed inset-0 z-[60] bg-black/50 hidden items-center justify-center p-4"
         onclick="if (event.target === this) FecharConta.fecharCliente()">
        <div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl p-5">
            <h3 class="font-bold text-slate-900 text-lg">Dados do cliente</h3>
            <p class="text-xs text-amber-800 mt-0.5" data-fc="aviso-modal-cliente"></p>
            <label class="block mt-4 text-xs font-semibold text-slate-500">Nome
                <input type="text" data-fc="cliente-nome" maxlength="60" placeholder="Nome do cliente" autocomplete="off"
                       class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-base font-normal text-slate-900 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none">
            </label>
            <label class="block mt-3 text-xs font-semibold text-slate-500">CPF
                <input type="text" data-fc="cliente-cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" autocomplete="off"
                       class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-base font-normal text-slate-900 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none">
            </label>
            <p data-fc="erro-cliente" class="hidden mt-3 rounded-xl bg-red-50 text-red-600 px-3 py-2 text-sm"></p>
            <div class="mt-5 flex items-center gap-2">
                <button type="button" onclick="FecharConta.fecharCliente()"
                        class="rounded-2xl px-4 py-3.5 text-base font-semibold text-slate-600 ring-1 ring-slate-200 active:bg-slate-100">Cancelar</button>
                <button type="button" onclick="FecharConta.salvarCliente()" data-fc="salvar-cliente"
                        class="flex-1 rounded-2xl bg-[#10284A] text-amber-50 px-4 py-3.5 text-base font-bold active:opacity-90">Salvar</button>
            </div>
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

        // NFC-e acima deste valor exige o CPF do cliente (config fiscal.cpf_obrigatorio_acima; 0 = sem a regra)
        const LIMITE_CPF = @json((float) config('fiscal.cpf_obrigatorio_acima', 500));
        const limiteTexto = () => (Number.isInteger(LIMITE_CPF) ? String(LIMITE_CPF) : formatarCampo(LIMITE_CPF)) + ' reais';
        const acimaDoLimite = () => LIMITE_CPF > 0 && centavos(estado?.conta?.total ?? 0) > centavos(LIMITE_CPF);
        const soDigitos = (v) => String(v ?? '').replace(/\D/g, '');
        function cpfValido(valor) {
            const c = soDigitos(valor);
            if (c.length !== 11 || /^(\d)\1{10}$/.test(c)) return false;
            const digito = (n) => {
                let soma = 0;
                for (let i = 0; i < n; i++) soma += Number(c[i]) * (n + 1 - i);
                const resto = (soma * 10) % 11;
                return resto === 10 ? 0 : resto;
            };
            return digito(9) === Number(c[9]) && digito(10) === Number(c[10]);
        }

        const formatarCpf = (v) => {
            const c = soDigitos(v).slice(0, 11);
            return c.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
        };

        let estado = null; // { conta, etapa: 'resumo'|'pagamento', pagamentos: [{ forma, valor }], cliente: { nome, cpf }, urlFechar, aoFechar }

        function abrirModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
        function fechar() { fecharCliente(); modal.classList.add('hidden'); modal.classList.remove('flex'); estado = null; }

        function erro(texto) {
            el('erro').textContent = texto || '';
            el('erro').classList.toggle('hidden', !texto);
        }

        async function abrir({ urlConta, urlFechar, aoFechar }) {
            estado = { conta: null, etapa: 'resumo', pagamentos: [], cliente: { nome: '', cpf: '' }, urlFechar, aoFechar };
            el('titulo').textContent = 'Carregando...';
            el('resumo').innerHTML = '';
            el('total').textContent = '';
            el('pagamentos').innerHTML = '';
            el('valor').value = '';
            el('forma').value = '';
            el('area-paga').classList.add('hidden');
            el('area-cliente').classList.add('hidden');
            fecharCliente();
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

            el('area-cliente').classList.toggle('hidden', !acimaDoLimite());
            el('aviso-cliente').textContent = `CPF obrigatório para emissão de vendas acima de ${limiteTexto()}.`;
            desenharCliente();
        }

        // ---- Nome e CPF do cliente (conta acima do limite) ----
        function desenharCliente() {
            const { nome, cpf } = estado.cliente;
            el('cliente-vazio').classList.toggle('hidden', !!cpf);
            el('cliente-preenchido').classList.toggle('hidden', !cpf);
            el('cliente-resumo-nome').textContent = nome || 'Cliente sem nome';
            el('cliente-resumo-cpf').textContent = cpf ? 'CPF ' + formatarCpf(cpf) : '';
        }

        function erroCliente(texto) {
            el('erro-cliente').textContent = texto || '';
            el('erro-cliente').classList.toggle('hidden', !texto);
        }

        function abrirCliente() {
            if (!estado?.conta || estado.etapa !== 'pagamento') return;
            // O primeiro nome dos pedidos da mesa já vem preenchido (o garçom confirma)
            el('cliente-nome').value = estado.cliente.nome || estado.conta.clientes?.[0] || '';
            el('cliente-cpf').value = formatarCpf(estado.cliente.cpf);
            el('aviso-modal-cliente').textContent = `CPF obrigatório para emissão de vendas acima de ${limiteTexto()}.`;
            erroCliente('');
            el('modal-cliente').classList.remove('hidden');
            el('modal-cliente').classList.add('flex');
            el(estado.cliente.nome || !el('cliente-nome').value ? 'cliente-cpf' : 'cliente-nome').focus();
        }

        function fecharCliente() {
            el('modal-cliente').classList.add('hidden');
            el('modal-cliente').classList.remove('flex');
        }

        const clienteAberto = () => !el('modal-cliente').classList.contains('hidden');

        function salvarCliente() {
            if (!estado) return;
            const nome = el('cliente-nome').value.trim();
            const cpf = soDigitos(el('cliente-cpf').value);
            if (!cpf) { erroCliente('Informe o CPF do cliente.'); el('cliente-cpf').focus(); return; }
            if (!cpfValido(cpf)) { erroCliente('CPF inválido. Confira os números.'); el('cliente-cpf').focus(); return; }
            estado.cliente = { nome, cpf };
            fecharCliente();
            desenharCliente();
        }

        el('cliente-cpf').addEventListener('input', (e) => { e.target.value = formatarCpf(e.target.value); });
        ['cliente-nome', 'cliente-cpf'].forEach(nome => el(nome).addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); salvarCliente(); }
        }));

        function voltarResumo() {
            if (!estado) return;
            estado.etapa = 'resumo';
            erro('');
            mostrarEtapa();
        }

        const centavosPagos = () => estado.pagamentos.reduce((t, p) => t + centavos(p.valor), 0);
        const centavosFaltando = () => Math.max(0, centavos(estado.conta.total) - centavosPagos());

        function desenharPagamentos() {
            el('pagamentos').innerHTML = estado.pagamentos.map((p, i) => `
                <div class="flex items-center gap-2 rounded-xl bg-slate-50 ring-1 ring-slate-200 pl-4 pr-1 py-1">
                    <span class="flex-1 min-w-0 font-semibold text-slate-800 truncate">${escHtml(FORMAS[p.forma].descricao)}</span>
                    <span class="shrink-0 font-bold text-slate-900">${escHtml(moeda(p.valor))}</span>
                    <button type="button" onclick="FecharConta.removerForma(${i})" aria-label="Remover"
                            class="w-10 h-10 rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600 text-xl leading-none">&times;</button>
                </div>`).join('')
                || '<p class="text-sm text-slate-400">Informe o valor, escolha a forma e toque em "Adicionar pagamento".</p>';

            // O campo de valor já sugere o que falta pagar
            const falta = centavosFaltando();
            el('valor').value = falta > 0 ? formatarCampo(falta / 100) : '';
            el('forma').value = '';
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
            el('adicionar').disabled = restante === 0;
            erro(problema);
        }

        // Valor digitado + forma escolhida → entra na lista (a mesma forma de novo soma no valor dela)
        function adicionarPagamento() {
            if (!estado?.conta || estado.etapa !== 'pagamento') return;
            const valor = lerValor(el('valor').value);
            const codigo = el('forma').value;
            const falta = centavosFaltando();

            if (falta === 0) return erro('A conta já está toda paga.');
            if (!valor) { erro('Informe o valor do pagamento.'); return el('valor').focus(); }
            if (!FORMAS[codigo]) { erro('Escolha a forma de pagamento.'); return el('forma').focus(); }
            if (centavos(valor) > falta && !FORMAS[codigo].permite_troco) {
                erro(`O valor passou do que falta (${moeda(falta / 100)}). Só ${dinheiroTexto()} dá troco.`);
                return el('valor').focus();
            }

            const existente = estado.pagamentos.find(p => p.forma === codigo);
            if (existente) existente.valor = (centavos(existente.valor) + centavos(valor)) / 100;
            else estado.pagamentos.push({ forma: codigo, valor });
            erro('');
            desenharPagamentos();
        }

        const dinheiroTexto = () => Object.values(FORMAS).filter(f => f.permite_troco).map(f => f.descricao.toLowerCase()).join(' ou ') || 'dinheiro';

        el('valor').addEventListener('blur', (e) => { e.target.value = formatarCampo(lerValor(e.target.value)); });
        el('valor').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); adicionarPagamento(); } });

        function removerForma(indice) {
            estado.pagamentos.splice(indice, 1);
            desenharPagamentos();
        }

        async function finalizar() {
            if (!estado?.conta || estado.etapa !== 'pagamento' || el('finalizar').disabled) return;

            // Acima do limite: sem o CPF só finaliza depois de confirmar (ao cancelar, abre a janelinha do CPF)
            const cliente = acimaDoLimite() ? { ...estado.cliente } : { nome: '', cpf: '' };
            if (acimaDoLimite() && !cliente.cpf
                && !confirm(`CPF obrigatório para emissão de vendas acima de ${limiteTexto()}, tem certeza que deseja finalizar sem informar o nome e CPF ?`)) {
                abrirCliente();
                return;
            }

            const botao = el('finalizar');
            botao.disabled = true;
            el('voltar').disabled = true;
            botao.textContent = 'Finalizando...';
            erro('');

            try {
                const resp = await fetch(estado.urlFechar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ conta_id: estado.conta.id, pagamentos: estado.pagamentos, cliente }),
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

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || !estado) return;
            clienteAberto() ? fecharCliente() : fechar();
        });

        return { abrir, fechar, irParaPagamento, voltarResumo, adicionarPagamento, removerForma, finalizar,
                 abrirCliente, fecharCliente, salvarCliente };
    })();
</script>
