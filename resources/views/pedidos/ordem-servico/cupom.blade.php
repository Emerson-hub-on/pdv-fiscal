@php
    $cnpjNum = preg_replace('/\D/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpjNum) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjNum)
        : $cnpjNum;

    $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;

    $enderecoLinha1 = trim(($empresa->logradouro ?? '') . ', ' . ($empresa->numero ?? '') . ($empresa->complemento ? ' ' . $empresa->complemento : ''), ', ');
    $enderecoLinha2 = collect([
        $empresa->bairro,
        $empresa->municipio ? $empresa->municipio . '/' . $empresa->uf : null,
    ])->filter()->implode(' - ');

    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd   = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $cancelada = $ordem->status === 'cancelada';
    $desconto  = (float) $ordem->desconto;
    $equipamentoTexto = trim($ordem->equipamento . ' ' . ($ordem->marca ?? '') . ' ' . ($ordem->modelo ?? ''));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupom - OS {{ $ordem->numeroExibicao() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Única regra fora do Tailwind: @page não existe como utilitário. Faz a página sair com 80 mm de largura e altura do conteúdo. --}}
    <style>
        @media print { @page { size: 80mm auto; margin: 0; } }
    </style>
</head>
<body class="bg-gray-200 print:bg-white text-black">

<div class="print:hidden flex flex-col items-center gap-2 py-4">
    <div class="flex gap-3">
        <button type="button" onclick="window.print()"
                class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir cupom (80 mm)</button>
        <a href="{{ route('ordens-servico.index') }}"
           class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</a>
    </div>
    <p class="text-xs text-gray-500 text-center max-w-xs">
        Na janela de impressão: escolha a POS-80, papel 80 mm, margens "Nenhuma", escala 100% e desmarque "Cabeçalhos e rodapés".
    </p>
</div>

{{-- Papel: 80 mm de largura, com folga lateral de 4 mm --}}
<main class="w-[80mm] mx-auto bg-white shadow print:shadow-none px-[4mm] py-[4mm] print:py-[2mm] font-mono text-[11px] leading-tight">

    {{-- Empresa --}}
    <header class="text-center">
        <p class="text-[13px] font-bold uppercase break-words">{{ $nomeEmpresa }}</p>
        @if ($empresa->nome_fantasia && $empresa->razao_social)
            <p class="break-words">{{ $empresa->razao_social }}</p>
        @endif
        <p>CNPJ: {{ $cnpjFmt ?: '—' }}@if ($empresa->ie) &nbsp;IE: {{ $empresa->ie }}@endif</p>
        @if ($enderecoLinha1) <p class="break-words">{{ $enderecoLinha1 }}</p> @endif
        @if ($enderecoLinha2) <p class="break-words">{{ $enderecoLinha2 }}</p> @endif
    </header>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Identificação do documento --}}
    <section class="text-center">
        <p class="text-[12px] font-bold">ORDEM DE SERVIÇO Nº {{ $ordem->numeroExibicao() }}</p>
        <p class="font-bold">*** NÃO É DOCUMENTO FISCAL ***</p>
        @if ($ordem->prioridade === 'urgente')
            <p class="font-bold">*** URGENTE ***</p>
        @endif
        @if ($cancelada)
            <p class="font-bold">*** OS CANCELADA ***</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    <section>
        <p>Abertura: {{ $ordem->data_abertura->format('d/m/Y') }}</p>
        @if ($ordem->previsao_entrega)
            <p>Previsão de entrega: {{ $ordem->previsao_entrega->format('d/m/Y') }}</p>
        @endif
        <p>Status: {{ $ordem->statusTexto() }}</p>
        <p class="break-words">Atendente: {{ $ordem->operador?->name ?? '—' }}</p>
        @if ($ordem->tecnico)
            <p class="break-words">Técnico: {{ $ordem->tecnico->name }}</p>
        @endif
        <p class="break-words">Cliente: {{ $ordem->cliente?->nome ?? '—' }}</p>
        @if ($ordem->cliente?->cpf_cnpj_formatado)
            <p>CPF/CNPJ: {{ $ordem->cliente->cpf_cnpj_formatado }}</p>
        @endif
        @if ($ordem->cliente?->telefone)
            <p>Tel.: {{ $ordem->cliente->telefone }}</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    <section>
        <p class="font-bold">EQUIPAMENTO</p>
        <p class="break-words">{{ $equipamentoTexto }}</p>
        @if ($ordem->numero_serie)
            <p class="break-words">Série/IMEI: {{ $ordem->numero_serie }}</p>
        @endif
        @if ($ordem->acessorios)
            <p class="break-words">Acessórios: {{ $ordem->acessorios }}</p>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    <section>
        <p class="font-bold">DEFEITO RELATADO</p>
        <p class="whitespace-pre-line break-words">{{ $ordem->defeito_relatado }}</p>
    </section>

    @if ($ordem->condicao_entrada)
        <hr class="border-t border-dashed border-black my-1.5">
        <section>
            <p class="font-bold">CONDIÇÃO NA ENTRADA</p>
            <p class="whitespace-pre-line break-words">{{ $ordem->condicao_entrada }}</p>
        </section>
    @endif

    @if ($ordem->itens->isNotEmpty())
        <hr class="border-t border-dashed border-black my-1.5">

        {{-- Peças (formato de cupom: descrição e, na linha de baixo, qtd x valor e total) --}}
        <section>
            <p class="font-bold">PEÇAS</p>
            <div class="flex justify-between font-bold">
                <span>QTD UN x VL UNIT</span>
                <span>VL ITEM (R$)</span>
            </div>

            <hr class="border-t border-dashed border-black my-1">

            @foreach ($ordem->itens as $item)
                <div class="mb-1">
                    <p class="break-words">
                        {{ str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT) }}
                        {{ $item->codigo_barras ?: '—' }}
                        {{ $item->descricao }}
                    </p>
                    <div class="flex justify-between">
                        <span>{{ $qtd($item->quantidade) }} {{ $item->unidade }} x {{ $moeda($item->valor_unitario) }}</span>
                        <span>{{ $moeda($item->total()) }}</span>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Totais --}}
    <section>
        <div class="flex justify-between">
            <span>PEÇAS</span>
            <span>{{ $moeda($ordem->valorPecas()) }}</span>
        </div>
        <div class="flex justify-between">
            <span>MÃO DE OBRA</span>
            <span>{{ $moeda($ordem->valor_servico) }}</span>
        </div>
        @if ($desconto > 0)
            <div class="flex justify-between">
                <span>DESCONTO</span>
                <span>-{{ $moeda($desconto) }}</span>
            </div>
        @endif
        <div class="flex justify-between text-[14px] font-bold mt-0.5">
            <span>TOTAL R$</span>
            <span>{{ $moeda($ordem->valor_total) }}</span>
        </div>
        @if ($ordem->formaPagamento)
            <div class="flex justify-between mt-0.5">
                <span>FORMA DE PAGAMENTO</span>
                <span class="text-right break-words">{{ $ordem->formaPagamento->descricao }}</span>
            </div>
        @endif
        @if ($ordem->garantia_dias)
            <div class="flex justify-between mt-0.5">
                <span>GARANTIA</span>
                <span>{{ $ordem->garantia_dias }} dias</span>
            </div>
        @endif
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    {{-- Assinatura do cliente (via do cliente: comprovante de entrada do equipamento) --}}
    <section class="text-center mt-6">
        <div class="border-t border-black pt-0.5">Assinatura do cliente</div>
    </section>

    <hr class="border-t border-dashed border-black my-1.5">

    <footer class="text-center">
        <p>Emitido em {{ now()->format('d/m/Y H:i:s') }}</p>
        <p class="font-bold mt-1">Documento sem valor fiscal.</p>
        <p>Apresente este comprovante para retirar o equipamento.</p>
    </footer>

    {{-- Folga no fim para o corte da guilhotina não pegar a última linha --}}
    <div class="h-[12mm]"></div>
</main>

@if (request()->boolean('auto'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

</body>
</html>
