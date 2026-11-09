<?php

namespace App\Services;

use App\Models\CardapioItem;
use App\Models\ComandaImpressora;
use App\Models\ComandaPedido;
use App\Models\ComandaPedidoItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Impressão das comandas direto do servidor nas impressoras térmicas (ESC/POS), sem a janela do navegador:
 *  - rede: envia para IP:porta (padrão 9100, o "RAW" das térmicas de rede);
 *  - USB:  impressora compartilhada no Windows do servidor (\\localhost\NomeCompartilhado)
 *          ou caminho do dispositivo no Linux (/dev/usb/lp0).
 * Cada impressora recebe só os itens das categorias dela (ou o pedido completo).
 */
class ImpressaoComandaService
{
    private const TEMPO_LIMITE = 4; // segundos para conectar em cada impressora

    // Comandos ESC/POS
    private const INICIO        = "\x1B@";
    private const CENTRO        = "\x1Ba\x01";
    private const ESQUERDA      = "\x1Ba\x00";
    private const NEGRITO       = "\x1BE\x01";
    private const SEM_NEGRITO   = "\x1BE\x00";
    private const GRANDE        = "\x1D!\x11"; // largura e altura dobradas
    private const ALTO          = "\x1D!\x01"; // só altura dobrada
    private const NORMAL        = "\x1D!\x00";
    private const AVANCAR       = "\x1Bd\x04";
    private const CORTAR        = "\x1DVA\x03";

    /**
     * Imprime o pedido nas impressoras ativas (ou só na indicada). Grava no pedido quando imprimiu ou o erro.
     *
     * @return array<int, array{impressora: string, ok: bool, itens: int, erro: ?string}>
     */
    public function imprimirPedido(ComandaPedido $pedido, ?ComandaImpressora $somente = null, bool $reimpressao = false): array
    {
        $pedido->loadMissing(['itens', 'mesa', 'garcom', 'comanda.pedidos:id,comanda_id', 'ifood']);
        $impressoras = $somente ? collect([$somente->loadMissing('categorias')])
            : ComandaImpressora::with('categorias')->where('ativo', true)->orderBy('nome')->get();

        // Categoria atual de cada item no cardápio
        $categorias = CardapioItem::whereIn('id', $pedido->itens->pluck('cardapio_item_id')->filter())
            ->pluck('cardapio_categoria_id', 'id');

        $resultados = [];
        foreach ($impressoras as $impressora) {
            $itens = $this->itensDaImpressora($pedido->itens, $impressora, $categorias);

            if ($itens->isEmpty()) {
                continue; // nada deste pedido é desta impressora
            }

            try {
                $this->enviar($impressora, $this->montarComanda($pedido, $impressora, $itens, $reimpressao));
                $resultados[] = ['impressora' => $impressora->nome, 'ok' => true, 'itens' => $itens->count(), 'erro' => null];
            } catch (Throwable $e) {
                Log::warning("Comanda: falha ao imprimir o pedido #{$pedido->id} em {$impressora->nome}: {$e->getMessage()}");
                $resultados[] = ['impressora' => $impressora->nome, 'ok' => false, 'itens' => $itens->count(), 'erro' => $e->getMessage()];
            }
        }

        $erros = collect($resultados)->where('ok', false)
            ->map(fn ($r) => "{$r['impressora']}: {$r['erro']}")->implode(' | ');

        $pedido->forceFill([
            'impresso_em'    => collect($resultados)->contains('ok', true) ? now() : $pedido->impresso_em,
            'impressao_erro' => $erros !== '' ? mb_substr($erros, 0, 500) : null,
        ])->save();

        return $resultados;
    }

