<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Apenas dados minimos para desenvolvimento local - NENHUM dado
     * real do sistema legado e importado aqui (a migracao de dados
     * reais e uma etapa separada, ainda nao autorizada).
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@amoradois.test',
            'role' => UserRole::Admin,
        ]);

        collect(['Canecas', 'Camisetas', 'Almofadas', 'Chaveiros', 'Quadros'])
            ->each(fn (string $nome) => Categoria::factory()->create(['nome' => $nome]));
    }
}
