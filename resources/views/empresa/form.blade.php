@extends('layouts.app')

@section('titulo', 'Dados da Empresa')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Dados da Empresa (Emitente)</h1>

    @if ($errors->any())
        <div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-3 mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('empresa.salvar') }}" method="POST" enctype="multipart/form-data"
          class="bg-white p-6 rounded shadow grid grid-cols-2 gap-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">CNPJ *</label>
            <input type="text" name="cnpj" maxlength="14" value="{{ old('cnpj', $empresa->cnpj) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Razão Social *</label>
            <input type="text" name="razao_social" value="{{ old('razao_social', $empresa->razao_social) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Nome Fantasia</label>
            <input type="text" name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Inscrição Estadual</label>
            <input type="text" name="ie" value="{{ old('ie', $empresa->ie) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Inscrição Municipal</label>
            <input type="text" name="im" value="{{ old('im', $empresa->im) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Regime Tributário (CRT) *</label>
            <select name="crt" class="w-full border rounded px-3 py-2">
                <option value="1" {{ old('crt', $empresa->crt) == 1 ? 'selected' : '' }}>1 - Simples Nacional</option>
                <option value="2" {{ old('crt', $empresa->crt) == 2 ? 'selected' : '' }}>2 - Simples Nacional (excesso)</option>
                <option value="3" {{ old('crt', $empresa->crt) == 3 ? 'selected' : '' }}>3 - Regime Normal</option>
            </select>
        </div>

        <hr class="col-span-2 my-2">
        <h3 class="col-span-2 font-semibold text-gray-700">Endereço</h3>

        <div>
            <label class="block text-sm font-medium mb-1">Logradouro *</label>
            <input type="text" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Número *</label>
            <input type="text" name="numero" value="{{ old('numero', $empresa->numero) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Complemento</label>
            <input type="text" name="complemento" value="{{ old('complemento', $empresa->complemento) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Bairro *</label>
            <input type="text" name="bairro" value="{{ old('bairro', $empresa->bairro) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">CEP *</label>
            <input type="text" name="cep" maxlength="8" value="{{ old('cep', $empresa->cep) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Município *</label>
            <input type="text" name="municipio" value="{{ old('municipio', $empresa->municipio) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Código IBGE do Município *</label>
            <input type="text" name="cod_municipio" maxlength="7" value="{{ old('cod_municipio', $empresa->cod_municipio) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">UF *</label>
            <input type="text" name="uf" maxlength="2" value="{{ old('uf', $empresa->uf) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <hr class="col-span-2 my-2">
        <h3 class="col-span-2 font-semibold text-gray-700">Certificado Digital e Configuração Fiscal</h3>

        <div class="col-span-2">
            <label class="block text-sm font-medium mb-1">
                Certificado (.pfx)
                @if ($empresa->certificado_base64)
                    <span class="text-green-600 text-xs">— já existe um certificado salvo, envie um novo só se quiser substituir</span>
                @endif
            </label>
            <input type="file" name="certificado" accept=".pfx,.p12"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">
                Senha do certificado
                @if ($empresa->certificado_senha)
                    <span class="text-xs text-gray-400 font-normal">(deixe em branco para manter a senha atual)</span>
                @endif
            </label>
            <input type="password" name="certificado_senha"
                placeholder="{{ $empresa->certificado_senha ? '(mantém a atual se deixar em branco)' : '' }}"
                class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Validade do certificado</label>
            <input type="date" name="certificado_validade" value="{{ old('certificado_validade', $empresa->certificado_validade?->format('Y-m-d')) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <p class="col-span-2 text-xs text-gray-400">
            Série, CSC e numeração de NFC-e agora são configurados individualmente em
            <a href="{{ route('pdvs.index') }}" class="text-blue-600 hover:underline">cada PDV</a>.
        </p>

        <div>
            <label class="block text-sm font-medium mb-1">Ambiente *</label>
            <select name="ambiente" class="w-full border rounded px-3 py-2">
                <option value="2" {{ old('ambiente', $empresa->ambiente ?? 2) == 2 ? 'selected' : '' }}>Homologação (testes)</option>
                <option value="1" {{ old('ambiente', $empresa->ambiente) == 1 ? 'selected' : '' }}>Produção</option>
            </select>
        </div>

        <div class="col-span-2 mt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold">
                Salvar
            </button>
        </div>
    </form>
@endsection