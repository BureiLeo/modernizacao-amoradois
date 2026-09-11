<?php

use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Livewire\Clientes\Create as ClientesCreate;
use App\Livewire\Clientes\Edit as ClientesEdit;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Clientes\Show as ClientesShow;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Produtos\Create as ProdutosCreate;
use App\Livewire\Produtos\Edit as ProdutosEdit;
use App\Livewire\Produtos\Index as ProdutosIndex;
use App\Livewire\Produtos\Show as ProdutosShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('categorias', CategoriasIndex::class)
    ->middleware(['auth'])
    ->name('categorias.index');

Route::middleware(['auth'])->prefix('clientes')->name('clientes.')->group(function () {
    Route::get('/', ClientesIndex::class)->name('index');
    Route::get('/create', ClientesCreate::class)->name('create');
    Route::get('/{cliente}', ClientesShow::class)->name('show');
    Route::get('/{cliente}/edit', ClientesEdit::class)->name('edit');
});

Route::middleware(['auth'])->prefix('produtos')->name('produtos.')->group(function () {
    Route::get('/', ProdutosIndex::class)->name('index');
    Route::get('/create', ProdutosCreate::class)->name('create');
    Route::get('/{produto}', ProdutosShow::class)->name('show');
    Route::get('/{produto}/edit', ProdutosEdit::class)->name('edit');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