    /**
     * Aviso de PEDIDO CANCELADO (cancelado pelo cliente/iFood depois de chegar): sai nas mesmas impressoras
     * que receberam o pedido, com os itens de cada setor, para a cozinha parar o preparo.
     */
    public function imprimirCancelamento(ComandaPedido $pedido, ?string $motivo = null): array
    {
        $pedido->loadMissing(['itens', 'mesa', 'ifood']);
        $categorias = CardapioItem::whereIn('id', $pedido->itens->pluck('cardapio_item_id')->filter())
            ->pluck('cardapio_categoria_id', 'id');

        $resultados = [];
        foreach (ComandaImpressora::with('categorias')->where('ativo', true)->orderBy('nome')->get() as $impressora) {
            $itens = $this->itensDaImpressora($pedido->itens, $impressora, $categorias);
            if ($itens->isEmpty()) {
                continue;
            }

            $l = $impressora->colunas;
            $traco = str_repeat('-', $l) . "\n";
            $f = $pedido->ifood;
            $via = self::INICIO . self::CENTRO
                . self::GRANDE . $this->texto('PEDIDO') . "\n" . $this->texto('CANCELADO') . "\n" . self::NORMAL
                . self::NEGRITO . $this->texto(($f ? 'iFood #' . ($f->display_id ?: $pedido->id) . ' - ' : '') . "Pedido #{$pedido->id}") . self::SEM_NEGRITO . "\n"
                . ($f?->cliente_nome ? $this->texto('Cliente: ' . $f->cliente_nome) . "\n" : '')
                . $this->texto('Cancelado em ' . now()->format('d/m/Y H:i')) . "\n"
                . self::ESQUERDA . $traco
                . ($motivo ? $this->quebrar('Motivo: ' . $motivo, $l) . $traco : '')
                . self::NEGRITO . $this->texto('NAO PREPARAR / DESCARTAR:') . self::SEM_NEGRITO . "\n";
            foreach ($itens as $item) {
                $via .= self::NEGRITO . $this->quebrar("{$item->quantidade}x {$item->nome}", $l) . self::SEM_NEGRITO;
            }
            $via .= $this->rodape($l) . self::AVANCAR . self::CORTAR;

            try {
                $this->enviar($impressora, $via);
                $resultados[] = ['impressora' => $impressora->nome, 'ok' => true, 'erro' => null];
            } catch (Throwable $e) {
                Log::warning("Comanda: falha ao imprimir o cancelamento do pedido #{$pedido->id} em {$impressora->nome}: {$e->getMessage()}");
                $resultados[] = ['impressora' => $impressora->nome, 'ok' => false, 'erro' => $e->getMessage()];
            }
        }

        return $resultados;
    }

    /** Página de teste: confere a conexão e a largura do papel. */
    public function testar(ComandaImpressora $impressora): void
    {
        $l = $impressora->colunas;
        $dados = self::INICIO . self::CENTRO . self::GRANDE . $this->texto('TESTE') . "\n" . self::NORMAL
            . self::NEGRITO . $this->texto($impressora->nome) . self::SEM_NEGRITO . "\n"
            . $this->texto($impressora->descricaoConexao()) . "\n"
            . $this->texto(now()->format('d/m/Y H:i:s')) . "\n"
            . self::ESQUERDA . str_repeat('-', $l) . "\n"
            . substr(str_repeat('1234567890', 8), 0, $l) . "\n"
            . self::CENTRO . $this->texto("Papel de {$l} colunas: a linha de numeros acima deve caber inteira.") . "\n"
            . $this->texto('Acentos removidos: Acai, Pao de queijo, Coracao') . "\n"
            . self::AVANCAR . self::CORTAR;

        $this->enviar($impressora, $dados);
    }

    /** Itens do pedido que esta impressora recebe. */
    private function itensDaImpressora(Collection $itens, ComandaImpressora $impressora, Collection $categorias): Collection
    {
        return $itens->filter(fn (ComandaPedidoItem $i) => $impressora->recebe(
            $i->cardapio_item_id && $categorias->has($i->cardapio_item_id) ? $categorias[$i->cardapio_item_id] : null
        ))->values();
    }

