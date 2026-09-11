<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Produto;
use App\Models\SerieNfe;
use App\Services\NotaFiscalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class NotaFiscalController extends Controller
{
    public function index(Request $request)
    {
        $notas = NotaFiscal::with('cliente')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate(20);

        return view('notasfiscais.index', compact('notas'));
    }

    public function create()
    {
        $clientes = Cliente::ativos()->orderBy('nome')->get();

        return view('notasfiscais.create', compact('clientes'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'cliente_id'        => ['required', 'exists:clientes,id'],
            'natureza_operacao' => ['required', 'string', 'max:255'],
            'finalidade'        => ['required', 'in:1,2,3,4'],
            'itens_json'        => ['required', 'string'],
        ]);

        $itens = json_decode($dados['itens_json'], true);

        if (!is_array($itens) || count($itens) === 0) {
            return back()->withErrors(['itens' => 'Adicione ao menos um item à nota.'])->withInput();
        }

        $notaFiscal = DB::transaction(function () use ($dados, $itens) {
            $notaFiscal = NotaFiscal::create([
                'cliente_id'        => $dados['cliente_id'],
                'natureza_operacao' => $dados['natureza_operacao'],
                'finalidade'        => $dados['finalidade'],
                'operador_id'       => auth()->id(),
                'tipo_operacao'     => 'saida',
                'origem_tipo'       => 'manual',
                'status'            => 'rascunho',
            ]);

            foreach ($itens as $itemDados) {
                $produto = Produto::findOrFail($itemDados['produto_id']);

                $quantidade    = (float) $itemDados['quantidade'];
                $valorUnitario = (float) $itemDados['valor_unitario'];
                $valorDesconto = (float) ($itemDados['valor_desconto'] ?? 0);
                $valorTotal    = ($quantidade * $valorUnitario) - $valorDesconto;

                $notaFiscal->itens()->create([
                    'produto_id'            => $produto->id,
                    'cfop'                  => $itemDados['cfop'],
                    // snapshot da tributação do produto no momento do cadastro
                    'ncm_id'                => $produto->ncm_id,
                    'cest_id'               => $produto->cest_id,
                    'class_trib_ibs_cbs_id' => $produto->class_trib_ibs_cbs_id,
                    'tributacao_id'         => $produto->tributacao_id,
                    'pis_cofins_id'         => $produto->pis_cofins_id,
                    'ipi_id'                => $produto->ipi_id,
                    'quantidade'            => $quantidade,
                    'valor_unitario'        => $valorUnitario,
                    'valor_desconto'        => $valorDesconto,
                    'valor_total'           => $valorTotal,
                ]);
            }

            $notaFiscal->recalcularTotais();

            return $notaFiscal;
        });

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal criada com sucesso.');
    }

    public function show(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load(['itens.produto', 'cliente']);

        return view('notasfiscais.show', compact('notaFiscal'));
    }

    public function destroy(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Só é possível excluir notas em rascunho.');

        $notaFiscal->delete();

        return redirect()->route('notasfiscais.index')->with('sucesso', 'Nota fiscal excluída.');
    }

    /**
     * Busca produto por código de barras ou nome — mesmo padrão do buscarProduto do PDV.
     */
    public function buscarProduto(Request $request)
    {
        $termo = $request->get('termo');

        $produtos = Produto::ativos()
            ->where(function ($q) use ($termo) {
                $q->where('codigo_barras', $termo)
                    ->orWhere('codigo_interno', $termo)
                    ->orWhere('nome', 'like', "{$termo}%"); // começa com, não "contém"
            })
            ->limit(10)
            ->get(['id', 'nome', 'codigo_interno', 'codigo_barras', 'preco_venda', 'ncm_id', 'cest_id', 'class_trib_ibs_cbs_id', 'tributacao_id', 'pis_cofins_id', 'ipi_id']);

        return response()->json($produtos);
    }

    public function adicionarItem(Request $request, NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já emitida, não pode ser alterada.');

        $dados = $request->validate([
            'produto_id'      => ['required', 'exists:produtos,id'],
            'quantidade'      => ['required', 'numeric', 'min:0.001'],
            'valor_unitario'  => ['required', 'numeric', 'min:0'],
            'valor_desconto'  => ['nullable', 'numeric', 'min:0'],
            'cfop'            => ['required', 'string', 'size:4'],
        ]);

        $produto = Produto::findOrFail($dados['produto_id']);

        $valorTotal = ($dados['quantidade'] * $dados['valor_unitario']) - ($dados['valor_desconto'] ?? 0);

        $notaFiscal->itens()->create([
            'produto_id'             => $produto->id,
            'cfop'                   => $dados['cfop'],
            // snapshot da tributação do produto no momento em que entra na nota
            'ncm_id'                 => $produto->ncm_id,
            'cest_id'                => $produto->cest_id,
            'class_trib_ibs_cbs_id'  => $produto->class_trib_ibs_cbs_id,
            'tributacao_id'          => $produto->tributacao_id,
            'pis_cofins_id'          => $produto->pis_cofins_id,
            'ipi_id'                 => $produto->ipi_id,
            'quantidade'             => $dados['quantidade'],
            'valor_unitario'         => $dados['valor_unitario'],
            'valor_desconto'         => $dados['valor_desconto'] ?? 0,
            'valor_total'            => $valorTotal,
        ]);

        $notaFiscal->recalcularTotais();

        return back()->with('sucesso', 'Item adicionado.');
    }

    public function removerItem(NotaFiscal $notaFiscal, NotaFiscalItem $item)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já emitida, não pode ser alterada.');
        abort_if($item->nota_fiscal_id !== $notaFiscal->id, 404);

        $item->delete();
        $notaFiscal->recalcularTotais();

        return back()->with('sucesso', 'Item removido.');
    }

    public function emitir(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'rascunho', 403, 'Nota já foi emitida ou cancelada.');
        abort_if(!$notaFiscal->cliente_id, 422, 'Selecione o cliente antes de emitir.');
        abort_if($notaFiscal->itens()->count() === 0, 422, 'Adicione ao menos um item antes de emitir.');

        try {
            DB::transaction(function () use ($notaFiscal) {
                $serie = SerieNfe::ativas()->lockForUpdate()->firstOrFail();
                $proximoNumero = $serie->numero_atual + 1;

                // Atribuição direta (não update()) — esses campos ficam de propósito
                // fora do $fillable, então mass assignment não gravaria nada aqui.
                $notaFiscal->serie_nfe_id = $serie->id;
                $notaFiscal->serie = $serie->serie;
                $notaFiscal->numero = $proximoNumero;
                $notaFiscal->save();

                $serie->update(['numero_atual' => $proximoNumero]);

                $resultado = (new NotaFiscalService())->emitir($notaFiscal);

                $notaFiscal->status = 'emitida';
                $notaFiscal->chave_acesso = $resultado['chave_acesso'];
                $notaFiscal->protocolo = $resultado['protocolo'];
                $notaFiscal->xml = $resultado['xml'];
                $notaFiscal->emitida_em = now();
                $notaFiscal->save();
            });
        } catch (\Throwable $e) {
            $notaFiscal->motivo_rejeicao = $e->getMessage();
            $notaFiscal->save();

            return back()->withErrors(['emissao' => 'Falha ao emitir: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal emitida com sucesso!');
    }

    public function formCancelar(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 403, 'Só é possível cancelar notas emitidas.');

        return view('notasfiscais.cancelar', compact('notaFiscal'));
    }

    public function cancelar(Request $request, NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 403, 'Só é possível cancelar notas emitidas.');

        $dados = $request->validate([
            'motivo_cancelamento' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $notaFiscal->status = 'cancelada';
        $notaFiscal->motivo_cancelamento = $dados['motivo_cancelamento'];
        $notaFiscal->save();

        return redirect()->route('notasfiscais.index')->with('sucesso', 'Nota fiscal cancelada.');
    }

    public function danfe(NotaFiscal $notaFiscal)
    {
        abort_if($notaFiscal->status !== 'emitida', 404);

        // (new NotaFiscalService())->gerarDanfe($notaFiscal) -> retorna PDF (usando NFePHP\DA\NFe\Danfe)
        abort(501, 'Geração de DANFE ainda não implementada.');
    }

    public function xml(NotaFiscal $notaFiscal)
    {
        abort_if(!$notaFiscal->xml, 404);

        return response($notaFiscal->xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=nfe-{$notaFiscal->numero}.xml",
        ]);
    }

    public function previsualizar(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load(['itens.produto', 'itens.ncm', 'itens.tributacao', 'cliente']);
        $empresa = Empresa::first();

        $totalBaseIcms = 0;
        $totalValorIcms = 0;

        $itens = $notaFiscal->itens->values()->map(function ($item, $index) use (&$totalBaseIcms, &$totalValorIcms) {
            $trib = $item->tributacao;
            $baseIcms = 0;
            $valorIcms = 0;
            $aliquotaIcms = 0;
            $cstOuCsosn = $trib?->csosn ?? $trib?->cst_icms ?? '—';

            if ($trib && $trib->cst_icms && in_array($trib->cst_icms, ['00', '10', '20', '70', '90'], true)) {
                $baseIcms = $item->valor_unitario * $item->quantidade;
                $aliquotaIcms = (float) $trib->aliquota_icms;
                $valorIcms = $baseIcms * $aliquotaIcms / 100;
            }

            $totalBaseIcms += $baseIcms;
            $totalValorIcms += $valorIcms;

            return [
                'numero'         => $index + 1,
                'codigo'         => $item->produto->codigo_interno,
                'descricao'      => $item->produto->nome,
                'ncm'            => $item->ncm->codigo ?? '—',
                'cst'            => $cstOuCsosn,
                'cfop'           => $item->cfop,
                'unidade'        => $item->produto->unidade_comercial,
                'quantidade'     => number_format($item->quantidade, 3, ',', '.'),
                'valor_unitario' => number_format($item->valor_unitario, 2, ',', '.'),
                'valor_total'    => number_format($item->valor_total, 2, ',', '.'),
                'bc_icms'        => number_format($baseIcms, 2, ',', '.'),
                'valor_icms'     => number_format($valorIcms, 2, ',', '.'),
                'aliquota_icms'  => number_format($aliquotaIcms, 2, ',', '.'),
            ];
        });

        $dados = [
            'emitida'           => $notaFiscal->status !== 'rascunho',
            'status'            => $notaFiscal->status,
            'numero'            => $notaFiscal->numero ?? '(a definir)',
            'serie'             => $notaFiscal->serie ?? '(a definir)',
            'natureza_operacao' => $notaFiscal->natureza_operacao,
            'tipo_operacao'     => $notaFiscal->tipo_operacao === 'entrada' ? '0-Entrada' : '1-Saída',
            'chave_acesso'      => $notaFiscal->chave_acesso ? $this->formatarChave($notaFiscal->chave_acesso) : null,
            'protocolo'         => $notaFiscal->protocolo,
            'data_emissao'      => $notaFiscal->emitida_em?->format('d/m/Y H:i') ?? '—',
            'ambiente'          => (int) $empresa->ambiente === 2 ? 'HOMOLOGAÇÃO' : 'PRODUÇÃO',

            'emitente' => [
                'razao_social'  => $empresa->razao_social,
                'nome_fantasia' => $empresa->nome_fantasia,
                'cnpj'          => $this->formatarCnpj($empresa->cnpj),
                'ie'            => $empresa->ie,
                'endereco'      => $empresa->logradouro . ', ' . $empresa->numero . ($empresa->complemento ? ' - ' . $empresa->complemento : ''),
                'bairro'        => $empresa->bairro,
                'municipio'     => $empresa->municipio,
                'uf'            => $empresa->uf,
                'cep'           => $this->formatarCep($empresa->cep),
            ],

            'destinatario' => [
                'nome'      => $notaFiscal->cliente->nome,
                'documento' => $notaFiscal->cliente->cpf_cnpj_formatado,
                'ie'        => $notaFiscal->cliente->ie ?? 'ISENTO',
                'endereco'  => $notaFiscal->cliente->logradouro . ', ' . $notaFiscal->cliente->numero . ($notaFiscal->cliente->complemento ? ' - ' . $notaFiscal->cliente->complemento : ''),
                'bairro'    => $notaFiscal->cliente->bairro,
                'municipio' => $notaFiscal->cliente->municipio,
                'uf'        => $notaFiscal->cliente->uf,
                'cep'       => $this->formatarCep($notaFiscal->cliente->cep),
                'telefone'  => $notaFiscal->cliente->telefone,
            ],

            'totais' => [
                'base_calculo_icms' => number_format($totalBaseIcms, 2, ',', '.'),
                'valor_icms'        => number_format($totalValorIcms, 2, ',', '.'),
                'valor_produtos'    => number_format($notaFiscal->valor_produtos, 2, ',', '.'),
                'valor_frete'       => number_format($notaFiscal->valor_frete, 2, ',', '.'),
                'valor_desconto'    => number_format($notaFiscal->valor_desconto, 2, ',', '.'),
                'valor_total_nota'  => number_format($notaFiscal->valor_total, 2, ',', '.'),
            ],

            'itens' => $itens,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('notasfiscais.pdf.previsualizacao', compact('dados'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("previsualizacao-nf-{$notaFiscal->id}.pdf");
    }

    private function formatarCnpj(string $cnpj): string
    {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    private function formatarCep(?string $cep): string
    {
        return $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : '—';
    }

    private function formatarChave(string $chave): string
    {
        return implode(' ', str_split($chave, 4));
    }
}