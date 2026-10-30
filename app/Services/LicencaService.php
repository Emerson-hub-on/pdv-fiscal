<?php

namespace App\Services;

use App\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Licença mensal do sistema: um código assinado (Ed25519) com o CNPJ e o último dia liberado.
 * Só quem tem a chave privada emite licenças; o sistema traz apenas a chave pública, que confere a assinatura.
 *
 * Cada computador guarda a licença em storage/app/licenca.json (o caixa continua conferindo sem rede)
 * e de tempos em tempos procura no servidor central (tabela licencas) uma mais nova.
 */
class LicencaService
{
    // Fica no código e não no .env: quem trocasse a chave pública poderia emitir as próprias licenças
    private const CHAVE_PUBLICA = 'S/I9fPWUjT322g3AhNjQjc/1SxIlc42JYb9YzSdfLBk=';
    private const PREFIXO = 'CYB1';

    public const TOLERANCIA_DIAS = 5;   // dias de uso depois do vencimento, antes de bloquear
    public const AVISO_DIAS = 7;        // a partir de quantos dias antes do vencimento aparece o aviso

    // Licença em dia: confere o central a cada hora. Vencida: a cada 5 minutos, para pegar logo a nova.
    private const RECONFERIR_EM_DIA_MIN = 60;
    private const RECONFERIR_VENCIDA_MIN = 5;

    private ?array $situacao = null;

    public static function arquivoChavePrivada(): string
    {
        $pasta = getenv('USERPROFILE') ?: getenv('HOME') ?: sys_get_temp_dir();

        return $pasta . DIRECTORY_SEPARATOR . '.cyberly' . DIRECTORY_SEPARATOR . 'licenca-privada.key';
    }

    public static function assinar(array $dados, string $chavePrivada): string
    {
        $json = json_encode($dados);

        return self::PREFIXO . '.' . self::b64($json) . '.' . self::b64(sodium_crypto_sign_detached($json, $chavePrivada));
    }

