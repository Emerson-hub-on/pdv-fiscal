@php $cliente = $cliente ?? null; @endphp

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

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 grid grid-cols-2 gap-5">

    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de pessoa <span class="text-red-500">*</span></label>
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
        <label id="label-nome" class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
        <input type="text" name="nome" value="{{ old('nome', $cliente->nome ?? '') }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div id="campo-nome-fantasia" class="col-span-2 hidden">
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome fantasia</label>
        <input type="text" name="nome_fantasia" value="{{ old('nome_fantasia', $cliente->nome_fantasia ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label id="label-documento" class="block text-sm font-medium text-gray-700 mb-1">CPF <span class="text-red-500">*</span></label>
        <input type="text" name="cpf_cnpj" id="campo-cpf-cnpj" value="{{ old('cpf_cnpj', $cliente->cpf_cnpj ?? '') }}" required
               maxlength="18"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
        <input type="text" name="telefone" value="{{ old('telefone', $cliente->telefone ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
        <input type="email" name="email" value="{{ old('email', $cliente->email ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div class="col-span-2 border-t pt-4 mt-1">
        <p class="text-sm font-semibold text-gray-700 mb-3">Situação tributária (ICMS)</p>
    </div>

    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Indicador de IE <span class="text-red-500">*</span></label>
        <select name="indicador_ie" id="campo-indicador-ie"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
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
        <label class="block text-sm font-medium text-gray-700 mb-1">Inscrição Estadual <span class="text-red-500">*</span></label>
        <input type="text" name="ie" value="{{ old('ie', $cliente->ie ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div class="col-span-2 border-t pt-4 mt-1">
        <p class="text-sm font-semibold text-gray-700 mb-1">Endereço</p>
        <p class="text-xs text-gray-400 mb-3">Não é obrigatório pra emitir NFC-e hoje, mas será exigido quando o sistema passar a emitir NF-e (modelo 55).</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
        <input type="text" name="cep" value="{{ old('cep', $cliente->cep ?? '') }}" maxlength="9"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
        <select name="uf"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
            <option value="">Selecione...</option>
            @foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                <option value="{{ $uf }}" {{ old('uf', $cliente->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
        <input type="text" name="logradouro" value="{{ old('logradouro', $cliente->logradouro ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
        <input type="text" name="numero" value="{{ old('numero', $cliente->numero ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
        <input type="text" name="complemento" value="{{ old('complemento', $cliente->complemento ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
        <input type="text" name="bairro" value="{{ old('bairro', $cliente->bairro ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Município</label>
        <input type="text" name="municipio" value="{{ old('municipio', $cliente->municipio ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Código IBGE do município
            <span class="text-xs text-gray-400 font-normal">(7 dígitos)</span>
        </label>
        <input type="text" name="cod_municipio" value="{{ old('cod_municipio', $cliente->cod_municipio ?? '') }}" maxlength="7"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
    </div>

</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar cliente
    </button>
    <a href="{{ route('clientes.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>

<script>
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
</script>
