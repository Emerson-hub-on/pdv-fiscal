@php extract(config('estilo.formulario')); @endphp
@php
    $credenciadora = $credenciadora ?? null;
    $campo = $inputCls;
    $meiosMarcados = old('meios', $credenciadora?->meios->pluck('meio_pagamento')->all() ?? []);
@endphp

<div class="space-y-6">
    <div class="{{ $cartaoCls }} p-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-4">Instituição</h2>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="{{ $labelCls }}">CNPJ <span class="text-red-500">*</span></label>
                <input type="text" name="cnpj" id="campo-cnpj" maxlength="18" required inputmode="numeric" placeholder="00.000.000/0000-00"
                       value="{{ old('cnpj', $credenciadora->cnpj_formatado ?? '') }}" class="{{ $campo }} font-mono">
                <p id="aviso-cnpj" class="hidden text-xs mt-1"></p>
            </div>

            <div>
                <label class="{{ $labelCls }}">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="nome" maxlength="100" required placeholder="Ex.: Itaú Unibanco, Cielo, Stone..."
                       value="{{ old('nome', $credenciadora->nome ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">CEP <span class="text-red-500">*</span></label>
                <input type="text" name="cep" maxlength="9" required value="{{ old('cep', $credenciadora->cep ?? '') }}" class="{{ $campo }} font-mono">
            </div>

            <div>
                <label class="{{ $labelCls }}">UF <span class="text-red-500">*</span></label>
                <select name="uf" required class="{{ $campo }} bg-white">
                    <option value="">Selecione...</option>
                    @foreach (\App\Models\Transportador::UFS as $uf)
                        <option value="{{ $uf }}" {{ old('uf', $credenciadora->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-2">
                <label class="{{ $labelCls }}">Logradouro <span class="text-red-500">*</span></label>
                <input type="text" name="logradouro" maxlength="60" required value="{{ old('logradouro', $credenciadora->logradouro ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Número</label>
                <input type="text" name="numero" maxlength="10" value="{{ old('numero', $credenciadora->numero ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Complemento</label>
                <input type="text" name="complemento" maxlength="60" value="{{ old('complemento', $credenciadora->complemento ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Bairro <span class="text-red-500">*</span></label>
                <input type="text" name="bairro" maxlength="60" required value="{{ old('bairro', $credenciadora->bairro ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Município <span class="text-red-500">*</span></label>
                <input type="text" name="municipio" maxlength="100" required value="{{ old('municipio', $credenciadora->municipio ?? '') }}" class="{{ $campo }}">
            </div>

            <div>
                <label class="{{ $labelCls }}">Código IBGE do município <span class="{{ $opcionalCls }}">(7 dígitos)</span></label>
                <input type="text" name="cod_municipio" maxlength="7" inputmode="numeric" value="{{ old('cod_municipio', $credenciadora->cod_municipio ?? '') }}" class="{{ $campo }} font-mono">
            </div>
        </div>
    </div>

    <div class="{{ $cartaoCls }} p-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Pagamentos recebidos por esta instituição</h2>
        <p class="text-xs text-gray-400 mb-4">
            Marque os meios que caem na conta desta instituição. Uma instituição pode receber vários meios, mas cada meio só pode ter uma instituição.
            No caixa, o PIX usa a instituição marcada em qualquer um dos códigos de PIX.
        </p>

        <div class="grid grid-cols-2 gap-3">
            @foreach ($meiosDisponiveis as $codigo => $rotulo)
                @php $ocupadoPor = $meiosOcupados[$codigo] ?? null; @endphp
                <label class="flex items-start gap-3 border border-gray-200 rounded-lg px-3 py-2.5 {{ $ocupadoPor ? 'bg-gray-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50' }}">
                    <input type="checkbox" name="meios[]" value="{{ $codigo }}"
                           {{ in_array($codigo, $meiosMarcados, true) ? 'checked' : '' }} {{ $ocupadoPor ? 'disabled' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[#10284A] focus:ring-2 focus:ring-[#10284A]/30">
                    <span>
                        <span class="block text-sm text-gray-700">{{ $codigo }} - {{ $rotulo }}</span>
                        @if ($ocupadoPor)
                            <span class="block text-xs text-gray-400">Já recebido por {{ $ocupadoPor }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        Salvar credenciadora
    </button>
    <a href="{{ route('credenciadoras.index') }}" class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>

<script>
    (() => {
        const campoCnpj = document.getElementById('campo-cnpj');
        const aviso = document.getElementById('aviso-cnpj');
        const definir = (nome, valor) => {
            const campo = document.querySelector(`[name="${nome}"]`);
            if (campo && valor) { campo.value = valor; campo.dispatchEvent(new Event('change', { bubbles: true })); }
        };

        campoCnpj.addEventListener('input', () => {
            const d = campoCnpj.value.replace(/\D/g, '').slice(0, 14);
            campoCnpj.value = d
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        });

        // Preenche nome e endereço pela consulta de CNPJ que o cadastro de clientes já usa
        campoCnpj.addEventListener('blur', async () => {
            const cnpj = campoCnpj.value.replace(/\D/g, '');
            if (cnpj.length !== 14) return;

            aviso.className = 'text-xs mt-1 text-gray-400';
            aviso.textContent = 'Consultando CNPJ...';

            try {
                const resp = await fetch(`/api/consulta-cnpj/${cnpj}`, { headers: { 'Accept': 'application/json' } });
                const dados = await resp.json();

                if (!resp.ok) {
                    aviso.className = 'text-xs mt-1 text-red-600';
                    aviso.textContent = dados.erro || 'Não foi possível consultar o CNPJ. Preencha os dados manualmente.';
                    return;
                }

                ['nome', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'cod_municipio', 'uf']
                    .forEach((c) => { if (!document.querySelector(`[name="${c}"]`).value) definir(c, dados[c]); });

                document.querySelector('[name="municipio"]').dispatchEvent(new CustomEvent('municipio-definido', { detail: { nome: dados.municipio } }));
                aviso.className = 'text-xs mt-1 text-green-600';
                aviso.textContent = 'Dados preenchidos pela consulta do CNPJ. Confira antes de salvar.';
            } catch (e) {
                aviso.className = 'text-xs mt-1 text-red-600';
                aviso.textContent = 'Não foi possível consultar o CNPJ. Preencha os dados manualmente.';
            }
        });
    })();
</script>

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
