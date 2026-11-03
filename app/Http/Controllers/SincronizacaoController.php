<?php

namespace App\Http\Controllers;

use App\Services\SyncService;
use App\Services\UsuarioCache;
use App\Support\CentralStatus;
use App\Support\Maquina;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SincronizacaoController extends Controller
{
    // Partes do sincronizarTudo() que aparecem no modal quando falham
    private const PARTES = [
        'empresa'       => 'Dados da empresa',
        'catalogo'      => 'Produtos',
        'clientes'      => 'Clientes',
        'usuarios'      => 'Usuários',
        'pdvs'          => 'PDVs',
        'pedidos'       => 'Pedidos de venda',
        'situacao_fiscal' => 'Situação fiscal das vendas',
        'inutilizacoes' => 'Inutilizações',
        'numeracao'     => 'Numeração da NFC-e',
        'ocorrencias'   => 'Ocorrências do caixa',
    ];

    public function executar()
    {
        try {
            CentralStatus::marcarOnline();
            $resultado = (new SyncService())->sincronizarTudo();
            $servidorFora = CentralStatus::fora();

            return response()->json([
                'sucesso' => true,
                'servidor_fora' => $servidorFora,
                'produtos_atualizados' => $resultado['catalogo']['produtos_atualizados'] ?? 0,
                'catalogo_ok' => $resultado['catalogo']['sucesso'] ?? false,
                'usuarios_ok' => $resultado['usuarios']['sucesso'] ?? false,
                'usuarios_erro' => $resultado['usuarios']['erro'] ?? null,
                'usuarios_atualizados' => $resultado['usuarios']['usuarios_atualizados'] ?? 0,
                'vendas_enviadas' => $resultado['vendas']['enviadas'] ?? 0,
                'vendas_falhas' => $resultado['vendas']['falhas'] ?? 0,
                'vendas_aguardando' => $this->vendasAguardando(),
                'vendas_com_erro' => $this->vendasComErro(),
                'caixas_com_erro' => $this->caixasComErro(),
                'clientes_com_erro' => $this->clientesComErro(),
                // Servidor fora: todas as partes falham pelo mesmo motivo, que o modal já explica
                'partes_com_erro' => $servidorFora ? [] : $this->partesComErro($resultado),
            ]);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $this->motivoLegivel($e->getMessage())], 500);
        }
    }

    /**
     * POST /caixa/primeira-carga — tela de login de um caixa novo (banco do caixa sem usuários).
     * Traz tudo do servidor de uma vez; depois disso o caixa só fala com o servidor no Atualizar Caixa
     * e no agendador. Sem login: só roda enquanto o caixa está vazio.
     */
    public function primeiraCarga()
    {
        if (!UsuarioCache::vazio()) {
            return response()->json(['sucesso' => true, 'ja_carregado' => true]);
        }

        try {
            CentralStatus::marcarOnline();
            $resultado = (new SyncService())->sincronizarTudo();
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $this->motivoLegivel($e->getMessage()), 'detalhe' => $e->getMessage()]);
        }

        if (CentralStatus::fora()) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Não foi possível falar com o servidor. Confira a rede e o endereço do servidor (DB_HOST no .env deste computador).',
                'detalhe' => $resultado['usuarios']['erro'] ?? null,
            ]);
        }

        if (UsuarioCache::vazio()) {
            $mensagem = (string) ($resultado['usuarios']['erro'] ?? '');

            return response()->json([
                'sucesso' => false,
                'erro' => $mensagem !== ''
                    ? $this->motivoLegivel($mensagem)
                    : 'O servidor não tem nenhum usuário ativo com código. Cadastre os operadores no painel.',
                'detalhe' => $mensagem ?: null,
            ]);
        }

        $pdvs = (int) ($resultado['pdvs']['pdvs_atualizados'] ?? 0);
        $avisos = $this->partesComErro($resultado);

        if ($pdvs === 0) {
            $avisos[] = [
                'parte' => 'PDVs',
                'motivo' => 'Nenhum PDV ativo para este computador (' . Maquina::nome() . '). No painel, em PDVs, informe esse nome no campo máquina e clique em Atualizar Caixa.',
                'detalhe' => null,
            ];
        }

        return response()->json([
            'sucesso' => true,
            'usuarios' => (int) ($resultado['usuarios']['usuarios_atualizados'] ?? 0),
            'produtos' => (int) ($resultado['catalogo']['produtos_atualizados'] ?? 0),
            'clientes' => (int) ($resultado['clientes']['clientes_atualizados'] ?? 0),
            'pdvs' => $pdvs,
            'avisos' => $avisos,
        ]);
    }

    // Vendas ainda não enviadas por falta de conexão (não são erro da venda)
    private function vendasAguardando(): int
    {
        return DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('status', 'pendente_sync')
            ->count();
    }

    private function vendasComErro(): array
    {
        return DB::connection('sqlite_local')->table('vendas_pendentes')
            ->where('status', 'erro_sync')
            ->orderBy('vendida_em')
            ->limit(20)
            ->get()
            ->map(fn ($v) => [
                'numero_nfce' => $v->numero_nfce,
                'serie_nfce' => $v->serie_nfce,
                'vendida_em' => $v->vendida_em ? Carbon::parse($v->vendida_em)->format('d/m/Y H:i') : null,
                'total' => 'R$ ' . number_format((float) $v->total, 2, ',', '.'),
                'motivo' => $this->motivoLegivel((string) $v->erro_sync_mensagem),
                'detalhe' => $v->erro_sync_mensagem,
            ])
            ->all();
    }

    private function caixasComErro(): array
    {
        return DB::connection('sqlite_local')->table('caixas_local')
            ->whereNotNull('sync_erro')
            ->where('sync_pendente', true)
            ->get()
            ->map(fn ($c) => [
                'aberto_em' => $c->data_abertura ? Carbon::parse($c->data_abertura)->format('d/m/Y H:i') : null,
                'motivo' => $this->motivoLegivel((string) $c->sync_erro),
                'detalhe' => $c->sync_erro,
            ])
            ->all();
    }

    // Clientes do cadastro rápido do caixa que o servidor recusou
    private function clientesComErro(): array
    {
        return DB::connection('sqlite_local')->table('clientes_pendentes')
            ->whereNull('id_central')
            ->whereNotNull('sync_erro')
            ->get()
            ->map(fn ($c) => [
                'nome' => $c->nome,
                'cpf_cnpj' => $c->cpf_cnpj,
                'motivo' => $this->motivoLegivel((string) $c->sync_erro),
                'detalhe' => $c->sync_erro,
            ])
            ->all();
    }

    private function partesComErro(array $resultado): array
    {
        $erros = [];

        foreach (self::PARTES as $chave => $nome) {
            if (isset($resultado[$chave]) && !($resultado[$chave]['sucesso'] ?? true)) {
                $mensagem = (string) ($resultado[$chave]['erro'] ?? 'sem mensagem');
                $erros[] = ['parte' => $nome, 'motivo' => $this->motivoLegivel($mensagem), 'detalhe' => $mensagem];
            }
        }

        return $erros;
    }

    // Traduz os erros mais comuns do banco para o operador; o texto original vai junto como detalhe
    private function motivoLegivel(string $mensagem): string
    {
        $m = mb_strtolower($mensagem);

        return match (true) {
            str_contains($m, 'foreign key') && str_contains($m, 'operador') =>
                'O operador desta venda não existe no servidor. Confira o cadastro de usuários no painel.',
            str_contains($m, 'foreign key') && str_contains($m, 'cliente') =>
                'O cliente desta venda não existe no servidor. Confira o cadastro de clientes no painel.',
            str_contains($m, 'foreign key') && str_contains($m, 'produto') =>
                'Um produto desta venda não existe mais no servidor. Confira o cadastro de produtos no painel.',
            str_contains($m, 'foreign key') && str_contains($m, 'caixa') =>
                'O caixa desta venda não está registrado no servidor.',
            str_contains($m, 'foreign key') && str_contains($m, 'pedido') =>
                'O pedido de venda ligado a esta venda não existe no servidor.',
            str_contains($m, 'caixa desta venda ainda não foi sincronizado') =>
                'O caixa desta venda ainda não está no servidor. Veja o problema do caixa em "Outros problemas"; a venda sobe depois dele.',
            str_contains($m, 'cliente desta venda (cadastro rápido do caixa)') =>
                'O consumidor cadastrado no caixa para esta venda ainda não está no servidor. Veja o problema do cliente em "Outros problemas"; a venda sobe depois dele.',
            str_contains($m, 'já tem outro caixa aberto') =>
                'O operador deste caixa já tem outro caixa aberto no servidor (em outro PDV). Feche aquele caixa e sincronize de novo.',
            str_contains($m, 'duplicate entry') =>
                'Já existe um registro igual no servidor (possível envio duplicado).',
            str_contains($m, "doesn't have a default value") || str_contains($m, 'cannot be null') =>
                'Falta uma informação obrigatória na venda para gravar no servidor.',
            str_contains($m, 'unknown column') || str_contains($m, "doesn't exist") =>
                'O banco do servidor está desatualizado. Rode a atualização do sistema no servidor (php artisan migrate).',
            str_contains($m, '[2002]') || str_contains($m, 'timed out') || str_contains($m, 'getaddrinfo') || str_contains($m, 'gone away') =>
                'Sem conexão com o servidor.',
            default => $mensagem,
        };
    }
}
