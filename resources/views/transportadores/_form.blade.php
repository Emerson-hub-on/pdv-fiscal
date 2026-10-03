@php
    $transportador = $transportador ?? null;
    $tipoInicial = old('tipo_pessoa_ui', $transportador && strlen($transportador->documento) === 11 ? 'fisica' : 'juridica');
@endphp

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200">

    <div class="border-b border-gray-200 px-6 pt-4">
        <nav class="flex gap-6">
            <button type="button" data-tab="geral" onclick="mudarAba('geral')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-blue-600 text-blue-600 transition">
                Dados Gerais
            </button>
            <button type="button" data-tab="endereco" onclick="mudarAba('endereco')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                Endereço
            </button>
        </nav>
    </div>

    <div class="p-6">

        {{-- ===================== ABA: DADOS GERAIS ===================== --}}
        <div id="aba-geral" class="tab-conteudo grid grid-cols-2 gap-5">

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de pessoa <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_pessoa_ui" value="juridica" onchange="alternarTipoPessoa()"
                               {{ $tipoInicial === 'juridica' ? 'checked' : '' }}>
                        Pessoa Jurídica (empresa de transporte)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_pessoa_ui" value="fisica" onchange="alternarTipoPessoa()"
                               {{ $tipoInicial === 'fisica' ? 'checked' : '' }}>
                        Pessoa Física (motorista autônomo)
                    </label>
                </div>
            </div>

            <div class="col-span-2">
                <label id="label-nome" class="block text-sm font-medium text-gray-700 mb-1">Razão social <span class="text-red-500">*</span></label>
                <input type="text" name="nome" maxlength="60" value="{{ old('nome', $transportador->nome ?? '') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label id="label-documento" class="block text-sm font-medium text-gray-700 mb-1">CNPJ <span class="text-red-500">*</span></label>
                <input type="text" name="documento" id="campo-documento" maxlength="18" required
                       oninput="this.value = this.value.toUpperCase()"
                       value="{{ old('documento', $transportador->documento_formatado ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Inscrição Estadual <span class="text-xs text-gray-400 font-normal">(números ou ISENTO)</span>
                </label>
                <input type="text" name="ie" maxlength="14" value="{{ old('ie', $transportador->ie ?? '') }}"
                       oninput="this.value = this.value.toUpperCase()"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

        </div>

        {{-- ===================== ABA: ENDEREÇO ===================== --}}
        <div id="aba-endereco" class="tab-conteudo grid grid-cols-2 gap-5 hidden">

            <div class="col-span-2">
                <p class="text-xs text-gray-400 -mt-1 mb-1">
                    No XML da NF-e, logradouro, número e bairro viram um único campo de até 60 caracteres.
                </p>
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro <span class="text-red-500">*</span></label>
                <input type="text" name="logradouro" maxlength="60" required value="{{ old('logradouro', $transportador->logradouro ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                <input type="text" name="numero" maxlength="10" placeholder="S/N" value="{{ old('numero', $transportador->numero ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bairro <span class="text-red-500">*</span></label>
                <input type="text" name="bairro" maxlength="40" required value="{{ old('bairro', $transportador->bairro ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Município <span class="text-red-500">*</span></label>
                <input type="text" name="municipio" maxlength="60" required value="{{ old('municipio', $transportador->municipio ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">UF <span class="text-red-500">*</span></label>
                <select name="uf" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                    <option value="">Selecione...</option>
                    @foreach (\App\Models\Transportador::UFS as $uf)
                        <option value="{{ $uf }}" {{ old('uf', $transportador->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                    @endforeach
                </select>
            </div>

        </div>

    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar transportadora
    </button>
    <a href="{{ route('transportadores.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>

{{-- Modal de consulta de CNPJ --}}
<div id="modal-consulta-cnpj" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-lg px-8 py-6 flex flex-col items-center gap-3">
        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <p class="text-sm font-medium text-gray-700">Buscando informações do CNPJ...</p>
    </div>
</div>

<script>
function mudarAba(aba) {
    document.querySelectorAll('.tab-conteudo').forEach(el => el.classList.add('hidden'));
    document.getElementById('aba-' + aba).classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        const ativa = btn.dataset.tab === aba;
        btn.classList.toggle('border-blue-600', ativa);
        btn.classList.toggle('text-blue-600', ativa);
        btn.classList.toggle('border-transparent', !ativa);
        btn.classList.toggle('text-gray-500', !ativa);
    });
}

function alternarTipoPessoa() {
    const tipo = document.querySelector('input[name="tipo_pessoa_ui"]:checked').value;
    const campoDoc = document.getElementById('campo-documento');

    if (tipo === 'juridica') {
        document.getElementById('label-nome').innerHTML = 'Razão social <span class="text-red-500">*</span>';
        document.getElementById('label-documento').innerHTML = 'CNPJ <span class="text-red-500">*</span>';
        campoDoc.placeholder = '00.000.000/0000-00';
    } else {
        document.getElementById('label-nome').innerHTML = 'Nome completo do motorista <span class="text-red-500">*</span>';
        document.getElementById('label-documento').innerHTML = 'CPF <span class="text-red-500">*</span>';
        campoDoc.placeholder = '000.000.000-00';
    }
}

document.addEventListener('DOMContentLoaded', alternarTipoPessoa);

function preencherSeVazio(name, valor) {
    const campo = document.querySelector(`[name="${name}"]`);
    if (campo && !campo.value && valor) {
        campo.value = valor;
    }
}

function abrirModalConsultaCnpj() {
    document.getElementById('modal-consulta-cnpj').classList.remove('hidden');
}

function fecharModalConsultaCnpj() {
    document.getElementById('modal-consulta-cnpj').classList.add('hidden');
}

// Só consulta CNPJ numérico; CNPJ com letras o operador preenche à mão
async function consultarCnpjAutomatico() {
    const tipo = document.querySelector('input[name="tipo_pessoa_ui"]:checked').value;
    if (tipo !== 'juridica') return;

    const cnpjLimpo = document.getElementById('campo-documento').value.replace(/[^0-9A-Za-z]/g, '');
    if (!/^\d{14}$/.test(cnpjLimpo)) return;

    abrirModalConsultaCnpj();

    try {
        const resp = await fetch(`/api/consulta-cnpj/${cnpjLimpo}`);
        const dados = await resp.json();

        if (!resp.ok) {
            alert(dados.erro || 'Não foi possível consultar o CNPJ.');
            return;
        }

        preencherSeVazio('nome', dados.nome);
        preencherSeVazio('logradouro', dados.logradouro);
        preencherSeVazio('numero', dados.numero);
        preencherSeVazio('bairro', dados.bairro);
        preencherSeVazio('municipio', dados.municipio);

        const campoUf = document.querySelector('select[name="uf"]');
        if (campoUf && !campoUf.value && dados.uf) {
            campoUf.value = dados.uf;
        }
    } catch (e) {
        alert('Erro ao consultar o CNPJ. Verifique sua conexão.');
    } finally {
        fecharModalConsultaCnpj();
    }
}

const campoDocumento = document.getElementById('campo-documento');

campoDocumento.addEventListener('blur', consultarCnpjAutomatico);

campoDocumento.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        consultarCnpjAutomatico();
    }
});
</script>