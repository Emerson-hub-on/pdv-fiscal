@php
    $mdfe = $mdfe ?? null;
    $ufs  = \App\Models\Transportador::UFS;

    extract(config('estilo.formulario')); // $inputCls, $labelCls, $botaoPrimarioCls...
    $campo    = $inputCls;
    $campoSel = $inputCls;
    $botaoAdd = $botaoSecundarioCls;
    $caixaRes = 'hidden absolute left-0 right-0 mt-1 bg-white ring-1 ring-slate-200 rounded-xl shadow-xl max-h-64 overflow-y-auto z-20';

    $abas = [
        'gerais'        => 'Dados gerais',
        'percurso'      => 'UFs do percurso',
        'carregamentos' => 'Municípios de carregamento',
        'documentos'    => 'NF-e',
        'veiculos'      => 'Veículos e condutores',
    ];

    // Se o servidor devolveu erro, abre a aba do primeiro campo com problema
    $primeiroErro = $errors->keys()[0] ?? null;
    $abaInicial = match (true) {
        $primeiroErro === null                                      => 'gerais',
        str_starts_with($primeiroErro, 'percurso')                  => 'percurso',
        str_starts_with($primeiroErro, 'carregamentos')             => 'carregamentos',
        str_starts_with($primeiroErro, 'documentos')                => 'documentos',
        str_starts_with($primeiroErro, 'veiculo'),
        str_starts_with($primeiroErro, 'reboques'),
        str_starts_with($primeiroErro, 'condutores')                => 'veiculos',
        default                                                     => 'gerais',
    };
@endphp

{{-- Card único (faixa azul-marinho + abas + painéis) --}}
<div class="{{ $cartaoCls }}">

<x-form.cabecalho rotulo="Faturamento · MDF-e" :titulo="$mdfe ? 'Editar MDF-e' : 'Novo MDF-e'" selo="Rascunho"
                  :voltar="route('mdfes.index')" voltar-titulo="Voltar para Manifestos" />

{{-- ===================== BARRA DE ABAS ===================== --}}
<div class="flex overflow-x-auto border-b border-slate-200 px-2">
    @foreach ($abas as $chave => $rotulo)
        <button type="button" data-aba="{{ $chave }}" onclick="abrirAba('{{ $chave }}')"
                class="aba-botao flex items-center gap-2 px-5 py-3.5 -mb-px text-sm font-semibold whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition">
            {{ $rotulo }}
            @if (in_array($chave, ['percurso', 'carregamentos', 'documentos']))
                <span data-contador="{{ $chave }}" class="px-1.5 py-0.5 rounded-full text-xs bg-slate-100 text-slate-500">0</span>
            @endif
        </button>
    @endforeach
</div>

