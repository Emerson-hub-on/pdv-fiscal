@extends('layouts.app')

@section('titulo', 'Nota Fiscal #' . $notaFiscal->id)
@section('body-class', 'sidebar-oculta')

@section('conteudo')

@include('notasfiscais._recalculo_flash')


<div class="flex flex-col gap-6 max-w-4xl">

    <div class="bg-white rounded-lg shadow p-6 flex justify-between items-start">
        <div>
            <h1 class="text-lg font-semibold">Nota Fiscal {{ $notaFiscal->numero ? '#' . $notaFiscal->numero : '(rascunho)' }}</h1>
            <p class="text-sm text-gray-500">Cliente: {{ $notaFiscal->cliente->nome }}</p>
            <p class="text-sm text-gray-500">Status:
                <span class="font-medium">{{ ucfirst($notaFiscal->status) }}</span>
            </p>
            <p class="text-sm text-gray-500">CFOP: {{ $notaFiscal->cfopSaida->codigo }} - {{ $notaFiscal->cfopSaida->descricao }}</p>
        </div>

        <div class="relative">
            <button type="button" onclick="toggleAcoesNota()"
                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-50 flex items-center gap-2">
                Ações
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="dropdown-acoes-nota" class="hidden absolute right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-48 z-50 text-left">
                <a href="{{ route('notasfiscais.previsualizar', $notaFiscal) }}" target="_blank"
                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Pré-visualizar PDF</a>

                    @if ($notaFiscal->status === 'rascunho')
                        <a href="{{ route('notasfiscais.edit', $notaFiscal) }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>

                        <form method="POST" action="{{ route('notasfiscais.recalcular', $notaFiscal) }}"
                            onsubmit="return confirm('Isso vai atualizar NCM, CEST, tributação, PIS/COFINS e IPI de cada item com base no cadastro atual dos produtos. Quantidade, valor e desconto não serão alterados. Confirma?')">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Recalcular</button>
                        </form>

                        <div class="border-t border-gray-100 my-1"></div>

                        <form method="POST" action="{{ route('notasfiscais.emitir', $notaFiscal) }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 font-medium hover:bg-green-50">Emitir NF-e</button>
                        </form>

                        <div class="border-t border-gray-100 my-1"></div>

                        <form method="POST" action="{{ route('notasfiscais.destroy', $notaFiscal) }}"
                            onsubmit="return confirm('Excluir esta nota em rascunho? Esta ação não pode ser desfeita.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Excluir rascunho</button>
                        </form>
                    @elseif ($notaFiscal->status === 'emitida')
                    <a href="{{ route('notasfiscais.xml', $notaFiscal) }}"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Baixar XML</a>

                    <div class="border-t border-gray-100 my-1"></div>

                    <a href="{{ route('notasfiscais.cancelar-form', $notaFiscal) }}"
                       class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Cancelar</a>
                @endif
            </div>
        </div>
    </div>

    @error('emissao')
        <div class="bg-red-100 text-red-800 border border-red-300 rounded px-4 py-3">{{ $message }}</div>
    @enderror

    @php
        // Mesma lógica de cálculo usada no _form (itensIniciais) e no
        // previsualizar — aqui aplicada em cima dos dados já persistidos
        // no item (snapshot de tributação/IPI no momento da nota).
        $cstsComBaseCalculo = ['00', '10', '20', '70', '90'];

        $linhasItens = $notaFiscal->itens->map(function ($item) use ($cstsComBaseCalculo) {
            $subtotalBruto = (float) $item->valor_unitario * (float) $item->quantidade;

            $trib = $item->tributacao;
            $bcIcms = 0; $valorIcms = 0; $aliquotaIcms = 0;

            if ($trib && in_array($trib->cst_icms, $cstsComBaseCalculo, true)) {
                $bcIcms = $subtotalBruto;
                $aliquotaIcms = (float) $trib->aliquota_icms;
                $valorIcms = $bcIcms * $aliquotaIcms / 100;
            }

            // IPI — só CST 50 (Saída Tributada) tem valor de fato.
            $ipi = $item->ipi;
            $valorIpi = 0; $aliquotaIpi = 0;

            if ($ipi && $ipi->codigo === '50' && $ipi->aliquota) {
                $aliquotaIpi = (float) $ipi->aliquota;
                $valorIpi = $subtotalBruto * $aliquotaIpi / 100;
            }

            $descontoPercentual = $subtotalBruto > 0
                ? round(((float) $item->valor_desconto / $subtotalBruto) * 100, 2)
                : 0;

            return [
                'codigo'              => $item->produto->codigo_interno,
                'codigo_barras'       => $item->produto->codigo_barras,
                'descricao'           => $item->descricao ?? $item->produto->nome,
                'quantidade'          => $item->quantidade_formatada,
                'valor_unitario'      => $item->valor_unitario,
                'valor_total'         => $item->valor_total,
                'valor_desconto'      => $item->valor_desconto,
                'desconto_percentual' => $descontoPercentual,
                'bc_icms'             => $bcIcms,
                'valor_icms'          => $valorIcms,
                'aliquota_icms'       => $aliquotaIcms,
                'valor_ipi'           => $valorIpi,
                'aliquota_ipi'        => $aliquotaIpi,
            ];
        });
    @endphp

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-700 text-amber-50 text-xs uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Cód. Barras</th>
                        <th class="text-left px-3 py-2">Descrição</th>
                        <th class="text-right px-3 py-2">Qtd</th>
                        <th class="text-right px-3 py-2">Vl Unit</th>
                        <th class="text-right px-3 py-2">Vl Total</th>
                        <th class="text-right px-3 py-2">Desconto</th>
                        <th class="text-right px-3 py-2">Desconto %</th>
                        <th class="text-right px-3 py-2">BC ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. ICMS</th>
                        <th class="text-right px-3 py-2">% ICMS</th>
                        <th class="text-right px-3 py-2">Vlr. IPI</th>
                        <th class="text-right px-3 py-2">% IPI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($linhasItens as $linha)
                        <tr>
                            <td class="px-3 py-2">{{ $linha['codigo'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $linha['codigo_barras'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $linha['descricao'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $linha['quantidade'] }}</td>
                            <td class="px-3 py-2 text-right">R$ {{ number_format($linha['valor_unitario'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-medium">R$ {{ number_format($linha['valor_total'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">R$ {{ number_format($linha['valor_desconto'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($linha['desconto_percentual'], 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">R$ {{ number_format($linha['bc_icms'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">R$ {{ number_format($linha['valor_icms'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($linha['aliquota_icms'], 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">R$ {{ number_format($linha['valor_ipi'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($linha['aliquota_ipi'], 2, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-700 text-amber-50 font-medium">
                    <tr>
                        <td colspan="12" class="px-3 py-2 text-right">Total</td>
                        <td class="px-3 py-2 text-right">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleAcoesNota() {
    document.getElementById('dropdown-acoes-nota').classList.toggle('hidden');
}
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('dropdown-acoes-nota');
    const container = dropdown?.closest('.relative');
    if (dropdown && !dropdown.classList.contains('hidden') && container && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
@endsection