@extends('layouts.app')

@section('titulo', 'iFood')
@section('body-class', 'conteudo-largo')

@section('conteudo')
@php
    extract(config('estilo.formulario'));
    $podeAlterar = auth()->user()?->podeAlterar('ifood');
    $distribuido = $cfg->modo_autenticacao !== \App\Models\IfoodConfiguracao::CENTRALIZADO;
    $situacaoInicial = [
        'conectado'         => $cfg->conectado(),
        'token_expira_em'   => $cfg->token_expira_em?->toIso8601String(),
        'polling_em_dia'    => $cfg->pollingEmDia(),
        'ultimo_polling_em' => $cfg->ultimo_polling_em?->toIso8601String(),
        'ultimo_erro'       => $cfg->ultimo_erro,
        'ultimo_erro_em'    => $cfg->ultimo_erro_em?->toIso8601String(),
        'aceite_automatico' => $cfg->aceite_automatico,
        'ativo'             => $cfg->ativo,
    ];
    $vinculoPendente = $cfg->user_code && $cfg->user_code_expira_em?->isFuture()
        ? ['codigo' => $cfg->user_code, 'url' => $cfg->verification_url, 'expira_em' => $cfg->user_code_expira_em->toIso8601String()]
        : null;
@endphp

    <x-form.cabecalho solto rotulo="Cadastros · iFood" titulo="Integração com o iFood">
        <span id="selo-situacao" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"></span>
    </x-form.cabecalho>

    @if (session('sucesso'))
        <div class="mb-4 rounded-xl bg-green-50 ring-1 ring-green-200 text-green-800 px-4 py-3 text-sm">{{ session('sucesso') }}</div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 text-amber-800 px-4 py-3 text-sm">{{ session('erro') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="{{ $cartaoCls }}">
        <div class="flex border-b border-slate-200">
            <button type="button" data-aba="conexao" onclick="trocarAba('conexao')"
                    class="px-6 py-4 text-sm font-medium border-b-2 border-[#10284A] text-[#10284A] transition">Conexão e pedidos</button>
            <button type="button" data-aba="cardapio" onclick="trocarAba('cardapio')"
                    class="px-6 py-4 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition">
                Cardápio do iFood <span id="contador-produtos" class="ml-1 rounded-full bg-slate-100 text-slate-500 px-2 py-0.5 text-xs">{{ $produtos->count() }}</span>
            </button>
        </div>

        {{-- ===================== Conexão ===================== --}}
        <div data-painel="conexao" class="p-6 grid grid-cols-1 xl:grid-cols-2 gap-6">
            <form action="{{ route('ifood.salvar') }}" method="POST" class="flex flex-col gap-5">
                @csrf
                <x-form.secao titulo="Credenciais" icone="documento">Do aplicativo no Portal do Desenvolvedor iFood e da loja.</x-form.secao>

                <div>
                    <span class="{{ $labelCls }}">Tipo do aplicativo</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="modo_autenticacao" value="distribuido" class="mt-0.5 accent-[#10284A]" @checked($distribuido) @disabled(!$podeAlterar)>
                            <span class="text-sm"><span class="font-semibold text-slate-800">Distribuído</span>
                                <span class="block text-xs text-slate-500">Vendido para vários restaurantes: cada loja autoriza com um código.</span></span>
                        </label>
                        <label class="flex items-start gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="modo_autenticacao" value="centralizado" class="mt-0.5 accent-[#10284A]" @checked(!$distribuido) @disabled(!$podeAlterar)>
                            <span class="text-sm"><span class="font-semibold text-slate-800">Centralizado</span>
                                <span class="block text-xs text-slate-500">Credenciais de uma loja só (sem código de vínculo).</span></span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelCls }}" for="client_id">Client ID</label>
                    <input id="client_id" name="client_id" type="text" maxlength="120" autocomplete="off" value="{{ old('client_id', $cfg->client_id) }}" class="{{ $inputCls }}" @disabled(!$podeAlterar)>
                </div>
                <div>
                    <label class="{{ $labelCls }}" for="client_secret">Client Secret</label>
                    <input id="client_secret" name="client_secret" type="password" maxlength="255" autocomplete="new-password"
                           placeholder="{{ filled($cfg->client_secret) ? '•••••••• salvo — deixe em branco para manter' : 'Cole o Client Secret' }}"
                           class="{{ $inputCls }}" @disabled(!$podeAlterar)>
                    <p class="mt-1 text-xs text-slate-400">Fica criptografado no banco e não aparece de novo na tela.</p>
                </div>
                <div>
                    <label class="{{ $labelCls }}" for="merchant_id">ID da loja (Merchant ID)</label>
                    <input id="merchant_id" name="merchant_id" type="text" maxlength="60" autocomplete="off" value="{{ old('merchant_id', $cfg->merchant_id) }}"
                           placeholder="00000000-0000-0000-0000-000000000000" class="{{ $inputCls }}" @disabled(!$podeAlterar)>
                </div>

                <x-form.secao titulo="Pedidos" icone="documento" class="mt-2">Como os pedidos do iFood entram na Lista de Pedidos.</x-form.secao>
                <label class="flex items-start gap-3 rounded-xl ring-1 ring-slate-200 px-4 py-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" class="mt-0.5 accent-[#10284A]" @checked($cfg->ativo) @disabled(!$podeAlterar)>
                    <span class="text-sm"><span class="font-semibold text-slate-800">Receber pedidos do iFood</span>
                        <span class="block text-xs text-slate-500">O sistema consulta o iFood a cada 30 segundos e os pedidos novos aparecem em Comandas → Pedidos e saem nas impressoras.</span></span>
                </label>
                <div>
                    <span class="{{ $labelCls }}">Aceite dos pedidos</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="aceite_automatico" value="0" class="mt-0.5 accent-[#10284A]" @checked(!$cfg->aceite_automatico) @disabled(!$podeAlterar)>
                            <span class="text-sm"><span class="font-semibold text-slate-800">Manual</span>
                                <span class="block text-xs text-slate-500">O operador clica em "Aceitar" no pedido. Se ninguém aceitar a tempo, o iFood cancela.</span></span>
                        </label>
                        <label class="flex items-start gap-2 rounded-xl ring-1 ring-slate-200 px-3 py-2.5 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-[#10284A] has-[:checked]:bg-slate-50">
                            <input type="radio" name="aceite_automatico" value="1" class="mt-0.5 accent-[#10284A]" @checked($cfg->aceite_automatico) @disabled(!$podeAlterar)>
                            <span class="text-sm"><span class="font-semibold text-slate-800">Automático</span>
                                <span class="block text-xs text-slate-500">O sistema aceita assim que o pedido chega.</span></span>
                        </label>
                    </div>
                </div>

                @if ($podeAlterar)
                    <div><button type="submit" class="{{ $botaoPrimarioCls }}">Salvar</button></div>
                @endif
            </form>

            <div class="flex flex-col gap-5">
                <x-form.secao titulo="Vínculo da loja" icone="documento">O restaurante autoriza o Cyberly a acessar a loja dele no iFood.</x-form.secao>

                <div class="rounded-2xl ring-1 ring-slate-200 p-4 flex flex-col gap-3">
                    <div id="vinculo-situacao" class="text-sm"></div>

                    @if ($podeAlterar)
                        <div id="vinculo-passos" class="hidden"><div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-4 text-sm flex flex-col gap-3">
                            <p class="font-semibold text-slate-800">1. No Portal do Parceiro iFood, autorize com este código:</p>
                            <div class="flex items-center gap-3 flex-wrap">
                                <span id="vinculo-codigo" class="font-mono text-2xl font-bold tracking-widest text-[#10284A]"></span>
                                <a id="vinculo-link" href="#" target="_blank" rel="noopener noreferrer" class="{{ $botaoSecundarioCls }}">Abrir o Portal do Parceiro ↗</a>
                            </div>
                            <p class="text-xs text-slate-500">O código vale até <span id="vinculo-expira"></span>. Entre com o login do restaurante e autorize o aplicativo.</p>
                            <p class="font-semibold text-slate-800">2. Cole aqui o código de autorização que o iFood mostrou:</p>
                            <div class="flex gap-2">
                                <input id="vinculo-autorizacao" type="text" autocomplete="off" class="{{ $inputCls }}" placeholder="Código de autorização">
                                <button type="button" onclick="concluirVinculo(this)" class="{{ $botaoAcaoCls }}">Concluir vínculo</button>
                            </div>
                        </div></div>

                        <div class="flex flex-wrap gap-2">
                            @if ($distribuido)
                                <button type="button" id="btn-vincular" onclick="gerarCodigo(this)" class="{{ $botaoAcaoCls }}">Vincular loja ao iFood</button>
                            @endif
                            <button type="button" onclick="acao('{{ route('ifood.testar') }}', this)" class="{{ $botaoSecundarioCls }}">Testar conexão</button>
                            <button type="button" onclick="acao('{{ route('ifood.pedidos.buscar') }}', this)" class="{{ $botaoSecundarioCls }}">Buscar pedidos agora</button>
                            <button type="button" id="btn-desvincular" onclick="desvincular(this)" class="hidden ml-auto inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50">Desvincular</button>
                        </div>
                    @endif
                </div>

                <x-form.secao titulo="Recebimento dos pedidos (polling)" icone="documento">O iFood exige uma consulta a cada 30 segundos enquanto a loja estiver aberta.</x-form.secao>
                <div class="rounded-2xl ring-1 ring-slate-200 p-4 text-sm flex flex-col gap-2">
                    <div id="polling-situacao"></div>
                    <p class="text-xs text-slate-500">O sistema consulta sozinho enquanto a tela <span class="font-semibold">Comandas → Pedidos</span> estiver aberta em algum computador.
                        Para funcionar sem a tela aberta, deixe rodando no servidor: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">php artisan ifood:polling --sempre</code>.</p>
                </div>

                <p id="mensagem-conexao" class="hidden rounded-xl px-4 py-3 text-sm"></p>
            </div>
        </div>

        {{-- ===================== Cardápio ===================== --}}
        <div data-painel="cardapio" class="hidden p-6">
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div class="flex-1 min-w-56">
                    <label class="{{ $labelCls }}" for="filtro-busca">Buscar</label>
                    <input id="filtro-busca" type="search" placeholder="Nome, categoria ou produto do sistema" class="{{ $inputCls }}" oninput="desenharProdutos()">
                </div>
                <div class="w-56">
                    <label class="{{ $labelCls }}" for="filtro-ligacao">Produto do sistema</label>
                    <select id="filtro-ligacao" class="{{ $inputCls }}" onchange="desenharProdutos()">
                        <option value="">Todos</option>
                        <option value="sem">Sem produto ligado</option>
                        <option value="com">Com produto ligado</option>
                    </select>
                </div>
                @if ($podeAlterar)
                    <button type="button" onclick="assimilar(this)" class="{{ $botaoSecundarioCls }}">Ligar automaticamente</button>
                    <button type="button" onclick="Modelos.abrir()" class="{{ $botaoSecundarioCls }}">Cadastrar produtos automaticamente</button>
                    <button type="button" onclick="importarCardapio(this)" class="{{ $botaoAcaoCls }}">Importar cardápio do iFood</button>
                @endif
            </div>
            <p class="mb-3 text-xs text-slate-500">
                Ligue cada item do iFood a um produto do sistema: é ele que entra no pedido (e, no futuro, na emissão da venda).
                O "Ligar automaticamente" usa o código de barras (EAN), o código externo do iFood (código interno, código de barras ou ID do produto) e o nome igual.
                O preço no sistema começa igual ao do iFood; se mudar, use "Enviar ao iFood" para atualizar lá.
                @if ($cfg->cardapio_importado_em) Última importação: {{ $cfg->cardapio_importado_em->format('d/m/Y H:i') }}. @endif
            </p>
            <p id="mensagem-cardapio" class="hidden mb-3 rounded-xl px-4 py-3 text-sm"></p>
            <div class="{{ $tabelaCaixaCls }} overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="{{ $theadCls }}">
                        <tr>
                            <th class="px-4 py-3 text-left">Item no iFood</th>
                            <th class="px-4 py-3 text-right w-32">Preço iFood</th>
                            <th class="px-4 py-3 text-right w-44">Preço no sistema</th>
                            <th class="px-4 py-3 text-left w-80">Produto do sistema</th>
                        </tr>
                    </thead>
                    <tbody id="lista-produtos" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Cadastro automático: modelo fiscal padrão e por categoria --}}
    <div id="modal-modelos" class="fixed inset-0 z-40 bg-black/50 hidden items-center justify-center p-4" onclick="if (event.target === this) Modelos.fechar()">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl max-h-[90vh] flex flex-col">
            <div class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-slate-900 text-lg">Cadastrar produtos automaticamente</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Os itens do iFood sem produto viram produtos do sistema: nome, descrição, preço e código de barras vêm do iFood.
                        A parte fiscal (NCM, CEST, tributação, PIS/COFINS, IPI, IBS/CBS, origem e unidade) é copiada do <span class="font-semibold">produto modelo</span> da categoria.</p>
                </div>
                <button type="button" onclick="Modelos.fechar()" aria-label="Fechar" class="w-10 h-10 rounded-xl text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4 flex flex-col gap-4">
                <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-4">
                    <p class="{{ $labelCls }}">Modelo fiscal padrão</p>
                    <p class="text-xs text-slate-500 mb-2">Usado nas categorias sem modelo próprio.</p>
                    <div data-mod="padrao"></div>
                </div>
                <div>
                    <p class="{{ $labelCls }}">Modelo por categoria do iFood</p>
                    <div data-mod="categorias" class="flex flex-col divide-y divide-slate-100 rounded-2xl ring-1 ring-slate-200"></div>
                </div>
                <p class="rounded-xl bg-amber-50 ring-1 ring-amber-200 text-amber-800 px-3 py-2 text-xs">
                    Escolha como modelo um produto já conferido pelo contador (ex.: um refrigerante para Bebidas, um lanche para Lanches).
                    Os produtos cadastrados ficam marcados "Cadastrado pelo iFood" para você revisar o NCM depois em Cadastros → Produtos.
                </p>
                <p data-mod="mensagem" class="hidden rounded-xl px-3 py-2 text-sm"></p>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center gap-2">
                <button type="button" onclick="Modelos.fechar()" class="{{ $botaoCancelarCls }}">Fechar</button>
                <button type="button" data-mod="cadastrar" onclick="Modelos.cadastrar(this)" class="ml-auto {{ $botaoAcaoCls }} disabled:opacity-50">Cadastrar</button>
            </div>
        </div>
    </div>

    {{-- Busca de produto do sistema para ligar --}}
    <div id="modal-produto" class="fixed inset-0 z-50 bg-black/50 hidden items-center justify-center p-4" onclick="if (event.target === this) fecharBusca()">
        <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl max-h-[85vh] flex flex-col">
            <div class="px-6 pt-5 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-400" id="busca-subtitulo">Ligar ao produto do sistema</p>
                        <h2 id="busca-titulo" class="font-bold text-slate-900 truncate"></h2>
                    </div>
                    <button type="button" onclick="fecharBusca()" aria-label="Fechar" class="w-10 h-10 rounded-xl text-slate-400 hover:bg-slate-100 text-2xl leading-none">&times;</button>
                </div>
                <input id="busca-termo" type="search" placeholder="Nome, código interno ou código de barras" class="{{ $inputCls }} mt-3" autocomplete="off">
            </div>
            <div id="busca-resultados" class="flex-1 overflow-y-auto px-3 py-2"></div>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center gap-2">
                <button type="button" id="busca-desligar" onclick="ligar(null, null)" class="hidden rounded-xl px-4 py-2 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50">Desligar produto</button>
                <button type="button" onclick="fecharBusca()" class="ml-auto {{ $botaoCancelarCls }}">Fechar</button>
            </div>
        </div>
    </div>

