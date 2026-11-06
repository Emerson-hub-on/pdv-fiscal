@php extract(config('estilo.formulario')); @endphp
@php $veiculo = $veiculo ?? null; @endphp

<div class="{{ $cartaoCls }} p-6">
    <div class="grid grid-cols-2 gap-5">

        <div>
            <label class="{{ $labelCls }}">Placa <span class="text-red-500">*</span></label>
            <input type="text" name="placa" maxlength="8" required placeholder="ABC1D23"
                   oninput="this.value = this.value.toUpperCase()"
                   value="{{ old('placa', $veiculo->placa_formatada ?? '') }}"
                   class="{{ $inputCls }} font-mono">
        </div>

        <div>
            <label class="{{ $labelCls }}">UF da placa <span class="text-red-500">*</span></label>
            <select name="uf" required
                    class="{{ $inputCls }}">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Transportador::UFS as $uf)
                    <option value="{{ $uf }}" {{ old('uf', $veiculo->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                RNTRC <span class="{{ $opcionalCls }}">(8 dígitos, ANTT)</span>
            </label>
            <input type="text" name="rntrc" maxlength="8" inputmode="numeric"
                   value="{{ old('rntrc', $veiculo->rntrc ?? '') }}"
                   class="{{ $inputCls }} font-mono">
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Transportadora <span class="{{ $opcionalCls }}">(opcional)</span>
            </label>
            <select name="transportador_id"
                    class="{{ $inputCls }}">
                <option value="">Sem transportadora (transporte próprio)</option>
                @foreach ($transportadores as $t)
                    <option value="{{ $t->id }}" {{ (string) old('transportador_id', $veiculo->transportador_id ?? '') === (string) $t->id ? 'selected' : '' }}>
                        {{ $t->nome }}
                    </option>
                @endforeach
            </select>
        </div>


        <div class="col-span-2 border-t border-gray-100 pt-5 -mb-1">
            <h2 class="text-sm font-semibold text-gray-800">Dados para o MDF-e</h2>
            <p class="text-xs text-gray-400 mt-0.5">Opcionais no cadastro, mas obrigatórios para usar o veículo em um MDF-e.</p>
        </div>

        <div>
            <label class="{{ $labelCls }}">Tipo do veículo</label>
            <select name="tipo"
                    class="{{ $inputCls }}">
                <option value="tracao" {{ old('tipo', $veiculo->tipo ?? 'tracao') === 'tracao' ? 'selected' : '' }}>Tração (cavalo, caminhão, utilitário)</option>
                <option value="reboque" {{ old('tipo', $veiculo->tipo ?? 'tracao') === 'reboque' ? 'selected' : '' }}>Reboque / semirreboque</option>
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">Tipo de rodado</label>
            <select name="tipo_rodado"
                    class="{{ $inputCls }}">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Veiculo::TIPOS_RODADO as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_rodado', $veiculo->tipo_rodado ?? '') === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">Tipo de carroceria</label>
            <select name="tipo_carroceria"
                    class="{{ $inputCls }}">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Veiculo::TIPOS_CARROCERIA as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_carroceria', $veiculo->tipo_carroceria ?? '') === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Tara <span class="{{ $opcionalCls }}">(kg)</span>
            </label>
            <input type="number" name="tara_kg" min="0" max="999999" step="1" inputmode="numeric"
                   value="{{ old('tara_kg', $veiculo->tara_kg ?? '') }}"
                   class="{{ $inputCls }}">
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Capacidade <span class="{{ $opcionalCls }}">(kg)</span>
            </label>
            <input type="number" name="capacidade_kg" min="0" max="999999" step="1" inputmode="numeric"
                   value="{{ old('capacidade_kg', $veiculo->capacidade_kg ?? '') }}"
                   class="{{ $inputCls }}">
        </div>

        <div>
            <label class="{{ $labelCls }}">
                Capacidade <span class="{{ $opcionalCls }}">(m³, opcional)</span>
            </label>
            <input type="number" name="capacidade_m3" min="0" max="999" step="1" inputmode="numeric"
                   value="{{ old('capacidade_m3', $veiculo->capacidade_m3 ?? '') }}"
                   class="{{ $inputCls }}">
        </div>

    </div>
</div>

<div class="mt-6 flex flex-row-reverse justify-start gap-3">
    <button type="submit" class="{{ $botaoPrimarioCls }}">
        Salvar veículo
    </button>
    <a href="{{ route('veiculos.index') }}" class="{{ $botaoCancelarCls }}">
        Cancelar
    </a>
</div>