<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\VendaController;
use App\Http\Controllers\FiscalController;
use App\Http\Controllers\PdvController;
use App\Http\Controllers\ContingenciaController;
use App\Services\FiscalEmissorService;
use App\Http\Controllers\InutilizacaoController;
use App\Http\Controllers\CancelamentoController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SincronizacaoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\NcmController;
use App\Http\Controllers\TributacaoController;
use App\Http\Controllers\CestController;
use App\Http\Controllers\ClassificacaoTributariaController;
use App\Http\Controllers\ClassificacaoPisCofinsController;
use App\Http\Controllers\ClassificacaoIpiController;
use App\Http\Controllers\ClienteController;





Route::get('/', [AuthController::class, 'tela'])->name('auth.escolha');
Route::get('/login', [AuthController::class, 'formulario'])->name('auth.login');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::middleware('auth')->group(function () {
    Route::resource('produtos', ProdutoController::class)->except(['destroy', 'show']);
    Route::patch('produtos/{produto}/toggle-ativo', [ProdutoController::class, 'toggleAtivo'])
    ->name('produtos.toggle-ativo');
    Route::get('empresa', [EmpresaController::class, 'editar'])->name('empresa.editar');
    Route::post('empresa', [EmpresaController::class, 'salvar'])->name('empresa.salvar');
    Route::get('caixa/abrir', [CaixaController::class, 'abrirForm'])->name('caixa.abrir-form');
    Route::post('caixa/abrir', [CaixaController::class, 'abrir'])->name('caixa.abrir');
    Route::get('caixa/fechar', [CaixaController::class, 'fecharForm'])->name('caixa.fechar-form');
    Route::post('caixa/fechar', [CaixaController::class, 'fechar'])->name('caixa.fechar');
    Route::get('pdv', [VendaController::class, 'pdv'])->name('vendas.pdv');
    Route::get('pdv/buscar-produto', [VendaController::class, 'buscarProduto'])->name('vendas.buscar-produto');
    Route::post('pdv/finalizar', [VendaController::class, 'finalizar'])->name('vendas.finalizar');
    Route::post('pdv/pagamento/preparar', [VendaController::class, 'prepararPagamento'])->name('vendas.preparar-pagamento');
    Route::get('pdv/pagamento', [VendaController::class, 'telaPagamento'])->name('vendas.pagamento');
    Route::get('pdv/venda/{uuid}/comprovante', [FiscalController::class, 'comprovante'])->name('vendas.comprovante');
    Route::post('pdv/venda/{uuid}/emitir', [FiscalController::class, 'emitir'])->name('vendas.emitir');
    Route::resource('pdvs', PdvController::class)->except(['destroy', 'show']);
    Route::patch('pdvs/{pdv}/toggle-ativo', [PdvController::class, 'toggleAtivo'])->name('pdvs.toggle-ativo');
    Route::get('contingencias', [ContingenciaController::class, 'listar'])->name('contingencias.listar');
    Route::post('contingencias/reenviar', [ContingenciaController::class, 'reenviar'])->name('contingencias.reenviar');
    Route::post('contingencias/{venda}/reenviar', [ContingenciaController::class, 'reenviar'])->name('contingencias.reenviar');
    Route::post('inutilizacao', [InutilizacaoController::class, 'executar'])->name('inutilizacao.executar');
    Route::get('cancelamento/listar', [CancelamentoController::class, 'listar'])->name('cancelamento.listar');
    Route::post('cancelamento/{venda}/cancelar', [CancelamentoController::class, 'cancelar'])->name('cancelamento.cancelar');
    Route::post('supervisor/autorizar', [SupervisorController::class, 'autorizar'])->name('supervisor.autorizar');
    Route::post('sincronizar-agora', [SincronizacaoController::class, 'executar'])->name('sincronizacao.executar');
    Route::post('pdv/limpar-sessao', [VendaController::class, 'limparSessaoCarrinho'])->name('vendas.limpar-sessao');
    Route::get('catalogo/listar', [CatalogoController::class, 'listar'])->name('catalogo.listar');
    Route::post('catalogo/criar', [CatalogoController::class, 'criar'])->name('catalogo.criar');
    Route::get('ncm/listar', [NcmController::class, 'listar'])->name('ncm.listar');
    Route::post('ncm/criar', [NcmController::class, 'criar'])->name('ncm.criar');
    Route::post('catalogo/editar', [CatalogoController::class, 'editar'])->name('catalogo.editar');
    Route::post('catalogo/excluir', [CatalogoController::class, 'excluir'])->name('catalogo.excluir');
    Route::post('ncm/editar', [NcmController::class, 'editar'])->name('ncm.editar');
    Route::post('ncm/excluir', [NcmController::class, 'excluir'])->name('ncm.excluir');
    Route::get('tributacao/listar', [TributacaoController::class, 'listar'])->name('tributacao.listar');
    Route::get('/cests/buscar', [CestController::class, 'buscar'])->name('cests.buscar');
    Route::get('/cest/listar', [CestController::class, 'listar'])->name('cest.listar');
    Route::post('/cest/criar', [CestController::class, 'criar'])->name('cest.criar');
    Route::post('/cest/editar', [CestController::class, 'editar'])->name('cest.editar');
    Route::post('/cest/excluir', [CestController::class, 'excluir'])->name('cest.excluir');
    Route::get('/classificacao-tributaria/listar', [ClassificacaoTributariaController::class, 'listar'])->name('classtrib.listar');
    Route::post('/classificacao-tributaria/criar', [ClassificacaoTributariaController::class, 'criar'])->name('classtrib.criar');
    Route::post('/classificacao-tributaria/editar', [ClassificacaoTributariaController::class, 'editar'])->name('classtrib.editar');
    Route::post('/classificacao-tributaria/excluir', [ClassificacaoTributariaController::class, 'excluir'])->name('classtrib.excluir');
    Route::get('/pis-cofins/listar', [ClassificacaoPisCofinsController::class, 'listar'])->name('piscofins.listar');
    Route::post('/pis-cofins/criar', [ClassificacaoPisCofinsController::class, 'criar'])->name('piscofins.criar');
    Route::post('/pis-cofins/editar', [ClassificacaoPisCofinsController::class, 'editar'])->name('piscofins.editar');
    Route::post('/pis-cofins/excluir', [ClassificacaoPisCofinsController::class, 'excluir'])->name('piscofins.excluir');
    Route::get('/ipi/listar', [ClassificacaoIpiController::class, 'listar'])->name('ipi.listar');
    Route::post('/ipi/criar', [ClassificacaoIpiController::class, 'criar'])->name('ipi.criar');
    Route::post('/ipi/editar', [ClassificacaoIpiController::class, 'editar'])->name('ipi.editar');
    Route::post('/ipi/excluir', [ClassificacaoIpiController::class, 'excluir'])->name('ipi.excluir');
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/criar', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::post('/clientes/{cliente}/toggle-ativo', [ClienteController::class, 'toggleAtivo'])->name('clientes.toggleAtivo');
    
    // Endpoints JSON usados pelo modal "Adicionar consumidor" no caixa
    Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
    Route::post('/clientes/criar-rapido', [ClienteController::class, 'criarRapido'])->name('clientes.criarRapido');
    Route::get('/api/consulta-cnpj/{cnpj}', [ClienteController::class, 'consultarCnpj'])
    ->name('clientes.consultarCnpj');
















    });