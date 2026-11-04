<?php

/*
 * Versão do Cyberly Sistema (aparece na tela de login e em Configuração → Versão).
 *
 * A cada modificação entregue, acrescente uma entrada NO TOPO do histórico; a versão atual é sempre a primeira.
 *   - correção ou ajuste pequeno .......... 1.0.0 → 1.0.1  (último número)
 *   - funcionalidade nova ................. 1.0.1 → 1.1.0  (número do meio; zera o último)
 *   - mudança grande / incompatível ....... 1.1.0 → 2.0.0  (primeiro número; zera os outros)
 */

$historico = [
    [
        'versao' => '1.1.0',
        'data' => '2026-11-04',
        'itens' => [
            'Cadastro da empresa: o CNPJ busca razão social, nome fantasia e endereço automaticamente, como no cadastro de clientes.',
            'Cadastro da empresa: o CEP preenche o endereço e o município preenche o código IBGE pela tabela de municípios.',
            'Cadastro da empresa: CNPJ e CEP podem ser digitados com pontos, barra e hífen.',
        ],
    ],
    [
        'versao' => '1.0.3',
        'data' => '2026-11-04',
        'itens' => [
            'NF-e: antes de enviar à SEFAZ, confere se o CFOP combina com o destino (5 dentro do estado, 6 para outro estado) e explica como corrigir.',
        ],
    ],
    [
        'versao' => '1.0.2',
        'data' => '2026-11-04',
        'itens' => [
            'Login: removido o bloqueio por senhas erradas; o operador pode errar quantas vezes precisar.',
        ],
    ],
    [
        'versao' => '1.0.1',
        'data' => '2026-11-04',
        'itens' => [
            'Segurança: nomes de produtos, descrições de catálogos e motivos não executam mais código nas telas da NF-e, do caixa e do cadastro de produtos.',
            'Segurança: o login bloqueia por 15 minutos depois de 5 senhas erradas no mesmo código (ou 20 no mesmo computador).',
            'Exportar XML: não permite salvar o .zip dentro da pasta do sistema.',
            'Exportar XML: a Área de trabalho, Documentos e Downloads voltaram a poder ser escolhidos como destino.',
        ],
    ],
    [
        'versao' => '1.0.0',
        'data' => '2026-11-03',
        'itens' => [
            'Primeira versão numerada do Cyberly Sistema.',
        ],
    ],
];

return [
    'atual' => $historico[0]['versao'],
    'historico' => $historico,
];
