<?php

return [
    /*
    | Confere o certificado do servidor da Sefin (recomendado). Se o cURL do seu PHP reclamar da cadeia
    | de certificados (erro 60) e você não conseguir configurar o cacert.pem, use false SÓ em homologação:
    | NFSE_VERIFICAR_SERVIDOR=false no .env
    */
    'verificar_servidor' => env('NFSE_VERIFICAR_SERVIDOR', true),

    // Segundos de espera da resposta da Sefin
    'timeout' => (int) env('NFSE_TIMEOUT', 60),

    // Troca a URL base da Sefin Nacional (só se a prefeitura/ambiente exigir outra)
    'url_homologacao' => env('NFSE_URL_HOMOLOGACAO'),
    'url_producao'    => env('NFSE_URL_PRODUCAO'),
];
