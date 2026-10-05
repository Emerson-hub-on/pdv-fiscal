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

    // Operador de Caixa: ações do PDV que, por padrão, exigem autorização de supervisor
    'caixa' => [
        'acoes' => [
            'cancelar_nfce'   => ['nome' => 'Cancelar NFC-e',   'descricao' => 'Cancelamento de NFC-e já emitida (F3)'],
            'cancelar_item'   => ['nome' => 'Cancelar item',    'descricao' => 'Remover um item do cupom em andamento'],
            'cancelar_cupom'  => ['nome' => 'Cancelar cupom',   'descricao' => 'Limpar todos os itens da venda em andamento'],
            'desconto_item'   => ['nome' => 'Desconto no item', 'descricao' => 'Desconto aplicado em um item do carrinho (F4)'],
            'desconto_global' => ['nome' => 'Desconto geral',   'descricao' => 'Desconto no total da venda, na tela de pagamento (F5)'],
        ],

        'niveis' => [
            'liberado'   => 'Liberado',
            'supervisor' => 'Exige supervisor',
        ],
    ],

    'acao'     => 'nullable|string|in:desconto_item,desconto_global,cancelar_item,cancelar_cupom,cancelar_nfce',
];