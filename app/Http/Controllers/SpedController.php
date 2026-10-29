<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\SpedConfiguracao;
use App\Models\SpedGeracao;
use App\Services\SpedFiscalGerador;
use App\Services\SpedVerificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SpedController extends Controller
{
    public const TIPOS = [
        'icms_ipi' => 'EFD ICMS/IPI (SPED Fiscal)',
    ];

    public function index(Request $request)
    {
        $resultado = null;
        $competencia = $request->get('competencia', now()->subMonth()->format('Y-m'));

        if ($request->filled('verificar')) {
            $dados = $this->validarPeriodo($request);
            $resultado = (new SpedVerificacaoService())->verificar($this->inicioDaCompetencia($dados['competencia']));
        }

        $crt = (int) Empresa::atual()->crt;

        return view('sped.index', [
            'tipos'       => self::TIPOS,
            'resultado'   => $resultado,
            'competencia' => $competencia,
            // Regime normal (CRT 2 e 3) destaca ICMS por padrão; Simples (1) e MEI (4) não
            'destacarPadrao'      => in_array($crt, [2, 3], true),
            'saldoAnteriorPadrao' => $this->saldoDoMesAnterior($competencia),
            'config'      => SpedConfiguracao::first(),
            'geracoes'    => SpedGeracao::with('usuario')->latest()->limit(10)->get(),
            ...$this->opcoesInventario($competencia),
        ]);
    }

    public function gerar(Request $request)
    {
        $dados = $this->validarPeriodo($request) + $request->validate([
            'destacar_icms'         => ['nullable', 'boolean'],
            'saldo_credor_anterior' => ['nullable', 'numeric', 'min:0'],
            'dt_vencimento'         => ['nullable', 'date'],
            'inventario_id'         => ['nullable', Rule::exists('inventarios', 'id')->where('status', Inventario::FECHADO)],
        ], [
            'inventario_id.exists' => 'Escolha um inventário fechado.',
        ]);

        $inicio = $this->inicioDaCompetencia($dados['competencia']);

        // Não gera arquivo com erro de cadastro: o PVA recusaria (ou, pior, aceitaria dados errados)
        $verificacao = (new SpedVerificacaoService())->verificar($inicio);
        $erros = collect($verificacao['problemas'])->where('nivel', 'erro');

        if ($erros->isNotEmpty()) {
            return redirect()->route('sped.index', [
                'tipo' => $dados['tipo'], 'competencia' => $dados['competencia'], 'finalidade' => $dados['finalidade'], 'verificar' => 1,
            ])
                ->withErrors(['sped' => "Corrija os {$erros->count()} erro(s) da verificação antes de gerar o arquivo."]);
        }

        $opcoes = [
            'finalidade'            => $dados['finalidade'],
            'destacar_icms'         => $request->boolean('destacar_icms'),
            'saldo_credor_anterior' => (float) ($dados['saldo_credor_anterior'] ?? 0),
            'dt_vencimento'         => $dados['dt_vencimento'] ?? null,
            'inventario_id'         => $dados['inventario_id'] ?? null,
        ];

        try {
            $resultado = (new SpedFiscalGerador())->gerar($inicio, $opcoes);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $cnpj = preg_replace('/\D/', '', (string) Empresa::atual()->cnpj);
        $nome = "SPED_EFD_{$cnpj}_{$inicio->format('Ym')}" . ($opcoes['finalidade'] === '1' ? '_SUBSTITUTO' : '') . '.txt';
        $caminho = "sped/{$inicio->format('Y')}/{$nome}";

        // O PVA espera o arquivo em ISO-8859-1 (Latin-1), não em UTF-8
        $conteudo = mb_convert_encoding($resultado['conteudo'], 'ISO-8859-1', 'UTF-8');
        Storage::disk('local')->put($caminho, $conteudo);

        SpedGeracao::create([
            'competencia'              => $inicio->toDateString(),
            'finalidade'               => $opcoes['finalidade'],
            'destacar_icms'            => $opcoes['destacar_icms'],
            'saldo_credor_anterior'    => $opcoes['saldo_credor_anterior'],
            'icms_recolher'            => $resultado['apuracao']['recolher'],
            'saldo_credor_transportar' => $resultado['apuracao']['transportar'],
            'arquivo'                  => $caminho,
            'total_linhas'             => $resultado['total_linhas'],
            'user_id'                  => auth()->id(),
        ]);

        return response($conteudo, 200, [
            'Content-Type'        => 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
        ]);
    }

    /** Baixa de novo um arquivo já gerado. */
    public function baixar(SpedGeracao $geracao)
    {
        abort_unless(Storage::disk('local')->exists($geracao->arquivo), 404, 'Arquivo não encontrado.');

        return Storage::disk('local')->download($geracao->arquivo, basename($geracao->arquivo), [
            'Content-Type' => 'text/plain; charset=ISO-8859-1',
        ]);
    }

    // ------------------------------------------------------------------

    private function validarPeriodo(Request $request): array
    {
        return $request->validate([
            'tipo'        => ['required', Rule::in(array_keys(self::TIPOS))],
            'competencia' => ['required', 'date_format:Y-m'],
            'finalidade'  => ['required', Rule::in(['0', '1'])],
        ], [
            'competencia.date_format' => 'Informe a competência no formato mês/ano.',
        ]);
    }

    private function inicioDaCompetencia(string $competencia): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $competencia . '-01')->startOfMonth();
    }

    /**
     * Inventários fechados que podem ir no arquivo (data até o fim da competência) e a sugestão:
     * no arquivo de fevereiro, o inventário de 31/12 do ano anterior.
     */
    private function opcoesInventario(string $competencia): array
    {
        try {
            $inicio = $this->inicioDaCompetencia($competencia);
        } catch (\Throwable) {
            return ['inventarios' => collect(), 'inventarioSugerido' => null];
        }

        $inventarios = Inventario::fechados()
            ->whereDate('data', '<=', $inicio->copy()->endOfMonth()->toDateString())
            ->orderByDesc('data')
            ->limit(24)
            ->get();

        $sugerido = $inicio->month === 2
            ? $inventarios->first(fn ($i) => $i->data->isSameDay($inicio->copy()->subYear()->endOfYear()))
            : null;

        return ['inventarios' => $inventarios, 'inventarioSugerido' => $sugerido?->id];
    }

    /** Saldo credor a transportar do último arquivo gerado para o mês anterior (vira o saldo anterior deste). */
    private function saldoDoMesAnterior(string $competencia): float
    {
        try {
            $anterior = $this->inicioDaCompetencia($competencia)->subMonth()->toDateString();
        } catch (\Throwable) {
            return 0.0;
        }

        return (float) (SpedGeracao::whereDate('competencia', $anterior)->latest()->value('saldo_credor_transportar') ?? 0);
    }
}