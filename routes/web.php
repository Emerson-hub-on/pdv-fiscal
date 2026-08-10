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
    
    });