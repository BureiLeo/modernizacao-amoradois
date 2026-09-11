<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    // Rota publica de auto-cadastro DESABILITADA (Etapa 5 #42).
    //
    // ANALISE: este e um sistema INTERNO (equipe da Amor a Dois
    // Personalizados). O sistema legado tinha um register.php publico
    // e SEM QUALQUER autenticacao/checagem (Etapa 0: qualquer pessoa na
    // internet podia criar um usuario com acesso total). Nao ha
    // necessidade de negocio para auto-cadastro publico neste tipo de
    // aplicacao.
    //
    // RECOMENDACAO (aplicada): desabilitar a rota publica agora. A
    // criacao de novos usuarios devera, no futuro, ser feita apenas por
    // um administrador (tela de Usuarios, ainda nao implementada nesta
    // etapa - ver config/navigation.php, item 'usuarios' fica "Em breve").
    //
    // Ate a tela de administracao de usuarios existir, novos usuarios
    // podem ser criados via `php artisan tinker` ou um seeder, usando o
    // Model App\Models\User (ver App\Enums\UserRole para o campo role).
    //
    // O componente Volt 'pages.auth.register' NAO foi removido (o
    // formulario/logica continuam intactos e testados) - apenas a ROTA
    // publica foi retirada, para nao quebrar quem depender do
    // componente em uma tela administrativa futura.
    //
    // Para reativar o auto-cadastro publico, descomente as 3 linhas
    // abaixo:
    // Volt::route('register', 'pages.auth.register')
    //     ->name('register');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
