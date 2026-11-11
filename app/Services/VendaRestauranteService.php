<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\ComandaPedido;
use App\Models\Condutor;
use App\Models\IfoodPedido;
use App\Support\FormasCaixa;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendas do restaurante emitidas pelo caixa (PDV → Puxar Pedido → Pedidos Restaurante).
 *
 * - Mesa: cada conta paga (comanda) vira uma NFC-e, com os pagamentos registrados no fechamento da conta.
 * - iFood: cada pedido concluído vira uma NFC-e; os pagamentos do iFood viram as formas do caixa de mesmo meio (tPag),
 *   e o iFood entra como intermediador.
 *
 * A NFC-e leva só os itens (a taxa de entrega e os descontos do iFood não entram). O estoque já foi baixado pelo pedido,
 * por isso a venda nasce marcada (restaurante_origem) e nem o caixa nem o servidor baixam de novo.
 * Os pedidos ficam no servidor (MySQL); os dados fiscais dos produtos, no SQLite do caixa.
 */
class VendaRestauranteService
{
    public const MESA  = 'mesa';
    public const IFOOD = 'ifood';

    /** Meio de pagamento (tPag) de cada forma do iFood, em ordem de preferência. */
    public const MEIOS_IFOOD = [
        'CREDIT'         => ['03'],
        'DEBIT'          => ['04'],
        'PIX'            => ['17', '20'],
        'CASH'           => ['01'],
        'MEAL_VOUCHER'   => ['11'],
        'FOOD_VOUCHER'   => ['10'],
        'GIFT_CARD'      => ['12'],
        'DIGITAL_WALLET' => ['18'],
        'OTHER'          => ['99'],
    ];

    /** Pedidos do dia (contas de mesa pagas e pedidos do iFood concluídos), já com o que falta para emitir. */
    public function listar(string $data): array
    {
        $contas = Comanda::with([
            'mesa',
            'pagamentos',
            'pedidos' => fn ($q) => $q->where('status', '!=', ComandaPedido::CANCELADO),
            'pedidos.itens',
        ])
            ->where('status', Comanda::PAGA)
            ->whereDate('fechada_em', $data)
            ->orderBy('fechada_em')
            ->get();

        $ifood = ComandaPedido::with(['itens', 'ifood'])
            ->where('origem', 'ifood')
            ->where('status', ComandaPedido::CONCLUIDO)
            ->whereDate('enviado_em', $data)
            ->orderBy('enviado_em')
            ->get();

        $linhas = $contas->map(fn (Comanda $c) => $this->linha(self::MESA, $c, $this->vendaDaConta($c)))
            ->concat($ifood->map(fn (ComandaPedido $p) => $this->linha(self::IFOOD, $p, $this->vendaDoIfood($p))))
            ->sortBy('momento')
            ->values();

        return $this->comSituacaoNoCaixa($linhas)->all();
    }

    /** Venda pronta para gravar no caixa. Lança DomainException com o que impede a emissão. */
    public function montar(string $tipo, int $id): array
    {
        $venda = match ($tipo) {
            self::MESA  => $this->vendaDaConta($this->conta($id)),
            self::IFOOD => $this->vendaDoIfood($this->pedidoIfood($id)),
            default     => throw new DomainException('Tipo de pedido inválido.'),
        };

        if ($venda['problemas']) {
            throw new DomainException(implode("\n", $venda['problemas']));
        }

        return $venda;
    }

    /**
     * Reserva o pedido para esta venda no servidor (trava a linha): outro caixa que tentar o mesmo pedido recebe erro.
     * Pedido antigo, de antes da baixa de estoque nos pedidos, baixa agora (uma vez só).
     */
    public function reservar(string $tipo, int $id, string $uuid): void
    {
        DB::transaction(function () use ($tipo, $id, $uuid) {
            $registro = $tipo === self::MESA
                ? Comanda::whereKey($id)->lockForUpdate()->first()
                : ComandaPedido::whereKey($id)->where('origem', 'ifood')->lockForUpdate()->first();

            if (!$registro) {
                throw new DomainException('Pedido não encontrado no servidor.');
            }

            if ($registro->venda_uuid && $registro->venda_uuid !== $uuid) {
                throw new DomainException('Este pedido já foi emitido (neste ou em outro caixa).');
            }

            $valido = $tipo === self::MESA
                ? $registro->status === Comanda::PAGA
                : $registro->status === ComandaPedido::CONCLUIDO;

            if (!$valido) {
                throw new DomainException($tipo === self::MESA ? 'A conta desta mesa não está paga.' : 'Este pedido do iFood não está concluído.');
            }

            $registro->forceFill(['venda_uuid' => $uuid, 'venda_reservada_em' => now()])->save();

            $pedidos = $tipo === self::MESA
                ? $registro->pedidos()->where('status', '!=', ComandaPedido::CANCELADO)->whereNull('estoque_baixado_em')->get()
                : collect($registro->estoque_baixado_em ? [] : [$registro]);

            $estoque = app(EstoqueService::class);
            $pedidos->each(fn (ComandaPedido $p) => $estoque->baixarPedido($p));
        });
    }

