<?php

namespace App\Http\Controllers;

use App\Models\Condutor;
use App\Models\Empresa;
use App\Models\Mdfe;
use App\Models\MdfeCondutor;
use App\Models\MdfeDocumento;
use App\Models\MdfeEvento;
use App\Models\MdfeMunicipio;
use App\Models\MdfePercurso;
use App\Models\MdfeVeiculo;
use App\Models\NotaFiscal;
use App\Models\SerieMdfe;
use App\Models\Transportador;
use App\Models\Veiculo;
use App\Services\MdfeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MdfeController extends Controller
{
    private const MAX_REBOQUES   = 3;
    private const MAX_CONDUTORES = 10;

    public function index(Request $request)
    {
        $filtro = $request->get('status', 'todos'); // todos | rascunho | emitido | encerrado | cancelado
        $busca  = trim((string) $request->get('busca', ''));
        $digitos = preg_replace('/\D/', '', $busca);

        $mdfes = Mdfe::query()
            ->when(in_array($filtro, [Mdfe::STATUS_RASCUNHO, Mdfe::STATUS_EMITIDO, Mdfe::STATUS_ENCERRADO, Mdfe::STATUS_CANCELADO], true),
                fn ($q) => $q->where('status', $filtro))
            ->when($digitos !== '', function ($q) use ($digitos) {
                $q->where(function ($q) use ($digitos) {
                    $q->where('numero', $digitos)
                      ->orWhere('chave_acesso', 'like', "%{$digitos}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // A busca ao vivo pede só a tabela
        if ($request->ajax()) {
            return view('mdfes._tabela', compact('mdfes'));
        }

        return view('mdfes.index', compact('mdfes', 'filtro'));
    }

    public function create()
    {
        return view('mdfes.create', $this->dadosDoFormulario(null));
    }

    public function store(Request $request)
    {
        $dados  = $this->validarDados($request);
        $grupos = $this->separarEmGrupos($dados);

        $criados = DB::transaction(function () use ($grupos) {
            $mdfes = [];

            foreach ($grupos as $grupo) {
                $mdfe = Mdfe::create($this->camposCabecalho($grupo) + ['operador_id' => auth()->id()]);
                $this->gravarFilhos($mdfe, $grupo);
                $mdfes[] = $mdfe;
            }

            return $mdfes;
        });

        return $this->redirecionarAposSalvar($criados, 'MDF-e salvo como rascunho.');
    }

    public function edit(Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_RASCUNHO, 403, 'Só é possível editar MDF-e em rascunho.');

        return view('mdfes.edit', $this->dadosDoFormulario($mdfe));
    }

    public function update(Request $request, Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_RASCUNHO, 403, 'Só é possível editar MDF-e em rascunho.');

        $dados  = $this->validarDados($request, $mdfe->id);
        $grupos = $this->separarEmGrupos($dados);

        $salvos = DB::transaction(function () use ($mdfe, $grupos, $dados) {
            // O primeiro grupo continua neste MDF-e; os demais (se houver separação por UF) viram novos rascunhos
            $primeiro = array_shift($grupos);
            $mdfe->update($this->camposCabecalho($primeiro));

            // Substitui tudo que é filho — mais simples e seguro que casar linha por linha
            $mdfe->documentos()->delete();
            $mdfe->condutores()->delete();
            $mdfe->veiculos()->delete();
            $mdfe->municipios()->delete();
            $mdfe->percursos()->delete();

            $this->gravarFilhos($mdfe, $primeiro);

            $mdfes = [$mdfe];
            foreach ($grupos as $grupo) {
                $novo = Mdfe::create($this->camposCabecalho($grupo) + ['operador_id' => auth()->id()]);
                $this->gravarFilhos($novo, $grupo);
                $mdfes[] = $novo;
            }

            return $mdfes;
        });

        return $this->redirecionarAposSalvar($salvos, 'MDF-e atualizado com sucesso.');
    }

    public function destroy(Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_RASCUNHO, 403, 'Só é possível excluir MDF-e em rascunho.');

        $mdfe->delete();

        return redirect()->route('mdfes.index')->with('sucesso', 'MDF-e excluído.');
    }

    /**
     * Busca de NF-e emitidas para o MDF-e.
     * Fica de fora quem já está em outro MDF-e em rascunho ou emitido (o atual não conta).
     */
    public function buscarNfe(Request $request)
    {
        $termo   = trim((string) $request->get('termo', ''));
        $digitos = preg_replace('/\D/', '', $termo);

        $emUso = $this->chavesEmUso($request->integer('mdfe_id') ?: null);

        $notas = NotaFiscal::with('cliente:id,nome,municipio,cod_municipio,uf')
            ->where('status', 'emitida')
            ->whereNotNull('chave_acesso')
            ->when($emUso, fn ($q) => $q->whereNotIn('chave_acesso', $emUso))
            ->when($termo !== '', function ($q) use ($termo, $digitos) {
                $q->where(function ($q) use ($termo, $digitos) {
                    if ($digitos !== '') {
                        $q->orWhere('numero', $digitos)->orWhere('chave_acesso', 'like', "%{$digitos}%");
                    }
                    $q->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$termo}%"));
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json($notas->map(fn (NotaFiscal $n) => $this->itemDocumento($n)));
    }

    // ------------------------------------------------------------------
    // Visualização, emissão e eventos
    // ------------------------------------------------------------------

    public function show(Mdfe $mdfe)
    {
        $mdfe->load([
            'percursos',
            'municipios.documentos.notaFiscal.cliente',
            'veiculos.condutores',
            'eventos.operador',
        ]);

        return view('mdfes.show', compact('mdfe'));
    }

    public function emitir(Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_RASCUNHO, 403, 'MDF-e já foi emitido ou cancelado.');

        try {
            DB::transaction(function () use ($mdfe) {
                $serie = SerieMdfe::ativas()->lockForUpdate()->firstOrFail();
                $proximoNumero = $serie->numero_atual + 1;

                $mdfe->serie_mdfe_id = $serie->id;
                $mdfe->serie = $serie->serie;
                $mdfe->numero = $proximoNumero;
                $mdfe->save();

                $serie->update(['numero_atual' => $proximoNumero]);

                $resultado = (new MdfeService())->emitir($mdfe);

                $mdfe->status = Mdfe::STATUS_EMITIDO;
                $mdfe->chave_acesso = $resultado['chave_acesso'];
                $mdfe->protocolo = $resultado['protocolo'];
                $mdfe->xml = $resultado['xml'];
                $mdfe->codigo_numerico = $resultado['codigo_numerico'];
                $mdfe->emitido_em = now();
                $mdfe->motivo_rejeicao = null;
                $mdfe->save();
            });
        } catch (\Throwable $e) {
            // A transação desfez número e série; o rascunho fica como estava, com o motivo da falha
            $mdfe->refresh();
            $mdfe->motivo_rejeicao = $e->getMessage();
            $mdfe->save();

            return back()->withErrors(['emissao' => 'Falha ao emitir: ' . $e->getMessage()]);
        }

        return redirect()->route('mdfes.show', $mdfe)->with('sucesso', 'MDF-e emitido com sucesso!');
    }

    public function cancelar(Request $request, Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_EMITIDO, 403, 'Só é possível cancelar MDF-e emitido e ainda não encerrado.');

        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ], [
            'justificativa.required' => 'Informe a justificativa do cancelamento.',
            'justificativa.min'      => 'A justificativa do cancelamento deve ter ao menos 15 caracteres.',
        ]);

        try {
            $resultado = (new MdfeService())->cancelar($mdfe, $dados['justificativa']);
        } catch (\Throwable $e) {
            return back()->withErrors(['cancelamento' => $e->getMessage()])->withInput();
        }

        DB::transaction(function () use ($mdfe, $dados, $resultado) {
            // forceFill: status e protocolos não são mass-assignable no model (só o cabeçalho é)
            $mdfe->forceFill([
                'status'                 => Mdfe::STATUS_CANCELADO,
                'cancelado_em'           => now(),
                'motivo_cancelamento'    => $dados['justificativa'],
                'protocolo_cancelamento' => $resultado['protocolo'],
            ])->save();

            $mdfe->eventos()->create([
                'tipo'          => MdfeEvento::CANCELAMENTO,
                'codigo_evento' => '110111',
                'justificativa' => $dados['justificativa'],
                'protocolo'     => $resultado['protocolo'],
                'xml'           => $resultado['xml'],
                'operador_id'   => auth()->id(),
                'registrado_em' => now(),
            ]);
        });

        return redirect()->route('mdfes.show', $mdfe)->with('sucesso', 'MDF-e cancelado. Protocolo: ' . $resultado['protocolo']);
    }

    public function encerrar(Request $request, Mdfe $mdfe)
    {
        abort_if($mdfe->status !== Mdfe::STATUS_EMITIDO, 403, 'Só é possível encerrar MDF-e emitido.');

        $dados = $request->validate([
            'municipio_id' => [
                'required',
                Rule::exists('mdfe_municipios', 'id')->where('mdfe_id', $mdfe->id),
            ],
        ], [
            'municipio_id.required' => 'Selecione o município de encerramento.',
        ]);

        $municipio = MdfeMunicipio::findOrFail($dados['municipio_id']);

        try {
            $resultado = (new MdfeService())->encerrar($mdfe, $municipio->codigo_ibge, $municipio->uf);
        } catch (\Throwable $e) {
            return back()->withErrors(['encerramento' => $e->getMessage()]);
        }

        DB::transaction(function () use ($mdfe, $municipio, $resultado) {
            $mdfe->forceFill([
                'status'                 => Mdfe::STATUS_ENCERRADO,
                'encerrado_em'           => now(),
                'protocolo_encerramento' => $resultado['protocolo'],
            ])->save();

            $mdfe->eventos()->create([
                'tipo'          => MdfeEvento::ENCERRAMENTO,
                'codigo_evento' => '110112',
                'dados'         => ['codigo_ibge' => $municipio->codigo_ibge, 'municipio' => $municipio->nome, 'uf' => $municipio->uf],
                'protocolo'     => $resultado['protocolo'],
                'xml'           => $resultado['xml'],
                'operador_id'   => auth()->id(),
                'registrado_em' => now(),
            ]);
        });

        return redirect()->route('mdfes.show', $mdfe)->with('sucesso', 'MDF-e encerrado. Protocolo: ' . $resultado['protocolo']);
    }

    public function xml(Mdfe $mdfe)
    {
        abort_if(!$mdfe->xml, 404, 'Este MDF-e ainda não tem XML autorizado.');

        return response($mdfe->xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $mdfe->chave_acesso . '.xml"',
        ]);
    }

    /** DAMDFE (PDF) gerado a partir do XML autorizado, com o pacote nfephp-org/sped-da. */
    public function pdf(Mdfe $mdfe)
    {
        abort_if(!$mdfe->xml, 404, 'Este MDF-e ainda não tem XML autorizado.');

        // XML gravado sem o protocolo (mdfeProc): o DAMDFE quebra. Busca o protocolo na SEFAZ e grava o XML completo.
        if (!str_contains((string) $mdfe->xml, '<mdfeProc')) {
            try {
                $xml = (new MdfeService())->xmlComProtocolo($mdfe);
                if ($xml) {
                    $mdfe->forceFill(['xml' => $xml])->save();
                }
            } catch (\Throwable $e) {
                return back()->withErrors(['pdf' => 'O XML deste MDF-e está sem o protocolo de autorização e não foi possível buscá-lo na SEFAZ: ' . $e->getMessage()]);
            }
        }

        try {
            $damdfe = new \NFePHP\DA\MDFe\Damdfe($this->xmlParaImpressao($mdfe->xml));
            $damdfe->debugMode(false);
            $damdfe->printParameters('P');
            $pdf = $damdfe->render();
        } catch (\Throwable $e) {
            // Arquivo e linha no log para saber em que parte do DAMDFE o XML não bateu
            report($e);
            return back()->withErrors(['pdf' => 'Não foi possível gerar o DAMDFE: ' . $e->getMessage()
                . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')']);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="MDFe-' . $mdfe->chave_acesso . '.pdf"',
        ]);
    }

    /**
     * Cópia do XML só para imprimir (o XML autorizado gravado não muda).
     * O sped-da 1.1.x lê o RNTRC de <rodo><infANTT> sem conferir se o grupo existe; MDF-e sem RNTRC
     * (carga própria) não tem <infANTT> e o DAMDFE quebra. Um <infANTT/> vazio é aceito pelo schema
     * e faz o DAMDFE sair com o campo RNTRC em branco.
     */
    private function xmlParaImpressao(string $xml): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (!@$dom->loadXML($xml)) {
            return $xml;
        }

        $ns = 'http://www.portalfiscal.inf.br/mdfe';
        $rodo = $dom->getElementsByTagNameNS($ns, 'rodo')->item(0);

        if ($rodo && $dom->getElementsByTagNameNS($ns, 'infANTT')->length === 0) {
            // infANTT é o primeiro filho de <rodo> no leiaute 3.00
            $rodo->insertBefore($dom->createElementNS($ns, 'infANTT'), $rodo->firstChild);

            return $dom->saveXML();
        }

        return $xml;
    }

    // ------------------------------------------------------------------
    // Separação por UF de descarga
    // ------------------------------------------------------------------

    /**
     * Sem a opção marcada devolve um único conjunto de dados. Com ela, agrupa as NF-e pela UF do município
     * de descarga e devolve um conjunto por UF — cada um com a sua UF de fim e o restante copiado.
     */
    private function separarEmGrupos(array $dados): array
    {
        if (empty($dados['dividir_por_uf'])) {
            return [$dados];
        }

        $porUf = [];
        foreach ($dados['documentos'] as $doc) {
            $uf = Mdfe::ufDoMunicipio((string) $doc['nota']->cliente->cod_municipio);
            $porUf[$uf][] = $doc;
        }

        $grupos = [];
        foreach ($porUf as $uf => $documentos) {
            $grupos[] = array_merge($dados, ['uf_fim' => $uf, 'documentos' => $documentos]);
        }

        return $grupos;
    }

    private function redirecionarAposSalvar(array $mdfes, string $mensagem)
    {
        if (count($mdfes) === 1) {
            return redirect()->route('mdfes.show', $mdfes[0])->with('sucesso', $mensagem);
        }

        $ufs = collect($mdfes)->pluck('uf_fim')->implode(', ');

        return redirect()->route('mdfes.index')->with(
            'sucesso',
            count($mdfes) . " MDF-e em rascunho, um por UF de descarga ({$ufs}). Revise o percurso de cada um antes de emitir."
        );
    }

    // ------------------------------------------------------------------
    // Validação e gravação
    // ------------------------------------------------------------------

    private function validarDados(Request $request, ?int $ignorarMdfeId = null): array
    {
        $request->merge([
            'uf_inicio' => strtoupper(trim((string) $request->input('uf_inicio'))),
            'uf_fim'    => strtoupper(trim((string) $request->input('uf_fim'))),
            'rntrc'     => preg_replace('/\D/', '', (string) $request->input('rntrc')) ?: null,
        ]);

        $dados = $request->validate([
            'tipo_emitente'      => ['required', Rule::in(array_keys(Mdfe::TIPOS_EMITENTE))],
            'tipo_transportador' => ['nullable', Rule::in(array_keys(Mdfe::TIPOS_TRANSPORTADOR))],
            'uf_inicio'          => ['required', Rule::in(Transportador::UFS)],
            'uf_fim'             => ['required_without:dividir_por_uf', 'nullable', Rule::in(Transportador::UFS)],
            'dividir_por_uf'     => ['nullable', 'boolean'],
            'inicio_viagem'      => ['nullable', 'date'],
            'rntrc'              => ['nullable', 'regex:/^\d{8}$/'],
            'informacoes_adicionais' => ['nullable', 'string', 'max:5000'],

            'percurso'   => ['nullable', 'array', 'max:25'],
            'percurso.*' => ['required', Rule::in(Transportador::UFS)],

            'carregamentos'               => ['required', 'array', 'min:1', 'max:50'],
            'carregamentos.*.codigo_ibge' => ['required', 'regex:/^\d{7}$/', 'distinct'],
            'carregamentos.*.nome'        => ['required', 'string', 'max:60'],
            'carregamentos.*.uf'          => ['required', Rule::in(Transportador::UFS)],

            'documentos'                      => ['required', 'array', 'min:1', 'max:2000'],
            'documentos.*.nota_fiscal_id'     => ['required', 'integer', 'distinct', 'exists:notas_fiscais,id'],
            'documentos.*.peso_bruto_kg'      => ['nullable', 'numeric', 'min:0', 'max:999999999'],

            'veiculo_tracao_id' => ['required', 'integer'],
            'reboques'          => ['nullable', 'array', 'max:' . self::MAX_REBOQUES],
            'reboques.*'        => ['integer', 'distinct'],
            'condutores'        => ['required', 'array', 'min:1', 'max:' . self::MAX_CONDUTORES],
            'condutores.*'      => ['integer', 'distinct'],
        ], [
            'tipo_emitente.required'       => 'Informe o tipo de emitente.',
            'carregamentos.required'       => 'Informe ao menos um município de carregamento.',
            'carregamentos.min'            => 'Informe ao menos um município de carregamento.',
            'carregamentos.*.codigo_ibge.distinct' => 'Há município de carregamento repetido.',
            'documentos.required'          => 'Adicione ao menos uma NF-e ao MDF-e.',
            'documentos.min'               => 'Adicione ao menos uma NF-e ao MDF-e.',
            'documentos.*.nota_fiscal_id.distinct' => 'Há NF-e repetida na lista.',
            'veiculo_tracao_id.required'   => 'Selecione o veículo de tração.',
            'condutores.required'          => 'Selecione ao menos um condutor.',
            'condutores.min'               => 'Selecione ao menos um condutor.',
            'rntrc.regex'                  => 'O RNTRC deve ter 8 dígitos.',
        ]);

        $dados['dividir_por_uf'] = $request->boolean('dividir_por_uf');
        $dados['uf_fim'] = $dados['uf_fim'] ?? null;

        // array_merge (e não +): os modelos resolvidos precisam sobrescrever os ids crus do formulário
        return array_merge($dados, $this->resolverRelacionados($dados, $ignorarMdfeId));
    }

    /**
     * Carrega do banco tudo o que o formulário só referencia por id — chave, valor, município de descarga,
     * dados do veículo — para não confiar no que veio do navegador.
     */
    private function resolverRelacionados(array $dados, ?int $ignorarMdfeId): array
    {
        $erros = [];
        $dividir = !empty($dados['dividir_por_uf']); // com a separação, a UF de fim é derivada de cada grupo

        // ---- Municípios x UF (rejeições 456 e 612 da SEFAZ) ----
        foreach ($dados['carregamentos'] as $m) {
            $ufDoCodigo = Mdfe::ufDoMunicipio($m['codigo_ibge']);

            if ($ufDoCodigo !== $dados['uf_inicio']) {
                $erros['carregamentos'][] = "Município de carregamento {$m['nome']} (IBGE {$m['codigo_ibge']}) não pertence à UF de início ({$dados['uf_inicio']}): o código é de " . ($ufDoCodigo ?? 'UF desconhecida') . '.';
            }
        }

        // ---- Documentos ----
        $notas = NotaFiscal::with('cliente')
            ->whereIn('id', collect($dados['documentos'])->pluck('nota_fiscal_id'))
            ->get()
            ->keyBy('id');

        $emUso = $this->chavesEmUso($ignorarMdfeId);

        $documentos = [];
        foreach ($dados['documentos'] as $linha) {
            $nota = $notas[$linha['nota_fiscal_id']];
            $rotulo = "NF-e nº {$nota->numero} série {$nota->serie}";

            if ($nota->status !== 'emitida' || !$nota->chave_acesso) {
                $erros['documentos'][] = "{$rotulo} não está emitida.";
                continue;
            }
            if (in_array($nota->chave_acesso, $emUso, true)) {
                $erros['documentos'][] = "{$rotulo} já está em outro MDF-e em rascunho ou emitido.";
                continue;
            }
            if (!preg_match('/^\d{7}$/', (string) $nota->cliente?->cod_municipio)) {
                $erros['documentos'][] = "{$rotulo}: o cliente não tem município com código IBGE no cadastro (necessário para o município de descarga).";
                continue;
            }

            $codigo     = (string) $nota->cliente->cod_municipio;
            $ufDoCodigo = Mdfe::ufDoMunicipio($codigo);
            $ufCliente  = strtoupper((string) $nota->cliente->uf);
            $descarga   = "{$nota->cliente->municipio}/{$ufCliente} (IBGE {$codigo})";

            if ($ufDoCodigo !== $ufCliente) {
                $erros['documentos'][] = "{$rotulo}: cadastro do cliente inconsistente — a UF é {$ufCliente}, mas o código IBGE {$codigo} é de " . ($ufDoCodigo ?? 'UF desconhecida') . '. Corrija o município do cliente.';
                continue;
            }
            if (!$dividir && $ufDoCodigo !== $dados['uf_fim']) {
                $erros['documentos'][] = "{$rotulo}: o município de descarga {$descarga} não pertence à UF de fim do MDF-e ({$dados['uf_fim']}). A SEFAZ exige municípios de descarga da UF de fim (rejeição 612): ajuste a UF de fim, marque \"Separar automaticamente por UF de descarga\" ou emita outro MDF-e para essa NF-e.";
                continue;
            }

            $documentos[] = [
                'nota'          => $nota,
                'peso_bruto_kg' => (float) ($linha['peso_bruto_kg'] ?? $nota->vol_peso_bruto ?? 0),
            ];
        }

        // ---- Veículos ----
        $tracao = Veiculo::with('transportador')->ativos()->tracao()->find($dados['veiculo_tracao_id']);
        if (!$tracao) {
            $erros['veiculo_tracao_id'][] = 'Veículo de tração inválido ou inativo.';
        } elseif ($faltam = $this->camposDoVeiculoFaltando($tracao, true)) {
            $erros['veiculo_tracao_id'][] = "Veículo {$tracao->placa_formatada}: complete no cadastro — {$faltam}.";
        }

        $reboques = Veiculo::with('transportador')->ativos()->reboques()
            ->whereIn('id', $dados['reboques'] ?? [])->get()
            ->sortBy(fn ($v) => array_search($v->id, $dados['reboques']))->values();

        if ($reboques->count() !== count($dados['reboques'] ?? [])) {
            $erros['reboques'][] = 'Há reboque inválido ou inativo na lista.';
        }
        foreach ($reboques as $reboque) {
            if ($faltam = $this->camposDoVeiculoFaltando($reboque, false)) {
                $erros['reboques'][] = "Reboque {$reboque->placa_formatada}: complete no cadastro — {$faltam}.";
            }
        }

        // ---- Condutores ----
        $condutores = Condutor::ativos()->whereIn('id', $dados['condutores'])->get()
            ->sortBy(fn ($c) => array_search($c->id, $dados['condutores']))->values();

        if ($condutores->count() !== count($dados['condutores'])) {
            $erros['condutores'][] = 'Há condutor inválido ou inativo na lista.';
        }

        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        return compact('documentos', 'tracao', 'reboques', 'condutores');
    }

    /** Lista (em texto) o que falta no cadastro do veículo para entrar no MDF-e; vazio = completo. */
    private function camposDoVeiculoFaltando(Veiculo $v, bool $tracao): string
    {
        $faltam = [];
        if ($v->tara_kg === null)        $faltam[] = 'tara';
        if ($v->capacidade_kg === null)  $faltam[] = 'capacidade em kg';
        if (!$v->tipo_carroceria)        $faltam[] = 'tipo de carroceria';
        if ($tracao && !$v->tipo_rodado) $faltam[] = 'tipo de rodado';

        return implode(', ', $faltam);
    }

    private function camposCabecalho(array $dados): array
    {
        return [
            'tipo_emitente'          => $dados['tipo_emitente'],
            'tipo_transportador'     => $dados['tipo_transportador'] ?? null,
            'uf_inicio'              => $dados['uf_inicio'],
            'uf_fim'                 => $dados['uf_fim'],
            'inicio_viagem'          => $dados['inicio_viagem'] ?? null,
            'rntrc'                  => $dados['rntrc'] ?? null,
            'informacoes_adicionais' => $dados['informacoes_adicionais'] ?? null,
        ];
    }

    private function gravarFilhos(Mdfe $mdfe, array $dados): void
    {
        foreach (array_values($dados['percurso'] ?? []) as $ordem => $uf) {
            MdfePercurso::create(['mdfe_id' => $mdfe->id, 'uf' => $uf, 'ordem' => $ordem]);
        }

        foreach (array_values($dados['carregamentos']) as $ordem => $m) {
            MdfeMunicipio::create([
                'mdfe_id'     => $mdfe->id,
                'tipo'        => MdfeMunicipio::CARREGAMENTO,
                'codigo_ibge' => $m['codigo_ibge'],
                'nome'        => $m['nome'],
                'uf'          => strtoupper($m['uf']),
                'ordem'       => $ordem,
            ]);
        }

        // Municípios de descarga saem dos clientes das NF-e: um registro por município, na ordem em que aparecem
        $descargas = [];
        foreach ($dados['documentos'] as $doc) {
            /** @var NotaFiscal $nota */
            $nota    = $doc['nota'];
            $cliente = $nota->cliente;
            $ibge    = $cliente->cod_municipio;

            if (!isset($descargas[$ibge])) {
                $descargas[$ibge] = MdfeMunicipio::create([
                    'mdfe_id'     => $mdfe->id,
                    'tipo'        => MdfeMunicipio::DESCARREGAMENTO,
                    'codigo_ibge' => $ibge,
                    'nome'        => mb_substr((string) $cliente->municipio, 0, 60),
                    'uf'          => strtoupper((string) $cliente->uf),
                    'ordem'       => count($descargas),
                ]);
            }

            MdfeDocumento::create([
                'mdfe_id'           => $mdfe->id,
                'mdfe_municipio_id' => $descargas[$ibge]->id,
                'tipo'              => 'nfe',
                'nota_fiscal_id'    => $nota->id,
                'chave_acesso'      => $nota->chave_acesso,
                'valor'             => $nota->valor_total,
                'peso_bruto_kg'     => $doc['peso_bruto_kg'],
            ]);
        }

        $veiculoTracao = MdfeVeiculo::create($this->snapshotVeiculo($mdfe, $dados['tracao'], MdfeVeiculo::TRACAO, 0));

        foreach ($dados['reboques'] as $ordem => $reboque) {
            MdfeVeiculo::create($this->snapshotVeiculo($mdfe, $reboque, MdfeVeiculo::REBOQUE, $ordem + 1));
        }

        foreach ($dados['condutores'] as $condutor) {
            MdfeCondutor::create([
                'mdfe_id'         => $mdfe->id,
                'mdfe_veiculo_id' => $veiculoTracao->id,
                'condutor_id'     => $condutor->id,
                'nome'            => $condutor->nome,
                'cpf'             => $condutor->cpf,
            ]);
        }

        $mdfe->recalcularTotais();
    }

    /** Foto do cadastro do veículo; o proprietário só entra quando o veículo tem transportadora. */
    private function snapshotVeiculo(Mdfe $mdfe, Veiculo $v, string $papel, int $ordem): array
    {
        $t = $v->transportador;

        return [
            'mdfe_id'         => $mdfe->id,
            'papel'           => $papel,
            'veiculo_id'      => $v->id,
            'ordem'           => $ordem,
            'placa'           => $v->placa,
            'uf'              => $v->uf,
            'rntrc'           => $v->rntrc,
            'tara_kg'         => $v->tara_kg,
            'capacidade_kg'   => $v->capacidade_kg,
            'capacidade_m3'   => $v->capacidade_m3,
            'tipo_rodado'     => $v->tipo_rodado,
            'tipo_carroceria' => $v->tipo_carroceria,

            'proprietario_tipo'      => $t ? 2 : null, // tpProp: 2 = outros
            'proprietario_documento' => $t?->documento,
            'proprietario_nome'      => $t ? mb_substr($t->nome, 0, 60) : null,
            'proprietario_ie'        => $t?->ie,
            'proprietario_uf'        => $t?->uf,
            'proprietario_rntrc'     => $t ? $v->rntrc : null,
        ];
    }

    /** Chaves das NF-e que já estão em MDF-e em rascunho ou emitido (menos o MDF-e que está sendo editado). */
    private function chavesEmUso(?int $ignorarMdfeId): array
    {
        return MdfeDocumento::query()
            ->whereHas('mdfe', function ($q) use ($ignorarMdfeId) {
                $q->whereIn('status', [Mdfe::STATUS_RASCUNHO, Mdfe::STATUS_EMITIDO])
                  ->when($ignorarMdfeId, fn ($q) => $q->where('id', '!=', $ignorarMdfeId));
            })
            ->pluck('chave_acesso')
            ->all();
    }

    // ------------------------------------------------------------------
    // Dados para o formulário (estado inicial do JavaScript)
    // ------------------------------------------------------------------

    private function dadosDoFormulario(?Mdfe $mdfe): array
    {
        $empresa = Empresa::first();

        return [
            'mdfe'              => $mdfe,
            'estado'            => $this->estadoDoFormulario($mdfe, $empresa),
            // O RNTRC é digitado no manifesto (pode mudar); sugere o último usado para poupar digitação
            'rntrcPadrao'       => Mdfe::latest('id')->value('rntrc'),
            'ufPadrao'          => $empresa?->uf,
            'tiposEmitente'     => Mdfe::TIPOS_EMITENTE,
            'tiposTransportador' => Mdfe::TIPOS_TRANSPORTADOR,
        ];
    }

    private function estadoDoFormulario(?Mdfe $mdfe, ?Empresa $empresa): array
    {
        // Validação falhou: refaz a tela com o que o usuário tinha montado
        if ($old = session()->getOldInput()) {
            return $this->estadoDoInputAntigo($old);
        }

        if ($mdfe) {
            $mdfe->load(['percursos', 'municipiosCarregamento', 'documentos.notaFiscal.cliente', 'veiculos.condutores']);

            $tracao   = $mdfe->veiculos->firstWhere('papel', MdfeVeiculo::TRACAO);
            $reboques = $mdfe->veiculos->where('papel', MdfeVeiculo::REBOQUE);

            return [
                'percurso'      => $mdfe->percursos->pluck('uf')->all(),
                'carregamentos' => $mdfe->municipiosCarregamento->map(fn ($m) => [
                    'codigo_ibge' => $m->codigo_ibge, 'nome' => $m->nome, 'uf' => $m->uf,
                ])->values()->all(),
                'documentos'    => $mdfe->documentos->filter(fn ($d) => $d->notaFiscal)
                    ->map(fn ($d) => $this->itemDocumento($d->notaFiscal, $d->peso_bruto_kg))->values()->all(),
                'tracao'        => $tracao ? $this->itemVeiculo($tracao->placa, $tracao->veiculo) : null,
                'reboques'      => $reboques->filter(fn ($r) => $r->veiculo)
                    ->map(fn ($r) => $this->itemVeiculo($r->placa, $r->veiculo))->values()->all(),
                'condutores'    => ($tracao?->condutores ?? collect())
                    ->filter(fn ($c) => $c->condutor_id)
                    ->map(fn ($c) => ['id' => $c->condutor_id, 'nome' => $c->nome, 'cpf' => Condutor::make(['cpf' => $c->cpf])->cpf_formatado])
                    ->values()->all(),
            ];
        }

        // MDF-e novo: carregamento começa pelo município da empresa
        return [
            'percurso'      => [],
            'carregamentos' => $empresa && preg_match('/^\d{7}$/', (string) $empresa->cod_municipio)
                ? [['codigo_ibge' => $empresa->cod_municipio, 'nome' => $empresa->municipio, 'uf' => $empresa->uf]]
                : [],
            'documentos'    => [],
            'tracao'        => null,
            'reboques'      => [],
            'condutores'    => [],
        ];
    }

    private function estadoDoInputAntigo(array $old): array
    {
        $notas = NotaFiscal::with('cliente')
            ->whereIn('id', collect($old['documentos'] ?? [])->pluck('nota_fiscal_id')->filter())
            ->get()->keyBy('id');

        $documentos = collect($old['documentos'] ?? [])
            ->filter(fn ($l) => isset($l['nota_fiscal_id']) && $notas->has($l['nota_fiscal_id']))
            ->map(fn ($l) => $this->itemDocumento($notas[$l['nota_fiscal_id']], $l['peso_bruto_kg'] ?? null))
            ->values()->all();

        $veiculos = Veiculo::with('transportador')
            ->whereIn('id', array_filter(array_merge([$old['veiculo_tracao_id'] ?? null], $old['reboques'] ?? [])))
            ->get()->keyBy('id');

        $condutores = Condutor::whereIn('id', $old['condutores'] ?? [])->get()->keyBy('id');

        return [
            'percurso'      => array_values($old['percurso'] ?? []),
            'carregamentos' => array_values($old['carregamentos'] ?? []),
            'documentos'    => $documentos,
            'tracao'        => isset($veiculos[$old['veiculo_tracao_id'] ?? 0])
                ? $this->itemVeiculo(null, $veiculos[$old['veiculo_tracao_id']]) : null,
            'reboques'      => collect($old['reboques'] ?? [])->filter(fn ($id) => $veiculos->has($id))
                ->map(fn ($id) => $this->itemVeiculo(null, $veiculos[$id]))->values()->all(),
            'condutores'    => collect($old['condutores'] ?? [])->filter(fn ($id) => $condutores->has($id))
                ->map(fn ($id) => ['id' => $condutores[$id]->id, 'nome' => $condutores[$id]->nome, 'cpf' => $condutores[$id]->cpf_formatado])
                ->values()->all(),
        ];
    }

    private function itemDocumento(NotaFiscal $n, $peso = null): array
    {
        $c = $n->cliente;

        return [
            'nota_fiscal_id' => $n->id,
            'numero'         => $n->numero,
            'serie'          => $n->serie,
            'cliente'        => $c?->nome,
            'municipio'      => $c?->municipio ? "{$c->municipio}/{$c->uf}" : null,
            'cod_municipio'  => $c?->cod_municipio,
            'valor'          => (float) $n->valor_total,
            'peso'           => (float) ($peso ?? $n->vol_peso_bruto ?? 0),
        ];
    }

    private function itemVeiculo(?string $placa, ?Veiculo $v): array
    {
        return [
            'id'             => $v?->id,
            'placa'          => $v?->placa_formatada ?? $placa,
            'transportadora' => $v?->transportador?->nome,
        ];
    }
}