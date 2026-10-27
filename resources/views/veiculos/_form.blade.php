@php $veiculo = $veiculo ?? null; @endphp

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

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="grid grid-cols-2 gap-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Placa <span class="text-red-500">*</span></label>
            <input type="text" name="placa" maxlength="8" required placeholder="ABC1D23"
                   oninput="this.value = this.value.toUpperCase()"
                   value="{{ old('placa', $veiculo->placa_formatada ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">UF da placa <span class="text-red-500">*</span></label>
            <select name="uf" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Transportador::UFS as $uf)
                    <option value="{{ $uf }}" {{ old('uf', $veiculo->uf ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                RNTRC <span class="text-xs text-gray-400 font-normal">(8 dígitos, ANTT)</span>
            </label>
            <input type="text" name="rntrc" maxlength="8" inputmode="numeric"
                   value="{{ old('rntrc', $veiculo->rntrc ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Transportadora <span class="text-xs text-gray-400 font-normal">(opcional)</span>
            </label>
            <select name="transportador_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo do veículo</label>
            <select name="tipo"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                <option value="tracao" {{ old('tipo', $veiculo->tipo ?? 'tracao') === 'tracao' ? 'selected' : '' }}>Tração (cavalo, caminhão, utilitário)</option>
                <option value="reboque" {{ old('tipo', $veiculo->tipo ?? 'tracao') === 'reboque' ? 'selected' : '' }}>Reboque / semirreboque</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de rodado</label>
            <select name="tipo_rodado"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Veiculo::TIPOS_RODADO as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_rodado', $veiculo->tipo_rodado ?? '') === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de carroceria</label>
            <select name="tipo_carroceria"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition bg-white">
                <option value="">Selecione...</option>
                @foreach (\App\Models\Veiculo::TIPOS_CARROCERIA as $codigo => $rotulo)
                    <option value="{{ $codigo }}" {{ (string) old('tipo_carroceria', $veiculo->tipo_carroceria ?? '') === (string) $codigo ? 'selected' : '' }}>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tara <span class="text-xs text-gray-400 font-normal">(kg)</span>
            </label>
            <input type="number" name="tara_kg" min="0" max="999999" step="1" inputmode="numeric"
                   value="{{ old('tara_kg', $veiculo->tara_kg ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Capacidade <span class="text-xs text-gray-400 font-normal">(kg)</span>
            </label>
            <input type="number" name="capacidade_kg" min="0" max="999999" step="1" inputmode="numeric"
                   value="{{ old('capacidade_kg', $veiculo->capacidade_kg ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Capacidade <span class="text-xs text-gray-400 font-normal">(m³, opcional)</span>
            </label>
            <input type="number" name="capacidade_m3" min="0" max="999" step="1" inputmode="numeric"
                   value="{{ old('capacidade_m3', $veiculo->capacidade_m3 ?? '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
        </div>

    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
        Salvar veículo
    </button>
    <a href="{{ route('veiculos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg text-sm font-medium transition">
        Cancelar
    </a>
</div>