    /** Desfaz a reserva (a venda não chegou a ser gravada, ou a NFC-e foi cancelada e o pedido pode ser emitido de novo). */
    public static function liberar(string $tipo, int $id, string $uuid): void
    {
        $tabela = $tipo === self::MESA ? 'comandas' : 'comanda_pedidos';

        DB::table($tabela)->where('id', $id)->where('venda_uuid', $uuid)
            ->update(['venda_uuid' => null, 'venda_reservada_em' => null]);
    }

    // =====================================================================

    private function conta(int $id): Comanda
    {
        $conta = Comanda::with([
            'mesa',
            'pagamentos',
            'pedidos' => fn ($q) => $q->where('status', '!=', ComandaPedido::CANCELADO),
            'pedidos.itens',
        ])->find($id);

        if (!$conta) {
            throw new DomainException('Conta da mesa não encontrada.');
        }

        return $conta;
    }

    private function pedidoIfood(int $id): ComandaPedido
    {
        $pedido = ComandaPedido::with(['itens', 'ifood'])->where('origem', 'ifood')->find($id);

        if (!$pedido) {
            throw new DomainException('Pedido do iFood não encontrado.');
        }

        return $pedido;
    }

    private function vendaDaConta(Comanda $conta): array
    {
        $rotulo = $conta->mesa?->titulo() ?? 'Mesa';
        [$itens, $total, $problemas] = $this->itens($conta->pedidos->flatMap->itens, 'Confira o item no Cardápio das comandas.');

        $formas = FormasCaixa::lista('caixa');
        $pagamentos = [];

        foreach ($conta->pagamentos as $p) {
            if (!$formas->has($p->forma_pagamento)) {
                $problemas[] = "A forma de pagamento “{$p->descricao}” não está neste caixa. Use \"Atualizar Caixa\".";
            }
            $pagamentos[] = ['forma_pagamento' => $p->forma_pagamento, 'valor' => round((float) $p->valor, 2)];
        }

        $troco = round((float) $conta->troco, 2);

        if (!$pagamentos) {
            $problemas[] = 'A conta não tem pagamento registrado.';
        } elseif ($itens && abs(array_sum(array_column($pagamentos, 'valor')) - $troco - $total) > 0.009) {
            $problemas[] = 'Os pagamentos da conta não batem com o total dos itens (R$ ' . number_format($total, 2, ',', '.') . ').';
        }

        $form = ['cpf' => (string) data_get($conta->dados_nfce, 'cpf', ''), 'nome' => (string) data_get($conta->dados_nfce, 'nome', '')];

        return $this->comCpfObrigatorio([
            'rotulo'     => mb_substr($rotulo, 0, 60),
            'momento'    => $conta->fechada_em,
            'itens'      => $itens,
            'total'      => $total,
            'pagamentos' => $pagamentos,
            'troco'      => $troco,
            'cpf'        => Condutor::cpfValido($form['cpf']) ? $form['cpf'] : null,
            'ind_pres'   => 1,
            'destinatario' => null,
            'intermediador_cnpj' => null,
            'intermediador_id'   => null,
            'resumo_pagamento'   => $conta->pagamentos->pluck('descricao')->unique()->implode(', '),
            'problemas'  => array_values(array_unique($problemas)),
        ], $form, max($total, (float) $conta->total));
    }

    /** Valor acima do qual a NFC-e exige o CPF do consumidor (0 = sem a regra). */
    public static function limiteCpf(): float
    {
        return (float) config('fiscal.cpf_obrigatorio_acima', 500);
    }

