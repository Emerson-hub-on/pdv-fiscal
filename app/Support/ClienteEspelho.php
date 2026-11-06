<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Fornecedor;
use Illuminate\Support\Facades\Log;

/**
 * Mantém um cadastro de cliente para cada fornecedor e para a própria empresa,
 * porque há NF-e que têm um deles como destinatário (devolução de compra, remessa, transferência...).
 *
 * O vínculo é pelo CPF/CNPJ (único em clientes):
 *  - não existe cliente com o documento: cria, ativo;
 *  - já existe: atualiza nome, inscrição estadual e endereço com o que veio preenchido;
 *    e-mail e telefone só entram se o cliente estiver sem (o contato de vendas pode ser outro).
 *  - nunca inativa nem apaga o cliente.
 * Chamado nos eventos "saved" de Fornecedor e Empresa; uma falha aqui não impede gravar o cadastro de origem.
 */
class ClienteEspelho
{
    private const CAMPOS_FISCAIS = [
        'tipo_pessoa', 'nome', 'nome_fantasia', 'indicador_ie', 'ie',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'cod_municipio', 'uf',
    ];

    private const CAMPOS_CONTATO = ['email', 'telefone'];

    public static function deFornecedor(Fornecedor $fornecedor): ?Cliente
    {
        return self::sincronizar(
            $fornecedor->only(array_merge(self::CAMPOS_FISCAIS, self::CAMPOS_CONTATO)) + ['cpf_cnpj' => $fornecedor->cpf_cnpj],
            "fornecedor #{$fornecedor->id}"
        );
    }

    public static function daEmpresa(Empresa $empresa): ?Cliente
    {
        $ie = trim((string) $empresa->ie);

        return self::sincronizar([
            'cpf_cnpj'      => $empresa->cnpj,
            'tipo_pessoa'   => 'juridica',
            'nome'          => $empresa->razao_social,
            'nome_fantasia' => $empresa->nome_fantasia,
            'indicador_ie'  => $ie === '' ? 'nao_contribuinte' : (strtoupper($ie) === 'ISENTO' ? 'isento' : 'contribuinte'),
            'ie'            => ($ie === '' || strtoupper($ie) === 'ISENTO') ? null : $ie,
        ] + $empresa->only(['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'cod_municipio', 'uf']), 'empresa');
    }

    private static function sincronizar(array $dados, string $origem): ?Cliente
    {
        $documento = preg_replace('/\D/', '', (string) ($dados['cpf_cnpj'] ?? ''));

        if (!in_array(strlen($documento), [11, 14], true) || trim((string) ($dados['nome'] ?? '')) === '') {
            return null; // sem documento válido ou sem nome não há o que espelhar
        }

        $dados['cpf_cnpj'] = $documento;
        $dados['tipo_pessoa'] = $dados['tipo_pessoa'] ?? (strlen($documento) === 11 ? 'fisica' : 'juridica');
        $preenchido = fn ($v) => $v !== null && trim((string) $v) !== '';

        try {
            $cliente = Cliente::where('cpf_cnpj', $documento)->first();

            if (!$cliente) {
                return Cliente::create(array_filter($dados, $preenchido) + ['ativo' => true]);
            }

            foreach (self::CAMPOS_FISCAIS as $campo) {
                if ($preenchido($dados[$campo] ?? null)) {
                    $cliente->{$campo} = $dados[$campo];
                }
            }
            // Contribuinte que deixou de ter IE: a inscrição antiga não pode ficar
            if (($dados['indicador_ie'] ?? null) && $dados['indicador_ie'] !== 'contribuinte') {
                $cliente->ie = null;
            }
            foreach (self::CAMPOS_CONTATO as $campo) {
                if (!$preenchido($cliente->{$campo}) && $preenchido($dados[$campo] ?? null)) {
                    $cliente->{$campo} = $dados[$campo];
                }
            }

            if ($cliente->isDirty()) {
                $cliente->save();
            }

            return $cliente;
        } catch (\Throwable $e) {
            Log::warning("Não foi possível manter o cliente espelho do {$origem}: {$e->getMessage()}");

            return null;
        }
    }
}
