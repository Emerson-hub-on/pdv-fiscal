<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('produtos', ProdutoController::class)->except(['destroy', 'show']);
Route::patch('produtos/{produto}/toggle-ativo', [ProdutoController::class, 'toggleAtivo'])
    ->name('produtos.toggle-ativo');