    private function montarComanda(ComandaPedido $pedido, ComandaImpressora $impressora, Collection $itens, bool $reimpressao): string
    {
        if ($pedido->origem === 'ifood' && $pedido->ifood) {
            return $this->montarComandaIfood($pedido, $impressora, $itens, $reimpressao);
        }

        $l = $impressora->colunas;
        $metade = intdiv($l, 2);
        $traco = str_repeat('-', $l) . "\n";
        $numeroNaMesa = $pedido->comanda ? $pedido->comanda->pedidos->where('id', '<=', $pedido->id)->count() : null;

        $via = self::INICIO;
        if ($reimpressao) {
            $via .= self::CENTRO . self::NEGRITO . $this->texto('*** REIMPRESSAO ***') . self::SEM_NEGRITO . "\n";
        }
        $via .= self::CENTRO . self::NEGRITO . $this->texto(mb_strtoupper($impressora->nome)) . self::SEM_NEGRITO . "\n"
            . self::GRANDE . $this->quebrar(mb_strtoupper($pedido->mesa?->titulo() ?? 'Pedido'), $metade) . self::NORMAL
            . self::NEGRITO . $this->texto("Pedido #{$pedido->id}" . ($numeroNaMesa ? " - {$numeroNaMesa}o da mesa" : '')) . self::SEM_NEGRITO . "\n"
            . self::ESQUERDA
            . $this->texto(($pedido->enviado_em ?? now())->format('d/m/Y H:i')) . "\n"
            . ($pedido->garcom ? $this->texto('Garcom: ' . $pedido->garcom->name) . "\n" : '')
            . ($pedido->cliente_nome ? $this->texto('Cliente: ' . $pedido->cliente_nome) . "\n" : '')
            . $traco;

        foreach ($itens as $item) {
            $via .= self::ALTO . self::NEGRITO . $this->quebrar("{$item->quantidade}x {$item->nome}", $l) . self::SEM_NEGRITO . self::NORMAL;
            if ($item->observacao) {
                $via .= $this->quebrar('   Obs.: ' . $item->observacao, $l);
            }
            if ($impressora->mostrar_precos) {
                $via .= $this->colunasDireita('   ' . $this->moeda($item->preco_unitario) . ' cada', $this->moeda($item->subtotal), $l);
            }
        }

        $via .= $traco;
        if ($itens->count() < $pedido->itens->count()) {
            $via .= $this->texto("Itens deste setor: {$itens->count()} de {$pedido->itens->count()}") . "\n";
        }
        if ($impressora->mostrar_precos) {
            $via .= self::NEGRITO . $this->colunasDireita('TOTAL', $this->moeda($itens->sum('subtotal')), $l) . self::SEM_NEGRITO;
        }
        $via .= $this->rodape($l) . self::AVANCAR . self::CORTAR;

        return str_repeat($via, max(1, $impressora->copias));
    }

