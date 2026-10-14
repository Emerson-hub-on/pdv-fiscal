@php
    $ehEdicao = $conta->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition disabled:bg-gray-100 disabled:cursor-not-allowed';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';

    // Parcelas já recebidas (travadas) vêm sempre do banco
    $recebidas = $conta->parcelas->filter(fn ($p) => $p->isRecebida())->sortBy('numero')->map(fn ($p) => [
        'id'             => $p->id,
        'vencimento'     => $p->vencimento->format('Y-m-d'),
        'valor'          => (float) $p->valor,
        'recebida'           => true,
        'data_recebimento' => $p->data_recebimento->format('Y-m-d'),
        'valor_recebido'     => (float) $p->valor_recebido,
    ])->values();

    // Pendentes: o que o operador digitou (se a validação falhou) ou o que está no banco
    if (is_array(old('parcelas'))) {
        $pendentes = collect(old('parcelas'))->map(fn ($p) => [
            'id'         => null,
            'vencimento' => $p['vencimento'] ?? '',
            'valor'      => $p['valor'] ?? '',
            'recebida'       => false,
        ])->values();
    } else {
        $pendentes = $conta->parcelas->reject(fn ($p) => $p->isRecebida())->sortBy('numero')->map(fn ($p) => [
            'id'         => $p->id,
            'vencimento' => $p->vencimento->format('Y-m-d'),
            'valor'      => (float) $p->valor,
            'recebida'       => false,
        ])->values();
    }

    $parcelasIniciais = $recebidas->concat($pendentes)->values();

    $vencimentoCab = old('vencimento', $conta->parcelas->reject(fn ($p) => $p->isRecebida())
        ->sortBy(fn ($p) => $p->vencimento->timestamp)->first()?->vencimento?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            mostrarAviso({{ Illuminate\Support\Js::from($errors->all()) }}.join('\n'), 'erro');
        });
    </script>
@endif

