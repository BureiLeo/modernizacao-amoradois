<?php

use App\Livewire\Dashboard\Index as DashboardIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
