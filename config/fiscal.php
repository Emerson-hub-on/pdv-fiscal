<?php

return [

    /*
    |--------------------------------------------------------------------
    | Emissão de IBS/CBS (Reforma Tributária - NT 2025.002-RTC)
    |--------------------------------------------------------------------
    | Para CRT 1/2 (Simples Nacional) e 4 (MEI), a obrigatoriedade e a
    | aceitação desses campos pela SEFAZ só começam em 04/01/2027
    | (art. 348 da LC 214/2025). Enviar antes disso resulta em rejeição
    | de schema, como já vimos.
    |
    | Deixe 'false' até essa data (ou até confirmar que sua UF já aceita
    | para o seu CRT). Quando for hora de ativar, é só virar 'true' aqui
    | — nenhum código precisa mudar.
    */
    'emitir_ibscbs' => env('FISCAL_EMITIR_IBSCBS', false),

    'aliquotas_ibscbs_transicao' => [
        'ibs_uf'  => 0.10,
        'ibs_mun' => 0.00,
        'cbs'     => 0.90,
    ],

    // CFOPs do cabeçalho em que cada item usa o CFOP cadastrado na sua tributação.
    // Qualquer outro CFOP (devolução, remessa, bonificação...) vale para todos os itens.
    'cfops_venda_por_item' => ['5102', '6102'],

    // Equivalente interestadual quando não basta trocar o 5 por 6
    'cfop_interestadual' => [
        '5405' => '6404',
        '5403' => '6403',
    ],

    // Finalidades com vínculo à nota original feito por ITEM (DFeReferenciado):
    // 4 = Devolução, 5 = Nota de Crédito. Se a 6 também exigir, inclua aqui.
    'finalidades_referencia_por_item' => [4],

    // Código do motivo do ajuste: tpNFCredito (finalidade 5) e tpNFDebito (6)
    'motivos_ajuste' => [
        5 => [
            '01' => 'Multa e juros',
            '02' => 'Apropriação de crédito presumido de IBS sobre saldo devedor na ZFM',
            '03' => 'Retorno por recusa na entrega ou não localização do destinatário',
            '04' => 'Redução de valores',
            '05' => 'Transferência de crédito na sucessão',
            '06' => 'Retorno por recusa parcial',
        ],
        6 => [
            '01' => 'Transferência de créditos para cooperativas',
            '02' => 'Anulação de crédito por saídas imunes/isentas',
            '03' => 'Débitos de notas fiscais não processadas na apuração',
            '04' => 'Multa e juros',
            '05' => 'Transferência de crédito de sucessão',
            '06' => 'Pagamento antecipado',
            '07' => 'Perda em estoque (perecimento, perda, furto, roubo)',
            '08' => 'Desenquadramento do Simples Nacional',
        ],
    ],

];