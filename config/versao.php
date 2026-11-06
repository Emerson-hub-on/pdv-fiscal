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
        'versao' => '1.5.0',
        'data' => '2026-11-06',
        'itens' => [
            'Todo fornecedor e a própria empresa passam a ter também um cadastro de cliente (pelo CPF/CNPJ), para poderem ser destinatários de NF-e (devolução, remessa, transferência...). Vale para o cadastro na tela, para o fornecedor criado na importação de XML e para os Dados da empresa.',
            'Ao alterar o fornecedor ou a empresa, o cliente correspondente recebe o nome, a inscrição estadual e o endereço novos; e-mail e telefone só são preenchidos se o cliente estiver sem.',
            'Fornecedores e empresa já cadastrados foram incluídos como clientes.',
        ],
    ],
    [
        'versao' => '1.4.0',
        'data' => '2026-11-06',
        'itens' => [
            'Faturamento → Montagem NF-e → NF-e Devolução: lista as notas de entrada do dia (ou por data ou número), abre uma janela com os itens da nota para escolher todos ou só alguns, com a quantidade a devolver, e monta o rascunho da NF-e de devolução (finalidade 4).',
            'CFOP automático: 5 para fornecedor do mesmo estado e 6 para outro estado; 5202/6202 na devolução comum e 5411/6411 quando a mercadoria foi comprada com substituição tributária. O CFOP que faltar no cadastro é incluído.',
            'Cada item da devolução referencia a chave da nota de compra e o nº do item (nItem) correspondente. O fornecedor vira o destinatário (cadastrado como cliente, se ainda não for).',
            'Controle de saldo: não é possível devolver mais do que foi comprado, somando as devoluções já feitas (inclusive rascunhos).',
            'Importação de XML na entrada passa a guardar o nº de cada item da nota (nItem).',
        ],
    ],
    [
        'versao' => '1.3.0',
        'data' => '2026-11-06',
        'itens' => [
            'Faturamento → Montagem NF-e → A partir de pedido: busca o pedido de venda pelo número ou pela data (padrão: hoje) e abre a NF-e já preenchida com itens, cliente, forma de pagamento e tipo de nota; ao salvar vira rascunho.',
            'Cada pedido gera uma única NF-e. Pedido já vendido no caixa mostra a NFC-e e leva para a montagem a partir de NFC-e; pedido usado numa venda ainda não sincronizada aparece como "Venda não sincronizada".',
            'Ao emitir a NF-e do pedido, o pedido passa a "Emitido" e sai da lista do caixa; se a NF-e for cancelada, o pedido volta a ficar em aberto. A emissão é recusada se o pedido foi vendido no caixa ou cancelado depois da montagem.',
            'Tela "A partir de NFC-e" no novo visual.',
            'Correção: o motivo de recusa da emissão (ex.: CFOP incompatível com o destino) não ficava gravado na nota.',
        ],
    ],
    [
        'versao' => '1.2.3',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastros (produtos, clientes, fornecedores, PDVs, transportadoras, veículos, condutores, credenciadoras, usuários), Dados da empresa e Série de NF-e no mesmo visual dos demais formulários: faixa azul-marinho com título e botão voltar, campos arredondados, títulos dos campos em destaque, abas e botões em azul-marinho.',
            'Ordem de serviço e NF-e de entrada também passaram para o novo visual.',
        ],
    ],
    [
        'versao' => '1.2.2',
        'data' => '2026-11-06',
        'itens' => [
            'MDF-e, Pedido de venda, Pedido de compra, Contas a pagar e Contas a receber: formulários no mesmo visual da tela de NF-e — faixa azul-marinho no topo, títulos dos campos em destaque, campos e seletores arredondados com ícones, seções com ícone, tabelas com cabeçalho azul-marinho e botão de salvar azul-marinho à direita.',
            'Janelas de busca e de confirmação desses formulários com o topo em azul-marinho.',
        ],
    ],
    [
        'versao' => '1.2.1',
        'data' => '2026-11-06',
        'itens' => [
            'Nota Fiscal (NF-e): tela de criação/edição do rascunho no visual do sistema — cabeçalho azul-marinho, campos de seleção com ícones, títulos dos campos em destaque, tabela de itens com cabeçalho azul-marinho e total da nota realçado.',
        ],
    ],
    [
        'versao' => '1.2.0',
        'data' => '2026-11-06',
        'itens' => [
            'Faturamento → Montagem NF-e → A partir de NFC-e: busca a NFC-e pela série e número do cupom ou pela data (padrão: hoje) e abre a NF-e já preenchida com itens, cliente, forma de pagamento, tipo de nota e a chave da NFC-e referenciada; ao salvar vira rascunho.',
            'Venda ainda não enviada pelo caixa ao servidor aparece como "Venda não sincronizada", com a orientação de usar Atualizar Caixa.',
            'Cada NFC-e gera uma única NF-e: se já existir, a tela mostra o link para a NF-e gerada.',
        ],
    ],
    [
        'versao' => '1.1.6',
        'data' => '2026-11-04',
        'itens' => [
            'Painel administrativo: cabeçalho das tabelas das telas (listagens, relatórios, permissões, SPED etc.) em azul-marinho.',
        ],
    ],
    [
        'versao' => '1.1.5',
        'data' => '2026-11-04',
        'itens' => [
            'Caixa: cabeçalho da tabela de itens da venda em azul-marinho, com os títulos das colunas em tom claro.',
        ],
    ],
    [
        'versao' => '1.1.4',
        'data' => '2026-11-04',
        'itens' => [
            'Caixa: cabeçalho da tela de vendas e da tela de pagamento em azul-marinho, com botões e perfil do operador em tons claros.',
        ],
    ],
    [
        'versao' => '1.1.3',
        'data' => '2026-11-04',
        'itens' => [
            'Painel administrativo: menu lateral e submenus em azul-marinho, iguais à barra do topo.',
        ],
    ],
    [
        'versao' => '1.1.2',
        'data' => '2026-11-04',
        'itens' => [
            'Painel administrativo: barra do topo em azul-marinho, com o caminho da tela e o perfil em tons claros.',
        ],
    ],
    [
        'versao' => '1.1.1',
        'data' => '2026-11-04',
        'itens' => [
            'Configuração > Versão: mostra só o que mudou na última atualização.',
        ],
    ],
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