    /**
     * Comanda do pedido do iFood: empresa, número do iFood e do sistema, data/hora, cliente (nome, telefone, CPF na nota),
     * endereço, itens com observações, valores, se já foi pago, forma de pagamento e observações do pedido.
     */
    private function montarComandaIfood(ComandaPedido $pedido, ComandaImpressora $impressora, Collection $itens, bool $reimpressao): string
    {
        $f = $pedido->ifood;
        $l = $impressora->colunas;
        $metade = intdiv($l, 2);
        $traco = str_repeat('-', $l) . "\n";
        $titulo = fn (string $t) => self::NEGRITO . $this->texto(mb_strtoupper($t)) . self::SEM_NEGRITO . "\n";
        $linha = fn (string $rotulo, ?string $valor) => filled($valor) ? $this->quebrar("{$rotulo}: {$valor}", $l) : '';
        $empresa = \App\Models\Empresa::atual();
        $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;
        $parcial = $itens->count() < $pedido->itens->count();

        $tipo = [
            'DELIVERY' => 'ENTREGA', 'TAKEOUT' => 'RETIRADA NO LOCAL', 'DINE_IN' => 'COMER NO LOCAL', 'INDOOR' => 'NO LOCAL',
        ][$f->tipo] ?? ($f->tipo ?: 'PEDIDO');
        if ($f->tipo === 'DELIVERY' && $f->entregue_por) {
            $tipo .= $f->entregue_por === 'IFOOD' ? ' (ENTREGADOR IFOOD)' : ' (ENTREGA PROPRIA)';
        }

        $documento = preg_replace('/\D/', '', (string) data_get($f->payload, 'customer.documentNumber', ''));
        if (strlen($documento) === 11) {
            $documento = substr($documento, 0, 3) . '.' . substr($documento, 3, 3) . '.' . substr($documento, 6, 3) . '-' . substr($documento, 9);
        } elseif (strlen($documento) === 14) {
            $documento = substr($documento, 0, 2) . '.' . substr($documento, 2, 3) . '.' . substr($documento, 5, 3) . '/' . substr($documento, 8, 4) . '-' . substr($documento, 12);
        }

        // ===== Topo =====
        $via = self::INICIO . self::CENTRO;
        if ($reimpressao) {
            $via .= self::NEGRITO . $this->texto('*** REIMPRESSAO ***') . self::SEM_NEGRITO . "\n";
        }
        if ($nomeEmpresa) {
            $via .= self::NEGRITO . $this->quebrar(mb_strtoupper($nomeEmpresa), $l) . self::SEM_NEGRITO;
        }
        $via .= self::GRANDE . $this->texto('PEDIDO IFOOD') . "\n"
            . ($f->display_id ? $this->texto('#' . $f->display_id) . "\n" : '') . self::NORMAL
            . self::NEGRITO . $this->texto($tipo) . self::SEM_NEGRITO . "\n"
            . ($parcial ? $this->texto('Setor: ' . mb_strtoupper($impressora->nome)) . "\n" : '')
            . self::ESQUERDA . $traco
            . $linha('Pedido no sistema', '#' . $pedido->id)
            . $linha('Feito em', ($f->criado_ifood_em ?? $pedido->enviado_em ?? now())->timezone(config('app.timezone'))->format('d/m/Y H:i'))
            . ($f->agendado_para ? self::NEGRITO . $linha('AGENDADO PARA', $f->agendado_para->timezone(config('app.timezone'))->format('d/m/Y H:i')) . self::SEM_NEGRITO : '')
            . $traco;

        // ===== Cliente =====
        $via .= $titulo('Cliente')
            . $linha('Nome', $f->cliente_nome ?? $pedido->cliente_nome)
            . $linha('Telefone', $f->cliente_telefone ? $f->cliente_telefone . ($f->localizador ? " (localizador {$f->localizador})" : '') : null)
            . ($documento !== '' ? $linha('CPF/CNPJ na nota', $documento) : '')
            . $traco;

        // ===== Endereço / retirada =====
        if ($f->endereco || $f->codigo_retirada) {
            $via .= $titulo($f->tipo === 'DELIVERY' ? 'Endereco de entrega' : 'Retirada')
                . ($f->endereco ? $this->quebrar($f->endereco, $l) : '')
                . $linha('Codigo de retirada', $f->codigo_retirada)
                . $traco;
        }

        // ===== Itens =====
        $via .= $titulo('Itens' . ($parcial ? " deste setor ({$itens->count()} de {$pedido->itens->count()})" : ''));
        foreach ($itens as $item) {
            // Mesmo tamanho do resto da comanda (só em negrito)
            $via .= self::NEGRITO . $this->colunasDireita("{$item->quantidade}x {$item->nome}", $this->moeda($item->subtotal), $l) . self::SEM_NEGRITO;
            if ($item->observacao) {
                $via .= $this->quebrar('   Obs.: ' . $item->observacao, $l);
            }
        }
        $via .= $traco;

        // ===== Valores e pagamento (do pedido inteiro) =====
        $via .= $this->colunasDireita('Subtotal', $this->moeda($f->subtotal), $l)
            . ((float) $f->taxa_entrega > 0 ? $this->colunasDireita('Taxa de entrega', $this->moeda($f->taxa_entrega), $l) : '')
            . ((float) $f->taxas_adicionais > 0 ? $this->colunasDireita('Taxas adicionais', $this->moeda($f->taxas_adicionais), $l) : '')
            . ((float) $f->descontos > 0 ? $this->colunasDireita('Descontos', '-' . $this->moeda($f->descontos), $l) : '')
            . self::NEGRITO . $this->colunasDireita('TOTAL', $this->moeda($f->total), $l) . self::SEM_NEGRITO
            . $traco;

        $cobrar = (float) $f->cobrar_na_entrega;
        $via .= self::CENTRO . self::GRANDE
            . $this->texto($cobrar > 0 ? 'NAO PAGO' : 'PAGO') . "\n" . self::NORMAL
            . self::NEGRITO . $this->texto($cobrar > 0 ? 'Cobrar na entrega: ' . $this->moeda($cobrar) : 'Pago online pelo app iFood') . self::SEM_NEGRITO . "\n"
            . self::ESQUERDA
            . $titulo('Forma de pagamento');

        $metodos = \App\Models\IfoodPedido::METODOS;
        foreach ($f->pagamentos ?? [] as $pg) {
            $descricao = ($metodos[$pg['metodo'] ?? ''] ?? ($pg['metodo'] ?? 'Pagamento'))
                . (!empty($pg['bandeira']) ? ' ' . $pg['bandeira'] : '')
                . (($pg['tipo'] ?? '') === 'ONLINE' ? ' (online)' : ' (na entrega)');
            $via .= $this->colunasDireita($descricao, $this->moeda($pg['valor'] ?? 0), $l);
            if (!empty($pg['troco_para'])) {
                $troco = max(0, (float) $pg['troco_para'] - (float) ($pg['valor'] ?? 0));
                $via .= $this->quebrar('   Troco para ' . $this->moeda($pg['troco_para']) . ' (levar ' . $this->moeda($troco) . ')', $l);
            }
        }
        if (empty($f->pagamentos)) {
            $via .= $this->texto('Nao informada') . "\n";
        }

        // ===== Observações =====
        if (filled($f->observacoes)) {
            $via .= $traco . $titulo('Observacoes') . $this->quebrar($f->observacoes, $l);
        }

        $via .= $this->rodape($l) . self::AVANCAR . self::CORTAR;

        return str_repeat($via, max(1, $impressora->copias));
    }

