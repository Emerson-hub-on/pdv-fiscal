<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Pdv;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Monta, a partir do SQLite, os mesmos objetos (Empresa e Pdv) que o NfeService
 * usa quando lê do servidor. Os models ficam só em memória: nada é gravado no MySQL.
 */
class DadosFiscaisLocais
{
    public static function empresa(): Empresa
    {
        $linha = DB::connection('sqlite_local')->table('empresa_cache')->first();

        if (!$linha) {
            throw new Exception('Dados da empresa ainda não foram sincronizados com o caixa. Conecte ao servidor e sincronize.');
        }

        if (!$linha->certificado || !$linha->certificado_senha) {
            throw new Exception('Certificado digital não sincronizado com o caixa.');
        }

        try {
            $certificado = Crypt::decryptString($linha->certificado);
            $senha = Crypt::decryptString($linha->certificado_senha);
        } catch (DecryptException $e) {
            throw new Exception('Não foi possível abrir o certificado salvo no caixa (APP_KEY diferente da usada na sincronização). Sincronize novamente com o servidor.');
        }

        return (new Empresa())->forceFill([
            'cnpj' => $linha->cnpj,
            'razao_social' => $linha->razao_social,
            'nome_fantasia' => $linha->nome_fantasia,
            'ie' => $linha->ie,
            'im' => $linha->im,
            'crt' => (int) $linha->crt,
            'logradouro' => $linha->logradouro,
            'numero' => $linha->numero,
            'complemento' => $linha->complemento,
            'bairro' => $linha->bairro,
            'cep' => $linha->cep,
            'municipio' => $linha->municipio,
            'cod_municipio' => $linha->cod_municipio,
            'uf' => $linha->uf,
            'ambiente' => (int) $linha->ambiente,
            'certificado_base64' => $certificado,
            'certificado_senha' => $senha,
        ]);
    }

    public static function pdv(int $id): Pdv
    {
        $linha = DB::connection('sqlite_local')->table('pdvs_cache')->where('id', $id)->first();

        if (!$linha) {
            throw new Exception("PDV {$id} não encontrado no caixa. Sincronize com o servidor.");
        }

        if (!$linha->csc || !$linha->csc_id) {
            throw new Exception("O CSC do PDV {$linha->nome} não foi sincronizado com o caixa.");
        }

        try {
            $csc = Crypt::decryptString($linha->csc);
        } catch (DecryptException $e) {
            throw new Exception('Não foi possível abrir o CSC salvo no caixa (APP_KEY diferente da usada na sincronização). Sincronize novamente.');
        }

        return (new Pdv())->forceFill([
            'id' => $linha->id,
            'nome' => $linha->nome,
            'serie_nfce' => $linha->serie_nfce,
            'numero_atual_nfce' => (int) $linha->numero_atual_nfce,
            'csc' => $csc,
            'csc_id' => $linha->csc_id,
            'ativo' => (bool) $linha->ativo,
        ]);
    }
}