    /**
     * NFC-e acima do limite precisa do CPF do cliente: sem ele, vira a pendência "CPF obrigatório para a emissão"
     * com o campo para informar (a entrega do iFood já pede o CPF junto com o endereço).
     */
    private function comCpfObrigatorio(array $venda, array $form, float $valor): array
    {
        // Valor do pedido (não só dos itens já ligados a produto: com item pendente o total dos itens fica menor)
        $limite = self::limiteCpf();
        $venda['pede_cpf'] = false;
        $venda['form_cpf'] = null;

        if ($limite > 0 && $valor > $limite && !$venda['cpf'] && empty($venda['destinatario']) && empty($venda['pede_destinatario'])) {
            $venda['pede_cpf'] = true;
            $venda['form_cpf'] = ['cpf' => $form['cpf'] ?? '', 'nome' => $form['nome'] ?? ''];
            $venda['problemas'][] = 'CPF obrigatório para a emissão: NFC-e acima de R$ ' . number_format($limite, 2, ',', '.')
                . ' exige o CPF do cliente.';
        }

        return $venda;
    }

    /** Operador informa o CPF (e o nome) do cliente de uma conta de mesa ou de um pedido do iFood. */
    public function salvarCpf(string $tipo, int $id, string $cpf, string $nome): void
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (!Condutor::cpfValido($cpf)) {
            throw new DomainException('CPF inválido.');
        }
        $nome = mb_substr(trim($nome), 0, 60);

        if ($tipo === self::MESA) {
            $conta = $this->conta($id);
            if ($conta->venda_uuid) {
                throw new DomainException('Esta conta já foi emitida: o CPF não pode mais ser alterado.');
            }
            $conta->forceFill(['dados_nfce' => array_filter(['cpf' => $cpf, 'nome' => $nome])])->save();

            return;
        }

        $pedido = $this->pedidoIfood($id);
        $ifood = $pedido->ifood ?? throw new DomainException('Pedido do iFood não encontrado.');
        if ($pedido->venda_uuid) {
            throw new DomainException('Este pedido já foi emitido: o CPF não pode mais ser alterado.');
        }
        $ifood->forceFill(['dados_nfce' => array_merge((array) ($ifood->dados_nfce ?? []), array_filter(['cpf' => $cpf, 'nome' => $nome]))])->save();