    /** Rodapé de todas as comandas: o nome do sistema, centralizado. */
    private function rodape(int $largura): string
    {
        return str_repeat('-', $largura) . "
" . self::CENTRO . $this->texto('Cyberly Sistema') . "
" . self::ESQUERDA;
    }

    /** Manda os bytes para a impressora (rede ou USB). */
    private function enviar(ComandaImpressora $impressora, string $dados): void
    {
        if ($impressora->conexao === ComandaImpressora::REDE) {
            $erroNumero = 0;
            $erroTexto = '';
            $socket = @stream_socket_client("tcp://{$impressora->endereco}:{$impressora->porta}", $erroNumero, $erroTexto, self::TEMPO_LIMITE);
            if (!$socket) {
                throw new RuntimeException("sem resposta em {$impressora->endereco}:{$impressora->porta} (confira se está ligada e na rede)");
            }
            stream_set_timeout($socket, self::TEMPO_LIMITE);
            $escrito = @fwrite($socket, $dados);
            fclose($socket);
            if ($escrito === false || $escrito < strlen($dados)) {
                throw new RuntimeException('a conexão caiu no meio da impressão');
            }

            return;
        }

        $destino = trim($impressora->endereco);

        // Linux: caminho do dispositivo (/dev/usb/lp0)
        if (str_starts_with($destino, '/')) {
            if (@file_put_contents($destino, $dados) === false) {
                throw new RuntimeException("não foi possível escrever em {$destino}");
            }

            return;
        }

        // Windows: impressora compartilhada (\\localhost\Nome ou \\maquina\Nome)
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('impressora USB compartilhada só funciona com o servidor no Windows; no Linux use o caminho do dispositivo (ex.: /dev/usb/lp0)');
        }
        $unc = str_starts_with($destino, '\\\\') ? $destino : '\\\\localhost\\' . $destino;
        $arquivo = tempnam(sys_get_temp_dir(), 'cmd');
        file_put_contents($arquivo, $dados);
        $saida = [];
        $codigo = 1;
        exec('copy /b ' . escapeshellarg($arquivo) . ' ' . escapeshellarg($unc) . ' 2>&1', $saida, $codigo);
        @unlink($arquivo);
        if ($codigo !== 0) {
            throw new RuntimeException("não foi possível enviar para {$unc} (a impressora está compartilhada com esse nome?)");
        }
    }

    // ===== Texto =====

    /** Térmicas baratas erram nos acentos: imprime sem acento (ASCII). */
    private function texto(string $texto): string
    {
        $ascii = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ç' => 'c', 'Ç' => 'C', 'ñ' => 'n', 'Ñ' => 'N', 'º' => 'o', 'ª' => 'a', '—' => '-', '–' => '-', '·' => '-', '×' => 'x',
        ]);

        // Sobra algo fora do ASCII (emoji etc.): troca por "?" e tira caracteres de controle
        return preg_replace(['/[^\x20-\x7E]/u', '/[\x00-\x1F\x7F]/'], ['?', ''], $ascii);
    }

    private function quebrar(string $texto, int $largura): string
    {
        return wordwrap($this->texto($texto), max(8, $largura), "\n", true) . "\n";
    }

    private function colunasDireita(string $esquerda, string $direita, int $largura): string
    {
        $esquerda = $this->texto($esquerda);
        $direita = $this->texto($direita);
        $espaco = $largura - strlen($esquerda) - strlen($direita);

        return $espaco >= 1
            ? $esquerda . str_repeat(' ', $espaco) . $direita . "\n"
            : $esquerda . "\n" . str_pad($direita, $largura, ' ', STR_PAD_LEFT) . "\n";
    }

    private function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
