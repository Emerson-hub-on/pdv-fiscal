@php
    $cnpjNum = preg_replace('/\D/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpjNum) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjNum)
        : $cnpjNum;

    $enderecoEmpresa = collect([
        trim(($empresa->logradouro ?? '') . ', ' . ($empresa->numero ?? ''), ', '),
        $empresa->bairro,
        $empresa->municipio ? $empresa->municipio . '/' . $empresa->uf : null,
    ])->filter()->implode(' — ');

    $moeda = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd   = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $valorPecas   = $ordem->valorPecas();
    $valorServico = (float) $ordem->valor_servico;
    $desconto     = (float) $ordem->desconto;

    $equipamentoTexto = trim($ordem->equipamento . ' ' . ($ordem->marca ?? '') . ' ' . ($ordem->modelo ?? ''));

    // Texto padrão do termo impresso. Ajuste o prazo e as condições conforme a política da sua loja
    // (vale conferir a redação com seu contador/advogado).
    $termo = 'Declaro que as informações acima conferem com o estado em que entreguei o equipamento e que autorizo a análise e o reparo descritos. '
           . 'Equipamentos não retirados em até 90 dias após o aviso de conclusão poderão ser destinados para cobrir custos do serviço. '
           . 'A garantia cobre apenas o serviço executado e as peças substituídas nesta ordem de serviço, e não cobre mau uso, quedas, oxidação ou violação do lacre.';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordem de Serviço {{ $ordem->numeroExibicao() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 print:bg-white text-gray-900">

<div class="print:hidden flex justify-center gap-3 py-4">
    <button type="button" onclick="window.print()"
            class="bg-gray-800 text-white rounded-lg px-5 py-2 text-sm hover:bg-gray-700">Imprimir / salvar PDF</button>
    <a href="{{ route('ordens-servico.index') }}"
       class="border border-gray-300 bg-white rounded-lg px-5 py-2 text-sm hover:bg-gray-50">Voltar</a>
</div>

<main class="max-w-4xl mx-auto bg-white shadow print:shadow-none p-8 mb-8 print:mb-0">

    <header class="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Ordem de Serviço</h1>
            <p class="text-sm text-gray-500">
                Nº {{ $ordem->numeroExibicao() }} — {{ $ordem->statusTexto() }}
                @if ($ordem->prioridade === 'urgente') — <span class="font-semibold text-red-600 print:text-black">URGENTE</span> @endif
            </p>
        </div>
        <dl class="text-sm text-right">
            <div><dt class="inline text-gray-500">Abertura:</dt> <dd class="inline font-medium">{{ $ordem->data_abertura->format('d/m/Y') }}</dd></div>
            @if ($ordem->previsao_entrega)
                <div><dt class="inline text-gray-500">Previsão de entrega:</dt> <dd class="inline font-medium">{{ $ordem->previsao_entrega->format('d/m/Y') }}</dd></div>
            @endif
            @if ($ordem->concluida_em)
                <div><dt class="inline text-gray-500">Concluída em:</dt> <dd class="inline font-medium">{{ $ordem->concluida_em->format('d/m/Y') }}</dd></div>
            @endif
            @if ($ordem->entregue_em)
                <div><dt class="inline text-gray-500">Entregue em:</dt> <dd class="inline font-medium">{{ $ordem->entregue_em->format('d/m/Y') }}</dd></div>
            @endif
        </dl>
    </header>

    <section class="grid grid-cols-2 gap-6 mb-6">
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Empresa</h2>
            <p class="text-sm font-medium">{{ $empresa->razao_social ?: '—' }}</p>
            <p class="text-sm text-gray-600">
                CNPJ {{ $cnpjFmt ?: '—' }}@if ($empresa->ie) · IE {{ $empresa->ie }}@endif
            </p>
            @if ($enderecoEmpresa)
                <p class="text-sm text-gray-600">{{ $enderecoEmpresa }}</p>
            @endif
        </div>

        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Cliente</h2>
            @if ($ordem->cliente)
                <p class="text-sm font-medium">{{ $ordem->cliente->nome }}</p>
                <p class="text-sm text-gray-600">{{ $ordem->cliente->cpf_cnpj_formatado }}</p>
                @if ($ordem->cliente->telefone)
                    <p class="text-sm text-gray-600">Tel.: {{ $ordem->cliente->telefone }}</p>
                @endif
            @else
                <p class="text-sm text-gray-500">Não informado</p>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-3 gap-6 mb-6">
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Atendente</h2>
            <p class="text-sm font-medium">
                {{ $ordem->operador?->name ?? '—' }}
                @if ($ordem->operador?->codigo)
                    <span class="font-normal text-gray-500">— Cód. {{ $ordem->operador->codigo }}</span>
                @endif
            </p>
        </div>
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Técnico responsável</h2>
            <p class="text-sm font-medium">{{ $ordem->tecnico?->name ?? '—' }}</p>
        </div>
        <div>
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Forma de pagamento</h2>
            <p class="text-sm font-medium">{{ $ordem->formaPagamento?->descricao ?? 'Não informada' }}</p>
        </div>
    </section>

    {{-- Equipamento --}}
    <section class="mb-6 border border-gray-300 rounded-lg p-4 break-inside-avoid">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-2">Equipamento</h2>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
            <p><span class="text-gray-500">Equipamento:</span> <span class="font-medium">{{ $equipamentoTexto }}</span></p>
            <p><span class="text-gray-500">Nº de série / IMEI:</span> <span class="font-mono">{{ $ordem->numero_serie ?: '—' }}</span></p>
            <p class="col-span-2"><span class="text-gray-500">Acessórios:</span> {{ $ordem->acessorios ?: 'Nenhum informado' }}</p>
        </div>
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Defeito relatado pelo cliente</h2>
        <p class="text-sm whitespace-pre-line">{{ $ordem->defeito_relatado }}</p>
    </section>

    @if ($ordem->condicao_entrada)
        <section class="mb-6 break-inside-avoid">
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Condição do equipamento na entrada</h2>
            <p class="text-sm whitespace-pre-line">{{ $ordem->condicao_entrada }}</p>
        </section>
    @endif

    @if ($ordem->laudo_tecnico)
        <section class="mb-6 break-inside-avoid">
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Laudo técnico</h2>
            <p class="text-sm whitespace-pre-line">{{ $ordem->laudo_tecnico }}</p>
        </section>
    @endif

    @if ($ordem->servico_executado)
        <section class="mb-6 break-inside-avoid">
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Serviço executado</h2>
            <p class="text-sm whitespace-pre-line">{{ $ordem->servico_executado }}</p>
        </section>
    @endif

    {{-- Peças --}}
    @if ($ordem->itens->isNotEmpty())
        <section class="mb-6">
            <h2 class="text-xs font-semibold uppercase text-gray-500 mb-2">Peças</h2>
            <table class="w-full text-sm border border-gray-300">
                <thead class="bg-gray-100 text-xs uppercase">
                    <tr>
                        <th class="text-left px-2 py-2 w-10">#</th>
                        <th class="text-left px-2 py-2 w-36">Cód. barras</th>
                        <th class="text-left px-2 py-2">Descrição</th>
                        <th class="text-left px-2 py-2 w-14">Un.</th>
                        <th class="text-right px-2 py-2 w-20">Qtd.</th>
                        <th class="text-right px-2 py-2 w-24">Vlr. unit.</th>
                        <th class="text-right px-2 py-2 w-28">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($ordem->itens as $item)
                        <tr>
                            <td class="px-2 py-1.5 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-2 py-1.5 font-mono text-xs">{{ $item->codigo_barras ?: '—' }}</td>
                            <td class="px-2 py-1.5">{{ $item->descricao }}</td>
                            <td class="px-2 py-1.5">{{ $item->unidade }}</td>
                            <td class="px-2 py-1.5 text-right">{{ $qtd($item->quantidade) }}</td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $moeda($item->valor_unitario) }}</td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $moeda($item->total()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    {{-- Totais --}}
    <section class="mb-6 flex justify-end break-inside-avoid">
        <dl class="w-full max-w-xs text-sm flex flex-col gap-1">
            <div class="flex justify-between text-gray-600"><dt>Peças</dt><dd>R$ {{ $moeda($valorPecas) }}</dd></div>
            <div class="flex justify-between text-gray-600"><dt>Mão de obra</dt><dd>R$ {{ $moeda($valorServico) }}</dd></div>
            @if ($desconto > 0)
                <div class="flex justify-between text-gray-600"><dt>Desconto</dt><dd>- R$ {{ $moeda($desconto) }}</dd></div>
            @endif
            <div class="flex justify-between items-baseline border-t-2 border-gray-300 pt-2 mt-1">
                <dt class="text-xs font-semibold uppercase text-gray-500">Total da OS</dt>
                <dd class="font-semibold">R$ {{ $moeda($ordem->valor_total) }}</dd>
            </div>
            @if ($ordem->garantia_dias)
                <div class="flex justify-between text-gray-600 pt-1"><dt>Garantia do serviço</dt><dd>{{ $ordem->garantia_dias }} dias</dd></div>
            @endif
        </dl>
    </section>

    <section class="mb-10 break-inside-avoid">
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Termo de responsabilidade</h2>
        <p class="text-xs text-gray-600 text-justify">{{ $termo }}</p>
    </section>

    <section class="grid grid-cols-2 gap-12 mt-16 break-inside-avoid">
        <div class="border-t border-gray-800 pt-1 text-center text-xs text-gray-600">
            {{ $ordem->cliente?->nome ?? 'Cliente' }}<br>Assinatura do cliente
        </div>
        <div class="border-t border-gray-800 pt-1 text-center text-xs text-gray-600">
            {{ $empresa->nome_fantasia ?: ($empresa->razao_social ?: 'Empresa') }}<br>Responsável
        </div>
    </section>
</main>

</body>
</html>