<script>
    const PODE = @json((bool) $podeAlterar);
    const CSRF = @json(csrf_token());
    const URL = {
        situacao: @json(route('ifood.situacao')),
        codigo: @json(route('ifood.vincular.codigo')),
        concluir: @json(route('ifood.vincular.concluir')),
        desvincular: @json(route('ifood.desvincular')),
        importar: @json(route('ifood.cardapio.importar')),
        assimilar: @json(route('ifood.cardapio.assimilar')),
        buscar: @json(route('ifood.cardapio.buscar-produto')),
        item: @json(route('ifood.cardapio.atualizar', ['item' => '__ID__'])),
        enviarPreco: @json(route('ifood.cardapio.enviar-preco', ['item' => '__ID__'])),
    };
    const DISTRIBUIDO = @json($distribuido);
    let produtos = @json($produtos);
    let situacao = @json($situacaoInicial);
    const vinculoPendente = @json($vinculoPendente);

    const moeda = (v) => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dataHora = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';

    async function pedir(url, metodo = 'POST', corpo = null) {
        const resp = await fetch(url, { method: metodo, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: corpo ? JSON.stringify(corpo) : null });
        if (resp.redirected) { location.reload(); throw new Error('Sessão expirada.'); }
        const json = await resp.json().catch(() => ({}));
        if (!resp.ok) throw new Error(json.message || 'Não foi possível concluir.');
        return json;
    }
    const erroRede = (e) => e.message === 'Failed to fetch' ? 'Sem conexão com o servidor.' : e.message;

    function mensagem(id, texto, erro = false) {
        const m = document.getElementById(id);
        m.textContent = texto || '';
        m.className = `${id === 'mensagem-cardapio' ? 'mb-3 ' : ''}rounded-xl px-4 py-3 text-sm ${erro ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-green-50 text-green-800 ring-1 ring-green-200'}`;
        m.classList.toggle('hidden', !texto);
    }

    async function comBotao(botao, fn) {
        const texto = botao.textContent;
        botao.disabled = true; botao.textContent = 'Aguarde...';
        try { await fn(); } finally { botao.disabled = false; botao.textContent = texto; }
    }

    function trocarAba(aba) {
        document.querySelectorAll('[data-painel]').forEach(p => p.classList.toggle('hidden', p.dataset.painel !== aba));
        document.querySelectorAll('[data-aba]').forEach(b => {
            const ativa = b.dataset.aba === aba;
            b.classList.toggle('border-[#10284A]', ativa); b.classList.toggle('text-[#10284A]', ativa);
            b.classList.toggle('border-transparent', !ativa); b.classList.toggle('text-slate-500', !ativa);
        });
        try { localStorage.setItem('ifood.aba', aba); } catch (e) {}
    }

    // ===== Conexão =====
    function desenharSituacao() {
        const s = situacao;
        const selo = document.getElementById('selo-situacao');
        const [cls, txt] = !s.ativo ? ['bg-slate-100 text-slate-600', 'Recebimento desligado']
            : !s.conectado ? ['bg-amber-100 text-amber-800', 'Loja não vinculada']
            : s.polling_em_dia ? ['bg-green-100 text-green-800', 'Recebendo pedidos']
            : ['bg-amber-100 text-amber-800', 'Aguardando a consulta (abra Comandas → Pedidos)'];
        selo.className = `inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${cls}`;
        selo.textContent = txt;

        document.getElementById('vinculo-situacao').innerHTML = s.conectado
            ? `<p class="font-semibold text-green-700">✓ Loja vinculada</p><p class="text-xs text-slate-500">Acesso renovado sozinho${s.token_expira_em ? ' · token atual até ' + escHtml(dataHora(s.token_expira_em)) : ''}.</p>`
            : `<p class="font-semibold text-amber-700">Loja ainda não vinculada</p><p class="text-xs text-slate-500">${DISTRIBUIDO
                ? 'Salve as credenciais e clique em "Vincular loja ao iFood".'
                : 'Salve as credenciais e clique em "Testar conexão".'}</p>`;
        document.getElementById('btn-desvincular')?.classList.toggle('hidden', !s.conectado);

        document.getElementById('polling-situacao').innerHTML = `
            <p><span class="text-slate-500">Última consulta:</span> <span class="font-semibold ${s.polling_em_dia ? 'text-green-700' : 'text-slate-700'}">${s.ultimo_polling_em ? escHtml(dataHora(s.ultimo_polling_em)) : 'nunca'}</span></p>
            ${s.ultimo_erro ? `<p class="rounded-lg bg-red-50 text-red-700 px-3 py-2 text-xs">Último erro (${escHtml(dataHora(s.ultimo_erro_em))}): ${escHtml(s.ultimo_erro)}</p>` : ''}`;
    }

    function mostrarVinculo(v) {
        const caixa = document.getElementById('vinculo-passos');
        if (!caixa) return;
        caixa.classList.toggle('hidden', !v);
        if (!v) return;
        document.getElementById('vinculo-codigo').textContent = v.codigo || '—';
        const link = document.getElementById('vinculo-link');
        link.href = v.url || 'https://portal.ifood.com.br';
        document.getElementById('vinculo-expira').textContent = new Date(v.expira_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    async function gerarCodigo(botao) {
        await comBotao(botao, async () => {
            try { mostrarVinculo(await pedir(URL.codigo)); mensagem('mensagem-conexao', ''); }
            catch (e) { mensagem('mensagem-conexao', erroRede(e), true); }
        });
    }

    async function concluirVinculo(botao) {
        await comBotao(botao, async () => {
            try {
                const r = await pedir(URL.concluir, 'POST', { codigo: document.getElementById('vinculo-autorizacao').value });
                situacao = r.situacao; desenharSituacao(); mostrarVinculo(null);
                mensagem('mensagem-conexao', r.message + ' Clique em "Testar conexão" para conferir.');
            } catch (e) { mensagem('mensagem-conexao', erroRede(e), true); }
        });
    }

    async function desvincular(botao) {
        if (!confirm('Desvincular a loja do iFood?\n\nOs pedidos param de chegar até vincular de novo.')) return;
        await comBotao(botao, async () => {
            try { const r = await pedir(URL.desvincular); situacao = r.situacao; desenharSituacao(); mensagem('mensagem-conexao', r.message); }
            catch (e) { mensagem('mensagem-conexao', erroRede(e), true); }
        });
    }

    async function acao(url, botao) {
        await comBotao(botao, async () => {
            try { const r = await pedir(url); if (r.situacao) { situacao = r.situacao; desenharSituacao(); } mensagem('mensagem-conexao', r.message); }
            catch (e) { mensagem('mensagem-conexao', erroRede(e), true); }
        });
    }

    async function atualizarSituacao() {
        try { situacao = (await pedir(URL.situacao, 'GET')).situacao; desenharSituacao(); } catch (e) {}
    }

    // ===== Cardápio =====
    function desenharProdutos() {
        const termo = document.getElementById('filtro-busca').value.trim().toLowerCase();
        const ligacao = document.getElementById('filtro-ligacao').value;
        const lista = produtos.filter(p =>
            (!termo || [p.nome, p.categoria, p.produto_nome, p.codigo_externo].some(v => (v || '').toLowerCase().includes(termo)))
            && (!ligacao || (ligacao === 'sem' ? !p.produto_id : !!p.produto_id)));

        document.getElementById('contador-produtos').textContent = produtos.length;
        document.getElementById('lista-produtos').innerHTML = lista.map(p => {
            const diferente = Math.round(p.preco * 100) !== Math.round(p.preco_ifood * 100);
            return `
            <tr class="align-top ${p.status !== 'AVAILABLE' ? 'opacity-60' : ''}">
                <td class="px-4 py-3">
                    <div class="flex items-start gap-3">
                        ${p.imagem_url ? `<img src="${escHtml(p.imagem_url)}" alt="" loading="lazy" class="w-12 h-12 rounded-lg object-cover ring-1 ring-slate-200 shrink-0">`
                            : '<div class="w-12 h-12 rounded-lg bg-slate-100 shrink-0"></div>'}
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">${escHtml(p.nome)}</p>
                            <p class="text-xs text-slate-500">${escHtml(p.categoria || 'Sem categoria')}${p.codigo_externo ? ' · Cód. externo ' + escHtml(p.codigo_externo) : ''}${p.status !== 'AVAILABLE' ? ' · <span class="text-red-600">Pausado no iFood</span>' : ''}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-right text-slate-600">${escHtml(moeda(p.preco_ifood))}</td>
                <td class="px-4 py-3 text-right">
                    ${PODE ? `<input type="text" inputmode="decimal" value="${p.preco.toFixed(2).replace('.', ',')}" data-preco="${p.id}"
                                 class="w-28 text-right bg-slate-50 border ${diferente ? 'border-amber-300' : 'border-slate-200'} rounded-lg px-2 py-1.5 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#10284A]/30 outline-none">`
                             : `<span class="font-semibold">${escHtml(moeda(p.preco))}</span>`}
                    ${diferente && PODE ? `<button type="button" onclick="enviarPreco(${p.id}, this)" class="block ml-auto mt-1 text-xs font-semibold text-[#10284A] hover:underline">Enviar ao iFood ↑</button>` : ''}
                </td>
                <td class="px-4 py-3">
                    ${p.produto_id
                        ? `<button type="button" ${PODE ? `onclick="abrirBusca(${p.id})"` : 'disabled'} class="w-full text-left rounded-lg bg-green-50 ring-1 ring-green-200 px-3 py-2 text-sm text-green-800 hover:ring-green-400">
                               <span class="font-semibold">✓ ${escHtml(p.produto_nome)}</span>${p.cadastrado_automaticamente ? '<span class="ml-1 rounded bg-amber-100 text-amber-800 px-1.5 py-0.5 text-[10px] font-semibold">Cadastrado pelo iFood · revisar NCM</span>' : ''}${PODE ? '<span class="block text-xs text-green-700/70">Clique para trocar</span>' : ''}</button>`
                        : PODE ? `<button type="button" onclick="abrirBusca(${p.id})" class="w-full text-left rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2 text-sm font-semibold text-amber-800 hover:ring-amber-400">+ Ligar a um produto</button>`
                               : '<span class="text-xs text-slate-400">Sem produto ligado</span>'}
                </td>
            </tr>`;
        }).join('') || `<tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">${produtos.length
            ? 'Nenhum item com esse filtro.' : 'Nenhum item importado. Clique em "Importar cardápio do iFood".'}</td></tr>`;

        document.querySelectorAll('[data-preco]').forEach(campo => campo.addEventListener('change', () => salvarPreco(parseInt(campo.dataset.preco, 10), campo)));
    }

    function trocarProduto(p) {
        produtos = produtos.map(x => x.id === p.id ? p : x);
        desenharProdutos();
    }

    async function salvarPreco(id, campo) {
        const valor = parseFloat(String(campo.value).replace(/\./g, '').replace(',', '.'));
        if (!Number.isFinite(valor) || valor < 0) { campo.classList.add('border-red-400'); return; }
        try { trocarProduto((await pedir(URL.item.replace('__ID__', id), 'PATCH', { preco: valor })).produto); }
        catch (e) { mensagem('mensagem-cardapio', erroRede(e), true); }
    }

    async function enviarPreco(id, botao) {
        await comBotao(botao, async () => {
            try { const r = await pedir(URL.enviarPreco.replace('__ID__', id)); trocarProduto(r.produto); mensagem('mensagem-cardapio', r.message); }
            catch (e) { mensagem('mensagem-cardapio', erroRede(e), true); }
        });
    }

    async function importarCardapio(botao) {
        await comBotao(botao, async () => {
            try { const r = await pedir(URL.importar); produtos = r.produtos; desenharProdutos(); mensagem('mensagem-cardapio', r.message); }
            catch (e) { mensagem('mensagem-cardapio', erroRede(e), true); }
        });
    }

    async function assimilar(botao) {
        await comBotao(botao, async () => {
            try { const r = await pedir(URL.assimilar); produtos = r.produtos; desenharProdutos(); mensagem('mensagem-cardapio', r.message); }
            catch (e) { mensagem('mensagem-cardapio', erroRede(e), true); }
        });
    }

    // ===== Busca do produto do sistema =====
    let itemBusca = null;
    let temporizador = null;
    const modal = document.getElementById('modal-produto');
    const campoBusca = document.getElementById('busca-termo');

    let aoEscolher = null; // escolha de modelo fiscal (Modelos) em vez de ligar o item
    let buscaModelo = false;

    function abrirBusca(id) {
        itemBusca = produtos.find(p => p.id === id);
        if (!itemBusca) return;
        aoEscolher = null; buscaModelo = false;
        document.getElementById('busca-subtitulo').textContent = 'Ligar ao produto do sistema';
        document.getElementById('busca-titulo').textContent = itemBusca.nome;
        document.getElementById('busca-desligar').classList.toggle('hidden', !itemBusca.produto_id);
        modal.classList.remove('hidden'); modal.classList.add('flex');
        campoBusca.value = itemBusca.nome;
        buscar();
        campoBusca.focus(); campoBusca.select();
    }

    // Busca de produto modelo (mostra NCM e tributação)
    function escolherModelo(titulo, callback) {
        itemBusca = { id: 0 };
        aoEscolher = callback; buscaModelo = true;
        document.getElementById('busca-subtitulo').textContent = 'Produto modelo fiscal';
        document.getElementById('busca-titulo').textContent = titulo;
        document.getElementById('busca-desligar').classList.add('hidden');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        campoBusca.value = '';
        document.getElementById('busca-resultados').innerHTML = '<p class="px-3 py-6 text-center text-sm text-slate-400">Digite o nome ou o código de um produto já cadastrado.</p>';
        campoBusca.focus();
    }
    function fecharBusca() { modal.classList.add('hidden'); modal.classList.remove('flex'); itemBusca = null; aoEscolher = null; }

    campoBusca.addEventListener('input', () => { clearTimeout(temporizador); temporizador = setTimeout(buscar, 250); });

    async function buscar() {
        const termo = campoBusca.value.trim();
        const area = document.getElementById('busca-resultados');
        if (!termo) { area.innerHTML = ''; return; }
        area.innerHTML = '<p class="px-3 py-6 text-center text-sm text-slate-400">Buscando...</p>';
        try {
            const lista = await pedir(URL.buscar + '?termo=' + encodeURIComponent(termo) + (buscaModelo ? '&modelo=1' : ''), 'GET');
            if (campoBusca.value.trim() !== termo) return;
            area.innerHTML = lista.map(r => `
                <button type="button" onclick="ligar(${r.produto_id}, ${r.produto_variante_id ?? 'null'})" data-produto="${r.produto_id}" data-nome="${escHtml(r.nome)}" data-resumo="${escHtml(r.codigo || '')}"
                        class="w-full text-left rounded-xl px-3 py-2.5 hover:bg-slate-50 flex items-center justify-between gap-3 ${itemBusca && r.produto_id === itemBusca.produto_id && (r.produto_variante_id ?? null) === (itemBusca.produto_variante_id ?? null) ? 'ring-2 ring-green-400' : ''}">
                    <span class="min-w-0"><span class="block font-semibold text-slate-800 truncate">${escHtml(r.nome)}</span>
                        <span class="text-xs text-slate-500">${r.codigo ? escHtml(r.codigo) : 'sem código'}</span></span>
                    <span class="shrink-0 text-sm text-slate-600">${escHtml(moeda(r.preco))}</span>
                </button>`).join('') || '<p class="px-3 py-6 text-center text-sm text-slate-400">Nenhum produto encontrado. Tente outra palavra ou o código de barras.</p>';
        } catch (e) { area.innerHTML = `<p class="px-3 py-6 text-center text-sm text-red-600">${escHtml(erroRede(e))}</p>`; }
    }

    async function ligar(produtoId, varianteId) {
        if (!itemBusca) return;
        if (aoEscolher) {
            const linha = [...document.querySelectorAll('#busca-resultados button')].find(b => b.dataset.produto === String(produtoId));
            aoEscolher({ id: produtoId, nome: linha?.dataset.nome || '', resumo: linha?.dataset.resumo || '' });
            fecharBusca();
            return;
        }
        try {
            const r = await pedir(URL.item.replace('__ID__', itemBusca.id), 'PATCH', { produto_id: produtoId, produto_variante_id: varianteId });
            trocarProduto(r.produto); fecharBusca();
        } catch (e) { mensagem('mensagem-cardapio', erroRede(e), true); }
    }

    window.Modelos = (function () {
        const URL_MODELOS = @json(route('ifood.cardapio.modelos'));
        const URL_SALVAR = @json(route('ifood.cardapio.modelos.salvar'));
        const URL_CADASTRAR = @json(route('ifood.cardapio.cadastrar'));
        const janela = document.getElementById('modal-modelos');
        const parte = (n) => janela.querySelector(`[data-mod="${n}"]`);
        let dados = null;

        function aviso(texto, erro = false) {
            parte('mensagem').textContent = texto || '';
            parte('mensagem').className = `rounded-xl px-3 py-2 text-sm ${erro ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-800'}`;
            parte('mensagem').classList.toggle('hidden', !texto);
        }

        const seletor = (modelo, onclick, rotuloVazio) => `
            <button type="button" onclick="${onclick}" class="w-full text-left rounded-xl px-3 py-2 text-sm ring-1 ${modelo ? 'ring-green-200 bg-green-50 hover:ring-green-400' : 'ring-slate-200 bg-white hover:ring-[#10284A]/40'}">
                ${modelo ? `<span class="block font-semibold text-slate-800">${escHtml(modelo.nome)}</span><span class="text-xs text-slate-500">NCM ${escHtml(modelo.ncm || '—')} · ${escHtml(modelo.tributacao || 'sem tributação')}</span>`
                         : `<span class="text-slate-500">${rotuloVazio}</span>`}
            </button>`;

        function desenhar() {
            parte('padrao').innerHTML = seletor(dados.padrao, 'Modelos.escolher(null)', '+ Escolher o produto modelo padrão');
            parte('categorias').innerHTML = dados.categorias.map((c, i) => `
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="w-44 shrink-0">
                        <p class="font-semibold text-slate-800 text-sm truncate">${escHtml(c.rotulo)}</p>
                        <p class="text-xs ${c.pendentes ? 'text-amber-700' : 'text-slate-400'}">${c.pendentes ? c.pendentes + ' sem produto' : 'todos ligados'}</p>
                    </div>
                    <div class="flex-1 min-w-0">${seletor(c.modelo, `Modelos.escolher(${i})`, dados.padrao ? 'Usa o padrão · trocar' : '+ Escolher modelo')}</div>
                    ${c.modelo ? `<button type="button" onclick="Modelos.limpar(${i})" title="Usar o padrão" class="shrink-0 w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100">&times;</button>` : ''}
                </div>`).join('') || '<p class="px-4 py-6 text-sm text-slate-400">Importe o cardápio do iFood primeiro.</p>';

            const semModelo = dados.categorias.filter(c => c.pendentes && !c.modelo && !dados.padrao).reduce((t, c) => t + c.pendentes, 0);
            const prontos = dados.total_pendentes - semModelo;
            parte('cadastrar').disabled = prontos <= 0;
            parte('cadastrar').textContent = prontos > 0 ? `Cadastrar ${prontos} produto(s)` : 'Nada para cadastrar';
        }

        async function abrir() {
            janela.classList.remove('hidden'); janela.classList.add('flex');
            aviso('');
            parte('categorias').innerHTML = '<p class="px-4 py-6 text-sm text-slate-400">Carregando...</p>';
            try { dados = await pedir(URL_MODELOS, 'GET'); desenhar(); }
            catch (e) { aviso(erroRede(e), true); }
        }

        function fechar() { janela.classList.add('hidden'); janela.classList.remove('flex'); }

        async function salvar() {
            const categorias = Object.fromEntries(dados.categorias.map(c => [c.categoria, c.modelo?.id ?? null]));
            dados = await pedir(URL_SALVAR, 'POST', { padrao_id: dados.padrao?.id ?? null, categorias });
            desenhar();
        }

        function escolher(indice) {
            const titulo = indice === null ? 'Modelo padrão' : `Categoria: ${dados.categorias[indice].rotulo}`;
            escolherModelo(titulo, async (p) => {
                const [ncm, tributacao] = String(p.resumo).replace(/^NCM /, '').split(' · ');
                const modelo = { id: p.id, nome: p.nome, ncm, tributacao };
                if (indice === null) dados.padrao = modelo; else dados.categorias[indice].modelo = modelo;
                desenhar();
                try { await salvar(); } catch (e) { aviso(erroRede(e), true); }
            });
        }

        async function limpar(indice) {
            dados.categorias[indice].modelo = null;
            desenhar();
            try { await salvar(); } catch (e) { aviso(erroRede(e), true); }
        }

        async function cadastrar(botao) {
            if (!confirm('Cadastrar os itens do iFood sem produto como produtos do sistema, com a parte fiscal dos modelos escolhidos?')) return;
            botao.disabled = true;
            botao.textContent = 'Cadastrando...';
            try {
                const r = await pedir(URL_CADASTRAR, 'POST', {});
                produtos = r.produtos; desenharProdutos();
                dados = { padrao: r.padrao, categorias: r.categorias, total_pendentes: r.total_pendentes };
                desenhar();
                aviso(r.message, r.erro);
            } catch (e) {
                aviso(erroRede(e), true);
                desenhar();
            }
        }

        return { abrir, fechar, escolher, limpar, cadastrar, aberto: () => !janela.classList.contains('hidden') };
    })();

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (itemBusca) return fecharBusca();
        if (Modelos.aberto()) Modelos.fechar();
    });

    // ===== Início =====
    let abaInicial = 'conexao';
    try { abaInicial = localStorage.getItem('ifood.aba') || 'conexao'; } catch (e) {}
    trocarAba(abaInicial);
    desenharSituacao();
    mostrarVinculo(vinculoPendente);
    desenharProdutos();
    setInterval(atualizarSituacao, 15000);
</script>
@endsection
