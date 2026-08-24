<?php

return [

    // ... suas outras configs fiscais que já existirem aqui ...

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

];