<form id="form-conta" method="POST"
      action="{{ $ehEdicao ? route('contas-receber.update', $conta) : route('contas-receber.store') }}"
      class="flex flex-col gap-6 w-full max-w-5xl mt-2 mb-6">
    @csrf
    @if ($ehEdicao) @method('PUT') @endif

    {{-- ===================== Dados da conta ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('contas-receber.index') }}" title="Voltar para Contas a Receber"
               class="text-gray-500 hover:text-gray-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-lg font-semibold">{{ $ehEdicao ? 'Editar Conta a Receber' : 'Nova Conta a Receber' }}</h1>
        </div>

        <div class="flex flex-col gap-4">
            {{-- Linha 1: tipo e cliente --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Tipo de conta <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select id="tipo_conta_receber_id" name="tipo_conta_receber_id" class="{{ $inputCls }}">
                            <option value="">Selecionar tipo...</option>
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo->id }}" @selected(old('tipo_conta_receber_id', $conta->tipo_conta_receber_id) == $tipo->id)>
                                    {{ $tipo->descricao }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" onclick="abrirModalTipo()"
                                class="shrink-0 border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                            + Novo
                        </button>
                    </div>
                    @error('tipo_conta_receber_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                @php
                    $clienteAtual = old('cliente_id')
                        ? \App\Models\Cliente::find(old('cliente_id'))
                        : $conta->cliente;
                @endphp

                <div>
                    <label class="{{ $labelCls }}">Cliente <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="hidden" name="cliente_id" id="campo-cliente"
                           value="{{ old('cliente_id', $conta->cliente_id) }}">
                    <div class="flex gap-2">
                        <button type="button" onclick="abrirModalCliente()"
                                class="w-full min-w-0 text-left border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50">
                            <span id="texto-cliente-selecionado" class="block truncate">
                                @if ($clienteAtual)
                                    {{ \App\Models\ContaReceber::rotuloCliente($clienteAtual) }} — {{ $clienteAtual->cpf_cnpj_formatado ?? $clienteAtual->cpf_cnpj }}
                                @else
                                    Selecionar cliente...
                                @endif
                            </span>
                        </button>
                        <button type="button" id="limpar-cliente" onclick="limparCliente()" title="Remover cliente"
                                class="shrink-0 border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ $clienteAtual ? '' : 'hidden' }}">
                            &times;
                        </button>
                    </div>
                    @error('cliente_id') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Linha 2: descrição e documento --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="{{ $labelCls }}">Descrição <span class="text-red-500">*</span></label>
                    <input type="text" name="descricao" maxlength="150" value="{{ old('descricao', $conta->descricao) }}"
                           placeholder="Ex.: Venda a prazo - Maria" class="{{ $inputCls }}">
                    @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Nº do documento <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="numero_documento" maxlength="30"
                           value="{{ old('numero_documento', $conta->numero_documento) }}" class="{{ $inputCls }}">
                    @error('numero_documento') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Linha 3: datas e valor --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Data da conta <span class="text-red-500">*</span></label>
                    <input type="date" name="data_conta"
                           value="{{ old('data_conta', $conta->data_conta?->format('Y-m-d')) }}" class="{{ $inputCls }}">
                    @error('data_conta') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Valor total (R$) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="valor_total" name="valor_total"
                           value="{{ old('valor_total', $ehEdicao ? (float) $conta->valor_total : '') }}"
                           oninput="atualizarSoma()" class="{{ $inputCls }}">
                    @error('valor_total') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Vencimento <span class="text-red-500">*</span></label>
                    <input type="date" id="vencimento" name="vencimento" value="{{ $vencimentoCab }}" class="{{ $inputCls }}">
                    <p class="text-xs text-gray-400 mt-1">Com mais de uma parcela, é o vencimento da 1ª.</p>
                    @error('vencimento') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $labelCls }}">Observação</label>
                <textarea name="observacao" rows="2" maxlength="1000" class="{{ $inputCls }}">{{ old('observacao', $conta->observacao) }}</textarea>
            </div>
        </div>
    </div>

    {{-- ===================== Parcelas ===================== --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-semibold mb-1">Parcelas</h2>
        <p class="text-xs text-gray-500 mb-4">
            Conta à vista ou em parcela única? Não precisa preencher nada aqui: será criada uma parcela com o valor total e o vencimento acima.
            Se for parcelada, informe a quantidade e clique em <strong>Gerar parcelas</strong>; depois você pode ajustar cada data e valor.
        </p>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="w-32">
                <label class="{{ $labelCls }}">Nº de parcelas</label>
                <input type="number" id="qtd-parcelas" min="1" max="120" value="1" class="{{ $inputCls }}">
            </div>
            <div class="w-56">
                <label class="{{ $labelCls }}">Periodicidade</label>
                <select id="periodicidade" class="{{ $inputCls }}">
                    <option value="mensal">Mensal (mesmo dia)</option>
                    <option value="30">A cada 30 dias</option>
                    <option value="15">A cada 15 dias</option>
                    <option value="7">Semanal (7 dias)</option>
                </select>
            </div>
            <button type="button" onclick="gerarParcelas()"
                    class="bg-gray-700 hover:bg-gray-500 text-amber-50 px-4 py-2 rounded-lg text-sm font-medium transition">
                Gerar parcelas
            </button>
            <button type="button" onclick="adicionarParcela()"
                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
                + Adicionar parcela
            </button>
        </div>

        @error('parcelas') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror

        <div class="overflow-auto max-h-[26rem]">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0 z-10">
                    <tr>
                        <th class="text-left px-3 py-2 w-12">Nº</th>
                        <th class="text-left px-3 py-2">Vencimento</th>
                        <th class="text-right px-3 py-2">Valor (R$)</th>
                        <th class="text-left px-3 py-2">Situação</th>
                        <th class="text-right px-3 py-2">Ações</th>
                    </tr>
                </thead>
                <tbody id="corpo-parcelas" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>

        <p id="resumo-parcelas" class="text-sm mt-3"></p>

        @if ($recebidas->isNotEmpty())
            <p class="text-xs text-gray-500 mt-2">
                Parcelas recebidas ficam travadas. Para alterá-las, estorne o recebimento. Alterações não salvas na tela
                são perdidas ao baixar ou estornar uma parcela — salve antes.
            </p>
        @endif

        <div class="flex flex-wrap gap-3 mt-6">
            <button type="submit"
                    class="bg-green-700 text-white rounded-lg px-6 py-2.5 text-sm font-medium hover:bg-green-800">
                {{ $ehEdicao ? 'Salvar Alterações' : 'Salvar Conta' }}
            </button>
            <a href="{{ route('contas-receber.index') }}"
               class="border border-gray-300 rounded-lg px-6 py-2.5 text-sm hover:bg-gray-50">Cancelar</a>
        </div>
    </div>
</form>

{{-- Formulários auxiliares (fora do form principal: HTML não permite form dentro de form) --}}
<form id="form-estorno" method="POST" class="hidden">@csrf</form>

<!-- Modal: baixa de parcela -->
<div id="modal-baixa" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <form id="form-baixa" method="POST" class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-y-auto">
        @csrf
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Baixar parcela</h2>
            <button type="button" onclick="fecharModalBaixa()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-3">
            <div>
                <label class="{{ $labelCls }}">Data do recebimento</label>
                <input type="date" id="baixa-data" name="data_recebimento" required class="{{ $inputCls }}">
            </div>
            <div>
                <label class="{{ $labelCls }}">Valor recebido (R$)</label>
                <input type="number" step="0.01" min="0.01" id="baixa-valor" name="valor_recebido" required class="{{ $inputCls }}">
                <p class="text-xs text-gray-400 mt-1">Pode ser diferente do valor da parcela (juros, multa ou desconto).</p>
            </div>
            <div>
                <label class="{{ $labelCls }}">Forma de pagamento</label>
                <select name="forma_pagamento_id" class="{{ $inputCls }}">
                    <option value="">Não informada</option>
                    @foreach ($formasPagamento as $forma)
                        <option value="{{ $forma->id }}">{{ $forma->ind_pag ? 'A prazo' : 'À vista' }} — {{ $forma->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="w-full bg-green-700 hover:bg-green-800 text-white py-2.5 rounded-lg text-sm font-medium">
                Confirmar baixa
            </button>
        </div>
    </form>
</div>

<!-- Modal de busca de cliente -->
<div id="modal-busca-cliente" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Selecionar Cliente</h2>
            <button type="button" onclick="fecharModalCliente()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6">
            <input type="text" id="busca-cliente-modal" placeholder="Buscar por nome ou CPF/CNPJ..."
                   autocomplete="off"
                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm mb-4 focus:ring-2 focus:ring-slate-800 outline-none transition">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b">
                        <th class="py-2">Nome</th>
                        <th class="py-2">CPF/CNPJ</th>
                        <th class="py-2">UF</th>
                        <th class="py-2">Telefone</th>
                    </tr>
                </thead>
                <tbody id="linhas-busca-cliente"></tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">Use ↑ ↓ para navegar e Enter para selecionar.</p>
        </div>
    </div>
</div>

<!-- Modal: cadastro rápido de tipo de conta -->
<div id="modal-tipo" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Novo tipo de conta</h2>
            <button type="button" onclick="fecharModalTipo()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-3">
            <div>
                <label class="{{ $labelCls }}">Descrição</label>
                <input type="text" id="tipo-descricao" maxlength="60" class="{{ $inputCls }}"
                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); salvarTipo(); }">
            </div>
            <p id="tipo-erro" class="text-red-600 text-sm hidden"></p>
            <button type="button" onclick="salvarTipo()"
                    class="w-full bg-green-700 hover:bg-green-800 text-white py-2.5 rounded-lg text-sm font-medium">
                Cadastrar tipo
            </button>
        </div>
    </div>
</div>

<div id="area-avisos" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]"></div>

<script>
const parcelasIniciais = {{ Illuminate\Support\Js::from($parcelasIniciais) }};
const urlTipoRapido    = {{ Illuminate\Support\Js::from(route('tipos-conta-receber.rapido')) }};
const urlBaixaModelo   = {{ Illuminate\Support\Js::from(route('contas-receber.parcelas.baixar', ['parcela' => '__ID__'])) }};
const urlEstornoModelo = {{ Illuminate\Support\Js::from(route('contas-receber.parcelas.estornar', ['parcela' => '__ID__'])) }};
const csrfToken        = {{ Illuminate\Support\Js::from(csrf_token()) }};
const clsInputParcela  = 'border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition';

const moeda     = v => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const centavos  = v => Math.round((parseFloat(v) || 0) * 100);
const dataBr    = iso => iso ? iso.split('-').reverse().join('/') : '—';
const esc       = s => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

let parcelas = parcelasIniciais.map(p => ({ ...p }));

// ---------------------------------------------------------------
// Datas (sempre em 'YYYY-MM-DD', sem fuso)
// ---------------------------------------------------------------
function dataParaIso(dt) {
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

function somarDias(iso, n) {
    const [a, m, d] = iso.split('-').map(Number);
    const dt = new Date(a, m - 1, d);
    dt.setDate(dt.getDate() + n);
    return dataParaIso(dt);
}

// soma meses mantendo o dia (31/01 + 1 mês = 28/02 ou 29/02)
function somarMeses(iso, n) {
    const [a, m, d] = iso.split('-').map(Number);
    const alvo = new Date(a, m - 1 + n, 1);
    const ultimoDia = new Date(alvo.getFullYear(), alvo.getMonth() + 1, 0).getDate();
    alvo.setDate(Math.min(d, ultimoDia));
    return dataParaIso(alvo);
}

// ---------------------------------------------------------------
// Avisos
// ---------------------------------------------------------------
function mostrarAviso(mensagem, tipo = 'erro') {
    const cores = {
        erro:    'bg-red-50 border-red-300 text-red-800',
        sucesso: 'bg-green-50 border-green-300 text-green-800',
        info:    'bg-blue-50 border-blue-300 text-blue-800',
    };

    const aviso = document.createElement('div');
    aviso.className = `border rounded-lg shadow-lg px-4 py-3 text-sm flex gap-3 items-start ${cores[tipo] ?? cores.info}`;
    aviso.setAttribute('role', 'alert');

    const texto = document.createElement('div');
    texto.className = 'flex-1 whitespace-pre-line';
    texto.textContent = mensagem;

    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'text-lg leading-none opacity-60 hover:opacity-100';
    fechar.innerHTML = '&times;';
    fechar.onclick = () => aviso.remove();

    aviso.append(texto, fechar);
    document.getElementById('area-avisos').appendChild(aviso);

    setTimeout(() => aviso.remove(), tipo === 'erro' ? 10000 : 4000);
}

// ---------------------------------------------------------------
// Tabela de parcelas
// ---------------------------------------------------------------
function renderizar() {
    const corpo = document.getElementById('corpo-parcelas');

    if (parcelas.length === 0) {
        corpo.innerHTML = `<tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">
            Nenhuma parcela informada — será criada uma parcela única com o valor total e o vencimento acima.
        </td></tr>`;
        atualizarSoma();
        return;
    }

    let n = 0; // índice só das pendentes, que são as enviadas no formulário

    corpo.innerHTML = parcelas.map((p, i) => {
        if (p.recebida) {
            return `<tr class="bg-green-50/60">
                <td class="px-3 py-2">${i + 1}</td>
                <td class="px-3 py-2">${dataBr(p.vencimento)}</td>
                <td class="px-3 py-2 text-right">${moeda(p.valor)}</td>
                <td class="px-3 py-2 text-green-700">Recebida em ${dataBr(p.data_recebimento)} — ${moeda(p.valor_recebido)}</td>
                <td class="px-3 py-2 text-right">
                    <button type="button" onclick="estornarParcela(${p.id})" class="text-xs text-red-600 hover:underline">Estornar</button>
                </td>
            </tr>`;
        }

        const idx = n++;
        const baixar = p.id
            ? `<button type="button" onclick="abrirModalBaixa(${p.id}, ${Number(p.valor) || 0})" class="text-xs text-green-700 hover:underline mr-3">Baixar</button>`
            : '';

        return `<tr>
            <td class="px-3 py-2">${i + 1}</td>
            <td class="px-3 py-2">
                <input type="date" required name="parcelas[${idx}][vencimento]" value="${esc(p.vencimento)}"
                       oninput="parcelas[${i}].vencimento = this.value" class="${clsInputParcela} w-44">
            </td>
            <td class="px-3 py-2 text-right">
                <input type="number" step="0.01" min="0.01" required name="parcelas[${idx}][valor]" value="${esc(p.valor)}"
                       oninput="parcelas[${i}].valor = this.value; atualizarSoma()" class="${clsInputParcela} w-40 text-right">
            </td>
            <td class="px-3 py-2 text-gray-500">Pendente</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
                ${baixar}
                <button type="button" onclick="removerParcela(${i})" class="text-xs text-red-600 hover:underline">Remover</button>
            </td>
        </tr>`;
    }).join('');

    atualizarSoma();
}

function atualizarSoma() {
    const el = document.getElementById('resumo-parcelas');

    if (parcelas.length === 0) {
        el.textContent = '';
        return;
    }

    const total = centavos(document.getElementById('valor_total').value);
    const soma  = parcelas.reduce((s, p) => s + centavos(p.valor), 0);
    const dif   = total - soma;

    el.className = 'text-sm mt-3 ' + (dif === 0 ? 'text-green-700' : 'text-red-600');
    el.textContent = dif === 0
        ? `Soma das parcelas: ${moeda(soma / 100)} — confere com o valor total.`
        : `Soma das parcelas: ${moeda(soma / 100)} · Valor total: ${moeda(total / 100)} · Diferença: ${moeda(dif / 100)}`;
}

function gerarParcelas() {
    const qtd      = Math.max(1, Math.min(120, parseInt(document.getElementById('qtd-parcelas').value) || 1));
    const primeiro = document.getElementById('vencimento').value;
    const periodo  = document.getElementById('periodicidade').value;
    const recebidas    = parcelas.filter(p => p.recebida);
    const restante = centavos(document.getElementById('valor_total').value)
                   - recebidas.reduce((s, p) => s + centavos(p.valor), 0);

    if (!primeiro) return mostrarAviso('Informe o vencimento (da 1ª parcela) antes de gerar as parcelas.');
    if (restante <= 0) return mostrarAviso('Informe um valor total maior que o já recebido antes de gerar as parcelas.');

    const base  = Math.floor(restante / qtd);
    const resto = restante - base * qtd; // centavos que sobram vão para a última parcela

    const novas = [];
    for (let i = 0; i < qtd; i++) {
        novas.push({
            id: null,
            vencimento: periodo === 'mensal' ? somarMeses(primeiro, i) : somarDias(primeiro, i * parseInt(periodo)),
            valor: (base + (i === qtd - 1 ? resto : 0)) / 100,
            recebida: false,
        });
    }

    parcelas = [...recebidas, ...novas];
    renderizar();
}

function adicionarParcela() {
    const total = centavos(document.getElementById('valor_total').value);
    const soma  = parcelas.reduce((s, p) => s + centavos(p.valor), 0);
    const falta = Math.max(0, total - soma);
    const ultima = parcelas.length ? parcelas[parcelas.length - 1].vencimento : document.getElementById('vencimento').value;

    parcelas.push({
        id: null,
        vencimento: ultima ? (parcelas.length ? somarMeses(ultima, 1) : ultima) : '',
        valor: falta > 0 ? falta / 100 : '',
        recebida: false,
    });
    renderizar();
}

function removerParcela(i) {
    parcelas.splice(i, 1);
    renderizar();
}

// confere a soma antes de enviar (o servidor valida de novo)
document.getElementById('form-conta').addEventListener('submit', (e) => {
    if (parcelas.length === 0) return;

    const total = centavos(document.getElementById('valor_total').value);
    const soma  = parcelas.reduce((s, p) => s + centavos(p.valor), 0);

    if (soma !== total) {
        e.preventDefault();
        mostrarAviso(`A soma das parcelas (${moeda(soma / 100)}) precisa ser igual ao valor total (${moeda(total / 100)}).`);
    }
});

// ---------------------------------------------------------------
// Baixa / estorno
// ---------------------------------------------------------------
function abrirModalBaixa(id, valor) {
    document.getElementById('form-baixa').action = urlBaixaModelo.replace('__ID__', id);
    document.getElementById('baixa-valor').value = Number(valor).toFixed(2);
    document.getElementById('baixa-data').value = dataParaIso(new Date());

    const modal = document.getElementById('modal-baixa');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModalBaixa() {
    const modal = document.getElementById('modal-baixa');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function estornarParcela(id) {
    if (!confirm('Estornar o recebimento desta parcela? Ela volta para pendente.')) return;

    const form = document.getElementById('form-estorno');
    form.action = urlEstornoModelo.replace('__ID__', id);
    form.submit();
}

// ---------------------------------------------------------------
// Cadastro rápido de tipo de conta
// ---------------------------------------------------------------
function abrirModalTipo() {
    const modal = document.getElementById('modal-tipo');
    document.getElementById('tipo-descricao').value = '';
    document.getElementById('tipo-erro').classList.add('hidden');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('tipo-descricao').focus();
}

function fecharModalTipo() {
    const modal = document.getElementById('modal-tipo');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function salvarTipo() {
    const erro = document.getElementById('tipo-erro');
    erro.classList.add('hidden');

    try {
        const resp = await fetch(urlTipoRapido, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ descricao: document.getElementById('tipo-descricao').value }),
        });
        const json = await resp.json();

        if (!resp.ok) {
            throw new Error(json.errors?.descricao?.[0] ?? json.message ?? 'Não foi possível cadastrar o tipo.');
        }

        document.getElementById('tipo_conta_receber_id').add(new Option(json.descricao, json.id, true, true));
        fecharModalTipo();
    } catch (e) {
        erro.textContent = e.message;
        erro.classList.remove('hidden');
    }
}

// ---------------------------------------------------------------
// Busca de cliente (modal)
// ---------------------------------------------------------------
const urlBuscaCliente    = {{ Illuminate\Support\Js::from(route('contas-receber.buscar-cliente')) }};
const inputBuscaCliente  = document.getElementById('busca-cliente-modal');
const linhasBuscaCliente = document.getElementById('linhas-busca-cliente');
let clientesAtuais = [];
let indiceCliente = -1;
let timeoutBuscaCliente;
let contadorBuscaCliente = 0; // descarta respostas antigas se o operador digitar rápido

function abrirModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    inputBuscaCliente.value = '';
    inputBuscaCliente.focus();
    buscarClientes(''); // sem termo = 20 primeiros em ordem alfabética
}

function fecharModalCliente() {
    const modal = document.getElementById('modal-busca-cliente');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    clientesAtuais = [];
    indiceCliente = -1;
}

async function buscarClientes(termo) {
    const minhaBusca = ++contadorBuscaCliente;
    let dados;

    try {
        const resp = await fetch(`${urlBuscaCliente}?termo=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) throw new Error(`Erro ${resp.status} ao consultar o servidor.`);
        dados = await resp.json();
    } catch (e) {
        if (minhaBusca === contadorBuscaCliente) { // erro de busca antiga não interessa
            mostrarAviso('Falha ao buscar clientes:\n' + e.message, 'erro');
            clientesAtuais = [];
            indiceCliente = -1;
            renderizarClientes();
        }
        return;
    }

    if (minhaBusca !== contadorBuscaCliente) return; // chegou uma busca mais nova, ignora esta

    clientesAtuais = dados;
    indiceCliente = dados.length > 0 ? 0 : -1;
    renderizarClientes();
}

function renderizarClientes() {
    if (clientesAtuais.length === 0) {
        linhasBuscaCliente.innerHTML = '<tr><td colspan="4" class="p-3 text-sm text-gray-400 text-center">Nenhum cliente encontrado.</td></tr>';
        return;
    }

    linhasBuscaCliente.innerHTML = clientesAtuais.map((f, index) => {
        const destacado = index === indiceCliente;
        return `
            <tr class="cursor-pointer border-b border-gray-100 transition ${destacado ? 'bg-slate-800 text-white' : 'hover:bg-gray-50'}"
                onclick="selecionarCliente(${index})">
                <td class="py-3 font-medium">${esc(f.nome)}</td>
                <td class="py-3 font-mono text-sm ${destacado ? 'text-slate-300' : 'text-gray-500'}">${esc(f.cpf_cnpj)}</td>
                <td class="py-3">${esc(f.uf)}</td>
                <td class="py-3">${esc(f.telefone)}</td>
            </tr>
        `;
    }).join('');

    // mantém a linha destacada visível ao navegar com as setas
    linhasBuscaCliente.children[indiceCliente]?.scrollIntoView({ block: 'nearest' });
}

function selecionarCliente(index) {
    const f = clientesAtuais[index];
    if (!f) return;

    document.getElementById('campo-cliente').value = f.id;
    document.getElementById('texto-cliente-selecionado').textContent = `${f.nome} — ${f.cpf_cnpj}`;
    document.getElementById('limpar-cliente').classList.remove('hidden');
    fecharModalCliente();
}

function limparCliente() {
    document.getElementById('campo-cliente').value = '';
    document.getElementById('texto-cliente-selecionado').textContent = 'Selecionar cliente...';
    document.getElementById('limpar-cliente').classList.add('hidden');
}

inputBuscaCliente?.addEventListener('input', () => {
    clearTimeout(timeoutBuscaCliente);
    timeoutBuscaCliente = setTimeout(() => buscarClientes(inputBuscaCliente.value.trim()), 300);
});

inputBuscaCliente?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { fecharModalCliente(); return; }
    if (clientesAtuais.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        indiceCliente = (indiceCliente + 1) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        indiceCliente = (indiceCliente - 1 + clientesAtuais.length) % clientesAtuais.length;
        renderizarClientes();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceCliente >= 0) selecionarCliente(indiceCliente);
    }
});

renderizar();
</script>