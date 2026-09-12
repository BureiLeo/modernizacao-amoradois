<?php

use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Livewire\Clientes\Create as ClientesCreate;
use App\Livewire\Clientes\Edit as ClientesEdit;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Clientes\Show as ClientesShow;
use App\Livewire\Compras\Create as ComprasCreate;
use App\Livewire\Compras\Index as ComprasIndex;
use App\Livewire\Compras\Show as ComprasShow;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Estoque\Index as EstoqueIndex;
use App\Livewire\Materiais\Create as MateriaisCreate;
use App\Livewire\Materiais\Edit as MateriaisEdit;
use App\Livewire\Materiais\Show as MateriaisShow;
use App\Livewire\Perdas\Index as PerdasIndex;
use App\Livewire\Produtos\Create as ProdutosCreate;
use App\Livewire\Produtos\Edit as ProdutosEdit;
use App\Livewire\Produtos\Index as ProdutosIndex;
use App\Livewire\Produtos\Show as ProdutosShow;
use App\Livewire\Vendas\Create as VendasCreate;
use App\Livewire\Vendas\Edit as VendasEdit;
use App\Livewire\Vendas\Index as VendasIndex;
use App\Livewire\Vendas\Show as VendasShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

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

Route::middleware(['auth'])->prefix('vendas')->name('vendas.')->group(function () {
    Route::get('/', VendasIndex::class)->name('index');
    Route::get('/create', VendasCreate::class)->name('create');
    Route::get('/{venda}', VendasShow::class)->name('show');
    Route::get('/{venda}/edit', VendasEdit::class)->name('edit');
});

Route::middleware(['auth'])->prefix('estoque')->name('estoque.')->group(function () {
    Route::get('/', EstoqueIndex::class)->name('index');
    Route::get('/ajustes', fn () => view('estoque.ajustes'))->name('ajustes');
    Route::get('/movimentacoes', fn () => view('estoque.movimentacoes'))->name('movimentacoes');
    // "Materiais" e "Estoque" sao a mesma lista: existe uma tela so.
    Route::redirect('/materiais', '/estoque')->name('materiais');
});

Route::middleware(['auth'])->prefix('estoque/compras')->name('compras.')->group(function () {
    Route::get('/', ComprasIndex::class)->name('index');
    Route::get('/create', ComprasCreate::class)->name('create');
    Route::get('/{lote}', ComprasShow::class)->name('show');
});

Route::middleware(['auth'])->prefix('materiais')->name('materiais.')->group(function () {
    Route::redirect('/', '/estoque')->name('index');
    Route::get('/create', MateriaisCreate::class)->name('create');
    Route::get('/{material}', MateriaisShow::class)->name('show');
    Route::get('/{material}/edit', MateriaisEdit::class)->name('edit');
});

Route::middleware(['auth'])->prefix('perdas')->name('perdas.')->group(function () {
    Route::get('/', PerdasIndex::class)->name('index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
