@php
    $ehEdicao = $servico->exists;
    $inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1';
    $erroCls  = 'text-red-600 text-xs mt-1';
    $opcional = '<span class="text-gray-400 font-normal">(opcional)</span>';
@endphp

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 max-w-3xl">
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="flex flex-col gap-6 max-w-3xl">

    {{-- ===================== Dados do serviço ===================== --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Dados do serviço</h2>

        <div class="space-y-5">
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Código</label>
                    <input type="text" disabled value="{{ $ehEdicao ? $servico->codigo : 'Gerado ao salvar' }}"
                           class="w-full border border-gray-200 bg-gray-50 text-gray-500 font-mono rounded-lg px-3 py-2.5 text-sm">
                </div>
                <div class="col-span-3">
                    <label class="{{ $labelCls }}">Nome do serviço <span class="text-red-500">*</span></label>
                    <input type="text" name="nome" maxlength="150" required autocomplete="off"
                           placeholder="Ex.: Manutenção de notebook"
                           value="{{ old('nome', $servico->nome) }}" class="{{ $inputCls }}">
                    @error('nome') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $labelCls }}">Descrição padrão na nota {!! $opcional !!}</label>
                <textarea name="descricao" rows="3" maxlength="2000"
                          placeholder="Texto sugerido na discriminação do serviço ao emitir a nota..."
                          class="{{ $inputCls }}">{{ old('descricao', $servico->descricao) }}</textarea>
                @error('descricao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>

            <div class="w-48">
                <label class="{{ $labelCls }}">Valor padrão (R$) {!! $opcional !!}</label>
                <input type="number" name="valor_padrao" step="0.01" min="0"
                       value="{{ old('valor_padrao', number_format((float) $servico->valor_padrao, 2, '.', '')) }}" class="{{ $inputCls }}">
                @error('valor_padrao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ===================== Dados fiscais (NFS-e) ===================== --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Dados fiscais (NFS-e)</h2>
        <p class="text-xs text-gray-500 mb-4">
            Usados na DPS do padrão nacional. Confirme os códigos com seu contador; a alíquota e o código municipal dependem do município e do regime da empresa.
        </p>

        <div class="space-y-5">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="{{ $labelCls }}">Código de tributação nacional <span class="text-red-500">*</span></label>
                    <input type="text" name="codigo_tributacao_nacional" maxlength="8" inputmode="numeric" required autocomplete="off"
                           placeholder="00.00.00"
                           value="{{ old('codigo_tributacao_nacional', $servico->codigoTributacaoFormatado()) }}" class="{{ $inputCls }} font-mono">
                    <p class="text-xs text-gray-400 mt-1">6 dígitos: item e subitem da LC 116 + desdobro. Pode digitar com ou sem pontos.</p>
                    @error('codigo_tributacao_nacional') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Código de tributação municipal {!! $opcional !!}</label>
                    <input type="text" name="codigo_tributacao_municipal" maxlength="20" autocomplete="off"
                           value="{{ old('codigo_tributacao_municipal', $servico->codigo_tributacao_municipal) }}" class="{{ $inputCls }} font-mono">
                    <p class="text-xs text-gray-400 mt-1">Só se o município exigir.</p>
                    @error('codigo_tributacao_municipal') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">CNAE {!! $opcional !!}</label>
                    <input type="text" name="cnae" maxlength="9" inputmode="numeric" autocomplete="off"
                           placeholder="0000-0/00"
                           value="{{ old('cnae', $servico->cnaeFormatado()) }}" class="{{ $inputCls }} font-mono">
                    @error('cnae') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="{{ $labelCls }}">Tributação do ISSQN <span class="text-red-500">*</span></label>
                    <select name="tributacao_issqn" class="{{ $inputCls }}">
                        @foreach (\App\Models\Servico::TRIBUTACAO_ISSQN as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected((int) old('tributacao_issqn', $servico->tributacao_issqn ?? 1) === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('tributacao_issqn') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelCls }}">Alíquota de ISS (%) {!! $opcional !!}</label>
                    <input type="number" name="aliquota_iss" step="0.01" min="0" max="5"
                           value="{{ old('aliquota_iss', $servico->aliquota_iss !== null ? number_format((float) $servico->aliquota_iss, 2, '.', '') : '') }}" class="{{ $inputCls }}">
                    @error('aliquota_iss') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
            Salvar
        </button>
        <a href="{{ route('servicos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
            Cancelar
        </a>
    </div>
</div>
