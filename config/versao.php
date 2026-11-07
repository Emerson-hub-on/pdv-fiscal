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
        'versao' => '1.14.3',
        'data' => '2026-11-07',
        'itens' => [
            'Comandas → Cardápio → Menu: produto com variação agora entra igual ao caixa, uma linha por variante, com o código de barras dela (ex.: "Arroz — BRANCO / 1", 9900000000011). Cada variante é um item do cardápio, com foto, descrição, ordem e situação próprias, e mostra o estoque dela. O produto pai sozinho não entra mais.',
            'O modal de busca lista cada variante separada, com o código, o preço e o estoque dela; ler o código de barras da variante escolhe direto. Variante ou produto que já está no cardápio não aparece na busca.',
        ],
    ],
    [
        'versao' => '1.14.2',
        'data' => '2026-11-07',
        'itens' => [
            'Caixa: o estoque do caixa podia ficar diferente do servidor. A venda do caixa baixa o estoque do caixa na hora e o do servidor quando sobe; se o produto fosse alterado no cadastro enquanto havia venda ainda não enviada, o "Atualizar Caixa" trazia o saldo do servidor sem essa venda e o caixa ficava com unidades a mais. As vendas de outros caixas também não chegavam ao estoque deste caixa.',
            'Agora todo "Atualizar Caixa" (e a sincronização automática) renova o estoque de todos os produtos e variantes com o saldo do servidor, descontando as vendas do caixa que ainda não subiram e somando os cancelamentos que ainda não subiram.',
            'Comandas → Cardápio → Menu: produto com variação mostra o estoque somado das variantes e o estoque de cada uma (no cartão, na edição e no modal de busca). Antes aparecia o estoque do produto pai, que não é movimentado pelas vendas das variantes. A busca também acha o produto pelo código de barras da variante.',
        ],
    ],
    [
        'versao' => '1.14.1',
        'data' => '2026-11-07',
        'itens' => [
            'Comandas → Cardápio → Menu → Adicionar produto: a busca do produto ficou igual à do caixa — ao começar a digitar abre o modal "Selecionar Produto" (código, produto, preço e estoque), com ↑ ↓ para navegar, Enter para escolher e Esc para fechar. Código de barras ou código interno exato escolhe o produto direto.',
        ],
    ],
    [
        'versao' => '1.14.0',
        'data' => '2026-11-07',
        'itens' => [
            'Novo menu Comandas → Cardápio → Menu: escolha os produtos que o garçom vê na hora do pedido. Cada produto aparece num cartão com a foto, o nome, o valor, a descrição e a quantidade em estoque (em vermelho sem estoque e em âmbar abaixo do estoque mínimo).',
            '"Adicionar produto" busca os produtos ativos que ainda não estão no cardápio (por nome, código interno ou código de barras) e permite enviar a foto (JPG, PNG ou WEBP, reduzida automaticamente para ficar leve no celular), uma descrição própria do cardápio (em branco usa a do cadastro), a ordem e se o item está ativo.',
            'Clicar num cartão abre a edição: trocar ou remover a foto, mudar a descrição, a ordem, ativar/inativar ou retirar do cardápio (o cadastro do produto não é alterado). Filtros por nome/código, categoria e situação, e cartões de resumo (itens, visíveis, sem foto, sem estoque).',
            'Nova permissão "Comandas" no cadastro de Operador Fiscal.',
        ],
    ],
    [
        'versao' => '1.13.1',
        'data' => '2026-11-07',
        'itens' => [
            'Relatórios → Vendas → Canceladas: nova coluna "Liberado por" (na tela, no PDF e no Excel) com quem liberou o cancelamento — o supervisor que autorizou ou "Próprio operador (liberado)" quando o operador tem liberação para cancelar. "Cancelado por" passou a mostrar só o operador.',
        ],
    ],
    [
        'versao' => '1.13.0',
        'data' => '2026-11-07',
        'itens' => [
            'Relatórios → Vendas → Canceladas: lista as vendas canceladas (NFC-e do caixa e NF-e de venda) com a data do cancelamento, o nº/série, a data da venda, o cliente, o valor, o motivo, quem cancelou (e o supervisor que liberou, na NFC-e), o vendedor e os itens em "Ver itens" (com o protocolo do cancelamento da NF-e).',
            'Filtros iguais aos outros relatórios de vendas: NFC-e, NF-e ou ambos, período (padrão: mês atual) pela data do cancelamento ou da venda, busca por nº do documento, cliente, motivo ou vendedor e ordenação. Cartões com a quantidade e o valor cancelado, separados por NFC-e e NF-e.',
            'Exportar Excel e PDF de todas as vendas canceladas do filtro, só as vendas (resumida) ou com os itens (detalhada).',
        ],
    ],
    [
        'versao' => '1.12.1',
        'data' => '2026-11-07',
        'itens' => [
            'Cadastros → Caixa → Formas de pagamento: a lista ficou só com as informações; clicar numa forma abre a edição, onde ficam a chave "Ativa" (ativar/inativar) e o botão Excluir (só para forma criada no painel e nunca usada em venda; nas demais aparece o motivo).',
        ],
    ],
    [
        'versao' => '1.12.0',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastros → Caixa → Formas de pagamento (novo grupo "Caixa" no menu, com PDVs e Formas de pagamento): lista as opções da tela de pagamento do caixa com ordem, meio de pagamento da NFC-e (tPag), se aceita troco, nº de vendas e situação, e o botão "Nova forma de pagamento".',
            'Nova forma: descrição no caixa, meio de pagamento da SEFAZ (dinheiro, cheque, cartões, vales, boleto, depósito, PIX, transferência, fidelidade, outros), ordem, aceita troco e ativa. Pode ser editada, inativada ou excluída (só se nunca foi usada em venda).',
            'As 6 formas de antes viram "do sistema" (Dinheiro, PIX, Crédito, Débito, Crédito em loja e Convênio): podem ser renomeadas, reordenadas e inativadas, mas não excluídas nem ter o meio trocado. O caixa nunca fica sem forma ativa.',
            'O caixa deixou de usar formas fixas: a lista vem no "Atualizar Caixa" (vale offline) e alimenta a tela de pagamento, a validação da venda, a NFC-e (tPag pelo cadastro; "99 - Outros" leva a descrição em xPag; cartões e PIX com tpIntegra), o fechamento (dinheiro da gaveta = formas de meio 01), a baixa do convênio no caixa, o SPED 1601, a montagem de NF-e, o relatório de vendas por forma de pagamento e o relatório de caixa.',
            'Troco só sai de forma que aceita troco: pagar a mais em cartão, PIX ou vale é recusado com aviso.',
        ],
    ],
    [
        'versao' => '1.11.7',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Pagamento → Autorização do supervisor (desconto geral): voltou o campo "Supervisor" ao lado do código, mostrando o nome do supervisor ao digitar o código (ou o aviso de supervisor não encontrado), igual ao modal do PDV.',
        ],
    ],
    [
        'versao' => '1.11.6',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Pagamento: modal "Adicionar consumidor" mais largo (cabe mais do nome na tabela Nome | CPF/CNPJ | UF) e modal do Convênio um pouco mais largo também.',
        ],
    ],
    [
        'versao' => '1.11.5',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Pagamento: a lista de clientes do "Adicionar consumidor" (e da busca do Convênio) virou tabela Nome | CPF/CNPJ | UF; nome grande (comum em CNPJ) termina em "..." numa única linha, e o cliente cadastrado no próprio caixa aparece com o selo "cadastrado no caixa". A busca do caixa passou a devolver a UF do cliente.',
        ],
    ],
    [
        'versao' => '1.11.4',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Pagamento → Adicionar consumidor (F4): a lista de clientes navega pelas setas ↑ ↓ a partir do campo de busca (o 1º já vem marcado, a lista rola junto) e Enter seleciona; o mouse sobre um cliente também o marca. O mesmo vale para a busca de consumidor do modal Convênio.',
        ],
    ],
    [
        'versao' => '1.11.3',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → PDV com o visual do sistema: busca de produto com ícone e rótulo, carrinho em cartão arredondado com cabeçalho azul e "carrinho vazio" ilustrado, e o quadro "Total da venda" igual ao da tela de pagamento (total em destaque no topo azul, desconto por item e Prosseguir para pagamento).',
            'Cabeçalho do PDV: botões arredondados com os atalhos (F1 a F9) em selo e menus Cancelamento, Impressão e Opções em branco com destaque azul.',
            'Modais do PDV (reimpressão, pedido, busca de produto, balança, contingências, autorização do supervisor, tipo de desconto, desconto em item, desconto geral, cancelar item, inutilização, cancelar NFC-e, convênios e exportar XMLs) com cabeçalho azul, rótulos, campos e botões no padrão; a linha selecionada nas listas fica azul.',
        ],
    ],
    [
        'versao' => '1.11.2',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Pagamento com o visual do sistema: cabeçalho azul com ícones e atalhos (F4, F5, Esc), os dois quadros centralizados no meio da tela — Pagamentos (lançados, valor + forma, restante a pagar e Finalizar venda F2) e Total a pagar (total em destaque, nº de itens, descontos e atalhos).',
            'Modais da tela (consumidor, autorização do supervisor, tipo de desconto, desconto geral, voltar aos itens, crédito em loja e convênio) com cabeçalho azul e campos e botões no padrão do sistema.',
        ],
    ],
    [
        'versao' => '1.11.1',
        'data' => '2026-11-06',
        'itens' => [
            'Contas a receber: o formulário da conta passou a ter abas — "Dados da conta", "Parcelas" (com o nº de parcelas na aba) e "Histórico" (na edição). Os botões Salvar/Cancelar ficam embaixo de todas as abas; depois de uma baixa a tela volta na aba Parcelas, depois de um estorno na aba Histórico, e erro nas parcelas abre direto a aba Parcelas.',
        ],
    ],
    [
        'versao' => '1.11.0',
        'data' => '2026-11-06',
        'itens' => [
            'Contas a receber → nova aba "Histórico" dentro da conta: lista a criação, as baixas (no painel ou no caixa), os estornos, as alterações e o cancelamento da venda do convênio, com data e hora, parcela, valor, forma de pagamento, origem (painel, caixa ou sistema) e o usuário.',
            'Estorno de recebimento agora pede o motivo (obrigatório) num modal; o estorno aparece destacado no histórico com o valor desfeito, a data da baixa original, se ela foi feita no caixa e o motivo. Depois do estorno a tela abre direto na aba Histórico.',
            'Contas já existentes receberam no histórico a criação e as baixas que já estavam registradas. Parcelas recebidas no caixa aparecem marcadas "(no caixa)".',
        ],
    ],
    [
        'versao' => '1.10.0',
        'data' => '2026-11-06',
        'itens' => [
            'Caixa → Convênios (F9): o operador de caixa dá baixa no convênio. Clicando na dívida abre o modal com o valor da venda e o saldo em aberto, para informar o valor pago (total ou parcial) e a forma de pagamento (dinheiro, PIX, crédito ou débito).',
            'A baixa funciona também offline e até para venda que ainda não subiu: já tira do saldo da dívida e do limite usado do cliente, e sobe ao servidor na sincronização, virando a baixa da parcela em Contas a receber (pagamento parcial divide a parcela: a parte paga fica recebida e o restante continua em aberto).',
            'Fechamento de caixa: nova seção "Recebimentos de convênio" (por forma e total) e o convênio recebido em dinheiro entra no esperado da gaveta — também no relatório de fechamento do painel. Estornar a baixa no painel tira o recebimento do fechamento daquele caixa.',
        ],
    ],
    [
        'versao' => '1.9.2',
        'data' => '2026-11-06',
        'itens' => [
            'Correção: produto de balança no caixa ia como 1 unidade com o preço total embutido, e o servidor recalculava pelo preço do kg (10 kg a R$ 2,00 virava venda de R$ 2,00, com troco errado no dinheiro e erro "convênio maior que o total"). Agora o item vai com quantidade = peso (kg, 3 casas) e preço = preço do kg, também no pedido puxado para o caixa.',
            'A NFC-e sai com qCom/qTrib = peso (ex.: 10,0000 KG × R$ 2,00 = R$ 20,00), o estoque baixa o peso vendido e os itens de venda passam a guardar quantidade fracionada.',
        ],
    ],
    [
        'versao' => '1.9.1',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastro do cliente → Limite de crédito dividido em sub-abas: "Limite" (indicadores de limite total, usado e disponível + histórico do limite), "Conceder limite" (chave liga/desliga do convênio e os campos de valor, prazo e observação) e "Vendas no convênio" (listagem das vendas fiado do cliente, com o nº de contas em aberto na aba).',
        ],
    ],
    [
        'versao' => '1.9.0',
        'data' => '2026-11-06',
        'itens' => [
            'Convênio (venda fiado): nova aba "Limite de crédito" no cadastro do cliente para conceder, alterar ou suspender o limite, com prazo de vencimento em dias e observação. Mostra o limite total, o já usado e o disponível, as vendas no convênio (com link para dar baixa) e o histórico de alterações do limite (quem mudou e quando).',
            'Caixa: nova forma de pagamento "Convênio (fiado)". Pede o consumidor quando ele ainda não foi identificado, mostra o limite total, o já usado e o disponível, e permite usar só parte do limite e pagar o restante em outra forma. Funciona também offline: o limite é trazido no "Atualizar Caixa".',
            'A venda no convênio vira uma conta a receber do cliente (tipo "Convênio", parcela única com o vencimento do prazo do cliente) quando a venda sobe para o servidor; ao dar baixa em Contas a receber, o limite volta a ficar disponível. Cancelar a NFC-e devolve o limite e exclui a dívida.',
            'Caixa: novo botão "Convênios" (F9) com os convênios em aberto, filtrando por data da venda, nº do cupom e cliente.',
            'NFC-e: o convênio sai como meio de pagamento 05 (Crédito Loja) a prazo (indPag 1). O recebimento posterior não gera documento fiscal e o convênio fica fora do registro 1601 do SPED e do dinheiro esperado na gaveta.',
        ],
    ],
    [
        'versao' => '1.8.0',
        'data' => '2026-11-06',
        'itens' => [
            'Financeiro → Relatório → Troca: lista as trocas (NF-e de entrada de devolução de cliente) com a venda original, o cliente, o valor devolvido, o vale-troca gerado, o saldo do crédito e a saída do item novo; os itens devolvidos abrem em "Ver itens".',
            'Filtros: data da troca (padrão: mês atual), venda original NFC-e, NF-e ou ambas, situação da NF-e de entrada, busca por cliente, CPF/CNPJ, nº da venda original, nº da NF-e de entrada ou vale-troca, e ordenação. Cartões com quantidade de trocas, valor devolvido e crédito gerado, usado e em aberto.',
            'Exportar Excel e PDF de todas as trocas filtradas, com itens (detalhada) ou só as trocas (resumida).',
        ],
    ],
    [
        'versao' => '1.7.2',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastro do cliente → Crédito em loja → Histórico: lista os itens da venda devolvida (nº do item, quantidade vendida e valor) e destaca os itens devolvidos naquela troca ("Devolvido" ou "Devolvido em parte"), com a quantidade e o valor devolvidos.',
        ],
    ],
    [
        'versao' => '1.7.1',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastro do cliente: nova aba "Crédito em loja" com o saldo disponível para compras, o total gerado e o já usado, as vendas devolvidas (NFC-e ou NF-e) que geraram cada crédito e o histórico de uso (NF-e ou caixa).',
        ],
    ],
    [
        'versao' => '1.7.0',
        'data' => '2026-11-06',
        'itens' => [
            'Crédito em loja (troca / haver do cliente): a NF-e de entrada de uma troca, ao ser autorizada, gera um crédito no valor da nota — em nome do cliente ou, sem cliente identificado, como vale-troca pelo código (VT000123, impresso nas informações complementares).',
            'NF-e de venda: novo campo "Crédito em loja" com o saldo do cliente. A nota sai com o pagamento "21 – Crédito em loja" e a forma escolhida só para a diferença. O crédito é abatido na autorização e devolvido se a NF-e for cancelada. A venda do item novo da troca já vem com o crédito.',
            'Caixa: nova forma de pagamento "Crédito em loja (troca)", pelo consumidor identificado ou pelo código do vale-troca. Funciona offline (o caixa tem a lista de créditos e abate na hora) e o servidor confirma quando a venda sobe. Crédito não dá troco. Cancelar a NFC-e devolve o crédito.',
            'SPED (1601) e fechamento de caixa: a parte paga com crédito em loja não conta como recebimento por cartão/Pix nem como dinheiro na gaveta.',
            'Financeiro → Créditos em loja: consulta dos créditos com saldo, por cliente ou código, com o histórico de cada um.',
            'A NF-e de entrada de troca não pode ser cancelada se o crédito gerado por ela já foi usado.',
        ],
    ],
    [
        'versao' => '1.6.2',
        'data' => '2026-11-06',
        'itens' => [
            'NF-e: cliente com número do endereço "0" era recusado na emissão como "endereço incompleto". Agora só conta como faltando o campo em branco, e a mensagem diz quais campos faltam.',
            'NFC-e: pelo mesmo motivo, o endereço do cliente com número "0" ficava fora do cupom; agora vai normalmente.',
        ],
    ],
    [
        'versao' => '1.6.1',
        'data' => '2026-11-06',
        'itens' => [
            'Cadastro de cliente: corrigido o erro "The cep field must be 8 characters" ao salvar com o CEP digitado no formato 00000-000.',
        ],
    ],
    [
        'versao' => '1.6.0',
        'data' => '2026-11-06',
        'itens' => [
            'Faturamento → Montagem NF-e → NF-e de Troca: busca a venda original (cupom NFC-e por série e número ou data, ou NF-e de venda por número ou data), abre uma janela com os itens para escolher o que o cliente está devolvendo e monta a NF-e de entrada de devolução (finalidade 4).',
            'CFOP da entrada: 1202/2202 para mercadoria normal e 1411/2411 quando foi vendida com substituição tributária (1 = mesmo estado, 2 = outro estado). O CFOP que faltar no cadastro é incluído.',
            'Cada item da entrada referencia a chave da venda original e o nº do item. Cliente identificado na venda: a NF-e sai em nome dele; sem cliente, sai contra a própria empresa.',
            'Saída do item novo: venda normal no caixa (NFC-e) ou o botão "Montar NF-e de venda do item novo" (CFOP 5102; itens com ST saem com o CFOP da tributação, ex.: 5405), ligada à entrada da troca.',
            'Controle de saldo: não é possível devolver mais do que foi vendido, somando as trocas já feitas.',
        ],
    ],
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
