{{-- Janela "Impressoras" (ícone de impressora no topo da Lista de Pedidos): cadastro das impressoras do
     restaurante (balcão, cozinha, churrascaria...), o que cada uma imprime e o teste de impressão.
     Uso: Impressoras.abrir(). Depois de salvar, chama window.aoMudarImpressoras(lista) se existir. --}}
@php
    $campo = 'w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none';
    $rotulo = 'block text-xs font-semibold text-slate-600 mb-1';
@endphp
<div id="modal-impressoras" class="fixed inset-0 z-40 bg-black/50 hidden items-center justify-center p-4"
     onclick="if (event.target === this) Impressoras.fechar()">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl max-h-[92vh] flex flex-col">
        <div class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#10284A] text-amber-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="font-bold text-slate-900 text-lg" data-imp="titulo">Impressoras</h2>
                <p class="text-xs text-slate-500">Os pedidos novos saem sozinhos nas impressoras ativas, cada uma com os itens do seu setor.</p>
            </div>
            <button type="button" onclick="Impressoras.fechar()" aria-label="Fechar"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
        </div>

        {{-- Lista --}}
        <div data-imp="tela-lista" class="flex-1 overflow-y-auto px-6 py-4">
            <div data-imp="lista" class="flex flex-col gap-3"></div>
        </div>

        {{-- Formulário --}}
        <form data-imp="tela-form" class="hidden flex-1 overflow-y-auto px-6 py-4" onsubmit="event.preventDefault(); Impressoras.salvar()">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="{{ $rotulo }}" for="imp-nome">Nome / setor</label>
                    <input id="imp-nome" type="text" maxlength="60" placeholder="Ex.: Cozinha, Balcão, Churrascaria" class="{{ $campo }}">
                </div>

                <div class="sm:col-span-2">
                    <span class="{{ $rotulo }}">Conexão</span>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="imp-conexao" value="rede" class="accent-[#10284A]" onchange="Impressoras.trocarConexao()">
                            <span class="text-sm"><span class="font-semibold text-slate-800">Rede</span> <span class="block text-xs text-slate-500">Impressora com cabo de rede / Wi-Fi (IP)</span></span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="imp-conexao" value="usb" class="accent-[#10284A]" onchange="Impressoras.trocarConexao()">
                            <span class="text-sm"><span class="font-semibold text-slate-800">USB no servidor</span> <span class="block text-xs text-slate-500">Ligada no computador do servidor</span></span>
                        </label>
                    </div>
                </div>

                <div data-imp="campos-rede" class="sm:col-span-2"><div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="{{ $rotulo }}" for="imp-ip">IP da impressora</label>
                        <input id="imp-ip" type="text" maxlength="120" placeholder="192.168.1.50" class="{{ $campo }}">
                    </div>
                    <div>
                        <label class="{{ $rotulo }}" for="imp-porta">Porta</label>
                        <input id="imp-porta" type="number" min="1" max="65535" placeholder="9100" class="{{ $campo }}">
                    </div>
                    <p class="col-span-3 -mt-2 text-xs text-slate-400">O IP sai na página de configuração da impressora (segure o botão FEED ao ligar). A porta quase sempre é 9100.</p>
                </div></div>

                <div data-imp="campos-usb" class="hidden sm:col-span-2">
                    <label class="{{ $rotulo }}" for="imp-compartilhada">Nome da impressora compartilhada</label>
                    <input id="imp-compartilhada" type="text" maxlength="120" placeholder="Ex.: COZINHA" class="{{ $campo }}">
                    <p class="mt-1 text-xs text-slate-400">No Windows do servidor: instale a impressora, abra Propriedades da impressora → Compartilhamento → "Compartilhar esta impressora" e use o mesmo nome aqui. No Linux, informe o caminho (ex.: /dev/usb/lp0).</p>
                </div>

                <div>
                    <label class="{{ $rotulo }}" for="imp-colunas">Papel</label>
                    <select id="imp-colunas" class="{{ $campo }}">
                        <option value="48">80 mm (48 colunas)</option>
                        <option value="42">80 mm (42 colunas)</option>
                        <option value="32">58 mm (32 colunas)</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $rotulo }}" for="imp-copias">Vias</label>
                    <select id="imp-copias" class="{{ $campo }}">
                        @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}">{{ $n }} {{ $n === 1 ? 'via' : 'vias' }}</option>@endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <span class="{{ $rotulo }}">O que imprime</span>
                    <label class="flex items-start gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer">
                        <input id="imp-completo" type="checkbox" class="mt-0.5 accent-[#10284A]" onchange="Impressoras.trocarCompleto()">
                        <span class="text-sm"><span class="font-semibold text-slate-800">Pedido completo</span> <span class="block text-xs text-slate-500">Todos os itens do pedido (ex.: balcão ou caixa)</span></span>
                    </label>
                    <div data-imp="categorias-area" class="mt-2">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="text-xs text-slate-500">Ou só os itens destas categorias do cardápio:</p>
                            <button type="button" data-imp="marcar-todas" onclick="Impressoras.marcarTodas()" class="shrink-0 text-xs font-semibold text-[#10284A] hover:underline">Marcar todas</button>
                        </div>
                        <div data-imp="categorias" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input id="imp-precos" type="checkbox" class="accent-[#10284A]"> Mostrar preços e total
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input id="imp-ativo" type="checkbox" class="accent-[#10284A]"> Ativa (imprime os pedidos novos)
                </label>
            </div>
        </form>

        <div class="px-6 py-4 border-t border-slate-100">
            <p data-imp="mensagem" class="hidden mb-3 rounded-xl px-3 py-2 text-sm"></p>
            <div data-imp="botoes-lista" class="flex items-center gap-2">
                <button type="button" onclick="Impressoras.fechar()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Fechar</button>
                @if ($podeAlterar)
                    <button type="button" onclick="Impressoras.editar(null)" class="ml-auto rounded-xl bg-[#10284A] text-amber-50 px-4 py-2.5 text-sm font-semibold hover:bg-[#163a68]">+ Nova impressora</button>
                @endif
            </div>
            <div data-imp="botoes-form" class="hidden">
                <div class="flex items-center gap-2">
                    <button type="button" data-imp="excluir" onclick="Impressoras.excluir()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Excluir</button>
                    <button type="button" onclick="Impressoras.voltar()" class="ml-auto rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Cancelar</button>
                    <button type="button" data-imp="salvar" onclick="Impressoras.salvar()" class="rounded-xl bg-[#10284A] text-amber-50 px-4 py-2.5 text-sm font-semibold hover:bg-[#163a68] disabled:opacity-50">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.Impressoras = (function () {
        const URL_LISTA = @json(route('comanda-impressoras.index'));
        const URL_SALVAR = @json(route('comanda-impressoras.store'));
        const URL_ITEM = @json(route('comanda-impressoras.update', ['impressora' => '__ID__']));
        const URL_TESTAR = @json(route('comanda-impressoras.testar', ['impressora' => '__ID__']));
        const PODE = @json((bool) $podeAlterar);
        const CSRF = @json(csrf_token());
        const modal = document.getElementById('modal-impressoras');
        const el = (n) => modal.querySelector(`[data-imp="${n}"]`);
        const id = (n) => document.getElementById(n);

        let dados = { impressoras: [], categorias: [] };
        let editando = null; // null = lista; 0 = nova; >0 = id

        async function pedir(url, metodo = 'GET', corpo = null) {
            const resp = await fetch(url, {
                method: metodo,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: corpo ? JSON.stringify(corpo) : null,
            });
            if (resp.redirected) { location.reload(); throw new Error('Sessão expirada.'); }
            const json = await resp.json().catch(() => ({}));
            if (!resp.ok) throw new Error(json.message || 'Não foi possível concluir.');
            return json;
        }

        function mensagem(texto, erro = false) {
            const m = el('mensagem');
            m.textContent = texto || '';
            m.className = `mb-3 rounded-xl px-3 py-2 text-sm ${erro ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'}`;
            m.classList.toggle('hidden', !texto);
        }

        function receber(json) {
            dados = { impressoras: json.impressoras, categorias: json.categorias };
            window.aoMudarImpressoras?.(dados.impressoras);
        }

        async function abrir() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            mensagem('');
            voltar();
            el('lista').innerHTML = '<p class="py-8 text-center text-sm text-slate-400">Carregando...</p>';
            try {
                receber(await pedir(URL_LISTA));
                desenharLista();
            } catch (e) {
                mensagem(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message, true);
            }
        }

        function fechar() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            editando = null;
        }

        function mostrar(tela) {
            const form = tela === 'form';
            el('tela-lista').classList.toggle('hidden', form);
            el('tela-form').classList.toggle('hidden', !form);
            el('botoes-lista').classList.toggle('hidden', form);
            el('botoes-form').classList.toggle('hidden', !form);
        }

        function voltar() {
            editando = null;
            el('titulo').textContent = 'Impressoras';
            mostrar('lista');
        }

        function setor(i) {
            if (i.pedido_completo) return 'Pedido completo';
            const nomes = dados.categorias.filter(c => i.categorias.includes(c.id)).map(c => c.nome);
            if (i.sem_categoria) nomes.push('Sem categoria');
            return nomes.join(', ') || '—';
        }

        // Categorias do cardápio que nenhuma impressora ativa recebe (os itens delas não saem em lugar nenhum)
        function semImpressora() {
            const ativas = dados.impressoras.filter(i => i.ativo);
            if (!ativas.length || ativas.some(i => i.pedido_completo)) return [];
            const faltam = dados.categorias.filter(c => !ativas.some(i => i.categorias.includes(c.id))).map(c => c.nome);
            if (!ativas.some(i => i.sem_categoria)) faltam.push('itens sem categoria');
            return faltam;
        }

        function desenharLista() {
            const faltam = semImpressora();
            const alerta = faltam.length ? `
                <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">Não saem em nenhuma impressora: ${escHtml(faltam.join(', '))}.</p>
                    <p class="text-xs mt-0.5">Marque essas categorias em alguma impressora, ou "Pedido completo" no balcão.</p>
                </div>` : '';
            el('lista').innerHTML = alerta + dados.impressoras.map(i => `
                <div class="rounded-2xl ring-1 ring-slate-200 px-4 py-3 flex items-center gap-3 ${i.ativo ? '' : 'opacity-60'}">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 flex items-center gap-2">${escHtml(i.nome)}
                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold ${i.ativo ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500'}">${i.ativo ? 'Ativa' : 'Inativa'}</span></p>
                        <p class="text-xs text-slate-500">${escHtml(i.descricao)} · ${i.colunas === 32 ? '58 mm' : '80 mm'}${i.copias > 1 ? ` · ${i.copias} vias` : ''}</p>
                        <p class="text-xs text-slate-500 truncate">Imprime: <span class="font-medium text-slate-700">${escHtml(setor(i))}</span></p>
                    </div>
                    <button type="button" onclick="Impressoras.testar(${i.id}, this)" class="shrink-0 rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-50">Testar</button>
                    ${PODE ? `<button type="button" onclick="Impressoras.editar(${i.id})" class="shrink-0 rounded-xl px-3 py-2 text-xs font-semibold text-[#10284A] ring-1 ring-[#10284A]/30 hover:bg-slate-50">Editar</button>` : ''}
                </div>`).join('')
                || `<div class="py-10 text-center">
                        <p class="font-semibold text-slate-700">Nenhuma impressora configurada</p>
                        <p class="mt-1 text-sm text-slate-500">Cadastre uma impressora para cada setor (balcão, cozinha, churrascaria...).<br>Os pedidos novos saem sozinhos em cada uma.</p>
                    </div>`;
        }

        function editar(impressoraId) {
            const i = dados.impressoras.find(x => x.id === impressoraId)
                ?? { id: 0, nome: '', conexao: 'rede', endereco: '', porta: 9100, colunas: 48, copias: 1,
                     pedido_completo: false, sem_categoria: false, mostrar_precos: false, ativo: true, categorias: [] };
            editando = i.id;
            el('titulo').textContent = i.id ? `Editar · ${i.nome}` : 'Nova impressora';
            mensagem('');

            id('imp-nome').value = i.nome;
            modal.querySelectorAll('[name="imp-conexao"]').forEach(r => r.checked = r.value === i.conexao);
            id('imp-ip').value = i.conexao === 'rede' ? i.endereco : '';
            id('imp-porta').value = i.porta || 9100;
            id('imp-compartilhada').value = i.conexao === 'usb' ? i.endereco : '';
            id('imp-colunas').value = String(i.colunas);
            id('imp-copias').value = String(i.copias);
            id('imp-completo').checked = i.pedido_completo;
            id('imp-precos').checked = i.mostrar_precos;
            id('imp-ativo').checked = i.ativo;

            el('categorias').innerHTML = dados.categorias.map(c => `
                <label class="flex items-center gap-1.5 rounded-xl ring-1 ring-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                    <input type="checkbox" value="${c.id}" data-categoria class="accent-[#10284A]" ${i.categorias.includes(c.id) ? 'checked' : ''}> ${escHtml(c.nome)}
                </label>`).join('') + `
                <label class="flex items-center gap-1.5 rounded-xl ring-1 ring-slate-300 px-3 py-2 text-sm cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                    <input type="checkbox" id="imp-sem-categoria" class="accent-[#10284A]" ${i.sem_categoria ? 'checked' : ''}> Itens sem categoria
                </label>`
                + (dados.categorias.length ? '' : '<p class="w-full text-xs text-slate-400">Nenhuma categoria no cardápio ainda (crie no cadastro do item em Comandas → Cardápio).</p>');

            el('categorias').querySelectorAll('input').forEach(c => c.addEventListener('change', atualizarMarcarTodas));
            atualizarMarcarTodas();
            el('excluir').classList.toggle('hidden', !i.id);
            trocarConexao();
            trocarCompleto();
            mostrar('form');
            id('imp-nome').focus();
        }

        // Marca todas as categorias (e os itens sem categoria); se já estão todas, desmarca
        function marcarTodas() {
            const caixas = [...el('categorias').querySelectorAll('input[type="checkbox"]')];
            const todas = caixas.every(c => c.checked);
            caixas.forEach(c => c.checked = !todas);
            atualizarMarcarTodas();
        }

        function atualizarMarcarTodas() {
            const caixas = [...el('categorias').querySelectorAll('input[type="checkbox"]')];
            el('marcar-todas').textContent = caixas.length && caixas.every(c => c.checked) ? 'Desmarcar todas' : 'Marcar todas';
        }

        function trocarConexao() {
            const rede = modal.querySelector('[name="imp-conexao"]:checked')?.value !== 'usb';
            el('campos-rede').classList.toggle('hidden', !rede);
            el('campos-usb').classList.toggle('hidden', rede);
        }

        function trocarCompleto() {
            el('marcar-todas').disabled = id('imp-completo').checked;
            el('categorias-area').classList.toggle('opacity-40', id('imp-completo').checked);
            el('categorias-area').querySelectorAll('input').forEach(c => c.disabled = id('imp-completo').checked);
        }

        async function salvar() {
            if (editando === null) return;
            const conexao = modal.querySelector('[name="imp-conexao"]:checked')?.value || 'rede';
            const corpo = {
                nome: id('imp-nome').value.trim(),
                conexao,
                endereco: (conexao === 'rede' ? id('imp-ip') : id('imp-compartilhada')).value.trim(),
                porta: conexao === 'rede' ? (parseInt(id('imp-porta').value, 10) || 9100) : null,
                colunas: parseInt(id('imp-colunas').value, 10),
                copias: parseInt(id('imp-copias').value, 10),
                pedido_completo: id('imp-completo').checked,
                sem_categoria: !!id('imp-sem-categoria')?.checked,
                mostrar_precos: id('imp-precos').checked,
                ativo: id('imp-ativo').checked,
                categorias: [...modal.querySelectorAll('[data-categoria]:checked')].map(c => parseInt(c.value, 10)),
            };
            const botao = el('salvar');
            botao.disabled = true;
            try {
                const json = editando
                    ? await pedir(URL_ITEM.replace('__ID__', editando), 'PUT', corpo)
                    : await pedir(URL_SALVAR, 'POST', corpo);
                receber(json);
                voltar();
                desenharLista();
                mensagem(`Impressora "${corpo.nome}" salva. Use "Testar" para conferir a conexão.`);
            } catch (e) {
                mensagem(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message, true);
            } finally {
                botao.disabled = false;
            }
        }

        async function excluir() {
            const i = dados.impressoras.find(x => x.id === editando);
            if (!i || !confirm(`Excluir a impressora "${i.nome}"?\n\nOs pedidos deixam de sair nela.`)) return;
            try {
                receber(await pedir(URL_ITEM.replace('__ID__', i.id), 'DELETE'));
                voltar();
                desenharLista();
                mensagem(`Impressora "${i.nome}" excluída.`);
            } catch (e) {
                mensagem(e.message, true);
            }
        }

        async function testar(impressoraId, botao) {
            botao.disabled = true;
            const texto = botao.textContent;
            botao.textContent = 'Enviando...';
            mensagem('');
            try {
                mensagem((await pedir(URL_TESTAR.replace('__ID__', impressoraId), 'POST')).message);
            } catch (e) {
                mensagem(e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message, true);
            } finally {
                botao.disabled = false;
                botao.textContent = texto;
            }
        }

        // Esc: no formulário volta para a lista; na lista fecha
        window.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || modal.classList.contains('hidden')) return;
            e.stopImmediatePropagation();
            editando !== null ? voltar() : fechar();
        }, true);

        return { abrir, fechar, editar, voltar, salvar, excluir, testar, trocarConexao, trocarCompleto, marcarTodas,
                 lista: () => dados.impressoras, carregar: async () => { receber(await pedir(URL_LISTA)); return dados.impressoras; } };
    })();
</script>