    /**
     * Dados da licença se o código for autêntico; null se foi alterado ou não é uma licença.
     */
    public function ler(?string $codigo): ?array
    {
        $partes = explode('.', trim((string) $codigo));
        if (count($partes) !== 3 || $partes[0] !== self::PREFIXO) {
            return null;
        }

        $json = self::deB64($partes[1]);
        $assinatura = self::deB64($partes[2]);
        if ($json === false || $assinatura === false || strlen($assinatura) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (!sodium_crypto_sign_verify_detached($assinatura, $json, base64_decode(self::CHAVE_PUBLICA))) {
            return null;
        }

        $dados = json_decode($json, true);

        try {
            return [
                'cnpj'       => (string) $dados['cnpj'],
                'valida_ate' => Carbon::createFromFormat('!Y-m-d', $dados['valida_ate']),
                'emitida_em' => Carbon::createFromFormat('!Y-m-d', $dados['emitida_em']),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Registra a licença colada na tela. Devolve a mensagem de erro, ou null se deu certo.
     */
    public function registrar(string $codigo): ?string
    {
        $codigo = trim($codigo);
        $licenca = $this->ler($codigo);

        if (!$licenca) {
            return 'Código de licença inválido. Confira se foi copiado inteiro.';
        }

        try {
            $cnpj = $this->cnpjEmpresa();

            if (!$this->doCnpj($licenca, $cnpj)) {
                return "Esta licença é de outro CNPJ ({$licenca['cnpj']}).";
            }

            $atual = $this->situacao();
            if ($atual['valida_ate'] && !$licenca['valida_ate']->gt($atual['valida_ate']) && !$atual['bloqueada']) {
                return 'Já existe uma licença registrada válida até ' . $atual['valida_ate']->format('d/m/Y') . '.';
            }

            DB::table('licencas')->insert([
                'codigo'     => $codigo,
                'cnpj'       => $licenca['cnpj'],
                'valida_ate' => $licenca['valida_ate']->format('Y-m-d'),
                'emitida_em' => $licenca['emitida_em']->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return 'Sem conexão com o servidor. Registre a licença com o servidor ligado.';
        }

        $local = $this->lerLocal();
        $local['codigo'] = $codigo;
        $local['conferida_em'] = now()->toIso8601String();
        $this->gravarLocal($local);
        $this->situacao = null;

        return null;
    }

    public function bloqueada(): bool
    {
        return $this->situacao()['bloqueada'];
    }

    /**
     * estado: valida | vencendo | tolerancia | vencida | sem_licenca | relogio
     */
    public function situacao(): array
    {
        return $this->situacao ??= $this->calcular();
    }

    private function calcular(): array
    {
        $original = $this->lerLocal();
        $local = $original;
        $licenca = $this->ler($local['codigo'] ?? null);
        $hoje = $this->hoje();

        $intervalo = $licenca && $licenca['valida_ate']->gte($hoje) ? self::RECONFERIR_EM_DIA_MIN : self::RECONFERIR_VENCIDA_MIN;
        $conferidaEm = isset($local['conferida_em']) ? Carbon::parse($local['conferida_em']) : null;

        if (!$conferidaEm || $conferidaEm->isFuture() || $conferidaEm->diffInMinutes(now()) >= $intervalo) {
            $local = $this->conferirCentral($local, $licenca);
            $licenca = $this->ler($local['codigo'] ?? null);
        }

        // Relógio voltado para trás (para esticar a licença): data menor que a já vista ou que a emissão
        $ultimaVista = isset($local['ultima_data_vista']) ? Carbon::createFromFormat('!Y-m-d', $local['ultima_data_vista']) : null;
        $relogioAtrasado = ($ultimaVista && $hoje->lt($ultimaVista)) || ($licenca && $hoje->lt($licenca['emitida_em']));

        if (!$relogioAtrasado && (!$ultimaVista || $hoje->gt($ultimaVista))) {
            $local['ultima_data_vista'] = $hoje->format('Y-m-d');
        }

        if ($local !== $original) {
            $this->gravarLocal($local);
        }

        if ($relogioAtrasado) {
            return $this->montar('relogio', $licenca, 'A data deste computador está atrasada. Corrija a data e a hora do Windows'
                . ($ultimaVista ? ' (o sistema já foi usado em ' . $ultimaVista->format('d/m/Y') . ').' : '.'));
        }

        if (!$licenca) {
            return $this->montar('sem_licenca', null, 'Nenhuma licença válida registrada para este sistema.');
        }

        $validaAte = $licenca['valida_ate'];
        $bloqueiaEm = $validaAte->copy()->addDays(self::TOLERANCIA_DIAS + 1);   // primeiro dia bloqueado
        $faltam = (int) $hoje->diffInDays($validaAte, false);

        if ($hoje->gte($bloqueiaEm)) {
            return $this->montar('vencida', $licenca, 'A licença do sistema venceu em ' . $validaAte->format('d/m/Y') . '.');
        }

        if ($hoje->gt($validaAte)) {
            return $this->montar('tolerancia', $licenca, 'A licença do sistema venceu em ' . $validaAte->format('d/m/Y')
                . '. O sistema será bloqueado em ' . $bloqueiaEm->format('d/m/Y') . '.');
        }

        if ($faltam <= self::AVISO_DIAS) {
            return $this->montar('vencendo', $licenca, 'A licença do sistema vence '
                . ($faltam === 0 ? 'hoje' : 'em ' . $validaAte->format('d/m/Y') . ' (' . ($faltam === 1 ? 'falta 1 dia' : "faltam {$faltam} dias") . ')') . '.');
        }

        return $this->montar('valida', $licenca, 'Licença válida até ' . $validaAte->format('d/m/Y') . '.');
    }

    private function montar(string $estado, ?array $licenca, string $mensagem): array
    {
        return [
            'estado'      => $estado,
            'bloqueada'   => in_array($estado, ['vencida', 'sem_licenca', 'relogio'], true),
            'valida_ate'  => $licenca['valida_ate'] ?? null,
            'bloqueia_em' => isset($licenca['valida_ate']) ? $licenca['valida_ate']->copy()->addDays(self::TOLERANCIA_DIAS + 1) : null,
            'cnpj'        => $licenca['cnpj'] ?? null,
            'mensagem'    => $mensagem,
        ];
    }

    // Fica com a licença mais nova do CNPJ da empresa entre a local e as registradas no central
    private function conferirCentral(array $local, ?array $licenca): array
    {
        $local['conferida_em'] = now()->toIso8601String();

        try {
            $cnpj = $this->cnpjEmpresa();
            $melhor = $licenca && $this->doCnpj($licenca, $cnpj) ? $licenca : null;
            $codigoMelhor = $melhor ? $local['codigo'] : null;

            $registradas = DB::table('licencas')->orderByDesc('valida_ate')->limit(10)->pluck('codigo');
            foreach ($registradas as $codigo) {
                $l = $this->ler($codigo);
                if ($l && $this->doCnpj($l, $cnpj) && (!$melhor || $l['valida_ate']->gt($melhor['valida_ate']))) {
                    $melhor = $l;
                    $codigoMelhor = $codigo;
                }
            }

            // Uma licença local de outro CNPJ (arquivo copiado de outro cliente) é descartada aqui
            $local['codigo'] = $codigoMelhor;
        } catch (\Throwable) {
            // Sem conexão com o central (caixa em contingência): segue com a cópia local
        }

        return $local;
    }

    // Antes de cadastrar a empresa (instalação) qualquer CNPJ é aceito; depois, só o da empresa
    private function doCnpj(array $licenca, string $cnpj): bool
    {
        return $cnpj === '' || $licenca['cnpj'] === $cnpj;
    }

    private function cnpjEmpresa(): string
    {
        return preg_replace('/\D/', '', (string) Empresa::atual()->cnpj);
    }

    private function hoje(): Carbon
    {
        return now()->startOfDay();
    }

    private function arquivoLocal(): string
    {
        return storage_path('app/licenca.json');
    }

    private function lerLocal(): array
    {
        $arquivo = $this->arquivoLocal();

        return is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];
    }

    private function gravarLocal(array $dados): void
    {
        try {
            file_put_contents($this->arquivoLocal(), json_encode($dados, JSON_PRETTY_PRINT), LOCK_EX);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function deB64(string $texto): string|false
    {
        return base64_decode(strtr($texto, '-_', '+/'), true);
    }
}
