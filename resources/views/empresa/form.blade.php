@extends('layouts.app')

@section('titulo', 'Dados da Empresa')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Dados da Empresa (Emitente)</h1>

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

    <form action="{{ route('empresa.salvar') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">

            {{-- Tabs --}}
            <div class="flex border-b border-gray-200">
                <button type="button" onclick="trocarTabEmpresa('geral')" id="tab-btn-emp-geral"
                        class="tab-btn-emp px-6 py-4 text-sm font-medium border-b-2 border-blue-600 text-blue-600 transition">
                    Dados Gerais
                </button>
                <button type="button" onclick="trocarTabEmpresa('endereco')" id="tab-btn-emp-endereco"
                        class="tab-btn-emp px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                    Endereço
                </button>
                <button type="button" onclick="trocarTabEmpresa('fiscal')" id="tab-btn-emp-fiscal"
                        class="tab-btn-emp px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                    Certificado e Fiscal
                </button>
            </div>

            {{-- Tab: Dados Gerais --}}
            <div id="tab-emp-geral" class="tab-painel-emp p-6 grid grid-cols-2 gap-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ <span class="text-red-500">*</span></label>
                    <input type="text" name="cnpj" maxlength="14" value="{{ old('cnpj', $empresa->cnpj) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razão Social <span class="text-red-500">*</span></label>
                    <input type="text" name="razao_social" value="{{ old('razao_social', $empresa->razao_social) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inscrição Estadual</label>
                    <input type="text" name="ie" value="{{ old('ie', $empresa->ie) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inscrição Municipal</label>
                    <input type="text" name="im" value="{{ old('im', $empresa->im) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Regime Tributário (CRT) <span class="text-red-500">*</span></label>
                    <select name="crt"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                        <option value="1" {{ old('crt', $empresa->crt) == 1 ? 'selected' : '' }}>1 - Simples Nacional</option>
                        <option value="2" {{ old('crt', $empresa->crt) == 2 ? 'selected' : '' }}>2 - Simples Nacional (excesso)</option>
                        <option value="3" {{ old('crt', $empresa->crt) == 3 ? 'selected' : '' }}>3 - Regime Normal</option>
                    </select>
                </div>
            </div>

            {{-- Tab: Endereço --}}
            <div id="tab-emp-endereco" class="tab-painel-emp p-6 grid grid-cols-2 gap-5 hidden">

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro <span class="text-red-500">*</span></label>
                    <input type="text" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Número <span class="text-red-500">*</span></label>
                    <input type="text" name="numero" value="{{ old('numero', $empresa->numero) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                    <input type="text" name="complemento" value="{{ old('complemento', $empresa->complemento) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bairro <span class="text-red-500">*</span></label>
                    <input type="text" name="bairro" value="{{ old('bairro', $empresa->bairro) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CEP <span class="text-red-500">*</span></label>
                    <input type="text" name="cep" maxlength="8" value="{{ old('cep', $empresa->cep) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Município <span class="text-red-500">*</span></label>
                    <input type="text" name="municipio" value="{{ old('municipio', $empresa->municipio) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código IBGE do Município <span class="text-red-500">*</span></label>
                    <input type="text" name="cod_municipio" maxlength="7" value="{{ old('cod_municipio', $empresa->cod_municipio) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">UF <span class="text-red-500">*</span></label>
                    <input type="text" name="uf" maxlength="2" value="{{ old('uf', $empresa->uf) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition uppercase">
                </div>
            </div>

            {{-- Tab: Certificado Digital e Configuração Fiscal --}}
            <div id="tab-emp-fiscal" class="tab-painel-emp p-6 grid grid-cols-2 gap-5 hidden">

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Certificado (.pfx)
                        @if ($empresa->certificado_base64)
                            <span class="text-green-600 text-xs font-normal">— já existe um certificado salvo, envie um novo só se quiser substituir</span>
                        @endif
                    </label>
                    <input type="file" name="certificado" accept=".pfx,.p12"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Senha do certificado
                        @if ($empresa->certificado_senha)
                            <span class="text-xs text-gray-400 font-normal">(deixe em branco para manter a atual)</span>
                        @endif
                    </label>
                    <input type="password" name="certificado_senha"
                        placeholder="{{ $empresa->certificado_senha ? '(mantém a atual se deixar em branco)' : '' }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Validade do certificado</label>
                    <input type="date" name="certificado_validade" value="{{ old('certificado_validade', $empresa->certificado_validade?->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ambiente <span class="text-red-500">*</span></label>
                    <select name="ambiente"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                        <option value="2" {{ old('ambiente', $empresa->ambiente ?? 2) == 2 ? 'selected' : '' }}>Homologação (testes)</option>
                        <option value="1" {{ old('ambiente', $empresa->ambiente) == 1 ? 'selected' : '' }}>Produção</option>
                    </select>
                </div>

                <p class="col-span-2 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
                    Série, CSC e numeração de NFC-e agora são configurados individualmente em
                    <a href="{{ route('pdvs.index') }}" class="text-blue-600 hover:underline">cada PDV</a>.
                </p>
            </div>
        </div>

        {{-- Botões de ação --}}
        <div class="mt-6 flex gap-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Salvar
            </button>
            <a href="{{ url()->previous() }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Cancelar
            </a>
        </div>
    </form>

    <script>
        function trocarTabEmpresa(tab) {
            document.querySelectorAll('.tab-painel-emp').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-btn-emp').forEach(b => {
                b.classList.remove('border-blue-600', 'text-blue-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById('tab-emp-' + tab).classList.remove('hidden');
            const btn = document.getElementById('tab-btn-emp-' + tab);
            btn.classList.add('border-blue-600', 'text-blue-600');
            btn.classList.remove('border-transparent', 'text-gray-500');
        }

        document.addEventListener('DOMContentLoaded', () => {
            @if ($errors->has('cnpj') || $errors->has('razao_social') || $errors->has('nome_fantasia') || $errors->has('ie') || $errors->has('im') || $errors->has('crt'))
                trocarTabEmpresa('geral');
            @elseif ($errors->has('logradouro') || $errors->has('numero') || $errors->has('complemento') || $errors->has('bairro') || $errors->has('cep') || $errors->has('municipio') || $errors->has('cod_municipio') || $errors->has('uf'))
                trocarTabEmpresa('endereco');
            @elseif ($errors->has('certificado') || $errors->has('certificado_senha') || $errors->has('certificado_validade') || $errors->has('ambiente'))
                trocarTabEmpresa('fiscal');
            @endif
        });
    </script>
@endsection