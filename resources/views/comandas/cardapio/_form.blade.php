@php
    extract(config('estilo.formulario'));
    $editando = $item->exists;
    $produto = $item->produto;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 {{ $cartaoCls }} p-6">
        <x-form.secao titulo="Produto" icone="caixa">
            Nome, valor e estoque vêm do cadastro do produto (para mudar, edite o produto em Cadastros → Produtos).
        </x-form.secao>

        @if ($editando)
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 mb-5 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-800 truncate">{{ $item->nomeExibido() }}</p>
                    <p class="text-xs text-slate-500">
                        <span class="font-mono">{{ $item->codigoExibido() ?: 'Sem código' }}</span>
                        @if ($produto->categoria) · {{ $produto->categoria->nome }} @endif
                        · Estoque: {{ rtrim(rtrim(number_format($item->estoqueAtual(), 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_comercial ?: 'UN' }}
                    </p>
                </div>
                <p class="shrink-0 text-lg font-bold text-[#10284A]">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</p>
            </div>
        @else
            {{-- Busca de produto igual à do caixa: começou a digitar, abre o modal "Selecionar Produto"
                 (só ativos que ainda não estão no cardápio) --}}
            <div class="mb-5">
                <label class="{{ $labelCls }}">Produto <span class="text-red-500">*</span></label>
                <input type="hidden" name="produto_id" id="produto-id" value="{{ old('produto_id') }}">
                <input type="hidden" name="produto_variante_id" id="produto-variante-id" value="{{ old('produto_variante_id') }}">
                <input type="text" id="busca-produto" autocomplete="off" placeholder="Digite o nome, código interno ou código de barras..."
                       value="{{ old('produto_nome') }}" name="produto_nome" class="{{ $inputCls }}">
                @error('produto_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror

                <div id="produto-escolhido" class="hidden mt-3 rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800 truncate" data-campo="nome"></p>
                        <p class="text-xs text-slate-500" data-campo="detalhe"></p>
                    </div>
                    <p class="shrink-0 text-lg font-bold text-[#10284A]" data-campo="preco"></p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-5">
            <div class="col-span-2">
                <label class="{{ $labelCls }}">Descrição no cardápio <span class="{{ $opcionalCls }}">(opcional)</span></label>
                <textarea name="descricao" id="descricao" rows="4" maxlength="500" class="{{ $inputCls }}"
                          placeholder="{{ $produto?->descricao ?: 'Ex.: Pão brioche, hambúrguer 180 g, queijo cheddar e bacon.' }}">{{ old('descricao', $item->descricao) }}</textarea>
                <p class="text-xs text-slate-400 mt-1">O que o garçom lê para o cliente. Em branco, usa a descrição do cadastro do produto.</p>
                @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $labelCls }}">Ordem no cardápio</label>
                <input type="number" name="ordem" min="0" max="9999" value="{{ old('ordem', $item->ordem) }}" class="{{ $inputCls }}">
                <p class="text-xs text-slate-400 mt-1">Menor número aparece primeiro para o garçom.</p>
                @error('ordem') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center">
                <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                    <input type="hidden" name="ativo" value="0">
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" name="ativo" value="1" class="peer sr-only" @checked(old('ativo', $item->ativo))>
                        <span class="w-11 h-6 rounded-full bg-slate-300 transition peer-checked:bg-[#10284A]"></span>
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Ativo</span>
                        <span class="block text-xs text-slate-500">Inativo não aparece para o garçom.</span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    {{-- Foto --}}
    <div class="{{ $cartaoCls }} p-6 flex flex-col">
        <x-form.secao titulo="Foto" icone="documento">
            Aparece para o garçom na hora do pedido.
        </x-form.secao>

        <label for="imagem" class="relative block aspect-4/3 rounded-xl overflow-hidden bg-slate-100 ring-1 ring-slate-200 border-2 border-dashed border-slate-300 cursor-pointer hover:border-[#10284A]/40 transition">
            <img id="previa-imagem" src="{{ $item->urlImagem() }}" alt="Foto do item"
                 class="w-full h-full object-cover {{ $item->imagem ? '' : 'hidden' }}">
            <div id="previa-vazia" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 text-center px-4 {{ $item->imagem ? 'hidden' : '' }}">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-medium">Clique para escolher a foto</span>
                <span class="text-xs">JPG, PNG ou WEBP, até 8 MB</span>
            </div>
        </label>
        <input type="file" name="imagem" id="imagem" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="previaImagem(this)">
        <p class="text-xs text-slate-400 mt-2">A foto é reduzida automaticamente para ficar leve no celular do garçom. Prefira foto na horizontal.</p>
        @error('imagem') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror

        <div class="flex items-center gap-2 mt-3">
            <label for="imagem" class="{{ $botaoSecundarioCls }} cursor-pointer">{{ $item->imagem ? 'Trocar foto' : 'Escolher foto' }}</label>
            @if ($item->imagem)
                <label class="inline-flex items-center gap-2 text-sm text-red-600 cursor-pointer select-none ml-auto">
                    <input type="checkbox" name="remover_imagem" value="1" id="remover-imagem" class="rounded border-slate-300 text-red-600 focus:ring-red-500"
                           onchange="marcarRemocao(this.checked)">
                    Remover foto
                </label>
            @endif
        </div>
    </div>
</div>

<div class="{{ $cartaoCls }} px-6 py-4 mt-6 flex items-center gap-3">
    @if ($editando)
        <button type="submit" form="form-excluir-item"
                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50 transition">
            Retirar do cardápio
        </button>
    @endif

    <div class="ml-auto flex flex-row-reverse gap-3">
        <button type="submit" class="{{ $botaoPrimarioCls }}">
            <x-form.icone nome="check" /> Salvar
        </button>
        <a href="{{ route('cardapio.index') }}" class="{{ $botaoCancelarCls }}">Cancelar</a>
    </div>
</div>

<script>
    const fotoOriginal = @json($item->urlImagem());

    function mostrarPrevia(src) {
        const img = document.getElementById('previa-imagem');
        img.classList.toggle('hidden', !src);
        document.getElementById('previa-vazia').classList.toggle('hidden', !!src);
        if (src) img.src = src;
    }

    function previaImagem(input) {
        const arquivo = input.files[0];
        if (!arquivo) return mostrarPrevia(fotoOriginal);
        const remover = document.getElementById('remover-imagem');
        if (remover) remover.checked = false;
        mostrarPrevia(URL.createObjectURL(arquivo));
    }

    function marcarRemocao(remover) {
        if (remover) document.getElementById('imagem').value = '';
        mostrarPrevia(remover ? null : fotoOriginal);
    }
</script>

@unless ($editando)
    {{-- Modal de busca de produto (mesmo da tela do caixa) --}}
    <div id="modal-busca-produto" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50"
         onclick="if (event.target === this) fecharModalBusca()">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center px-6 py-4 bg-[#10284A]">
                <h2 class="text-lg font-bold text-amber-50">Selecionar Produto</h2>
                <button type="button" onclick="fecharModalBusca()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
            </div>

            <div class="p-6">
                <input type="text" id="busca-produto-modal" autocomplete="off" placeholder="Buscar por nome, código ou código de barras..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition mb-4">
                <table class="w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-40">
                        <col>
                        <col class="w-28">
                        <col class="w-24">
                    </colgroup>
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <th class="py-2 px-2">Código</th>
                            <th class="py-2 px-2">Produto</th>
                            <th class="py-2 px-2">Preço</th>
                            <th class="py-2 px-2">Estoque</th>
                        </tr>
                    </thead>
                    <tbody id="linhas-busca-produto"></tbody>
                </table>

                <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar. Só aparecem produtos ativos que ainda não estão no cardápio.</p>
            </div>
        </div>
    </div>

    <script>
        const inputBusca = document.getElementById('busca-produto');
        const inputBuscaModal = document.getElementById('busca-produto-modal');
        const modalBusca = document.getElementById('modal-busca-produto');
        const linhasBusca = document.getElementById('linhas-busca-produto');
        const campoProdutoId = document.getElementById('produto-id');
        const campoVarianteId = document.getElementById('produto-variante-id');
        const produtoEscolhido = document.getElementById('produto-escolhido');
        const moeda = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const qtd = (v) => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
        let resultadosAtuais = [];
        let indiceSelecionado = -1;
        let timeoutBusca = null;
        let ultimaBusca = 0;

        const modalAberto = () => !modalBusca.classList.contains('hidden');

        function abrirModalBusca() {
            if (modalAberto()) return;
            modalBusca.classList.remove('hidden');
            modalBusca.classList.add('flex');
            // O texto digitado no campo de trás passa para o campo do modal, que manda na busca daqui em diante
            inputBuscaModal.value = inputBusca.value;
            inputBuscaModal.focus();
            inputBuscaModal.setSelectionRange(inputBuscaModal.value.length, inputBuscaModal.value.length);
            agendarBusca();
        }

        function fecharModalBusca() {
            modalBusca.classList.add('hidden');
            modalBusca.classList.remove('flex');
            clearTimeout(timeoutBusca);
            ultimaBusca++;
            resultadosAtuais = [];
            indiceSelecionado = -1;
            linhasBusca.innerHTML = '';
            if (!campoProdutoId.value) inputBusca.value = '';
            inputBusca.focus();
        }

        function agendarBusca() {
            clearTimeout(timeoutBusca);
            const termo = inputBuscaModal.value.trim();
            if (termo === '') {
                ultimaBusca++;
                resultadosAtuais = [];
                indiceSelecionado = -1;
                linhasBusca.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Digite para buscar.</td></tr>';
                return;
            }
            // Código numérico longo (EAN) busca mais rápido, como no caixa
            timeoutBusca = setTimeout(() => buscarProduto(termo), /^\d{8,}$/.test(termo) ? 150 : 300);
        }

        async function buscarProduto(termo) {
            const busca = ++ultimaBusca;
            let produtos = [];
            try {
                const resp = await fetch(@json(route('cardapio.buscar-produto')) + '?termo=' + encodeURIComponent(termo), { headers: { Accept: 'application/json' } });
                produtos = resp.ok ? await resp.json() : [];
            } catch (e) {
                produtos = [];
            }
            if (busca !== ultimaBusca) return; // chegou uma resposta mais nova (ou o modal fechou)

            resultadosAtuais = produtos;

            // Leitor de código de barras: só um produto/variante bate exatamente com o código → escolhe direto
            const exatos = produtos.filter(p => String(p.codigo_barras ?? '') === termo || String(p.codigo_interno ?? '') === termo);
            if (exatos.length === 1) {
                escolherProduto(exatos[0]);
                return;
            }

            indiceSelecionado = produtos.length ? 0 : -1;
            renderizarResultados();
        }

        function renderizarResultados() {
            if (!resultadosAtuais.length) {
                linhasBusca.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum produto encontrado.</td></tr>';
                return;
            }

            linhasBusca.innerHTML = resultadosAtuais.map((p, index) => {
                const destacado = index === indiceSelecionado;
                const codigo = p.codigo_barras || p.codigo_interno || '';
                return `
                    <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-[#10284A] text-amber-50' : 'hover:bg-slate-50'}"
                        data-index="${index}">
                        <td class="py-3 px-2 font-mono text-sm truncate ${destacado ? 'text-slate-300' : 'text-gray-500'}">${escHtml(codigo)}</td>
                        <td class="py-3 px-2 font-medium truncate" title="${escHtml(p.nome)}">${escHtml(p.nome)}</td>
                        <td class="py-3 px-2 whitespace-nowrap ${destacado ? 'text-emerald-300' : 'text-emerald-600'} font-semibold">${escHtml(moeda(p.preco_venda))}</td>
                        <td class="py-3 px-2 ${destacado ? 'text-slate-300' : 'text-gray-500'}">${escHtml(qtd(p.estoque))}</td>
                    </tr>`;
            }).join('');

            linhasBusca.querySelector(`[data-index="${indiceSelecionado}"]`)?.scrollIntoView({ block: 'nearest' });
        }

        function escolherProduto(p) {
            campoProdutoId.value = p.id;
            campoVarianteId.value = p.produto_variante_id ?? '';
            fecharModalBusca();
            inputBusca.value = p.nome;
            produtoEscolhido.querySelector('[data-campo="nome"]').textContent = p.nome;
            produtoEscolhido.querySelector('[data-campo="detalhe"]').textContent =
                [p.codigo_barras || p.codigo_interno || 'Sem código', p.categoria, 'Estoque: ' + qtd(p.estoque) + ' ' + p.unidade].filter(Boolean).join(' · ');
            produtoEscolhido.querySelector('[data-campo="preco"]').textContent = moeda(p.preco_venda);
            produtoEscolhido.classList.remove('hidden');
            produtoEscolhido.classList.add('flex');
            const descricao = document.getElementById('descricao');
            descricao.placeholder = p.descricao || 'Ex.: Pão brioche, hambúrguer 180 g, queijo cheddar e bacon.';
            descricao.focus();
        }

        // Começou a digitar no campo do produto: abre o modal na hora (igual ao caixa)
        inputBusca.addEventListener('input', () => {
            campoProdutoId.value = '';
            campoVarianteId.value = '';
            produtoEscolhido.classList.add('hidden');
            produtoEscolhido.classList.remove('flex');
            if (inputBusca.value.trim() !== '') abrirModalBusca();
        });

        // Enter no campo de trás não envia o formulário: abre a busca
        inputBusca.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (inputBusca.value.trim() !== '' && !campoProdutoId.value) abrirModalBusca();
        });

        inputBuscaModal.addEventListener('input', agendarBusca);

        inputBuscaModal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); fecharModalBusca(); return; }

            if (e.key === 'Enter') {
                e.preventDefault();
                if (indiceSelecionado >= 0 && resultadosAtuais[indiceSelecionado]) {
                    escolherProduto(resultadosAtuais[indiceSelecionado]);
                } else if (inputBuscaModal.value.trim() !== '') {
                    // Enter força a busca na hora (código só com números, por exemplo)
                    clearTimeout(timeoutBusca);
                    buscarProduto(inputBuscaModal.value.trim());
                }
                return;
            }

            if (!resultadosAtuais.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                indiceSelecionado = (indiceSelecionado + 1) % resultadosAtuais.length;
                renderizarResultados();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                indiceSelecionado = (indiceSelecionado - 1 + resultadosAtuais.length) % resultadosAtuais.length;
                renderizarResultados();
            }
        });

        linhasBusca.addEventListener('click', (e) => {
            const linha = e.target.closest('[data-index]');
            if (linha) escolherProduto(resultadosAtuais[linha.dataset.index]);
        });

        inputBusca.focus();
    </script>
@endunless

