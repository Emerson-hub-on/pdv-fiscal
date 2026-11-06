@php extract(config('estilo.formulario')); @endphp
@php $cliente = $cliente ?? null; @endphp

<div class="{{ $cartaoCls }}">

    {{-- Navegação das abas --}}
    <div class="border-b border-slate-200 px-6 pt-4">
        <nav class="flex gap-6">
            <button type="button" data-tab="geral" onclick="mudarAba('geral')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-[#10284A] text-[#10284A] transition">
                Dados Gerais
            </button>
            <button type="button" data-tab="endereco" onclick="mudarAba('endereco')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                Endereço
            </button>
            <button type="button" data-tab="fiscal" onclick="mudarAba('fiscal')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                Fiscal
            </button>
            <button type="button" data-tab="limite-credito" onclick="mudarAba('limite-credito')"
                    class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                Limite de crédito
            </button>
            @if ($cliente?->exists)
                <button type="button" data-tab="credito-loja" onclick="mudarAba('credito-loja')"
                        class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                    Crédito em loja
                </button>
            @endif
        </nav>
    </div>

    <div class="p-6">

        {{-- ===================== ABA: DADOS GERAIS ===================== --}}
        <div id="aba-geral" class="tab-conteudo grid grid-cols-2 gap-5">

            <div class="col-span-2">
                <label class="{{ $labelCls }}">Tipo de pessoa <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_pessoa" value="fisica" onchange="alternarTipoPessoa()"
                               {{ old('tipo_pessoa', $cliente->tipo_pessoa ?? 'fisica') === 'fisica' ? 'checked' : '' }}>
                        Pessoa Física
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_pessoa" value="juridica" onchange="alternarTipoPessoa()"
                               {{ old('tipo_pessoa', $cliente->tipo_pessoa ?? '') === 'juridica' ? 'checked' : '' }}>
                        Pessoa Jurídica
                    </label>
                </div>
            </div>

            <div class="col-span-2">
                <label id="label-nome" class="{{ $labelCls }}">Nome completo <span class="text-red-500">*</span></label>
                <input type="text" name="nome" value="{{ old('nome', $cliente->nome ?? '') }}" required
                       class="{{ $inputCls }}">
            </div>

            <div id="campo-nome-fantasia" class="col-span-2 hidden">
                <label class="{{ $labelCls }}">Nome fantasia</label>
                <input type="text" name="nome_fantasia" value="{{ old('nome_fantasia', $cliente->nome_fantasia ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label id="label-documento" class="{{ $labelCls }}">CPF <span class="text-red-500">*</span></label>
                <input type="text" name="cpf_cnpj" id="campo-cpf-cnpj" value="{{ old('cpf_cnpj', $cliente->cpf_cnpj ?? '') }}" required
                       maxlength="18"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Telefone</label>
                <input type="text" name="telefone" value="{{ old('telefone', $cliente->telefone ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div class="col-span-2">
                <label class="{{ $labelCls }}">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $cliente->email ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

        </div>

        {{-- ===================== ABA: ENDEREÇO ===================== --}}
        <div id="aba-endereco" class="tab-conteudo grid grid-cols-2 gap-5 hidden">

            <div class="col-span-2">
                <p class="text-xs text-gray-400 -mt-1 mb-1">Não é obrigatório pra emitir NFC-e hoje, mas será exigido quando o sistema passar a emitir NF-e (modelo 55).</p>
            </div>

            <div>
                <label class="{{ $labelCls }}">CEP</label>
                <input type="text" name="cep" value="{{ old('cep', $cliente->cep ?? '') }}" maxlength="9"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">UF</label>
                <select name="uf"
                        class="{{ $inputCls }}">
                    <option value="">Selecione...</option>
                    @foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                        <option value="{{ $uf }}" {{ old('uf', $cliente->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-2">
                <label class="{{ $labelCls }}">Logradouro</label>
                <input type="text" name="logradouro" value="{{ old('logradouro', $cliente->logradouro ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Número</label>
                <input type="text" name="numero" value="{{ old('numero', $cliente->numero ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Complemento</label>
                <input type="text" name="complemento" value="{{ old('complemento', $cliente->complemento ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Bairro</label>
                <input type="text" name="bairro" value="{{ old('bairro', $cliente->bairro ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Município</label>
                <input type="text" name="municipio" value="{{ old('municipio', $cliente->municipio ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">
                    Código IBGE do município
                    <span class="{{ $opcionalCls }}">(7 dígitos)</span>
                </label>
                <input type="text" name="cod_municipio" value="{{ old('cod_municipio', $cliente->cod_municipio ?? '') }}" maxlength="7"
                       class="{{ $inputCls }}">
            </div>

        </div>

        {{-- ===================== ABA: FISCAL ===================== --}}
        <div id="aba-fiscal" class="tab-conteudo grid grid-cols-2 gap-5 hidden">

            <div class="col-span-2">
                <label class="{{ $labelCls }}">Indicador de IE <span class="text-red-500">*</span></label>
                <select name="indicador_ie" id="campo-indicador-ie"
                        class="{{ $inputCls }}">
                    <option value="nao_contribuinte" {{ old('indicador_ie', $cliente->indicador_ie ?? 'nao_contribuinte') === 'nao_contribuinte' ? 'selected' : '' }}>
                        Não Contribuinte (consumidor final, órgão público, hospital...)
                    </option>
                    <option value="contribuinte" {{ old('indicador_ie', $cliente->indicador_ie ?? '') === 'contribuinte' ? 'selected' : '' }}>
                        Contribuinte de ICMS
                    </option>
                    <option value="isento" {{ old('indicador_ie', $cliente->indicador_ie ?? '') === 'isento' ? 'selected' : '' }}>
                        Contribuinte Isento de IE
                    </option>
                </select>
            </div>

            <div id="campo-ie" class="col-span-2 hidden">
                <label class="{{ $labelCls }}">Inscrição Estadual <span class="text-red-500">*</span></label>
                <input type="text" name="ie" value="{{ old('ie', $cliente->ie ?? '') }}"
                       class="{{ $inputCls }}">
            </div>

        </div>

        {{-- ===================== ABA: LIMITE DE CRÉDITO (CONVÊNIO) ===================== --}}
        <div id="aba-limite-credito" class="tab-conteudo hidden">
            @include('clientes._limite-credito', ['cliente' => $cliente])
        </div>

        @if ($cliente?->exists)
            {{-- ===================== ABA: CRÉDITO EM LOJA ===================== --}}
            <div id="aba-credito-loja" class="tab-conteudo hidden">
                @include('clientes._credito-loja', ['cliente' => $cliente])
            </div>
        @endif

    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        Salvar cliente
    </button>
    <a href="{{ route('clientes.index') }}" class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>

{{-- Modal de consulta de CNPJ --}}
<div id="modal-consulta-cnpj" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-lg px-8 py-6 flex flex-col items-center gap-3">
        <svg class="animate-spin h-8 w-8 text-[#10284A]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <p class="text-sm font-medium text-gray-700">Buscando informações do CNPJ...</p>
    </div>
</div>

@include('partials.municipio-autocomplete', [
    'municipio' => '[name="municipio"]',
    'codigo'    => '[name="cod_municipio"]',
    'uf'        => '[name="uf"]',
])

@include('partials.cep-autocomplete', [
    'cep'        => '[name="cep"]',
    'logradouro' => '[name="logradouro"]',
    'numero'     => '[name="numero"]',
    'bairro'     => '[name="bairro"]',
    'municipio'  => '[name="municipio"]',
    'codigo'     => '[name="cod_municipio"]',
    'uf'         => '[name="uf"]',
])

<script>
    
function mudarAba(aba) {
    document.querySelectorAll('.tab-conteudo').forEach(el => el.classList.add('hidden'));
    document.getElementById('aba-' + aba).classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        const ativa = btn.dataset.tab === aba;
        btn.classList.toggle('border-[#10284A]', ativa);
        btn.classList.toggle('text-[#10284A]', ativa);
        btn.classList.toggle('border-transparent', !ativa);
        btn.classList.toggle('text-gray-500', !ativa);
    });
}

function alternarTipoPessoa() {
    const tipo = document.querySelector('input[name="tipo_pessoa"]:checked').value;
    const labelNome = document.getElementById('label-nome');
    const campoFantasia = document.getElementById('campo-nome-fantasia');
    const labelDocumento = document.getElementById('label-documento');
    const campoDoc = document.getElementById('campo-cpf-cnpj');

    if (tipo === 'juridica') {
        labelNome.innerHTML = 'Razão social <span class="text-red-500">*</span>';
        campoFantasia.classList.remove('hidden');
        labelDocumento.innerHTML = 'CNPJ <span class="text-red-500">*</span>';
        campoDoc.placeholder = '00.000.000/0000-00';
    } else {
        labelNome.innerHTML = 'Nome completo <span class="text-red-500">*</span>';
        campoFantasia.classList.add('hidden');
        labelDocumento.innerHTML = 'CPF <span class="text-red-500">*</span>';
        campoDoc.placeholder = '000.000.000-00';
    }
}

function alternarCampoIE() {
    const indicador = document.getElementById('campo-indicador-ie').value;
    const campoIe = document.getElementById('campo-ie');
    campoIe.classList.toggle('hidden', indicador !== 'contribuinte');
}

document.getElementById('campo-indicador-ie').addEventListener('change', alternarCampoIE);
document.addEventListener('DOMContentLoaded', () => {
    alternarTipoPessoa();
    alternarCampoIE();
});

async function consultarCnpjAutomatico() {
    const tipo = document.querySelector('input[name="tipo_pessoa"]:checked').value;
    if (tipo !== 'juridica') return;

    const campoCnpj = document.getElementById('campo-cpf-cnpj');
    const cnpjLimpo = campoCnpj.value.replace(/\D/g, '');
    if (cnpjLimpo.length !== 14) return;

    abrirModalConsultaCnpj();

    try {
        const resp = await fetch(`/api/consulta-cnpj/${cnpjLimpo}`);
        const dados = await resp.json();

        if (!resp.ok) {
            alert(dados.erro || 'Não foi possível consultar o CNPJ.');
            return;
        }

        preencherSeVazio('nome', dados.nome);
        preencherSeVazio('nome_fantasia', dados.nome_fantasia);
        preencherSeVazio('telefone', dados.telefone);
        preencherSeVazio('email', dados.email);
        preencherSeVazio('cep', dados.cep);
        preencherSeVazio('logradouro', dados.logradouro);
        preencherSeVazio('numero', dados.numero);
        preencherSeVazio('complemento', dados.complemento);
        preencherSeVazio('bairro', dados.bairro);
        preencherSeVazio('municipio', dados.municipio);
        preencherSeVazio('cod_municipio', dados.cod_municipio);

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

const campoCnpj = document.getElementById('campo-cpf-cnpj');

campoCnpj.addEventListener('blur', consultarCnpjAutomatico);

campoCnpj.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault(); // impede o submit do form
        consultarCnpjAutomatico();
    }
});
</script>