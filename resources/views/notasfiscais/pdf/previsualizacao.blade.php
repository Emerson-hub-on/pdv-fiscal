<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 5px; vertical-align: top; }
        .caixa { border: 1px solid #000; }
        .label { font-size: 7px; color: #444; }
        .valor { font-size: 10px; font-weight: bold; }
        .valor-sm { font-size: 9px; font-weight: bold; }
        .centro { text-align: center; }
        .direita { text-align: right; }
        .titulo-secao {
            background: #e5e5e5;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 5px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .semvalor { color: #c00; font-weight: bold; font-size: 9px; }
        .itens th {
            background: #e5e5e5;
            font-size: 7px;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 3px;
        }
        .itens td { border: 1px solid #000; font-size: 8px; padding: 3px; }
    </style>
</head>
<body>

    <!-- Canhoto de recebimento -->
    <table class="caixa">
        <tr>
            <td width="75%" style="border-right:1px solid #000;">
                RECEBEMOS DE {{ $dados['emitente']['razao_social'] }} OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO
                <table style="margin-top:14px;">
                    <tr>
                        <td width="50%" style="border-top:1px solid #000;">
                            <span class="label">DATA DE RECEBIMENTO</span>
                        </td>
                        <td width="50%" style="border-top:1px solid #000; border-left:1px solid #000;">
                            <span class="label">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</span>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="25%" class="centro">
                <span class="label">Nº</span> <span class="valor">{{ $dados['numero'] }}</span><br>
                <span class="label">Série</span> <span class="valor-sm">{{ $dados['serie'] }}</span>
            </td>
        </tr>
    </table>

    <div style="height:6px;"></div>

    <!-- Bloco principal DANFE -->
    <table class="caixa">
        <tr>
            <td width="30%" style="border-right:1px solid #000;">
                <div class="valor" style="font-size:13px;">{{ $dados['emitente']['nome_fantasia'] ?? $dados['emitente']['razao_social'] }}</div>
                <div>{{ $dados['emitente']['endereco'] }}</div>
                <div>{{ $dados['emitente']['bairro'] }} — {{ $dados['emitente']['municipio'] }}/{{ $dados['emitente']['uf'] }}</div>
                <div>CEP: {{ $dados['emitente']['cep'] }}</div>
            </td>
            <td width="45%" class="centro" style="border-right:1px solid #000;">
                <div class="valor" style="font-size:14px;">DANFE</div>
                <div style="font-size:8px;">Documento Auxiliar da Nota Fiscal Eletrônica</div>
                <div style="font-size:8px; margin-top:4px;">0-Entrada &nbsp;&nbsp; 1-Saída</div>
                <div class="valor" style="border:1px solid #000; display:inline-block; padding:2px 8px; margin-top:2px;">
                    {{ substr($dados['tipo_operacao'], 0, 1) }}
                </div>
                <div style="margin-top:6px;">Nº {{ $dados['numero'] }}</div>
                <div>SÉRIE: {{ $dados['serie'] }} &nbsp; Página 1 de 1</div>
            </td>
            <td width="25%" class="centro">
                <span class="label">CONTROLE DO FISCO</span><br>
                @if ($dados['chave_acesso'])
                    <div style="font-size:7px; word-break:break-all; margin-top:20px;">{{ $dados['chave_acesso'] }}</div>
                @else
                    <div style="margin-top:20px; font-size:8px; color:#666;">(código de barras gerado<br>somente após a emissão)</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="caixa" style="border-top:none;">
        <tr>
            <td style="border-right:1px solid #000;">
                <span class="label">NATUREZA DA OPERAÇÃO</span><br>
                <span class="valor-sm">{{ $dados['natureza_operacao'] }}</span>
            </td>
            <td width="35%">
                <span class="label">INSCRIÇÃO ESTADUAL</span><br>
                <span class="valor-sm">{{ $dados['emitente']['ie'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top:1px solid #000;">
                <span class="label">CHAVE DE ACESSO</span><br>
                @if ($dados['chave_acesso'])
                    <span class="valor-sm">{{ $dados['chave_acesso'] }}</span>
                @else
                    <span style="font-size:8px; color:#666;">Chave gerada somente após a emissão</span>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top:1px solid #000;">
                @if ($dados['emitida'])
                    <span class="label">NÚMERO DE PROTOCOLO DE AUTORIZAÇÃO DE USO DA NF-E</span><br>
                    <span class="valor-sm">{{ $dados['protocolo'] }}</span>
                @else
                    <span class="semvalor">PRÉ-VISUALIZAÇÃO — DOCUMENTO SEM VALOR FISCAL — NOTA AINDA NÃO EMITIDA</span>
                @endif
            </td>
        </tr>
    </table>

    <div style="height:6px;"></div>

    <!-- Destinatário -->
    <table class="caixa">
        <tr><td colspan="4" class="titulo-secao">DESTINATÁRIO / REMETENTE</td></tr>
        <tr>
            <td width="45%"><span class="label">NOME/RAZÃO SOCIAL</span><br><span class="valor-sm">{{ $dados['destinatario']['nome'] }}</span></td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">CNPJ/CPF</span><br><span class="valor-sm">{{ $dados['destinatario']['documento'] }}</span></td>
            <td width="30%" style="border-left:1px solid #000;"><span class="label">DATA DE EMISSÃO</span><br><span class="valor-sm">{{ $dados['data_emissao'] }}</span></td>
        </tr>
        <tr style="border-top:1px solid #000;">
            <td width="45%"><span class="label">ENDEREÇO</span><br>{{ $dados['destinatario']['endereco'] }}</td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">BAIRRO</span><br>{{ $dados['destinatario']['bairro'] }}</td>
            <td width="30%" style="border-left:1px solid #000;"><span class="label">CEP</span><br>{{ $dados['destinatario']['cep'] }}</td>
        </tr>
        <tr style="border-top:1px solid #000;">
            <td width="45%"><span class="label">MUNICÍPIO</span><br>{{ $dados['destinatario']['municipio'] }}</td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">FONE/FAX</span><br>{{ $dados['destinatario']['telefone'] ?? '—' }}</td>
            <td width="30%" style="border-left:1px solid #000;"><span class="label">UF</span> {{ $dados['destinatario']['uf'] }} &nbsp; <span class="label">INSC. ESTADUAL</span> {{ $dados['destinatario']['ie'] }}</td>
        </tr>
    </table>

    <div style="height:6px;"></div>

    <!-- Cálculo do imposto -->
    <table class="caixa">
        <tr><td colspan="4" class="titulo-secao">CÁLCULO DO IMPOSTO</td></tr>
        <tr>
            <td width="25%"><span class="label">BASE DE CÁLCULO DO ICMS</span><br><span class="valor-sm">R$ {{ $dados['totais']['base_calculo_icms'] }}</span></td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">VALOR DO ICMS</span><br><span class="valor-sm">R$ {{ $dados['totais']['valor_icms'] }}</span></td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">VALOR DO FRETE</span><br><span class="valor-sm">R$ {{ $dados['totais']['valor_frete'] }}</span></td>
            <td width="25%" style="border-left:1px solid #000;"><span class="label">DESCONTO</span><br><span class="valor-sm">R$ {{ $dados['totais']['valor_desconto'] }}</span></td>
        </tr>
        <tr style="border-top:1px solid #000;">
            <td colspan="3"><span class="label">VALOR TOTAL DOS PRODUTOS</span><br><span class="valor-sm">R$ {{ $dados['totais']['valor_produtos'] }}</span></td>
            <td style="border-left:1px solid #000;"><span class="label">VALOR TOTAL DA NOTA</span><br><span class="valor" style="font-size:12px;">R$ {{ $dados['totais']['valor_total_nota'] }}</span></td>
        </tr>
    </table>

    <div style="height:6px;"></div>

    <!-- Itens -->
    <table class="itens">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descrição do produto/serviço</th>
                <th>NCM/SH</th>
                <th>CST/CSOSN</th>
                <th>CFOP</th>
                <th>Un</th>
                <th>Qtde</th>
                <th>Valor unit.</th>
                <th>Valor total</th>
                <th>BC ICMS</th>
                <th>Vlr. ICMS</th>
                <th>% ICMS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados['itens'] as $item)
                <tr>
                    <td>{{ $item['codigo'] }}</td>
                    <td>{{ $item['descricao'] }}</td>
                    <td>{{ $item['ncm'] }}</td>
                    <td>{{ $item['cst'] }}</td>
                    <td>{{ $item['cfop'] }}</td>
                    <td>{{ $item['unidade'] }}</td>
                    <td class="direita">{{ $item['quantidade'] }}</td>
                    <td class="direita">{{ $item['valor_unitario'] }}</td>
                    <td class="direita">{{ $item['valor_total'] }}</td>
                    <td class="direita">{{ $item['bc_icms'] }}</td>
                    <td class="direita">{{ $item['valor_icms'] }}</td>
                    <td class="direita">{{ $item['aliquota_icms'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="height:6px;"></div>

    <!-- Dados adicionais -->
    <table class="caixa">
        <tr><td colspan="2" class="titulo-secao">DADOS ADICIONAIS</td></tr>
        <tr>
            <td width="70%" style="height:50px;"><span class="label">OBSERVAÇÕES</span></td>
            <td width="30%" style="border-left:1px solid #000;"><span class="label">RESERVADO AO FISCO</span></td>
        </tr>
    </table>

    <div style="margin-top:10px; font-size:7px; text-align:center; color:#666;">
        @if (!$dados['emitida'])
            Este documento é apenas uma pré-visualização gerada localmente e não possui chave de acesso, protocolo de
            autorização ou código de barras — não substitui o DANFE oficial, gerado somente após a emissão e autorização pela SEFAZ.
        @endif
        &nbsp;Ambiente de {{ $dados['ambiente'] }} — {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>