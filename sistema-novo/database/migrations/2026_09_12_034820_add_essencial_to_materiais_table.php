<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materiais', function (Blueprint $table) {
            // Materiais "essenciais" (ex.: a caneca em si) bloqueiam a venda
            // quando zeram no estoque. Materiais de apoio (papel, fita,
            // caixinha, embalagem...) nao bloqueiam - a venda segue normal
            // mesmo que faltem, so o estoque desses fica negativo.
            $table->boolean('essencial')->default(true)->after('observacoes');
        });

        // Marca automaticamente os materiais de apoio/embalagem ja
        // cadastrados como "nao essenciais", para nao precisar editar um
        // por um logo apos a migration. O usuario pode ajustar depois em
        // Estoque > editar material.
        $palavrasChave = ['papel', 'fita', 'caixinha', 'sacola', 'embalagem', 'outros custos', 'bombom', 'fitilho', 'etiqueta'];

        DB::table('materiais')
            ->where(function ($query) use ($palavrasChave) {
                foreach ($palavrasChave as $palavra) {
                    $query->orWhere('nome', 'like', "%{$palavra}%");
                }
            })
            ->update(['essencial' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materiais', function (Blueprint $table) {
            $table->dropColumn('essencial');
        });
    }
};
