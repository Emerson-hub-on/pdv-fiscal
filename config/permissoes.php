<?php

return [
    'modulos' => [
        'produtos'        => ['nome' => 'Produtos e classificações fiscais', 'descricao' => 'Produtos, NCM, CEST, tributação, PIS/COFINS, IPI'],
        'clientes'        => ['nome' => 'Clientes',                          'descricao' => 'Cadastro de clientes'],
        'empresa'         => ['nome' => 'Empresa',                           'descricao' => 'Dados da empresa emitente'],
        'pdvs'            => ['nome' => 'PDVs',                              'descricao' => 'Cadastro de PDVs'],
        'notas'           => ['nome' => 'Nota Fiscal - Saída',               'descricao' => 'Montar, emitir e cancelar NF-e; CFOPs e formas de pagamento'],
        'series'          => ['nome' => 'Numeração de NF-e',                 'descricao' => 'Séries e última numeração'],
        'transportadoras' => ['nome' => 'Transportadoras e veículos',        'descricao' => 'Cadastro de transportadoras e veículos'],
    ],

    'niveis' => [
        'total'     => 'Acesso total',
        'consulta'  => 'Somente consulta',
        'bloqueado' => 'Sem acesso',
    ],
];