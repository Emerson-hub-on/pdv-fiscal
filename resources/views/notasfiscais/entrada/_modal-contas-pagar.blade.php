@php
    // duplicatas lidas do XML e gravadas no rascunho (null/vazio em entrada manual)
    $duplicatasXml = \App\Support\XmlDuplicatas::normalizar($entrada->duplicatas ?? []);
    $clsCp = 'border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-800 outline-none transition';
@endphp

{{-- Campos cp_parcelas[...] e lancar_contas_pagar são criados aqui pelo JS no momento da confirmação --}}
<div id="cp-campos" class="hidden"></div>

<div id="modal-contas-pagar" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[80]">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 bg-linear-to-r from-slate-800 via-slate-900 to-slate-900">
            <h2 class="text-lg font-bold text-white">Lançar no Contas a Pagar?</h2>
            <button type="button" onclick="fecharModalContasPagar()" class="text-slate-400 hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        <div class="p-6 flex flex-col gap-4">
            <p class="text-sm text-gray-600">
                Ao finalizar, o estoque será atualizado e a nota não poderá mais ser alterada.
                Confira as parcelas abaixo para lançar no financeiro.
            </p>

            <p id="cp-origem" class="text-sm rounded-lg px-3 py-2"></p>

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-28">
                    <label class="block text-xs text-gray-500 mb-1">Nº de parcelas</label>
                    <input type="number" id="cp-qtd" min="1" max="120" value="1" class="{{ $clsCp }} w-full">
                </div>
                <div class="w-48">
                    <label class="block text-xs text-gray-500 mb-1">Periodicidade</label>
                    <select id="cp-periodo" class="{{ $clsCp }} w-full">
                        <option value="mensal">Mensal (mesmo dia)</option>
                        <option value="30">A cada 30 dias</option>
                        <option value="15">A cada 15 dias</option>
                        <option value="7">Semanal (7 dias)</option>
                    </select>
                </div>
                <button type="button" onclick="cpGerar()"
                        class="bg-gray-700 hover:bg-gray-500 text-amber-50 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Gerar parcelas
                </button>
                <button type="button" onclick="cpAdicionar()"
                        class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
                    + Adicionar
                </button>
            </div>

            <div class="overflow-auto max-h-64 border border-gray-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase sticky top-0">
                        <tr>
                            <th class="text-left px-3 py-2 w-10">Nº</th>
                            <th class="text-left px-3 py-2">Vencimento</th>
                            <th class="text-right px-3 py-2">Valor (R$)</th>
                            <th class="px-3 py-2 w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="cp-corpo" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <p id="cp-resumo" class="text-sm"></p>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="button" onclick="cpConfirmar(true)"
                        class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-5 py-2.5 text-sm font-medium">
                    Lançar e finalizar
                </button>
                <button type="button" onclick="cpConfirmar(false)"
                        class="bg-gray-800 hover:bg-gray-700 text-white rounded-lg px-5 py-2.5 text-sm font-medium">
                    Finalizar sem lançar
                </button>
                <button type="button" onclick="fecharModalContasPagar()"
                        class="border border-gray-300 rounded-lg px-5 py-2.5 text-sm hover:bg-gray-50">
                    Voltar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let cpParcelas = [];
const cpDuplicatasXml = {{ Illuminate\Support\Js::from($duplicatasXml) }};
const cpClsInput = {{ Illuminate\Support\Js::from($clsCp) }};

const cpMoeda = v => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const cpCent  = v => Math.round((parseFloat(v) || 0) * 100);
const cpEsc   = s => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const cpDataBr = iso => iso ? iso.split('-').reverse().join('/') : '—';

