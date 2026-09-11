<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .watermark {
            position: fixed;
            top: 300px;
            left: 60px;
            font-size: 60px;
            color: #f87171;
            opacity: 0.15;
            transform: rotate(-30deg);
            z-index: -1;
        }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid #9ca3af; padding: 8px; margin-bottom: 10px; }
        .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .valor { font-size: 12px; font-weight: bold; }
        .titulo { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 4px; }
        .subtitulo { font-size: 10px; text-align: center; color: #dc2626; font-weight: bold; margin-bottom: 15px; }
        .tabela-itens th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; padding: 6px; border: 1px solid #d1d5db; text-align: left; }
        .tabela-itens td { padding: 6px; border: 1px solid #d1d5db; font-size: 10px; }
        .text-right { text-align: right; }
        .totais { margin-top: 10px; }
        .totais td { padding: 4px 8px; }
    </style>
</head>
<body>
    <div class="watermark">PRÉ-VISUALIZAÇÃO</div>

    <div class="titulo">NOTA FISCAL ELETRÔNICA — MODELO 55</div>
    <div class="subtitulo">
        @if ($notaFiscal->status === 'rascunho')
            PRÉ-VISUALIZAÇÃO — SEM VALOR FISCAL — NOTA AINDA NÃO EMITIDA
        @else
            {{ strtoupper($notaFiscal->status) }}
        @endif
    </div>

    <table class="box">
        <tr>
            <td width="60%">
                <div class="label">Emitente</div>
                <div class="valor">{{ $notaFiscal->empresa_razao_social ?? \App\Models\Empresa::first()->razao_social }}</div>
                <div>CNPJ: {{ \App\Models\Empresa::first()->cnpj }}</div>
            </td>
            <td width="40%">
                <div class="label">Nº / Série</div>
                <div class="valor">
                    {{ $notaFiscal->numero ?? '(a definir na emissão)' }}
                    @if ($notaFiscal->serie) / Série {{ $notaFiscal->serie }} @endif
                </div>
                <div class="label" style="margin-top:6px;">Natureza da operação</div>
                <div>{{ $notaFiscal->natureza_operacao }}</div>
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td width="60%">
                <div class="label">Destinatário</div>
                <div class="valor">{{ $notaFiscal->cliente->nome }}</div>
                <div>{{ $notaFiscal->cliente->cpf_cnpj_formatado }}</div>
            </td>
            <td width="40%">
                <div class="label">Endereço</div>
                <div>
                    {{ $notaFiscal->cliente->logradouro }}, {{ $notaFiscal->cliente->numero }}
                    @if ($notaFiscal->cliente->complemento) - {{ $notaFiscal->cliente->complemento }} @endif
                    <br>
                    {{ $notaFiscal->cliente->bairro }} — {{ $notaFiscal->cliente->municipio }}/{{ $notaFiscal->cliente->uf }}
                    <br>
                    CEP: {{ $notaFiscal->cliente->cep }}
                </div>
            </td>
        </tr>
    </table>

    <table class="tabela-itens">
        <thead>
            <tr>
                <th>Cód.</th>
                <th>Produto</th>
                <th>CFOP</th>
                <th class="text-right">Qtd.</th>
                <th class="text-right">Vl. Unit.</th>
                <th class="text-right">Desconto</th>
                <th class="text-right">Vl. Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($notaFiscal->itens as $item)
                <tr>
                    <td>{{ $item->produto->codigo_interno }}</td>
                    <td>{{ $item->produto->nome }}</td>
                    <td>{{ $item->cfop }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->valor_desconto, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totais box">
        <tr>
            <td>Total dos produtos</td>
            <td class="text-right">R$ {{ number_format($notaFiscal->valor_produtos, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Desconto</td>
            <td class="text-right">R$ {{ number_format($notaFiscal->valor_desconto, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Frete</td>
            <td class="text-right">R$ {{ number_format($notaFiscal->valor_frete, 2, ',', '.') }}</td>
        </tr>
        <tr style="font-weight:bold; font-size:13px;">
            <td>Valor total da nota</td>
            <td class="text-right">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
        </tr>
    </table>

    @if ($notaFiscal->status === 'rascunho')
        <p style="margin-top:20px; font-size:9px; color:#6b7280;">
            Este documento é apenas uma pré-visualização gerada localmente e não possui chave de acesso, protocolo de autorização
            ou QR Code — não substitui o DANFE oficial, que só é gerado após a emissão e autorização pela SEFAZ.
        </p>
    @endif
</body>
</html>