        if ($ifood->ifood_cliente_id) {
            \App\Models\IfoodCliente::whereKey($ifood->ifood_cliente_id)->update(['cpf' => $cpf]);
        }
    }

    private function vendaDoIfood(ComandaPedido $pedido): array
    {
        $ifood = $pedido->ifood;
        $rotulo = 'iFood #' . ($ifood?->display_id ?? $pedido->id);
        [$itens, $total, $problemas] = $this->itens($pedido->itens, 'Ligue o item a um produto no Cardápio iFood.');

        // Pagamentos do iFood → formas do caixa pelo meio (tPag), no valor dos itens (sem taxa de entrega e descontos)
        $origem = collect($ifood?->pagamentos ?? [])->filter(fn ($p) => (float) ($p['valor'] ?? 0) > 0)->values();
        $somaOrigem = round((float) $origem->sum(fn ($p) => (float) $p['valor']), 2);
        $pagamentos = [];

        if ($origem->isEmpty() || $somaOrigem <= 0) {
            $problemas[] = 'O pedido não tem forma de pagamento informada pelo iFood.';
        } else {
            $restante = $total;

            foreach ($origem as $i => $p) {
                $codigo = $this->formaDoMeio($p['metodo'] ?? 'OTHER');

                if (!$codigo) {
                    $meio = FormasCaixa::MEIOS[self::MEIOS_IFOOD[$p['metodo'] ?? 'OTHER'][0] ?? '99'] ?? 'Outros';
                    $problemas[] = "Não há forma de pagamento com o meio “{$meio}” no caixa (Cadastros → Caixa → Formas de pagamento).";
                    continue;
                }

                $valor = $i === $origem->count() - 1
                    ? round($restante, 2)
                    : round((float) $p['valor'] * $total / $somaOrigem, 2);
                $restante -= $valor;

                if ($valor > 0) {
                    $existente = array_search($codigo, array_column($pagamentos, 'forma_pagamento'), true);
                    if ($existente !== false) {
                        $pagamentos[$existente]['valor'] = round($pagamentos[$existente]['valor'] + $valor, 2);
                    } else {
                        $pagamentos[] = ['forma_pagamento' => $codigo, 'valor' => $valor];
                    }
                }
            }
        }

        // Venda pela plataforma: o iFood é sempre o intermediador. Entrega = indPres 4 (entrega em domicílio), que
        // exige destinatário com CPF e endereço (rejeições 787/788): sem eles vira pendência, e o operador informa
        // os dados na janela da pendência. Retirada/consumo no local = presencial (1).
        $entrega = ($ifood?->tipo ?? 'DELIVERY') === 'DELIVERY';
        $form = $this->formDestinatario($ifood);
        $cpf = Condutor::cpfValido($form['cpf']) ? $form['cpf'] : null;
        $destinatario = null;
        $pedeDestinatario = false;

        if ($entrega) {
            [$destinatario, $faltando] = $this->destinatario($form);
            if ($faltando) {
                $pedeDestinatario = true;
                $problemas[] = 'Entrega do iFood: a NFC-e de entrega a domicílio exige o CPF e o endereço do cliente. '
                    . implode(' ', $faltando);
            }
        }

        return $this->comCpfObrigatorio([
            'rotulo'     => mb_substr($rotulo, 0, 60),
            'momento'    => $pedido->enviado_em,
            'itens'      => $itens,
            'total'      => $total,
            'pagamentos' => $pagamentos,
            'troco'      => 0.0,
            'cpf'        => $cpf,
            // NFC-e só aceita presencial (1) ou entrega em domicílio (4)
            'ind_pres'   => $entrega ? 4 : 1,
            'destinatario' => $destinatario,
            'pede_destinatario' => $pedeDestinatario,
            'form_destinatario' => $entrega ? $form : null,
            'intermediador_cnpj' => IfoodPedido::CNPJ_INTERMEDIADOR,
            'intermediador_id'   => $ifood?->merchant_id ? mb_substr($ifood->merchant_id, 0, 60) : null,
            'resumo_pagamento'   => $ifood ? collect($ifood->paraTela()['pagamentos'])->pluck('descricao')->implode(', ') : '',
            'problemas'  => array_values(array_unique($problemas)),
        ], $form, max($total, (float) $pedido->total));
    }

    public const CAMPOS_DESTINATARIO = ['cpf', 'nome', 'logradouro', 'numero', 'complemento', 'bairro', 'cep', 'cidade', 'uf'];

    /** Dados do cliente para a NFC-e: o que o operador informou vale sobre o que veio do iFood. */
    private function formDestinatario(?IfoodPedido $ifood): array
    {
        $p = (array) ($ifood?->payload ?? []);
        $end = (array) data_get($p, 'delivery.deliveryAddress', []);
        $doIfood = [
            'cpf'         => preg_replace('/\D/', '', (string) data_get($p, 'customer.documentNumber')),
            'nome'        => trim((string) data_get($p, 'customer.name')),
            'logradouro'  => trim((string) ($end['streetName'] ?? '')),
            'numero'      => trim((string) ($end['streetNumber'] ?? '')),
            'complemento' => trim((string) ($end['complement'] ?? '')),
            'bairro'      => trim((string) ($end['neighborhood'] ?? '')),
            'cep'         => preg_replace('/\D/', '', (string) ($end['postalCode'] ?? '')),
            'cidade'      => trim((string) ($end['city'] ?? '')),
            'uf'          => mb_strtoupper(trim((string) ($end['state'] ?? ''))),
        ];

        $informado = collect((array) ($ifood?->dados_nfce ?? []))->only(self::CAMPOS_DESTINATARIO)
            ->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '')->all();

        return array_merge($doIfood, $informado);
    }

    /**
     * Destinatário da NFC-e de entrega, com o município achado no cadastro do IBGE pela cidade e UF.
     *
     * @return array{0: ?array, 1: array<int, string>} [destinatário, o que falta]
     */
    private function destinatario(array $f): array
    {
        $faltando = [];
        $cep = preg_replace('/\D/', '', $f['cep']);

        if ($f['cpf'] === '') {
            $faltando[] = 'Falta o CPF do cliente.';
        } elseif (!Condutor::cpfValido($f['cpf'])) {
            $faltando[] = 'O CPF do cliente é inválido.';
        }
        if ($f['logradouro'] === '' || $f['bairro'] === '') {
            $faltando[] = 'Falta a rua ou o bairro.';
        }
        if (strlen($cep) !== 8) {
            $faltando[] = 'O CEP precisa ter 8 dígitos.';
        }

        $municipio = strlen($f['uf']) === 2 && $f['cidade'] !== ''
            ? \App\Models\Municipio::where('uf', $f['uf'])->where('nome_busca', \App\Models\Municipio::normalizar($f['cidade']))->first()
            : null;
        if (!$municipio) {
            $faltando[] = 'Cidade/UF não encontrada no cadastro de municípios do IBGE.';
        }

        if ($faltando) {
            return [null, $faltando];
        }

        return [[
            'cpf'     => $f['cpf'],
            'nome'    => mb_substr($f['nome'] ?: 'CONSUMIDOR', 0, 60),
            'xLgr'    => mb_substr($f['logradouro'], 0, 60),
            'nro'     => mb_substr($f['numero'] ?: 'S/N', 0, 60),
            'xCpl'    => $f['complemento'] !== '' ? mb_substr($f['complemento'], 0, 60) : null,
            'xBairro' => mb_substr($f['bairro'], 0, 60),
            'cMun'    => $municipio->codigo_ibge,
            'xMun'    => $municipio->nome,
            'UF'      => $f['uf'],
            'CEP'     => $cep,
        ], []];
    }

    /**
     * Operador informa o CPF/endereço do cliente de um pedido de entrega do iFood (janela da pendência no PDV).
     * Devolve o que ainda falta para emitir (vazio = liberado).
     */
    public function salvarDestinatario(int $pedidoId, array $dados): array
    {
        $pedido = $this->pedidoIfood($pedidoId);
        $ifood = $pedido->ifood ?? throw new DomainException('Pedido do iFood não encontrado.');

        if ($pedido->venda_uuid) {
            throw new DomainException('Este pedido já foi emitido: os dados do cliente não podem mais ser alterados.');
        }

        $limpo = collect($dados)->only(self::CAMPOS_DESTINATARIO)->map(fn ($v) => mb_substr(trim((string) $v), 0, 60));
        $limpo['cpf'] = preg_replace('/\D/', '', (string) ($limpo['cpf'] ?? ''));
        $limpo['cep'] = preg_replace('/\D/', '', (string) ($limpo['cep'] ?? ''));
        $limpo['uf'] = mb_strtoupper((string) ($limpo['uf'] ?? ''));

        if ($limpo['cpf'] !== '' && !Condutor::cpfValido($limpo['cpf'])) {
            throw new DomainException('CPF inválido.');
        }

        $ifood->forceFill(['dados_nfce' => $limpo->all()])->save();

        // O CPF informado também vai para o cadastro do cliente do iFood
        if ($limpo['cpf'] !== '' && $ifood->ifood_cliente_id) {
            \App\Models\IfoodCliente::whereKey($ifood->ifood_cliente_id)->update(['cpf' => $limpo['cpf']]);
        }

        return $this->destinatario($this->formDestinatario($ifood->refresh()))[1];
    }

    /**
     * Itens da NFC-e: o mesmo produto com o mesmo preço vira uma linha. Quando o preço do pedido não fecha
     * (ex.: R$ 10,00 em 3 unidades), o unitário sobe um centavo e a diferença vai como desconto do item.
     * Item de valor zero não entra (não muda o total e a NFC-e não aceita item sem valor).
     *
     * @return array{0: array, 1: float, 2: array}
     */
    private function itens(Collection $itens, string $dica): array
    {
        $problemas = [];
        $grupos = [];

        foreach ($itens as $item) {
            if ((float) $item->subtotal <= 0) {
                continue;
            }

            if (!$item->produto_id) {
                $problemas[] = "“{$item->nome}” não está ligado a um produto do sistema. {$dica}";
                continue;
            }

            $chave = $item->produto_id . '|' . ($item->produto_variante_id ?? 0) . '|' . $item->preco_unitario;
            $grupos[$chave] ??= [
                'produto_id' => (int) $item->produto_id,
                'produto_variante_id' => $item->produto_variante_id ? (int) $item->produto_variante_id : null,
                'nome' => $item->nome,
                'preco' => (float) $item->preco_unitario,
                'quantidade' => 0,
                'subtotal' => 0.0,
            ];
            $grupos[$chave]['quantidade'] += (int) $item->quantidade;
            $grupos[$chave]['subtotal'] = round($grupos[$chave]['subtotal'] + (float) $item->subtotal, 2);
        }

        if (!$grupos && !$problemas) {
            $problemas[] = 'O pedido não tem itens com valor.';
        }

        // Dados fiscais vêm do cache do caixa
        $db = DB::connection('sqlite_local');
        $produtos = $db->table('produtos_cache')->whereIn('id', array_column($grupos, 'produto_id'))->get()->keyBy('id');
        $variantes = $db->table('produto_variantes_cache')
            ->whereIn('id', array_filter(array_column($grupos, 'produto_variante_id')))->pluck('id')->flip();

        $saida = [];
        $total = 0.0;

        foreach ($grupos as $g) {
            $p = $produtos->get($g['produto_id']);

            if (!$p || !$p->ativo) {
                $problemas[] = "“{$g['nome']}”: produto inativo ou ausente neste caixa. Use \"Atualizar Caixa\".";
                continue;
            }
            if ($g['produto_variante_id'] && !$variantes->has($g['produto_variante_id'])) {
                $problemas[] = "“{$g['nome']}”: variação ausente neste caixa. Use \"Atualizar Caixa\".";
                continue;
            }

            $qtd = $g['quantidade'];
            $preco = round($g['preco'], 2);

            if (abs(round($preco * $qtd, 2) - $g['subtotal']) >= 0.005) {
                $preco = ceil(round($g['subtotal'] / $qtd * 100, 6)) / 100;
            }

            $saida[] = [
                'produto_id' => $g['produto_id'],
                'produto_variante_id' => $g['produto_variante_id'],
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'desconto' => max(0, round(round($preco * $qtd, 2) - $g['subtotal'], 2)),
            ];
            $total += $g['subtotal'];
        }

        return [$saida, round($total, 2), $problemas];
    }

    /** Forma ativa do caixa com o meio de pagamento do iFood; sem ela, uma forma de meio 99 (Outros). */
    private function formaDoMeio(string $metodo): ?string
    {
        $formas = FormasCaixa::ativas('caixa')->reject(fn ($f) => in_array($f['tipo'], FormasCaixa::TIPOS_ESPECIAIS, true));

        foreach ([...(self::MEIOS_IFOOD[$metodo] ?? ['99']), '99'] as $meio) {
            $forma = $formas->firstWhere('meio_pagamento', $meio);
            if ($forma) {
                return $forma['codigo'];
            }
        }

        return null;
    }

    private function linha(string $tipo, Comanda|ComandaPedido $registro, array $venda): array
    {
        return [
            'tipo'       => $tipo,
            'id'         => $registro->id,
            'rotulo'     => $venda['rotulo'],
            'momento'    => $venda['momento']?->toIso8601String(),
            'hora'       => $venda['momento']?->format('H:i'),
            'itens'      => (int) array_sum(array_column($venda['itens'], 'quantidade')),
            'total'      => $venda['total'],
            'pagamento'  => $venda['resumo_pagamento'],
            'venda_uuid' => $registro->venda_uuid,
            'problemas'  => $venda['problemas'],
            // Entrega do iFood sem CPF/endereço: a janela da pendência abre o formulário com estes dados
            'pede_destinatario' => (bool) ($venda['pede_destinatario'] ?? false),
            'destinatario'      => ($venda['pede_destinatario'] ?? false) ? $venda['form_destinatario'] : null,
            // Acima do limite sem CPF: a janela da pendência pede só o CPF (e o nome)
            'pede_cpf'          => (bool) ($venda['pede_cpf'] ?? false),
            'cpf_form'          => $venda['form_cpf'] ?? null,
        ];
    }

    /** Situação de cada pedido: a emitir, com problema, emitido aqui (com a NFC-e) ou emitido em outro caixa. */
    private function comSituacaoNoCaixa(Collection $linhas): Collection
    {
        $uuids = $linhas->pluck('venda_uuid')->filter()->values();
        $locais = $uuids->isEmpty() ? collect() : DB::connection('sqlite_local')->table('vendas_pendentes')
            ->whereIn('uuid', $uuids)->get(['uuid', 'status_fiscal', 'numero_nfce', 'motivo_rejeicao'])->keyBy('uuid');

        return $linhas->map(function (array $l) use ($locais) {
            $local = $l['venda_uuid'] ? $locais->get($l['venda_uuid']) : null;

            $l['situacao'] = match (true) {
                !$l['venda_uuid'] => $l['problemas'] ? 'bloqueado' : 'pendente',
                !$local => 'outro_caixa',
                default => match ($local->status_fiscal) {
                    'emitida' => 'emitida',
                    'contingencia' => 'contingencia',
                    'cancelada' => 'cancelada',
                    default => 'nao_emitida',
                },
            };
            $l['numero_nfce'] = $local?->numero_nfce;
            $l['motivo'] = $local && $local->status_fiscal !== 'emitida' ? $local->motivo_rejeicao : null;

            return $l;
        });
    }
}
