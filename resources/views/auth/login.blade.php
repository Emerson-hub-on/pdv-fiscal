@php
    // A mesma tela serve para a escolha e para o login: sem modo, o operador escolhe no dropdown
    $modo = old('modo', $modo ?? request('modo'));
    $modo = in_array($modo, ['admin', 'operador'], true) ? $modo : null;

    $modos = [
        'admin' => [
            'titulo'    => 'Painel Administrativo',
            'descricao' => 'Cadastros, faturamento, fiscal e relatórios',
            'subtitulo' => 'Acesso ao painel administrativo',
        ],
        'operador' => [
            'titulo'    => 'Operador de Caixa',
            'descricao' => 'Frente de caixa (PDV) e emissão de NFC-e',
            'subtitulo' => 'Acesso à frente de caixa',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Cyberly Sistema</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-gray-800 antialiased">
<div class="min-h-screen grid lg:grid-cols-[46%_54%]">

    {{-- ===================== LADO ESQUERDO (marca) ===================== --}}
    <aside class="relative hidden lg:flex flex-col overflow-hidden bg-linear-to-br from-[#0f2a5c] via-[#163f86] to-[#1d5bbf] text-white px-12 py-10">
        {{-- Padrão de "+" e círculos decorativos --}}
        <svg class="absolute inset-0 h-full w-full opacity-[0.07]" aria-hidden="true">
            <defs>
                <pattern id="padrao-mais" width="36" height="36" patternUnits="userSpaceOnUse">
                    <path d="M18 13v10M13 18h10" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#padrao-mais)"/>
        </svg>
        <div class="absolute -top-16 -right-24 h-72 w-72 rounded-full bg-white/10"></div>
        <div class="absolute top-36 right-20 h-24 w-24 rounded-full bg-white/10"></div>

        <div class="relative flex items-center gap-3">
            <div class="h-12 w-12 rounded-xl bg-slate-900 ring-1 ring-white/20 flex items-center justify-center">
                @include('partials.logo-simbolo', ['tamanho' => 34])
            </div>
            <span class="text-xl font-semibold tracking-tight">Cyberly Sistema</span>
        </div>

        <div class="relative mt-20 max-w-md">
            <h1 id="titulo-lateral" class="text-5xl font-bold leading-tight">
                {{ $modo ? $modos[$modo]['titulo'] : 'Bem-vindo' }}
            </h1>
            <p class="mt-5 text-base text-blue-100/90 leading-relaxed">
                Gerencie sua operação com eficiência e segurança em um único lugar.
            </p>

            <ul class="mt-10 space-y-4 text-sm text-blue-50">
                <li class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14h6m-6-4h6m-6 8h3M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                    Emissão de NFC-e, NF-e e MDF-e
                </li>
                <li class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15v-4m4 4V8m4 7v-6"/></svg>
                    SPED, estoque e relatórios detalhados
                </li>
                <li class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z"/></svg>
                    Acesso seguro por usuário e senha
                </li>
            </ul>
        </div>
    </aside>

    {{-- ===================== LADO DIREITO (formulário) ===================== --}}
    <main class="flex items-center justify-center px-6 py-10">
        <div class="w-full max-w-sm">

            {{-- Marca no celular (o lado esquerdo some em telas pequenas) --}}
            <div class="lg:hidden flex items-center gap-2 mb-8">
                <div class="h-10 w-10 rounded-xl bg-slate-900 flex items-center justify-center">
                    @include('partials.logo-simbolo', ['tamanho' => 28])
                </div>
                <span class="font-semibold">Cyberly Sistema</span>
            </div>

            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-[#163f86]">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/></svg>
                Área restrita
            </span>

            <div class="mt-5 flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-blue-100 text-[#163f86] flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 20a8 8 0 0 1 16 0"/></svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Entrar na conta</h2>
                    <p id="subtitulo" class="text-sm text-gray-500">
                        {{ $modo ? $modos[$modo]['subtitulo'] : 'Escolha como você vai acessar o sistema' }}
                    </p>
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('auth.login.submit') }}" method="POST" class="mt-6 space-y-5" id="form-login">
                @csrf
                <input type="hidden" name="modo" id="campo-modo" value="{{ $modo }}">

                {{-- ===== 1. Tipo de acesso (dropdown) ===== --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de acesso</label>
                    <div class="relative" id="seletor-modo">
                        <button type="button" id="btn-modo" onclick="alternarLista()"
                                class="w-full flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-left text-sm shadow-xs hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            <span id="icone-modo" class="h-8 w-8 shrink-0 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span id="texto-modo" class="block font-medium {{ $modo ? 'text-gray-900' : 'text-gray-400' }}">
                                    {{ $modo ? $modos[$modo]['titulo'] : 'Selecione o tipo de acesso...' }}
                                </span>
                                <span id="descricao-modo" class="block text-xs text-gray-500 truncate {{ $modo ? '' : 'hidden' }}">
                                    {{ $modo ? $modos[$modo]['descricao'] : '' }}
                                </span>
                            </span>
                            <svg id="seta-modo" class="h-4 w-4 text-gray-400 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div id="lista-modo" class="hidden absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                            @foreach ($modos as $chave => $m)
                                <button type="button" onclick="escolherModo('{{ $chave }}')" data-opcao="{{ $chave }}"
                                        class="w-full flex items-center gap-3 px-3.5 py-3 text-left text-sm hover:bg-blue-50 transition {{ $loop->first ? '' : 'border-t border-gray-100' }}">
                                    <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $chave === 'admin' ? 'bg-blue-100 text-[#163f86]' : 'bg-emerald-100 text-emerald-700' }}">
                                        @if ($chave === 'admin')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h7v7H4zM13 5h7v4h-7zM13 11h7v8h-7zM4 14h7v5H4z"/></svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM7 17v3h10v-3M7 11h4"/></svg>
                                        @endif
                                    </span>
                                    <span class="flex-1">
                                        <span class="block font-medium text-gray-900">{{ $m['titulo'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $m['descricao'] }}</span>
                                    </span>
                                    <svg data-check class="h-4 w-4 text-blue-600 {{ $modo === $chave ? '' : 'invisible' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ===== 2. Usuário e senha (aparecem depois de escolher o acesso) ===== --}}
                {{-- Operador com o caixa vazio: o formulário só aparece depois da primeira carga --}}
                <div id="bloco-credenciais" class="space-y-5 {{ $modo && !($modo === 'operador' && ($caixaVazio ?? false)) ? '' : 'hidden' }}">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="campo-codigo" class="block text-sm font-medium text-gray-700 mb-1.5">Código</label>
                            <input type="text" id="campo-codigo" name="codigo" inputmode="numeric" pattern="[0-9]*"
                                   autocomplete="off" value="{{ old('codigo') }}" {{ $modo ? 'required' : '' }}
                                   class="w-full rounded-xl border border-gray-200 bg-blue-50/60 px-3.5 py-3 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Usuário</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 20a8 8 0 0 1 16 0"/></svg>
                                <input type="text" id="campo-usuario" readonly tabindex="-1" placeholder="—"
                                       class="w-full rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-3.5 py-3 text-sm text-gray-800 outline-none truncate">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="campo-senha" class="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z"/></svg>
                            <input type="password" id="campo-senha" name="password" autocomplete="current-password" {{ $modo ? 'required' : '' }}
                                   class="w-full rounded-xl border border-gray-200 bg-blue-50/60 pl-10 pr-11 py-3 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            <button type="button" onclick="alternarSenha()" tabindex="-1" title="Mostrar/ocultar senha"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-linear-to-r from-[#163f86] to-[#2563eb] py-3 text-sm font-semibold text-white shadow-md hover:from-[#12346f] hover:to-[#1d4fd0] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                        Entrar
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </form>

            <p class="mt-8 flex items-center justify-center gap-1.5 text-xs text-gray-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/></svg>
                Acesso restrito a usuários cadastrados
            </p>
        </div>
    </main>
</div>

{{-- Aparece ao enviar o login e fica até a tela do caixa ou do painel carregar --}}
<div id="modal-validando" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-6"
     role="dialog" aria-modal="true" aria-labelledby="titulo-validando">
    <div class="w-full max-w-xs rounded-2xl bg-white px-6 py-8 text-center shadow-xl">
        <div class="mx-auto h-12 w-12 rounded-full border-4 border-blue-100 border-t-[#163f86] animate-spin" aria-hidden="true"></div>
        <h3 id="titulo-validando" class="mt-5 text-base font-semibold text-gray-900">Validando informações...</h3>
        <p id="texto-validando" class="mt-1 text-sm text-gray-500" aria-live="polite"></p>
    </div>
</div>

{{-- Caixa novo (banco do caixa vazio): primeira carga dos dados do servidor ao escolher Operador de Caixa --}}
<div id="modal-primeira-carga" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-6"
     role="dialog" aria-modal="true" aria-labelledby="pc-titulo">
    <div class="w-full max-w-md rounded-2xl bg-white px-6 py-8 shadow-xl">
        <div id="pc-carregando" class="text-center">
            <div class="mx-auto h-12 w-12 rounded-full border-4 border-blue-100 border-t-[#163f86] animate-spin" aria-hidden="true"></div>
            <h3 class="mt-5 text-base font-semibold text-gray-900">Primeiro acesso deste caixa</h3>
            <p class="mt-1 text-sm text-gray-500">Carregando dados do servidor: usuários, PDVs, empresa, produtos e clientes...</p>
        </div>

        <div id="pc-resultado" class="hidden">
            <div class="text-center">
                <span id="pc-icone" class="mx-auto h-12 w-12 rounded-full flex items-center justify-center text-xl font-bold"></span>
                <h3 id="pc-titulo" class="mt-4 text-base font-semibold text-gray-900"></h3>
                <p id="pc-texto" class="mt-1 text-sm text-gray-600" aria-live="polite"></p>
            </div>
            <div id="pc-lista" class="mt-4 space-y-2 text-sm"></div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" id="pc-fechar" onclick="fecharPrimeiraCarga()"
                        class="hidden rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Fechar
                </button>
                <button type="button" id="pc-botao"
                        class="rounded-lg bg-[#163f86] px-5 py-2 text-sm font-semibold text-white hover:bg-[#12346f] focus:outline-none focus:ring-2 focus:ring-blue-500">
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const MODOS = @json($modos);
    let modoLogin = @json($modo);

    const campoModo = document.getElementById('campo-modo');
    const campoCodigo = document.getElementById('campo-codigo');
    const campoUsuario = document.getElementById('campo-usuario');
    const campoSenha = document.getElementById('campo-senha');
    const lista = document.getElementById('lista-modo');

    // ---------------- Dropdown do tipo de acesso ----------------
    function alternarLista(abrir = lista.classList.contains('hidden')) {
        lista.classList.toggle('hidden', !abrir);
        document.getElementById('seta-modo').classList.toggle('rotate-180', abrir);
    }

    function escolherModo(modo) {
        const mudou = modo !== modoLogin;
        modoLogin = modo;
        campoModo.value = modo;

        const m = MODOS[modo];
        document.getElementById('texto-modo').textContent = m.titulo;
        document.getElementById('texto-modo').classList.replace('text-gray-400', 'text-gray-900');
        document.getElementById('descricao-modo').textContent = m.descricao;
        document.getElementById('descricao-modo').classList.remove('hidden');
        document.getElementById('subtitulo').textContent = m.subtitulo;
        document.getElementById('titulo-lateral').textContent = m.titulo;

        const icone = document.getElementById('icone-modo');
        icone.className = 'h-8 w-8 shrink-0 rounded-lg flex items-center justify-center '
            + (modo === 'admin' ? 'bg-blue-100 text-[#163f86]' : 'bg-emerald-100 text-emerald-700');
        icone.innerHTML = lista.querySelector(`[data-opcao="${modo}"] span svg`).outerHTML;

        lista.querySelectorAll('[data-opcao]').forEach(b =>
            b.querySelector('[data-check]').classList.toggle('invisible', b.dataset.opcao !== modo));

        // Trocar de acesso limpa o usuário (o código é de outro cadastro)
        if (mudou) {
            campoSenha.value = '';
            buscarUsuario();
        }

        alternarLista(false);
        history.replaceState(null, '', `{{ route('auth.login') }}?modo=${modo}`);

        // Operador: antes do formulário, confere se este caixa já tem os dados do servidor
        if (modo === 'operador') {
            verificarCaixa();
        } else {
            mostrarCredenciais(true);
        }
    }

    function mostrarCredenciais(mostrar) {
        document.getElementById('bloco-credenciais').classList.toggle('hidden', !mostrar);
        campoCodigo.required = mostrar;
        campoSenha.required = mostrar;
        if (mostrar) campoCodigo.focus();
    }

    document.addEventListener('click', (e) => {
        if (!document.getElementById('seletor-modo').contains(e.target)) alternarLista(false);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') alternarLista(false); });

    // Sem acesso escolhido, Enter não envia o formulário
    const modalValidando = document.getElementById('modal-validando');
    const textoValidando = document.getElementById('texto-validando');
    const botaoEntrar = document.querySelector('#form-login button[type="submit"]');
    let timeoutDemora;

    document.getElementById('form-login').addEventListener('submit', (e) => {
        if (!modoLogin) {
            e.preventDefault();
            alternarLista(true);
            return;
        }

        // Enviado: modal até a próxima tela carregar (e sem segundo envio no meio)
        if (botaoEntrar.disabled) {
            e.preventDefault();
            return;
        }
        botaoEntrar.disabled = true;
        textoValidando.textContent = modoLogin === 'admin' ? 'Abrindo o painel administrativo' : 'Preparando o caixa';
        modalValidando.classList.remove('hidden');
        timeoutDemora = setTimeout(() => {
            textoValidando.textContent = 'Está demorando mais que o normal. Aguarde...';
        }, 8000);
    });

    // Voltou para esta tela pelo botão Voltar (página guardada pelo navegador): some com o modal
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            clearTimeout(timeoutDemora);
            modalValidando.classList.add('hidden');
            botaoEntrar.disabled = false;
        }
    });

    function alternarSenha() {
        campoSenha.type = campoSenha.type === 'password' ? 'text' : 'password';
    }

    // ---------------- Nome do usuário pelo código (igual ao login anterior) ----------------
    let timeoutUsuario;
    let ultimaBusca = 0;

    function definirUsuario(texto, erro = false) {
        campoUsuario.value = texto;
        campoUsuario.classList.toggle('text-red-600', erro);
        campoUsuario.classList.toggle('text-gray-800', !erro);
    }

    async function buscarUsuario() {
        const codigo = campoCodigo.value.trim();
        const busca = ++ultimaBusca;

        if (!modoLogin || !/^\d+$/.test(codigo)) {
            definirUsuario('');
            return;
        }

        // Primeira carga em andamento: ela mesma traz os usuários (duas cargas juntas disputariam o SQLite)
        if (carregandoPrimeira) return;

        try {
            const url = `{{ route('auth.usuario') }}?modo=${modoLogin}&codigo=${encodeURIComponent(codigo)}`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const dados = await resp.json();

            if (busca !== ultimaBusca) return; // chegou uma resposta mais nova

            dados.nome ? definirUsuario(dados.nome) : definirUsuario('Código não encontrado', true);
        } catch (e) {
            if (busca === ultimaBusca) definirUsuario('');
        }
    }

    campoCodigo.addEventListener('input', () => {
        clearTimeout(timeoutUsuario);
        timeoutUsuario = setTimeout(buscarUsuario, 250);
    });

    // Enter no código vai para a senha, em vez de enviar o formulário
    campoCodigo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            campoSenha.focus();
        }
    });

    // ---------------- Primeira carga de um caixa novo ----------------
    let caixaVazio = @json($caixaVazio ?? false);
    let carregandoPrimeira = false;
    const modalPrimeira = document.getElementById('modal-primeira-carga');

    function itemPrimeiraCarga(classes, titulo, texto, detalhe) {
        const caixa = document.createElement('div');
        caixa.className = 'rounded-lg border px-3 py-2 ' + classes;
        const t = document.createElement('p');
        t.className = 'font-medium';
        t.textContent = titulo;
        caixa.appendChild(t);
        if (texto) {
            const p = document.createElement('p');
            p.textContent = texto;
            caixa.appendChild(p);
        }
        if (detalhe && detalhe !== texto) {
            const d = document.createElement('p');
            d.className = 'mt-1 font-mono text-[11px] opacity-70 break-all';
            d.textContent = detalhe;
            caixa.appendChild(d);
        }
        document.getElementById('pc-lista').appendChild(caixa);
    }

    function resultadoPrimeiraCarga(tipo, titulo, texto, rotuloBotao, acaoBotao, mostrarFechar) {
        const estilos = {
            ok:    ['bg-emerald-100 text-emerald-700', '✓'],
            aviso: ['bg-amber-100 text-amber-700', '!'],
            erro:  ['bg-red-100 text-red-700', '✕'],
        };
        const icone = document.getElementById('pc-icone');
        icone.className = 'mx-auto h-12 w-12 rounded-full flex items-center justify-center text-xl font-bold ' + estilos[tipo][0];
        icone.textContent = estilos[tipo][1];
        document.getElementById('pc-titulo').textContent = titulo;
        document.getElementById('pc-texto').textContent = texto;

        const botao = document.getElementById('pc-botao');
        botao.textContent = rotuloBotao;
        botao.onclick = acaoBotao;
        document.getElementById('pc-fechar').classList.toggle('hidden', !mostrarFechar);

        document.getElementById('pc-carregando').classList.add('hidden');
        document.getElementById('pc-resultado').classList.remove('hidden');
        botao.focus();
    }

    // Banco do caixa vazio: modal da primeira carga; preenchido: formulário de código e senha
    async function verificarCaixa() {
        mostrarCredenciais(false);

        try {
            const resp = await fetch('{{ route('caixa.situacao') }}', { headers: { 'Accept': 'application/json' } });
            caixaVazio = (await resp.json()).vazio;
        } catch (e) {
            caixaVazio = false; // na dúvida mostra o formulário: o login avisa se o caixa estiver vazio
        }

        if (modoLogin !== 'operador') return; // trocou de acesso enquanto conferia

        caixaVazio ? primeiraCarga() : mostrarCredenciais(true);
    }

    // Fechar sem carregar: o formulário continua escondido; escolher Operador de novo tenta outra vez
    function fecharPrimeiraCarga() {
        modalPrimeira.classList.add('hidden');
    }

    function continuarAposCarga() {
        modalPrimeira.classList.add('hidden');
        mostrarCredenciais(true);
    }

    async function primeiraCarga() {
        if (carregandoPrimeira) return;
        carregandoPrimeira = true;

        document.getElementById('pc-lista').replaceChildren();
        document.getElementById('pc-resultado').classList.add('hidden');
        document.getElementById('pc-carregando').classList.remove('hidden');
        modalPrimeira.classList.remove('hidden');

        try {
            const resp = await fetch('{{ route('caixa.primeira-carga') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });

            if (resp.status === 419) {
                resultadoPrimeiraCarga('erro', 'Sessão expirada', 'Recarregue a página (F5) e tente de novo.', 'Fechar', fecharPrimeiraCarga, false);
            } else if (resp.status === 429) {
                resultadoPrimeiraCarga('erro', 'Muitas tentativas', 'Aguarde um minuto e tente de novo.', 'Tentar novamente', primeiraCarga, true);
            } else {
                const r = await resp.json();

                if (r.ja_carregado) {
                    caixaVazio = false;
                    continuarAposCarga();
                } else if (r.sucesso) {
                    caixaVazio = false;
                    const avisos = r.avisos || [];
                    const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;
                    itemPrimeiraCarga('border-gray-200 bg-gray-50 text-gray-700', 'Dados recebidos',
                        [plural(r.usuarios, 'usuário', 'usuários'), plural(r.pdvs, 'PDV', 'PDVs'),
                         plural(r.produtos, 'produto', 'produtos'), plural(r.clientes, 'cliente', 'clientes')].join(' · '));
                    avisos.forEach(a => itemPrimeiraCarga('border-amber-200 bg-amber-50 text-amber-800', a.parte, a.motivo, a.detalhe));

                    avisos.length
                        ? resultadoPrimeiraCarga('aviso', 'Sincronização realizada com avisos', 'O caixa já pode ser usado, mas confira os avisos abaixo.', 'Continuar', continuarAposCarga, false)
                        : resultadoPrimeiraCarga('ok', 'Sincronização realizada', 'Este caixa já tem os dados do servidor. Entre com seu código e senha.', 'Continuar', continuarAposCarga, false);
                } else {
                    if (r.erro) itemPrimeiraCarga('border-red-200 bg-red-50 text-red-700', 'Motivo', r.erro, r.detalhe);
                    resultadoPrimeiraCarga('erro', 'Não foi possível carregar os dados', 'Sem esses dados o operador ainda não consegue entrar neste caixa.', 'Tentar novamente', primeiraCarga, true);
                }
            }
        } catch (e) {
            resultadoPrimeiraCarga('erro', 'Erro de conexão', 'Este computador não conseguiu completar a carga. Tente de novo em instantes.', 'Tentar novamente', primeiraCarga, true);
        }

        carregandoPrimeira = false;
    }

    // Voltou de uma tentativa com erro (ou veio com ?modo=): já abre no campo certo
    if (modoLogin) {
        if (campoCodigo.value) {
            buscarUsuario();
            campoSenha.focus();
        } else {
            campoCodigo.focus();
        }
    }

    // Veio com ?modo=operador (atalho do caixa): confere o banco do caixa já ao abrir
    if (modoLogin === 'operador') verificarCaixa();
</script>
</body>
</html>