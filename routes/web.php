<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;

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
});