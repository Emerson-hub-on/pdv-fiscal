@extends('layouts.app')

@section('titulo', 'Configuração do SPED')

@section('conteudo')
    @php
        $campo    = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
        $campoSel = $campo . ' bg-white';
        $label    = 'block text-sm font-medium text-gray-700 mb-1';
        $ajuda    = 'text-xs text-gray-400 mt-1';
        $opcional = 'text-xs text-gray-400 font-normal';
        $ufs      = \App\Models\Transportador::UFS;
        $regimeNormal = (int) \App\Models\Empresa::atual()->crt === 3;

        // Campos de cada aba: com erro de validação, a tela abre na aba do primeiro campo com problema
        $abas = [
            'empresa'       => ['titulo' => 'Empresa',                'campos' => ['ind_ativ', 'clas_estab_ind', 'empresa_fone', 'empresa_email']],
            'icms'          => ['titulo' => 'SPED Fiscal (ICMS/IPI)', 'campos' => ['ind_perfil', 'cod_receita_icms']],
            'contribuicoes' => ['titulo' => 'EFD-Contribuições',      'campos' => ['regime_pis_cofins', 'cod_cta_receita', 'nome_cta_receita', 'cod_cta_compras', 'nome_cta_compras']],
            'contador'      => ['titulo' => 'Contador',               'campos' => ['contador_nome', 'contador_cpf', 'contador_crc', 'contador_cnpj', 'contador_cep',
                                    'contador_endereco', 'contador_numero', 'contador_complemento', 'contador_bairro', 'contador_municipio',
                                    'contador_cod_municipio', 'contador_uf', 'contador_fone', 'contador_email']],
        ];

        $abaComErro = fn (string $aba) => collect($abas[$aba]['campos'])->contains(fn ($c) => $errors->has($c));
        $abaInicial = collect(array_keys($abas))->first(fn ($a) => $abaComErro($a)) ?? 'empresa';
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

    <form action="{{ route('sped-config.salvar') }}" method="POST">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">

            {{-- Navegação das abas --}}
            <div class="border-b border-gray-200 px-6 pt-4">
                <nav class="flex gap-6">
                    @foreach ($abas as $chave => $aba)
                        <button type="button" data-tab="{{ $chave }}" onclick="mudarAba('{{ $chave }}')"
                                class="tab-btn pb-3 text-sm font-medium border-b-2 transition flex items-center gap-1.5 {{ $abaInicial === $chave ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            {{ $aba['titulo'] }}
                            @if ($abaComErro($chave))
                                <span class="w-2 h-2 rounded-full bg-red-500" title="Esta aba tem campos com erro"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">

                {{-- ===================== ABA: EMPRESA ===================== --}}
                <div id="aba-empresa" class="tab-conteudo grid grid-cols-2 gap-5 {{ $abaInicial === 'empresa' ? '' : 'hidden' }}">
                    <div class="col-span-2">
                        <label class="{{ $label }}">Indicador de atividade <span class="text-red-500">*</span></label>
                        <select name="ind_ativ" class="{{ $campoSel }}"
                                onchange="document.getElementById('campo-clas-estab').classList.toggle('hidden', this.value !== '0')">
                            <option value="1" {{ (string) old('ind_ativ', $config->ind_ativ) === '1' ? 'selected' : '' }}>Comércio / outros</option>
                            <option value="0" {{ (string) old('ind_ativ', $config->ind_ativ) === '0' ? 'selected' : '' }}>Industrial ou equiparado a industrial</option>
                        </select>
                        <p class="{{ $ajuda }}">
                            Vale para o SPED Fiscal e para a EFD-Contribuições. Indústria costuma exigir também o controle de produção e estoque (Bloco K)
                            e a apuração do IPI, que entram em etapas futuras. Confirme a obrigatoriedade com o contador.
                        </p>
                    </div>

                    <div id="campo-clas-estab" class="col-span-2 {{ (string) old('ind_ativ', $config->ind_ativ) === '0' ? '' : 'hidden' }}">
                        <label class="{{ $label }}">Classificação industrial <span class="text-red-500">*</span></label>
                        <select name="clas_estab_ind" class="{{ $campoSel }}">
                            <option value="">Selecione...</option>
                            @foreach (\App\Models\SpedConfiguracao::CLASSIFICACOES_INDUSTRIAIS as $valor => $rotulo)
                                <option value="{{ $valor }}" {{ old('clas_estab_ind', $config->clas_estab_ind) === $valor ? 'selected' : '' }}>{{ $valor }} - {{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $ajuda }}">Registro 0002 do SPED Fiscal, exigido de estabelecimento industrial ou equiparado.</p>
                    </div>

                    <div>
                        <label class="{{ $label }}">Telefone da empresa <span class="{{ $opcional }}">(opcional)</span></label>
                        <input type="text" name="empresa_fone" maxlength="15" inputmode="numeric" placeholder="DDD + número"
                               value="{{ old('empresa_fone', $config->empresa_fone) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">E-mail da empresa <span class="{{ $opcional }}">(opcional)</span></label>
                        <input type="email" name="empresa_email" maxlength="100"
                               value="{{ old('empresa_email', $config->empresa_email) }}" class="{{ $campo }}">
                    </div>

                    <p class="col-span-2 text-xs text-gray-400">
                        Razão social, CNPJ, IE e endereço vêm do <a href="{{ route('empresa.editar') }}" class="text-blue-600 hover:text-blue-700">cadastro da empresa</a>.
                    </p>
                </div>

                {{-- ===================== ABA: SPED FISCAL (ICMS/IPI) ===================== --}}
                <div id="aba-icms" class="tab-conteudo grid grid-cols-2 gap-5 {{ $abaInicial === 'icms' ? '' : 'hidden' }}">
                    <div>
                        <label class="{{ $label }}">Perfil de apresentação <span class="text-red-500">*</span></label>
                        <select name="ind_perfil" class="{{ $campoSel }}">
                            @foreach (\App\Models\SpedConfiguracao::PERFIS as $valor => $rotulo)
                                <option value="{{ $valor }}" {{ old('ind_perfil', $config->ind_perfil ?? 'A') === $valor ? 'selected' : '' }}>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $ajuda }}">Definido pela SEFAZ da sua UF. Na dúvida, confirme com o contador.</p>
                    </div>

                    <div>
                        <label class="{{ $label }}">Código de receita do ICMS <span class="{{ $opcional }}">(registro E116)</span></label>
                        <input type="text" name="cod_receita_icms" maxlength="20"
                               value="{{ old('cod_receita_icms', $config->cod_receita_icms) }}" class="{{ $campo }} font-mono">
                        <p class="{{ $ajuda }}">Código da guia do ICMS próprio, definido pela SEFAZ da UF. Só é usado quando o mês tiver ICMS a recolher.</p>
                    </div>
                </div>

                {{-- ===================== ABA: EFD-CONTRIBUIÇÕES ===================== --}}
                <div id="aba-contribuicoes" class="tab-conteudo grid grid-cols-2 gap-5 {{ $abaInicial === 'contribuicoes' ? '' : 'hidden' }}">
                    @if ($regimeNormal)
                        <div class="col-span-2">
                            <label class="{{ $label }}">Regime do PIS/COFINS <span class="text-red-500">*</span></label>
                            <select name="regime_pis_cofins" class="{{ $campoSel }}">
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\SpedConfiguracao::REGIMES_PIS_COFINS as $valor => $rotulo)
                                    <option value="{{ $valor }}" {{ old('regime_pis_cofins', $config->regime_pis_cofins) === $valor ? 'selected' : '' }}>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            <p class="{{ $ajuda }}">
                                O CRT 3 não diferencia Lucro Presumido de Lucro Real. Define o crédito de PIS/COFINS nas entradas e a EFD-Contribuições.
                                Ao mudar, recalcule as entradas já lançadas com <code class="font-mono">php artisan fiscal:pis-cofins-entradas</code>.
                            </p>
                        </div>

                        <div class="col-span-2 border-t border-gray-100 pt-4">
                            <p class="text-sm font-medium text-gray-700">Contas contábeis <span class="{{ $opcional }}">(campo COD_CTA e registro 0500)</span></p>
                            <p class="text-xs text-gray-400 mt-0.5">Use o código e o nome do plano de contas do contador. Obrigatórias no Lucro Real; no Presumido só são dispensadas se a empresa não tiver contabilidade (ECD).</p>
                        </div>

                        <div>
                            <label class="{{ $label }}">Conta de receita de vendas — código</label>
                            <input type="text" name="cod_cta_receita" maxlength="60" placeholder="Ex.: 3.1.1.01.001"
                                   value="{{ old('cod_cta_receita', $config->cod_cta_receita) }}" class="{{ $campo }} font-mono">
                        </div>
                        <div>
                            <label class="{{ $label }}">Conta de receita de vendas — nome</label>
                            <input type="text" name="nome_cta_receita" maxlength="60" placeholder="Ex.: Receita de venda de mercadorias"
                                   value="{{ old('nome_cta_receita', $config->nome_cta_receita) }}" class="{{ $campo }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Conta de compras / estoque — código</label>
                            <input type="text" name="cod_cta_compras" maxlength="60" placeholder="Ex.: 1.1.4.01.001"
                                   value="{{ old('cod_cta_compras', $config->cod_cta_compras) }}" class="{{ $campo }} font-mono">
                        </div>
                        <div>
                            <label class="{{ $label }}">Conta de compras / estoque — nome</label>
                            <input type="text" name="nome_cta_compras" maxlength="60" placeholder="Ex.: Estoque de mercadorias para revenda"
                                   value="{{ old('nome_cta_compras', $config->nome_cta_compras) }}" class="{{ $campo }}">
                        </div>
                    @else
                        <div class="col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                            A empresa está no Simples Nacional: o PIS/COFINS é apurado no PGDAS-D e não há EFD-Contribuições a configurar.
                            Estes campos aparecem quando o regime tributário (CRT) do
                            <a href="{{ route('empresa.editar') }}" class="text-blue-600 hover:text-blue-700 font-medium">cadastro da empresa</a> for 3 — regime normal.
                        </div>
                    @endif
                </div>

                {{-- ===================== ABA: CONTADOR ===================== --}}
                <div id="aba-contador" class="tab-conteudo grid grid-cols-2 gap-5 {{ $abaInicial === 'contador' ? '' : 'hidden' }}">
                    <p class="col-span-2 text-xs text-gray-400 -mt-1">Registro 0100, usado no SPED Fiscal e na EFD-Contribuições.</p>

                    <div class="col-span-2">
                        <label class="{{ $label }}">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_nome" maxlength="100" required
                               value="{{ old('contador_nome', $config->contador_nome) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">CPF <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_cpf" id="contador-cpf" maxlength="14" required inputmode="numeric" placeholder="000.000.000-00"
                               value="{{ old('contador_cpf', $config->contador_cpf) }}" class="{{ $campo }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $label }}">CRC <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_crc" maxlength="15" required
                               value="{{ old('contador_crc', $config->contador_crc) }}" class="{{ $campo }} font-mono">
                    </div>

                    <div class="col-span-2">
                        <label class="{{ $label }}">CNPJ do escritório <span class="{{ $opcional }}">(se houver)</span></label>
                        <input type="text" name="contador_cnpj" maxlength="18" inputmode="numeric"
                               value="{{ old('contador_cnpj', $config->contador_cnpj) }}" class="{{ $campo }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $label }}">CEP <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_cep" maxlength="9" required
                               value="{{ old('contador_cep', $config->contador_cep) }}" class="{{ $campo }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $label }}">UF <span class="text-red-500">*</span></label>
                        <select name="contador_uf" required class="{{ $campoSel }}">
                            <option value="">Selecione...</option>
                            @foreach ($ufs as $uf)
                                <option value="{{ $uf }}" {{ old('contador_uf', $config->contador_uf) === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="{{ $label }}">Logradouro <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_endereco" maxlength="60" required
                               value="{{ old('contador_endereco', $config->contador_endereco) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Número</label>
                        <input type="text" name="contador_numero" maxlength="10"
                               value="{{ old('contador_numero', $config->contador_numero) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Complemento</label>
                        <input type="text" name="contador_complemento" maxlength="60"
                               value="{{ old('contador_complemento', $config->contador_complemento) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Bairro <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_bairro" maxlength="60" required
                               value="{{ old('contador_bairro', $config->contador_bairro) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Município <span class="text-red-500">*</span></label>
                        <input type="text" name="contador_municipio" maxlength="100" required
                               value="{{ old('contador_municipio', $config->contador_municipio) }}" class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Código IBGE do município <span class="{{ $opcional }}">(7 dígitos)</span></label>
                        <input type="text" name="contador_cod_municipio" maxlength="7" inputmode="numeric"
                               value="{{ old('contador_cod_municipio', $config->contador_cod_municipio) }}" class="{{ $campo }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $label }}">Telefone <span class="{{ $opcional }}">(opcional)</span></label>
                        <input type="text" name="contador_fone" maxlength="15" inputmode="numeric" placeholder="DDD + número"
                               value="{{ old('contador_fone', $config->contador_fone) }}" class="{{ $campo }}">
                    </div>

                    <div class="col-span-2">
                        <label class="{{ $label }}">E-mail <span class="text-red-500">*</span></label>
                        <input type="email" name="contador_email" maxlength="100" required
                               value="{{ old('contador_email', $config->contador_email) }}" class="{{ $campo }}">
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                Salvar configurações
            </button>
            <a href="{{ route('sped.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Voltar
            </a>
        </div>
    </form>

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
                btn.classList.toggle('hover:text-gray-700', !ativa);
            });
        }

        // Campo obrigatório em aba escondida: o navegador não consegue mostrar o aviso,
        // então abre a aba do campo antes de reclamar
        document.querySelectorAll('.tab-conteudo [required]').forEach(campo => {
            campo.addEventListener('invalid', () => {
                const aba = campo.closest('.tab-conteudo');
                if (aba && aba.classList.contains('hidden')) {
                    mudarAba(aba.id.replace('aba-', ''));
                }
            });
        });

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