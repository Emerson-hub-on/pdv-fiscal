{{--
    Uso no formulário de produto:

    <x-cest-select
        name="cest_id"
        :selected-id="$produto->cest_id"
        :selected-label="$produto->cest?->codigo_formatado . ' — ' . $produto->cest?->descricao"
    />
--}}
@props(['name' => 'cest_id', 'selectedId' => null, 'selectedLabel' => null])

<div
    x-data="{
        open: false,
        query: @js($selectedLabel ?? ''),
        selectedId: @js($selectedId),
        results: [],
        loading: false,

        buscar() {
            if (this.query.length < 2) { this.results = []; return; }
            this.loading = true;
            fetch('{{ route('cests.buscar') }}?q=' + encodeURIComponent(this.query))
                .then(r => r.json())
                .then(data => { this.results = data; this.loading = false; })
                .catch(() => { this.loading = false; });
        },

        selecionar(item) {
            this.selectedId = item.id;
            this.query = item.label;
            this.open = false;
            this.results = [];
        },

        limpar() {
            this.selectedId = null;
            this.query = '';
            this.results = [];
        }
    }"
    class="relative"
>
    <label class="block text-sm font-medium text-gray-700 mb-1">CEST</label>

    <div class="relative">
        <input
            type="text"
            x-model="query"
            @input.debounce.300ms="buscar()"
            @focus="open = true"
            @click.outside="open = false"
            placeholder="Buscar por código ou descrição..."
            class="w-full border rounded-md px-3 py-2 pr-8"
            autocomplete="off"
        />
        <button
            type="button"
            x-show="query.length > 0"
            @click="limpar()"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >&times;</button>
    </div>

    <input type="hidden" name="{{ $name }}" :value="selectedId">

    <div
        x-show="open && (results.length > 0 || loading)"
        x-cloak
        class="absolute z-20 mt-1 w-full bg-white border rounded-md shadow-lg max-h-64 overflow-y-auto"
    >
        <div x-show="loading" class="px-3 py-2 text-sm text-gray-500">Buscando...</div>
        <template x-for="item in results" :key="item.id">
            <button
                type="button"
                @click="selecionar(item)"
                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 border-b last:border-b-0"
                x-text="item.label"
            ></button>
        </template>
    </div>

    <p class="text-xs text-gray-500 mt-1">
        Deixe em branco se o produto não é sujeito a Substituição Tributária.
    </p>
</div>