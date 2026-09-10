<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Produto;
use App\Models\SerieNfe;
use App\Services\NotaFiscalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        return view('notasfiscais.create');
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'cliente_id'         => ['required', 'exists:clientes,id'],
            'natureza_operacao'  => ['required', 'string', 'max:255'],
            'finalidade'         => ['required', 'in:1,2,3,4'],
        ]);

        $notaFiscal = NotaFiscal::create([
            ...$dados,
            'operador_id'   => auth()->id(),
            'tipo_operacao' => 'saida',
            'origem_tipo'   => 'manual',
            'status'        => 'rascunho',
        ]);

        return redirect()
            ->route('notasfiscais.show', $notaFiscal)
            ->with('sucesso', 'Nota fiscal criada. Adicione os itens abaixo.');
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
                    ->orWhere('nome', 'like', "%{$termo}%");
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
                // Reserva série/número com lock, igual o fluxo de NFC-e faz com o pdv
                $serie = SerieNfe::ativas()->lockForUpdate()->firstOrFail();
                $proximoNumero = $serie->numero_atual + 1;

                $notaFiscal->update([
                    'serie_nfe_id' => $serie->id,
                    'serie'        => $serie->serie,
                    'numero'       => $proximoNumero,
                ]);

                $serie->update(['numero_atual' => $proximoNumero]);

                // Monta XML, assina e transmite à SEFAZ
                $resultado = (new NotaFiscalService())->emitir($notaFiscal);

                $notaFiscal->update([
                    'status'       => 'emitida',
                    'chave_acesso' => $resultado['chave_acesso'],
                    'protocolo'    => $resultado['protocolo'],
                    'xml'          => $resultado['xml'],
                    'emitida_em'   => now(),
                ]);
            });
        } catch (\Throwable $e) {
            $notaFiscal->update(['motivo_rejeicao' => $e->getMessage()]);

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

        // (new NotaFiscalService())->cancelar($notaFiscal, $dados['motivo_cancelamento']);

        $notaFiscal->update([
            'status'              => 'cancelada',
            'motivo_cancelamento' => $dados['motivo_cancelamento'],
        ]);

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
}