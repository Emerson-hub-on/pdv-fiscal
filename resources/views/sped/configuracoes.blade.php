@extends('layouts.app')

@section('titulo', 'Configuração do SPED')

@section('conteudo')
    @php
        $campo    = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $campoSel = $campo . ' bg-white';
        $ufs      = \App\Models\Transportador::UFS;
        $card     = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6';
        $titulo   = 'text-xs font-semibold uppercase tracking-wide text-gray-500 mb-4';
    @endphp

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Configuração do SPED</h1>

    @if (session('sucesso'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('sucesso') }}</div>
    @endif

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

    <form action="{{ route('sped-config.salvar') }}" method="POST" class="space-y-6">
        @csrf

        {{-- ===================== ATIVIDADE ===================== --}}
        <div class="{{ $card }}">
            <h2 class="{{ $titulo }}">Atividade da empresa</h2>

            <div class="grid grid-cols-2 gap-5">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Indicador de atividade <span class="text-red-500">*</span></label>
                    <select name="ind_ativ" class="{{ $campoSel }}"
                            onchange="document.getElementById('campo-clas-estab').classList.toggle('hidden', this.value !== '0')">
                        <option value="1" {{ (string) old('ind_ativ', $config->ind_ativ) === '1' ? 'selected' : '' }}>Comércio / outros</option>
                        <option value="0" {{ (string) old('ind_ativ', $config->ind_ativ) === '0' ? 'selected' : '' }}>Industrial ou equiparado a industrial</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Indústria costuma exigir também o controle de produção e estoque (Bloco K) e a apuração do IPI, que entram em etapas futuras.
                        Confirme a obrigatoriedade com o contador.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Perfil de apresentação <span class="text-red-500">*</span></label>
                    <select name="ind_perfil" class="{{ $campoSel }}">
                        @foreach (\App\Models\SpedConfiguracao::PERFIS as $valor => $rotulo)
                            <option value="{{ $valor }}" {{ old('ind_perfil', $config->ind_perfil ?? 'A') === $valor ? 'selected' : '' }}>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Definido pela SEFAZ da sua UF. Na dúvida, confirme com o contador.</p>
                </div>

                <div id="campo-clas-estab" class="{{ (string) old('ind_ativ', $config->ind_ativ) === '0' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Classificação industrial <span class="text-red-500">*</span></label>
                    <select name="clas_estab_ind" class="{{ $campoSel }}">
                        <option value="">Selecione...</option>
                        @foreach (\App\Models\SpedConfiguracao::CLASSIFICACOES_INDUSTRIAIS as $valor => $rotulo)
                            <option value="{{ $valor }}" {{ old('clas_estab_ind', $config->clas_estab_ind) === $valor ? 'selected' : '' }}>{{ $valor }} - {{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Registro 0002, exigido de estabelecimento industrial ou equiparado.</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Código de receita do ICMS <span class="text-xs text-gray-400 font-normal">(registro E116)</span>
                    </label>
                    <input type="text" name="cod_receita_icms" maxlength="20"
                           value="{{ old('cod_receita_icms', $config->cod_receita_icms) }}" class="{{ $campo }} font-mono">
                    <p class="text-xs text-gray-400 mt-1">Código da guia de recolhimento do ICMS próprio, definido pela SEFAZ da UF. Só é usado quando o mês tiver ICMS a recolher.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone da empresa <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="empresa_fone" maxlength="15" inputmode="numeric" placeholder="DDD + número"
                           value="{{ old('empresa_fone', $config->empresa_fone) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail da empresa <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                    <input type="email" name="empresa_email" maxlength="100"
                           value="{{ old('empresa_email', $config->empresa_email) }}" class="{{ $campo }}">
                </div>
            </div>
        </div>

        {{-- ===================== CONTADOR ===================== --}}
        <div class="{{ $card }}">
            <h2 class="{{ $titulo }}">Contador responsável (registro 0100)</h2>

            <div class="grid grid-cols-2 gap-5">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_nome" maxlength="100" required
                           value="{{ old('contador_nome', $config->contador_nome) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_cpf" id="contador-cpf" maxlength="14" required inputmode="numeric" placeholder="000.000.000-00"
                           value="{{ old('contador_cpf', $config->contador_cpf) }}" class="{{ $campo }} font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CRC <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_crc" maxlength="15" required
                           value="{{ old('contador_crc', $config->contador_crc) }}" class="{{ $campo }} font-mono">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ do escritório <span class="text-xs text-gray-400 font-normal">(se houver)</span></label>
                    <input type="text" name="contador_cnpj" maxlength="18" inputmode="numeric"
                           value="{{ old('contador_cnpj', $config->contador_cnpj) }}" class="{{ $campo }} font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CEP <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_cep" maxlength="9" required
                           value="{{ old('contador_cep', $config->contador_cep) }}" class="{{ $campo }} font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">UF <span class="text-red-500">*</span></label>
                    <select name="contador_uf" required class="{{ $campoSel }}">
                        <option value="">Selecione...</option>
                        @foreach ($ufs as $uf)
                            <option value="{{ $uf }}" {{ old('contador_uf', $config->contador_uf) === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_endereco" maxlength="60" required
                           value="{{ old('contador_endereco', $config->contador_endereco) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                    <input type="text" name="contador_numero" maxlength="10"
                           value="{{ old('contador_numero', $config->contador_numero) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                    <input type="text" name="contador_complemento" maxlength="60"
                           value="{{ old('contador_complemento', $config->contador_complemento) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bairro <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_bairro" maxlength="60" required
                           value="{{ old('contador_bairro', $config->contador_bairro) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Município <span class="text-red-500">*</span></label>
                    <input type="text" name="contador_municipio" maxlength="100" required
                           value="{{ old('contador_municipio', $config->contador_municipio) }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código IBGE do município <span class="text-xs text-gray-400 font-normal">(7 dígitos)</span></label>
                    <input type="text" name="contador_cod_municipio" maxlength="7" inputmode="numeric"
                           value="{{ old('contador_cod_municipio', $config->contador_cod_municipio) }}" class="{{ $campo }} font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone <span class="text-xs text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="contador_fone" maxlength="15" inputmode="numeric" placeholder="DDD + número"
                           value="{{ old('contador_fone', $config->contador_fone) }}" class="{{ $campo }}">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail <span class="text-red-500">*</span></label>
                    <input type="email" name="contador_email" maxlength="100" required
                           value="{{ old('contador_email', $config->contador_email) }}" class="{{ $campo }}">
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Salvar configurações
            </button>
            <a href="{{ route('sped.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Voltar
            </a>
        </div>
    </form>

    <script>
        // Máscara do CPF do contador
        document.getElementById('contador-cpf').addEventListener('input', (e) => {
            const d = e.target.value.replace(/\D/g, '').slice(0, 11);
            e.target.value = d
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        });
    </script>

    @include('partials.municipio-autocomplete', [
        'municipio' => '[name="contador_municipio"]',
        'codigo'    => '[name="contador_cod_municipio"]',
        'uf'        => '[name="contador_uf"]',
    ])

    @include('partials.cep-autocomplete', [
        'cep'        => '[name="contador_cep"]',
        'logradouro' => '[name="contador_endereco"]',
        'numero'     => '[name="contador_numero"]',
        'bairro'     => '[name="contador_bairro"]',
        'municipio'  => '[name="contador_municipio"]',
        'codigo'     => '[name="contador_cod_municipio"]',
        'uf'         => '[name="contador_uf"]',
    ])
@endsection