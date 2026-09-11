<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Produto;
use App\Policies\CategoriaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ProdutoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro explicito (nao depender so da convencao de auto-discovery)
        // - Etapa 6/7.
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Produto::class, ProdutoPolicy::class);
        Gate::policy(Categoria::class, CategoriaPolicy::class);
    }
}