function cpIso(dt) {
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

function cpSomarDias(iso, n) {
    const [a, m, d] = iso.split('-').map(Number);
    const dt = new Date(a, m - 1, d);
    dt.setDate(dt.getDate() + n);
    return cpIso(dt);
}

function cpSomarMeses(iso, n) {
    const [a, m, d] = iso.split('-').map(Number);
    const alvo = new Date(a, m - 1 + n, 1);
    const ultimo = new Date(alvo.getFullYear(), alvo.getMonth() + 1, 0).getDate();
    alvo.setDate(Math.min(d, ultimo));
    return cpIso(alvo);
}

// total da nota como está na tela agora (o valor gravado só existe depois de salvar)
function cpTotalNota() {
    const texto = document.getElementById('total-nota')?.textContent ?? '';
    return parseFloat(texto.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
}

function cpLerDuplicatas() {
    return cpDuplicatasXml;
}

/** Chamado pelo botão "Finalizar Entrada": abre o modal em vez de enviar direto. */
function abrirModalContasPagar(event) {
    event.preventDefault();

    const duplicatas = cpLerDuplicatas();
    const origem = document.getElementById('cp-origem');

    if (duplicatas.length > 0) {
        cpParcelas = duplicatas.map(d => ({ vencimento: d.vencimento, valor: d.valor }));
        document.getElementById('cp-qtd').value = duplicatas.length;
        origem.className = 'text-sm rounded-lg px-3 py-2 bg-blue-50 text-blue-800';
        origem.textContent = `${duplicatas.length} duplicata(s) encontrada(s) no XML da nota. Você pode ajustar antes de lançar.`;
    } else {
        const base = document.querySelector('[name="data_entrada"]')?.value || cpIso(new Date());
        cpParcelas = [{ vencimento: base, valor: cpTotalNota() }];
        document.getElementById('cp-qtd').value = 1;
        origem.className = 'text-sm rounded-lg px-3 py-2 bg-gray-50 text-gray-600';
        origem.textContent = 'Esta nota não traz duplicatas. Informe as parcelas (por padrão, uma parcela com o total da nota).';
    }

    cpRender();

    const modal = document.getElementById('modal-contas-pagar');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    return false;
}

function fecharModalContasPagar() {
    const modal = document.getElementById('modal-contas-pagar');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function cpRender() {
    const corpo = document.getElementById('cp-corpo');

    corpo.innerHTML = cpParcelas.map((p, i) => `<tr>
        <td class="px-3 py-2">${i + 1}</td>
        <td class="px-3 py-2">
            <input type="date" value="${cpEsc(p.vencimento)}" oninput="cpParcelas[${i}].vencimento = this.value"
                   class="${cpClsInput} w-44">
        </td>
        <td class="px-3 py-2 text-right">
            <input type="number" step="0.01" min="0.01" value="${cpEsc(p.valor)}"
                   oninput="cpParcelas[${i}].valor = this.value; cpResumo()" class="${cpClsInput} w-36 text-right">
        </td>
        <td class="px-3 py-2 text-right">
            <button type="button" onclick="cpRemover(${i})" class="text-xs text-red-600 hover:underline">Remover</button>
        </td>
    </tr>`).join('');

    cpResumo();
}

function cpResumo() {
    const el = document.getElementById('cp-resumo');
    const soma = cpParcelas.reduce((s, p) => s + cpCent(p.valor), 0);
    const totalNota = cpCent(cpTotalNota());
    const dif = totalNota - soma;

    if (dif === 0) {
        el.className = 'text-sm text-green-700';
        el.textContent = `Soma das parcelas: ${cpMoeda(soma / 100)} — igual ao total da nota.`;
    } else {
        el.className = 'text-sm text-amber-700';
        el.textContent = `Soma das parcelas: ${cpMoeda(soma / 100)} · Total da nota: ${cpMoeda(totalNota / 100)} · Diferença: ${cpMoeda(dif / 100)}. `
            + 'O Contas a Pagar usa a soma das parcelas.';
    }
}

function cpGerar() {
    const qtd = Math.max(1, Math.min(120, parseInt(document.getElementById('cp-qtd').value) || 1));
    const periodo = document.getElementById('cp-periodo').value;
    const total = cpCent(cpTotalNota());
    const primeiro = cpParcelas[0]?.vencimento || document.querySelector('[name="data_entrada"]')?.value || cpIso(new Date());

    if (total <= 0) return mostrarAviso('O total da nota está zerado. Adicione os itens antes de gerar as parcelas.');

    const base = Math.floor(total / qtd);
    const resto = total - base * qtd; // centavos que sobram vão para a última parcela

    cpParcelas = Array.from({ length: qtd }, (_, i) => ({
        vencimento: periodo === 'mensal' ? cpSomarMeses(primeiro, i) : cpSomarDias(primeiro, i * parseInt(periodo)),
        valor: (base + (i === qtd - 1 ? resto : 0)) / 100,
    }));

    cpRender();
}

function cpAdicionar() {
    const soma = cpParcelas.reduce((s, p) => s + cpCent(p.valor), 0);
    const falta = Math.max(0, cpCent(cpTotalNota()) - soma);
    const ultima = cpParcelas[cpParcelas.length - 1]?.vencimento;

    cpParcelas.push({
        vencimento: ultima ? cpSomarMeses(ultima, 1) : (document.querySelector('[name="data_entrada"]')?.value || ''),
        valor: falta > 0 ? falta / 100 : '',
    });
    cpRender();
}

function cpRemover(i) {
    cpParcelas.splice(i, 1);
    cpRender();
}

function cpCampo(nome, valor) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = nome;
    input.value = valor;
    input.setAttribute('form', 'form-entrada'); // o container está fora do <form>
    document.getElementById('cp-campos').appendChild(input);
}

/** lancar = true: envia as parcelas junto; false: finaliza só a nota. */
function cpConfirmar(lancar) {
    document.getElementById('cp-campos').innerHTML = '';

    if (lancar) {
        if (cpParcelas.length === 0) return mostrarAviso('Informe ao menos uma parcela ou escolha "Finalizar sem lançar".');

        if (cpParcelas.some(p => !p.vencimento || cpCent(p.valor) <= 0)) {
            return mostrarAviso('Preencha o vencimento e um valor maior que zero em todas as parcelas.');
        }

        cpCampo('lancar_contas_pagar', '1');
        cpParcelas.forEach((p, i) => {
            cpCampo(`cp_parcelas[${i}][vencimento]`, p.vencimento);
            cpCampo(`cp_parcelas[${i}][valor]`, (cpCent(p.valor) / 100).toFixed(2));
        });
    }

    fecharModalContasPagar();

    // requestSubmit com o botão como "submitter" envia acao=finalizar e dispara o submit normal do form
    document.getElementById('form-entrada').requestSubmit(document.getElementById('btn-finalizar'));
}
</script>