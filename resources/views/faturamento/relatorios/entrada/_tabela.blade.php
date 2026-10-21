@php
    $qtd = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 3, ',', '.');
    $dinheiro = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    // Valor unitário pode ter até 4 casas: mostra 4 só quando precisa
    $unitario = fn ($v) => 'R$ ' . number_format((float) $v, abs(round((float) $v, 2) - (float) $v) < 0.00001 ? 2 : 4, ',', '.');
    $data = fn ($v, $formato = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($formato) : '—';
    $documento = function ($v) {
        $d = preg_replace('/\D/', '', (string) $v);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => $v ?: '—',
        };
    };

    $colunas = 14;
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Notas finalizadas</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totais->finalizadas, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor das entradas</p>
        <p class="text-lg font-semibold text-gray-800">{{ $dinheiro($totais->valor_finalizado) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Rascunhos</p>
        <p class="text-lg font-semibold text-amber-600">{{ number_format($totais->rascunhos, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
        <p class="text-xs uppercase tracking-wide text-gray-500">Valor em rascunho</p>
        <p class="text-lg font-semibold text-amber-600">{{ $dinheiro($totais->valor_rascunho) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-700 rounded-lg font-medium text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Nota</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Entrada</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Emissão</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Fornecedor</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">CPF/CNPJ</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Operação</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Origem</th>
                    <th class="px-4 py-3 text-left font-medium text-amber-50">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Produtos</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Frete</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Outras</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Desconto</th>
                    <th class="px-4 py-3 text-right font-medium text-amber-50">Total</th>
                    <th class="px-4 py-3 text-center font-medium text-amber-50">Itens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($entradas as $entrada)
                    @php
                        $finalizada = $entrada->status === 'finalizada';
                        $tituloNota = "Nota nº {$entrada->numero} / série {$entrada->serie}";
                    @endphp
                    <tr class="hover:bg-gray-200 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <a href="{{ route('entradas-nota.edit', $entrada) }}" class="hover:text-blue-600 hover:underline">
                                {{ $entrada->numero }}<span class="text-xs font-normal text-gray-500"> / série {{ $entrada->serie }}</span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($entrada->data_entrada) }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $data($entrada->data_emissao) }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $entrada->fornecedor->nome ?? '' }}">{{ $entrada->fornecedor->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $documento($entrada->fornecedor->cpf_cnpj ?? null) }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $entrada->operacao->descricao ?? '' }}">{{ $entrada->operacao->descricao ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $entrada->tipo_entrada === 'xml' ? 'XML' : 'Digitada' }}</td>
                        <td class="px-4 py-3">
                            @if ($finalizada)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-green-50 text-green-700"
                                      title="Finalizada em {{ $data($entrada->finalizada_em, 'd/m/Y H:i') }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Finalizada
                                </span>
                            @elseif ($entrada->status === 'rascunho')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-amber-50 text-amber-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Rascunho
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-red-50 text-red-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    {{ ucfirst($entrada->status) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($entrada->valor_produtos) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($entrada->valor_frete) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($entrada->valor_outras) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">{{ $dinheiro($entrada->valor_desconto) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800 whitespace-nowrap">{{ $dinheiro($entrada->valor_total) }}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" onclick="abrirItens(this)"
                                    data-nota="{{ $entrada->id }}"
                                    data-titulo="{{ $tituloNota }}"
                                    data-subtitulo="{{ $entrada->fornecedor->nome ?? '' }}"
                                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                                Ver itens
                                <span class="bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5 leading-none">{{ $entrada->itens->count() }}</span>
                            </button>

                            {{-- Conteúdo do modal desta nota: não aparece na tela, só quando o botão é clicado --}}
                            <template id="itens-nota-{{ $entrada->id }}">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-left">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Fornecedor</p>
                                        <p class="text-sm font-medium text-gray-800 truncate" title="{{ $entrada->fornecedor->nome ?? '' }}">{{ $entrada->fornecedor->nome ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $documento($entrada->fornecedor->cpf_cnpj ?? null) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Entrada</p>
                                        <p class="text-sm font-medium text-gray-800">{{ $data($entrada->data_entrada) }}</p>
                                        <p class="text-xs text-gray-500">Emissão {{ $data($entrada->data_emissao) }}</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Situação</p>
                                        <p class="text-sm font-medium {{ $finalizada ? 'text-green-700' : 'text-amber-600' }}">{{ $finalizada ? 'Finalizada' : ucfirst($entrada->status) }}</p>
                                        @if ($finalizada)
                                            <p class="text-xs text-gray-500">{{ $data($entrada->finalizada_em, 'd/m/Y H:i') }}</p>
                                        @endif
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">Total da nota</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ $dinheiro($entrada->valor_total) }}</p>
                                        @if ($entrada->valor_total_xml !== null && abs((float) $entrada->valor_total_xml - (float) $entrada->valor_total) >= 0.01)
                                            <p class="text-xs text-amber-600">XML: {{ $dinheiro($entrada->valor_total_xml) }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <thead class="bg-gray-700 text-xs uppercase tracking-wide">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">#</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Código</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Cód. forn.</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Descrição</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Und</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Quantidade</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Un./caixa</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Qtd. estoque</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Valor unit.</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Desconto</th>
                                                    <th class="px-3 py-2.5 text-right font-medium text-amber-50">Total</th>
                                                    <th class="px-3 py-2.5 text-left font-medium text-amber-50">Lote / validade</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @forelse ($entrada->itens as $indice => $item)
                                                    @php $porCaixa = max((int) $item->qtd_por_caixa, 1); @endphp
                                                    <tr class="text-gray-700">
                                                        <td class="px-3 py-2.5 text-gray-400">{{ $indice + 1 }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->produto->codigo_interno ?? '—' }}</td>
                                                        <td class="px-3 py-2.5 font-mono text-xs">{{ $item->codigo_fornecedor ?: '—' }}</td>
                                                        <td class="px-3 py-2.5 font-medium">
                                                            {{ $item->descricao ?: ($item->produto->nome ?? '—') }}
                                                            @if ($item->variante)
                                                                <span class="text-xs font-normal text-gray-500">({{ $item->variante->rotulo() }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2.5">{{ $item->unidade ?: ($item->produto->unidade_comercial ?? '') }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $qtd($item->quantidade) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $porCaixa > 1 ? $porCaixa : '—' }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $qtd($item->quantidade_estoque) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $unitario($item->valor_unitario) }}</td>
                                                        <td class="px-3 py-2.5 text-right">{{ $dinheiro($item->valor_desconto) }}</td>
                                                        <td class="px-3 py-2.5 text-right font-medium">{{ $dinheiro($item->valor_total) }}</td>
                                                        <td class="px-3 py-2.5 text-xs text-gray-500">
                                                            @if ($item->lote || $item->validade)
                                                                {{ $item->lote ?: '—' }}{{ $item->validade ? ' · val. ' . $data($item->validade) : '' }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="12" class="px-3 py-6 text-center text-gray-400">Nota sem itens.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 mt-4 text-sm text-gray-600">
                                    <span>Produtos: <strong class="text-gray-800">{{ $dinheiro($entrada->valor_produtos) }}</strong></span>
                                    <span>Frete: <strong class="text-gray-800">{{ $dinheiro($entrada->valor_frete) }}</strong></span>
                                    <span>Outras: <strong class="text-gray-800">{{ $dinheiro($entrada->valor_outras) }}</strong></span>
                                    <span>Desconto: <strong class="text-gray-800">{{ $dinheiro($entrada->valor_desconto) }}</strong></span>
                                    <span>Total: <strong class="text-gray-800">{{ $dinheiro($entrada->valor_total) }}</strong></span>
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colunas }}" class="px-4 py-10 text-center text-gray-400 text-sm">Nenhuma nota encontrada no período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $entradas->links() }}
</div>
