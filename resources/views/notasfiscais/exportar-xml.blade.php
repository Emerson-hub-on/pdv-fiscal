@extends('layouts.app')

@section('titulo', 'Exportar XML NF-e')

@section('breadcrumb')
    <span>Faturamento</span>
    <span class="text-gray-300">›</span>
    <span>Nota Fiscal</span>
    <span class="text-gray-300">›</span>
    <span class="text-gray-700 font-medium">Exportar XML NF-e</span>
@endsection

@section('conteudo')
<div class="max-w-xl mx-auto mt-[8vh]">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 pt-5 pb-3 border-b border-gray-100">
            <h1 class="text-lg font-bold text-gray-800">Exportar XML NF-e</h1>
            <p class="text-xs text-gray-500">NF-e autorizadas (com protocolo), cancelamentos e inutilizações do período, num arquivo .zip.</p>
        </div>

        {{-- Formulário --}}
        <div id="xml-formulario" class="px-6 py-4 space-y-4">
            <div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="xml-de" class="block text-sm font-medium mb-1">Data inicial</label>
                        <input type="date" id="xml-de" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="xml-ate" class="block text-sm font-medium mb-1">Data final</label>
                        <input type="date" id="xml-ate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-2">
                    <button type="button" onclick="xmlPeriodoRapido('hoje')" class="px-2.5 py-1 text-xs rounded border border-gray-300 hover:bg-gray-100 cursor-pointer">Hoje</button>
                    <button type="button" onclick="xmlPeriodoRapido('mes')" class="px-2.5 py-1 text-xs rounded border border-gray-300 hover:bg-gray-100 cursor-pointer">Este mês</button>
                    <button type="button" onclick="xmlPeriodoRapido('mes_passado')" class="px-2.5 py-1 text-xs rounded border border-gray-300 hover:bg-gray-100 cursor-pointer">Mês passado</button>
                </div>
                <p id="xml-resumo" class="mt-2 text-sm text-gray-600 min-h-5"></p>
            </div>

            <div>
                <label for="xml-destino" class="block text-sm font-medium mb-1">Salvar em</label>
                <div class="flex gap-2">
                    <input type="text" id="xml-destino" readonly placeholder="Escolha a pasta de destino"
                           onclick="xmlAbrirNavegador()"
                           class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 cursor-pointer truncate">
                    <button type="button" onclick="xmlAbrirNavegador()"
                            class="shrink-0 px-3 py-2 text-sm font-medium rounded-lg bg-slate-700 hover:bg-slate-800 text-white cursor-pointer">
                        Escolher pasta
                    </button>
                </div>

                {{-- Navegador de pastas (do computador onde o sistema está instalado) --}}
                <div id="xml-navegador" class="hidden mt-2 border border-gray-200 rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border-b border-gray-200">
                        <button type="button" id="xml-voltar" onclick="xmlVoltarPasta()"
                                class="shrink-0 px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-100 cursor-pointer disabled:opacity-40 disabled:cursor-default">
                            ↑ Voltar
                        </button>
                        <span id="xml-caminho-atual" class="text-xs text-gray-600 truncate">Este computador</span>
                    </div>
                    <ul id="xml-lista-pastas" class="max-h-52 overflow-y-auto divide-y divide-gray-100 text-sm"></ul>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-gray-50 border-t border-gray-200">
                        <span id="xml-aviso-pasta" class="text-xs text-gray-500 truncate"></span>
                        <button type="button" id="xml-usar-pasta" onclick="xmlUsarPastaAtual()"
                                class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white cursor-pointer disabled:opacity-40 disabled:cursor-default">
                            Usar esta pasta
                        </button>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    As pastas são do computador onde o sistema está instalado. Em outro computador, use <strong>Baixar .zip</strong>.
                </p>
            </div>

            <p id="xml-erro" class="hidden text-sm text-red-600"></p>
        </div>

        {{-- Resultado --}}
        <div id="xml-resultado" class="hidden px-6 py-6 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p id="xml-resultado-titulo" class="mt-3 text-lg font-bold"></p>
            <p id="xml-resultado-pasta" class="mt-1 text-xs text-gray-500 break-all"></p>
            <p id="xml-resultado-detalhe" class="mt-2 text-sm text-gray-600"></p>
        </div>

        <div class="flex flex-wrap justify-end gap-2 px-6 py-4 border-t border-gray-100">
            <button type="button" id="xml-btn-nova" onclick="xmlNovaExportacao()"
                    class="hidden px-4 py-2 text-sm font-semibold rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 cursor-pointer">
                Nova exportação
            </button>
            <button type="button" id="xml-btn-baixar" onclick="xmlBaixar()"
                    class="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 cursor-pointer">
                Baixar .zip
            </button>
            <button type="button" id="xml-btn-exportar" onclick="xmlExportar()"
                    class="px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white cursor-pointer disabled:opacity-50 disabled:cursor-default">
                Salvar na pasta
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const XML_URLS = {
        pastas: @json(route('notasfiscais.exportar-xml.pastas')),
        resumo: @json(route('notasfiscais.exportar-xml.resumo')),
        exportar: @json(route('notasfiscais.exportar-xml.gravar')),
        baixar: @json(route('notasfiscais.exportar-xml.baixar')),
    };
    const XML_CHAVE_DESTINO = 'cyberly.exportar_xml_nfe.destino';
    let xmlPastaAtual = null;
    let xmlPastaPai = null;
    let xmlExportando = false;
    let xmlTimerResumo = null;
    let xmlTotalPeriodo = null;

    const xmlEl = (id) => document.getElementById(id);
    const xmlData = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    function xmlErro(mensagem) {
        xmlEl('xml-erro').textContent = mensagem;
        xmlEl('xml-erro').classList.toggle('hidden', !mensagem);
    }

    function xmlPeriodoRapido(tipo, atualizar = true) {
        const hoje = new Date();
        let de = hoje, ate = hoje;
        if (tipo === 'mes') {
            de = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        } else if (tipo === 'mes_passado') {
            de = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
            ate = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
        }
        xmlEl('xml-de').value = xmlData(de);
        xmlEl('xml-ate').value = xmlData(ate);
        if (atualizar) xmlAtualizarResumo();
    }

    async function xmlJson(resposta) {
        const dados = await resposta.json().catch(() => ({}));
        if (!resposta.ok) {
            const primeiro = dados.errors ? Object.values(dados.errors)[0]?.[0] : null;
            throw new Error(dados.erro || primeiro || dados.message || 'Erro ao comunicar com o servidor.');
        }
        return dados;
    }

    function xmlTextoContagem(r) {
        const partes = [`${r.autorizadas} NF-e autorizada${r.autorizadas === 1 ? '' : 's'}`];
        if (r.cancelamentos) partes.push(`${r.cancelamentos} cancelamento${r.cancelamentos === 1 ? '' : 's'}`);
        if (r.inutilizadas) partes.push(`${r.inutilizadas} inutilizaç${r.inutilizadas === 1 ? 'ão' : 'ões'}`);
        let texto = partes.join(' · ');
        if (r.canceladas_depois) texto += ` (${r.canceladas_depois} delas cancelada${r.canceladas_depois === 1 ? '' : 's'} depois)`;
        return texto;
    }

    async function xmlAtualizarResumo() {
        clearTimeout(xmlTimerResumo);
        xmlTimerResumo = setTimeout(async () => {
            const resumo = xmlEl('xml-resumo');
            const de = xmlEl('xml-de').value, ate = xmlEl('xml-ate').value;
            xmlTotalPeriodo = null;
            if (!de || !ate) { resumo.textContent = ''; return; }

            resumo.className = 'mt-2 text-sm text-gray-500 min-h-5';
            resumo.textContent = 'Contando os XMLs...';
            try {
                const r = await xmlJson(await fetch(`${XML_URLS.resumo}?de=${de}&ate=${ate}`, { headers: { Accept: 'application/json' } }));
                xmlTotalPeriodo = r.autorizadas + r.cancelamentos + r.inutilizadas;
                resumo.className = 'mt-2 text-sm min-h-5 ' + (xmlTotalPeriodo ? 'text-gray-700' : 'text-amber-700');
                resumo.textContent = xmlTotalPeriodo ? xmlTextoContagem(r) : 'Nenhum XML de NF-e neste período.';
            } catch (e) {
                resumo.className = 'mt-2 text-sm text-red-600 min-h-5';
                resumo.textContent = e.message;
            }
        }, 250);
    }

    // ---------------- Navegador de pastas ----------------
    function xmlAbrirNavegador() {
        xmlEl('xml-navegador').classList.remove('hidden');
        const atual = xmlEl('xml-destino').value;
        xmlCarregarPastas(atual || null, !!atual);
    }

    async function xmlCarregarPastas(caminho, voltarParaRaizSeFalhar = false) {
        const lista = xmlEl('xml-lista-pastas');
        lista.innerHTML = '<li class="px-3 py-3 text-gray-500 text-xs">Carregando...</li>';

        try {
            const url = caminho ? `${XML_URLS.pastas}?caminho=${encodeURIComponent(caminho)}` : XML_URLS.pastas;
            const r = await xmlJson(await fetch(url, { headers: { Accept: 'application/json' } }));

            xmlPastaAtual = r.caminho;
            xmlPastaPai = r.pai;
            xmlEl('xml-caminho-atual').textContent = r.caminho || 'Este computador';
            xmlEl('xml-voltar').disabled = !r.caminho;
            xmlEl('xml-usar-pasta').disabled = !r.caminho || r.gravavel === false;
            xmlEl('xml-aviso-pasta').textContent = r.caminho
                ? (r.gravavel === false ? 'Não é possível salvar nesta pasta (sem permissão ou é a pasta do sistema).' : '')
                : 'Escolha um local para abrir.';

            lista.innerHTML = '';
            if (!r.pastas.length) {
                lista.innerHTML = '<li class="px-3 py-3 text-gray-500 text-xs">Nenhuma subpasta. Use esta pasta ou volte.</li>';
                return;
            }

            r.pastas.forEach((p) => {
                const item = document.createElement('li');
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-slate-100 cursor-pointer';
                botao.innerHTML = p.tipo === 'unidade'
                    ? '<svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 12h.01"/></svg>'
                    : '<svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/></svg>';
                const nome = document.createElement('span');
                nome.className = 'truncate';
                nome.textContent = p.nome;
                botao.appendChild(nome);
                botao.addEventListener('click', () => xmlCarregarPastas(p.caminho));
                item.appendChild(botao);
                lista.appendChild(item);
            });
        } catch (e) {
            if (voltarParaRaizSeFalhar) return xmlCarregarPastas(null);
            lista.innerHTML = '';
            const item = document.createElement('li');
            item.className = 'px-3 py-3 text-red-600 text-xs';
            item.textContent = e.message;
            lista.appendChild(item);
        }
    }

    function xmlVoltarPasta() {
        xmlCarregarPastas(xmlPastaPai || null);
    }

    function xmlUsarPastaAtual() {
        if (!xmlPastaAtual) return;
        xmlEl('xml-destino').value = xmlPastaAtual;
        xmlEl('xml-navegador').classList.add('hidden');
        xmlErro('');
        try { localStorage.setItem(XML_CHAVE_DESTINO, xmlPastaAtual); } catch (e) { /* sem localStorage */ }
    }

    // ---------------- Exportar ----------------
    function xmlPeriodoValido() {
        const de = xmlEl('xml-de').value, ate = xmlEl('xml-ate').value;
        if (!de || !ate) { xmlErro('Informe a data inicial e a data final.'); return null; }
        if (ate < de) { xmlErro('A data final deve ser igual ou posterior à data inicial.'); return null; }
        return { de, ate };
    }

    async function xmlExportar() {
        if (xmlExportando) return;
        const periodo = xmlPeriodoValido();
        if (!periodo) return;
        const destino = xmlEl('xml-destino').value;
        if (!destino) { xmlAbrirNavegador(); return xmlErro('Escolha a pasta onde os XMLs serão salvos.'); }

        xmlErro('');
        xmlExportando = true;
        const botao = xmlEl('xml-btn-exportar');
        botao.disabled = true;
        botao.textContent = 'Exportando...';

        try {
            const r = await xmlJson(await fetch(XML_URLS.exportar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({ ...periodo, destino }),
            }));

            xmlEl('xml-formulario').classList.add('hidden');
            xmlEl('xml-resultado').classList.remove('hidden');
            botao.classList.add('hidden');
            xmlEl('xml-btn-baixar').classList.add('hidden');
            xmlEl('xml-btn-nova').classList.remove('hidden');
            xmlEl('xml-resultado-titulo').textContent = `${r.copiados} XML${r.copiados === 1 ? '' : 's'} exportado${r.copiados === 1 ? '' : 's'}`;
            xmlEl('xml-resultado-pasta').textContent = r.pasta;
            xmlEl('xml-resultado-detalhe').textContent = xmlTextoContagem(r)
                + (r.qtd_falhas ? `. ${r.qtd_falhas} não puderam ser incluídos: ${r.falhas.join(', ')}` : '');
        } catch (e) {
            xmlErro(e.message);
        } finally {
            xmlExportando = false;
            botao.disabled = false;
            botao.textContent = 'Salvar na pasta';
        }
    }

    function xmlBaixar() {
        const periodo = xmlPeriodoValido();
        if (!periodo) return;
        if (xmlTotalPeriodo === 0) return xmlErro('Nenhum XML de NF-e neste período.');
        xmlErro('');
        window.location.href = `${XML_URLS.baixar}?de=${periodo.de}&ate=${periodo.ate}`;
    }

    function xmlNovaExportacao() {
        xmlEl('xml-formulario').classList.remove('hidden');
        xmlEl('xml-resultado').classList.add('hidden');
        xmlEl('xml-btn-exportar').classList.remove('hidden');
        xmlEl('xml-btn-baixar').classList.remove('hidden');
        xmlEl('xml-btn-nova').classList.add('hidden');
    }

    xmlEl('xml-de').addEventListener('change', xmlAtualizarResumo);
    xmlEl('xml-ate').addEventListener('change', xmlAtualizarResumo);

    // Esc fecha o navegador de pastas; Enter num campo de data salva na pasta
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !xmlEl('xml-navegador').classList.contains('hidden')) {
            xmlEl('xml-navegador').classList.add('hidden');
        } else if (e.key === 'Enter' && e.target.matches?.('#xml-de, #xml-ate')) {
            e.preventDefault();
            xmlExportar();
        }
    });

    xmlPeriodoRapido('mes_passado', false);
    try { xmlEl('xml-destino').value = localStorage.getItem(XML_CHAVE_DESTINO) || ''; } catch (e) { /* sem localStorage */ }
    xmlAtualizarResumo();
</script>
@endsection