{{-- ===================== DADOS GERAIS ===================== --}}
<div data-painel="gerais" class="p-6">
    <x-form.secao titulo="Dados gerais" icone="caminhao">Emitente, UFs de início e fim da viagem e RNTRC.</x-form.secao>
    <div class="grid grid-cols-3 gap-5">

        <div class="col-span-2">
            <label class="{{ $labelCls }}">Tipo de emitente <span class="text-red-500">*</span></label>
            <select name="tipo_emitente" required class="{{ $campoSel }}">
                @foreach ($tiposEmitente as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_emitente', $mdfe->tipo_emitente ?? 2) === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Tipo de transportador <span class="{{ $opcionalCls }}">(opcional)</span>
            </label>
            <select name="tipo_transportador" class="{{ $campoSel }}">
                <option value="">Não informar</option>
                @foreach ($tiposTransportador as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_transportador', $mdfe->tipo_transportador ?? '') === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">UF de início <span class="text-red-500">*</span></label>
            <select name="uf_inicio" required class="{{ $campoSel }}">
                <option value="">Selecione...</option>
                @foreach ($ufs as $uf)
                    <option value="{{ $uf }}" {{ old('uf_inicio', $mdfe->uf_inicio ?? $ufPadrao) === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">UF de fim <span class="text-red-500">*</span></label>
            <select name="uf_fim" required class="{{ $campoSel }}">
                <option value="">Selecione...</option>
                @foreach ($ufs as $uf)
                    <option value="{{ $uf }}" {{ old('uf_fim', $mdfe->uf_fim ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Início da viagem <span class="{{ $opcionalCls }}">(opcional)</span>
            </label>
            <input type="datetime-local" name="inicio_viagem"
                   value="{{ old('inicio_viagem', $mdfe?->inicio_viagem?->format('Y-m-d\TH:i')) }}"
                   class="{{ $campo }}">
        </div>

        <div>
            <label class="{{ $labelCls }}">
                RNTRC <span class="{{ $opcionalCls }}">(8 dígitos, ANTT)</span>
            </label>
            <input type="text" name="rntrc" maxlength="8" inputmode="numeric"
                   value="{{ old('rntrc', $mdfe->rntrc ?? $rntrcPadrao) }}"
                   class="{{ $campo }} font-mono">
            <p class="text-xs text-gray-400 mt-1">Fica salvo neste MDF-e. Sugerimos o último usado; confira se ainda está vigente.</p>
        </div>

        <div class="col-span-3">
            <label class="flex items-start gap-3 cursor-pointer rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 hover:bg-white transition">
                <input type="checkbox" id="dividir-por-uf" name="dividir_por_uf" value="1"
                       {{ old('dividir_por_uf') ? 'checked' : '' }}
                       class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-[#10284A] focus:ring-2 focus:ring-[#10284A]/30">
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Separar automaticamente por UF de descarga</span>
                    <span class="block text-xs text-gray-400 mt-0.5">
                        Cada MDF-e atende uma única UF de descarga. Com esta opção, as NF-e são divididas em um MDF-e por UF
                        (a UF de fim de cada um vem do município do cliente) e os demais dados são copiados para todos.
                    </span>
                </span>
            </label>
        </div>

        <div class="col-span-3">
            <label class="{{ $labelCls }}">
                Informações adicionais <span class="{{ $opcionalCls }}">(opcional)</span>
            </label>
            <textarea name="informacoes_adicionais" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('informacoes_adicionais', $mdfe->informacoes_adicionais ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- ===================== PERCURSO ===================== --}}
<div data-painel="percurso" class="hidden p-6">
    <x-form.secao titulo="UFs do percurso" icone="mapa">Estados por onde a carga passa entre o início e o fim, na ordem da viagem (opcional quando início e fim são vizinhos).</x-form.secao>

    <div class="flex items-center gap-2 mb-3">
        <div class="w-32">
            <select id="perc-uf" class="{{ $campoSel }}">
                @foreach ($ufs as $uf)
                    <option value="{{ $uf }}">{{ $uf }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" onclick="adicionarPercurso()" class="{{ $botaoAdd }}">Adicionar UF</button>
    </div>
    <div id="lista-percurso" class="flex flex-wrap gap-2"></div>
</div>

{{-- ===================== CARREGAMENTO ===================== --}}
<div data-painel="carregamentos" class="hidden p-6">
    <x-form.secao titulo="Municípios de carregamento" icone="pino">Informe ao menos um município. Os municípios de descarga vêm automaticamente do cadastro dos clientes das NF-e.</x-form.secao>

    <div class="grid grid-cols-12 gap-2 mb-3">
        <input type="text" id="car-ibge" maxlength="7" inputmode="numeric" placeholder="Código IBGE (7 dígitos)" data-sem-enter
               class="{{ $campo }} col-span-3 font-mono">
        <input type="text" id="car-nome" maxlength="60" placeholder="Digite o município e escolha na lista" data-sem-enter class="{{ $campo }} col-span-5">
        <select id="car-uf" class="{{ $campoSel }} col-span-2">
            <option value="">UF</option>
            @foreach ($ufs as $uf)
                <option value="{{ $uf }}">{{ $uf }}</option>
            @endforeach
        </select>
        <button type="button" onclick="adicionarCarregamento()" class="{{ $botaoAdd }} col-span-2">Adicionar</button>
    </div>
    <div id="lista-carregamentos" class="space-y-2"></div>
</div>

{{-- ===================== DOCUMENTOS ===================== --}}
<div data-painel="documentos" class="hidden p-6">
    <x-form.secao titulo="NF-e do manifesto" icone="documento">Só aparecem NF-e emitidas que ainda não estão em outro MDF-e em rascunho ou emitido.</x-form.secao>

    <div class="relative mb-4">
        <input type="text" id="nfe-busca" autocomplete="off" data-sem-enter
               placeholder="Buscar por número, chave ou nome do cliente..." class="{{ $campo }}">
        <div id="nfe-resultados" class="{{ $caixaRes }}"></div>
    </div>

    <div id="lista-documentos"></div>
</div>

{{-- ===================== VEÍCULOS E CONDUTORES ===================== --}}
<div data-painel="veiculos" class="hidden p-6">
    <x-form.secao titulo="Veículos e condutores" icone="caminhao">Veículo de tração, reboques e condutores da viagem.</x-form.secao>
    <div class="grid grid-cols-2 gap-6">

        <div>
            <label class="{{ $labelCls }}">Veículo de tração <span class="text-red-500">*</span></label>
            <div class="relative">
                <input type="text" id="trac-busca" autocomplete="off" data-sem-enter placeholder="Buscar por placa ou transportadora..." class="{{ $campo }}">
                <div id="trac-resultados" class="{{ $caixaRes }}"></div>
            </div>
            <div id="area-tracao" class="mt-2"></div>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Reboques <span class="{{ $opcionalCls }}">(até 3, opcional)</span>
            </label>
            <div class="relative">
                <input type="text" id="reb-busca" autocomplete="off" data-sem-enter placeholder="Buscar reboque por placa..." class="{{ $campo }}">
                <div id="reb-resultados" class="{{ $caixaRes }}"></div>
            </div>
            <div id="lista-reboques" class="mt-2 space-y-2"></div>
        </div>

        <div class="col-span-2">
            <label class="{{ $labelCls }}">Condutores <span class="text-red-500">*</span> <span class="{{ $opcionalCls }}">(até 10)</span></label>
            <div class="relative">
                <input type="text" id="cond-busca" autocomplete="off" data-sem-enter placeholder="Buscar por nome ou CPF..." class="{{ $campo }}">
                <div id="cond-resultados" class="{{ $caixaRes }}"></div>
            </div>
            <div id="lista-condutores" class="mt-2 space-y-2"></div>
        </div>
    </div>
</div>

</div>{{-- /card --}}

<div class="mt-6 flex flex-row-reverse gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        <x-form.icone nome="check" />
        Salvar rascunho
    </button>
    <a href="{{ route('mdfes.index') }}" class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>

<script>
    const formulario = document.currentScript.closest('form');
    const estado = @json($estado);
    const UFS = @json($ufs);
    const MDFE_ID = @json($mdfe?->id);
    const ABA_INICIAL = @json($abaInicial);
    const ROTAS = {
        nfe: @json(route('mdfes.buscar-nfe')),
        veiculos: @json(route('veiculos.listar')),
        condutores: @json(route('condutores.listar')),
    };
    const MAX_REBOQUES = 3;
    const MAX_CONDUTORES = 10;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const brl = (n) => Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const kg = (n) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

    const CLASSE_ITEM = 'flex items-center justify-between gap-3 bg-slate-50 ring-1 ring-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700';
    const CLASSE_REMOVER = 'text-red-600 hover:text-red-700 font-medium text-sm';
    const ATIVA = ['border-[#10284A]', 'text-[#10284A]'];
    const INATIVA = ['border-transparent', 'text-slate-500', 'hover:text-slate-800'];

    // ---------------- Separar por UF de descarga ----------------
    const caixaDividir = document.getElementById('dividir-por-uf');
    const ufFim = formulario.querySelector('[name="uf_fim"]');

    // O texto da descarga vem como "Recife/PE"
    const ufDaDescarga = (d) => (String(d.municipio ?? '').split('/').pop() || '').trim().toUpperCase();

    function atualizarAvisoUf() {
        const el = document.getElementById('aviso-uf');
        if (!el) return;

        const ufsDescarga = [...new Set(estado.documentos.map(ufDaDescarga).filter(Boolean))];

        if (ufsDescarga.length < 2) { el.textContent = ''; return; }

        if (caixaDividir.checked) {
            el.className = 'mt-2 text-sm text-blue-700';
            el.textContent = `Serão criados ${ufsDescarga.length} MDF-e, um por UF de descarga (${ufsDescarga.join(', ')}). Revise o percurso de cada um depois de salvar.`;
        } else {
            el.className = 'mt-2 text-sm text-amber-700';
            el.textContent = `Estas NF-e descarregam em ${ufsDescarga.length} UFs (${ufsDescarga.join(', ')}). Marque "Separar automaticamente por UF de descarga" em Dados gerais ou remova as NF-e de outras UFs.`;
        }
    }

    // Com a separação ligada, a UF de fim é definida pelo sistema em cada MDF-e
    function atualizarUfFim() {
        ufFim.disabled = caixaDividir.checked;
        ufFim.required = !caixaDividir.checked;
        atualizarAvisoUf();
    }
    caixaDividir.addEventListener('change', atualizarUfFim);

    // ---------------- Abas ----------------
    // Os painéis só ficam escondidos (não são removidos), então tudo continua indo no envio do formulário.
    function abrirAba(chave) {
        document.querySelectorAll('[data-painel]').forEach((p) => p.classList.toggle('hidden', p.dataset.painel !== chave));
        document.querySelectorAll('.aba-botao').forEach((b) => {
            const ativa = b.dataset.aba === chave;
            b.classList.remove(...ATIVA, ...INATIVA);
            b.classList.add(...(ativa ? ATIVA : INATIVA));
        });
    }

    // Campo obrigatório vazio dentro de uma aba escondida: abre a aba para o navegador mostrar o aviso
    formulario.addEventListener('invalid', (e) => {
        const painel = e.target.closest('[data-painel]');
        if (painel && painel.classList.contains('hidden')) abrirAba(painel.dataset.painel);
    }, true);

    function atualizarContador(chave, total) {
        const el = document.querySelector(`[data-contador="${chave}"]`);
        if (!el) return;
        el.textContent = total;
        el.className = 'px-1.5 py-0.5 rounded-full text-xs ' + (total > 0 ? 'bg-[#10284A] text-amber-50' : 'bg-slate-100 text-slate-500');
    }

    // ---------------- UFs do percurso ----------------
    function renderPercurso() {
        document.getElementById('lista-percurso').innerHTML = estado.percurso.length
            ? estado.percurso.map((uf, i) => `
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-[#10284A] text-amber-50">
                    ${i + 1}. ${esc(uf)}
                    <button type="button" onclick="removerPercurso(${i})" class="text-slate-300 hover:text-red-300">×</button>
                    <input type="hidden" name="percurso[]" value="${esc(uf)}">
                </span>`).join('')
            : '<p class="text-xs text-gray-400">Nenhuma UF intermediária.</p>';
        atualizarContador('percurso', estado.percurso.length);
    }
    function adicionarPercurso() {
        if (estado.percurso.length >= 25) return;
        estado.percurso.push(document.getElementById('perc-uf').value);
        renderPercurso();
    }
    function removerPercurso(i) { estado.percurso.splice(i, 1); renderPercurso(); }

    // ---------------- Carregamento ----------------
    function renderCarregamentos() {
        document.getElementById('lista-carregamentos').innerHTML = estado.carregamentos.length
            ? estado.carregamentos.map((m, i) => `
                <div class="${CLASSE_ITEM}">
                    <span><span class="font-medium">${esc(m.nome)}/${esc(m.uf)}</span>
                        <span class="text-xs text-gray-400 font-mono ml-2">IBGE ${esc(m.codigo_ibge)}</span></span>
                    <button type="button" onclick="removerCarregamento(${i})" class="${CLASSE_REMOVER}">Remover</button>
                    <input type="hidden" name="carregamentos[${i}][codigo_ibge]" value="${esc(m.codigo_ibge)}">
                    <input type="hidden" name="carregamentos[${i}][nome]" value="${esc(m.nome)}">
                    <input type="hidden" name="carregamentos[${i}][uf]" value="${esc(m.uf)}">
                </div>`).join('')
            : '<p class="text-xs text-gray-400">Nenhum município de carregamento.</p>';
        atualizarContador('carregamentos', estado.carregamentos.length);
    }
    function adicionarCarregamento() {
        const ibge = document.getElementById('car-ibge').value.replace(/\D/g, '');
        const nome = document.getElementById('car-nome').value.trim();
        const uf = document.getElementById('car-uf').value;

        if (!/^\d{7}$/.test(ibge) || !nome || !uf) {
            alert('Informe o código IBGE (7 dígitos), o município e a UF.');
            return;
        }
        if (estado.carregamentos.some((m) => m.codigo_ibge === ibge)) return;

        estado.carregamentos.push({ codigo_ibge: ibge, nome, uf });
        ['car-ibge', 'car-nome', 'car-uf'].forEach((id) => (document.getElementById(id).value = ''));
        renderCarregamentos();
    }
    function removerCarregamento(i) { estado.carregamentos.splice(i, 1); renderCarregamentos(); }

    // ---------------- Documentos (NF-e) ----------------
    function renderDocumentos() {
        const box = document.getElementById('lista-documentos');
        atualizarContador('documentos', estado.documentos.length);

        if (!estado.documentos.length) {
            box.innerHTML = '<p class="text-xs text-gray-400">Nenhuma NF-e adicionada.</p>';
            return;
        }

        box.innerHTML = `
            <div class="rounded-xl ring-1 ring-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">NF-e</th>
                            <th class="px-3 py-2 text-left font-medium">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium">Descarga</th>
                            <th class="px-3 py-2 text-left font-medium">Valor</th>
                            <th class="px-3 py-2 text-left font-medium">Peso bruto (kg)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        ${estado.documentos.map((d, i) => `
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">${esc(d.numero)} / ${esc(d.serie)}
                                    <input type="hidden" name="documentos[${i}][nota_fiscal_id]" value="${d.nota_fiscal_id}"></td>
                                <td class="px-3 py-2 text-gray-700">${esc(d.cliente)}</td>
                                <td class="px-3 py-2 text-gray-600">${esc(d.municipio ?? '—')}</td>
                                <td class="px-3 py-2 text-gray-600">${brl(d.valor)}</td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" step="0.001" name="documentos[${i}][peso_bruto_kg]" value="${d.peso}"
                                           oninput="estado.documentos[${i}].peso = this.value; atualizarTotais()"
                                           class="w-32 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" onclick="removerDocumento(${i})" class="${CLASSE_REMOVER}">Remover</button>
                                </td>
                            </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <p id="totais-documentos" class="mt-3 text-sm text-gray-600"></p>
            <p id="aviso-uf" class="mt-2 text-sm"></p>`;

        atualizarTotais();
        atualizarAvisoUf();
    }
    function atualizarTotais() {
        const el = document.getElementById('totais-documentos');
        if (!el) return;
        const valor = estado.documentos.reduce((s, d) => s + Number(d.valor || 0), 0);
        const peso = estado.documentos.reduce((s, d) => s + Number(d.peso || 0), 0);
        el.innerHTML = `<span class="font-medium">${estado.documentos.length}</span> NF-e · valor da carga <span class="font-medium">${brl(valor)}</span> · peso <span class="font-medium">${kg(peso)} kg</span>`;
    }
    function removerDocumento(i) { estado.documentos.splice(i, 1); renderDocumentos(); }

    // ---------------- Veículos ----------------
    function renderTracao() {
        const t = estado.tracao;
        document.getElementById('area-tracao').innerHTML = t
            ? `<div class="${CLASSE_ITEM}">
                    <span><span class="font-mono font-medium">${esc(t.placa)}</span>
                        <span class="text-xs text-gray-400 ml-2">${esc(t.transportadora ?? 'Sem transportadora')}</span></span>
                    <button type="button" onclick="estado.tracao = null; renderTracao()" class="${CLASSE_REMOVER}">Remover</button>
                    <input type="hidden" name="veiculo_tracao_id" value="${t.id}">
               </div>`
            : '<p class="text-xs text-gray-400">Nenhum veículo de tração selecionado.</p>';
    }
    function renderReboques() {
        document.getElementById('lista-reboques').innerHTML = estado.reboques.length
            ? estado.reboques.map((r, i) => `
                <div class="${CLASSE_ITEM}">
                    <span><span class="font-mono font-medium">${esc(r.placa)}</span>
                        <span class="text-xs text-gray-400 ml-2">${esc(r.transportadora ?? 'Sem transportadora')}</span></span>
                    <button type="button" onclick="estado.reboques.splice(${i}, 1); renderReboques()" class="${CLASSE_REMOVER}">Remover</button>
                    <input type="hidden" name="reboques[]" value="${r.id}">
                </div>`).join('')
            : '';
    }

    // ---------------- Condutores ----------------
    function renderCondutores() {
        document.getElementById('lista-condutores').innerHTML = estado.condutores.length
            ? estado.condutores.map((c, i) => `
                <div class="${CLASSE_ITEM}">
                    <span><span class="font-medium">${esc(c.nome)}</span>
                        <span class="text-xs text-gray-400 font-mono ml-2">${esc(c.cpf)}</span></span>
                    <button type="button" onclick="estado.condutores.splice(${i}, 1); renderCondutores()" class="${CLASSE_REMOVER}">Remover</button>
                    <input type="hidden" name="condutores[]" value="${c.id}">
                </div>`).join('')
            : '<p class="text-xs text-gray-400">Nenhum condutor selecionado.</p>';
    }

    // ---------------- Busca genérica (campo + lista suspensa) ----------------
    function ligarBusca({ input, caixa, url, extra, filtrar, rotulo, escolher }) {
        const campo = document.getElementById(input);
        const lista = document.getElementById(caixa);
        let timeout;

        const buscar = async () => {
            const params = new URLSearchParams({ termo: campo.value, ...(extra ? extra() : {}) });
            const resp = await fetch(`${url}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const itens = (await resp.json()).filter(filtrar ?? (() => true));

            lista.innerHTML = itens.length
                ? itens.map((it, i) => `<button type="button" data-i="${i}" class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100 last:border-0">${rotulo(it)}</button>`).join('')
                : '<p class="px-3 py-2 text-sm text-gray-400">Nada encontrado.</p>';
            lista.classList.remove('hidden');

            lista.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => {
                escolher(itens[b.dataset.i]);
                campo.value = '';
                lista.classList.add('hidden');
            }));
        };

        campo.addEventListener('input', () => { clearTimeout(timeout); timeout = setTimeout(buscar, 300); });
        campo.addEventListener('focus', buscar);
        document.addEventListener('click', (e) => {
            if (!lista.contains(e.target) && e.target !== campo) lista.classList.add('hidden');
        });
    }

    const veiculoIncompleto = (v) => v.tara_kg == null || v.capacidade_kg == null || !v.tipo_carroceria;
    const avisoVeiculo = (v) => veiculoIncompleto(v) ? '<span class="text-amber-600 text-xs ml-2">cadastro incompleto</span>' : '';

    ligarBusca({
        input: 'nfe-busca', caixa: 'nfe-resultados', url: ROTAS.nfe,
        extra: () => (MDFE_ID ? { mdfe_id: MDFE_ID } : {}),
        filtrar: (n) => !estado.documentos.some((d) => d.nota_fiscal_id === n.nota_fiscal_id),
        rotulo: (n) => `<span class="font-medium">NF-e ${esc(n.numero)}/${esc(n.serie)}</span> · ${esc(n.cliente)}
            <span class="text-gray-400">· ${esc(n.municipio ?? 'sem município')} · ${brl(n.valor)}</span>
            ${/^\d{7}$/.test(n.cod_municipio ?? '') ? '' : '<span class="text-amber-600 text-xs ml-2">cliente sem código IBGE</span>'}`,
        escolher: (n) => { estado.documentos.push(n); renderDocumentos(); },
    });

    ligarBusca({
        input: 'trac-busca', caixa: 'trac-resultados', url: ROTAS.veiculos,
        extra: () => ({ tipo: 'tracao' }),
        rotulo: (v) => `<span class="font-mono font-medium">${esc(v.placa_formatada)}</span>
            <span class="text-gray-400">· ${esc(v.transportador?.nome ?? 'Sem transportadora')}</span>${avisoVeiculo(v)}`,
        escolher: (v) => { estado.tracao = { id: v.id, placa: v.placa_formatada, transportadora: v.transportador?.nome ?? null }; renderTracao(); },
    });

    ligarBusca({
        input: 'reb-busca', caixa: 'reb-resultados', url: ROTAS.veiculos,
        extra: () => ({ tipo: 'reboque' }),
        filtrar: (v) => !estado.reboques.some((r) => r.id === v.id),
        rotulo: (v) => `<span class="font-mono font-medium">${esc(v.placa_formatada)}</span>
            <span class="text-gray-400">· ${esc(v.transportador?.nome ?? 'Sem transportadora')}</span>${avisoVeiculo(v)}`,
        escolher: (v) => {
            if (estado.reboques.length >= MAX_REBOQUES) { alert('Máximo de 3 reboques.'); return; }
            estado.reboques.push({ id: v.id, placa: v.placa_formatada, transportadora: v.transportador?.nome ?? null });
            renderReboques();
        },
    });

    ligarBusca({
        input: 'cond-busca', caixa: 'cond-resultados', url: ROTAS.condutores,
        filtrar: (c) => !estado.condutores.some((x) => x.id === c.id),
        rotulo: (c) => `<span class="font-medium">${esc(c.nome)}</span> <span class="text-gray-400 font-mono">· ${esc(c.cpf_formatado)}</span>`,
        escolher: (c) => {
            if (estado.condutores.length >= MAX_CONDUTORES) { alert('Máximo de 10 condutores.'); return; }
            estado.condutores.push({ id: c.id, nome: c.nome, cpf: c.cpf_formatado });
            renderCondutores();
        },
    });

    // Enter nos campos de busca não envia o formulário
    document.querySelectorAll('[data-sem-enter]').forEach((el) =>
        el.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); }));

    renderPercurso();
    renderCarregamentos();
    renderDocumentos();
    renderTracao();
    renderReboques();
    renderCondutores();
    atualizarUfFim();
    abrirAba(ABA_INICIAL);
</script>

@include('partials.municipio-autocomplete', [
    'municipio' => '#car-nome',
    'codigo'    => '#car-ibge',
    'uf'        => '#car-uf',
    'exigir'    => false,
])