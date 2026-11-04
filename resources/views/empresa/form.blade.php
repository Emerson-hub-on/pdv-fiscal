@extends('layouts.app')

@section('titulo', 'Dados da Empresa')

@section('conteudo')
    <h1 class="text-2xl font-bold mb-6">Dados da Empresa (Emitente)</h1>

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
                    <input type="text" name="cnpj" id="campo-cnpj-empresa" maxlength="18" value="{{ old('cnpj', $empresa->cnpj) }}" required
                           placeholder="00.000.000/0000-00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <p id="cnpj-empresa-status" class="mt-1 text-xs text-gray-500">Ao sair do campo (ou com Enter), os dados da empresa são buscados pelo CNPJ.</p>
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
                    <input type="text" name="cep" maxlength="9" value="{{ old('cep', $empresa->cep) }}" required placeholder="00000-000"
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

    {{-- Consulta de CNPJ (mesma do cadastro de clientes) --}}
    <div id="modal-consulta-cnpj-empresa" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg px-8 py-6 flex flex-col items-center gap-3">
            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-700">Buscando informações do CNPJ...</p>
        </div>
    </div>

    {{-- Município ↔ código IBGE pela tabela de municípios, e endereço pelo CEP (mesmas peças do cadastro de clientes) --}}
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
        // CNPJ: ao sair do campo (ou com Enter) busca razão social, nome fantasia e endereço.
        // Só preenche campos vazios, como no cadastro de clientes: editar a empresa não sobrescreve nada.
        (() => {
            const campoCnpj = document.getElementById('campo-cnpj-empresa');
            const status = document.getElementById('cnpj-empresa-status');
            const modal = document.getElementById('modal-consulta-cnpj-empresa');
            let ultimoConsultado = ''; // evita repetir a consulta do mesmo CNPJ seguidamente

            const preencherSeVazio = (nome, valor) => {
                const campo = document.querySelector(`#tab-emp-geral [name="${nome}"], #tab-emp-endereco [name="${nome}"]`);
                if (campo && !campo.value.trim() && valor) {
                    campo.value = valor;
                    return true;
                }
                return false;
            };

            const mensagem = (texto, cor) => {
                status.textContent = texto;
                status.className = 'mt-1 text-xs ' + cor;
            };

            async function consultar() {
                const cnpj = campoCnpj.value.replace(/\D/g, '');
                if (cnpj.length !== 14 || cnpj === ultimoConsultado) return;
                ultimoConsultado = cnpj;

                modal.classList.remove('hidden');
                try {
                    const resp = await fetch(`/api/consulta-cnpj/${cnpj}`, { headers: { Accept: 'application/json' } });
                    const dados = await resp.json().catch(() => ({}));

                    if (!resp.ok) {
                        mensagem(dados.erro || 'Não foi possível consultar o CNPJ. Preencha os dados manualmente.', 'text-amber-700');
                        return;
                    }

                    const preenchidos = [
                        preencherSeVazio('razao_social', dados.nome),
                        preencherSeVazio('nome_fantasia', dados.nome_fantasia),
                        preencherSeVazio('cep', dados.cep),
                        preencherSeVazio('logradouro', dados.logradouro),
                        preencherSeVazio('numero', dados.numero),
                        preencherSeVazio('complemento', dados.complemento),
                        preencherSeVazio('bairro', dados.bairro),
                        preencherSeVazio('municipio', dados.municipio),
                        preencherSeVazio('cod_municipio', dados.cod_municipio),
                        preencherSeVazio('uf', (dados.uf || '').toUpperCase()),
                    ].filter(Boolean).length;

                    mensagem(preenchidos
                        ? `Dados encontrados: ${preenchidos} campo(s) preenchido(s). Confira também a aba Endereço e informe a Inscrição Estadual.`
                        : 'CNPJ encontrado. Os campos já estavam preenchidos e não foram alterados.', 'text-green-700');
                } catch (e) {
                    mensagem('Erro ao consultar o CNPJ. Verifique a conexão com a internet.', 'text-red-600');
                } finally {
                    modal.classList.add('hidden');
                }
            }

            campoCnpj.addEventListener('blur', consultar);
            campoCnpj.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault(); // não envia o formulário
                    consultar();
                }
            });
        })();

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