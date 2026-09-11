<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('caixa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data');
            // venda | compra | ajuste | outro (App\Enums\CaixaTipo).
            $table->string('tipo', 20);
            // Referencia polimorfica "por convencao" (igual ao legado):
            // ref_tabela guarda o nome da tabela de origem, ref_id o id.
            $table->string('ref_tabela', 20)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('descricao', 180)->nullable();
            // Valor com sinal: positivo = entrada, negativo = saida.
            $table->decimal('valor', 12, 2);
            $table->timestamps();

            $table->index('data');
            $table->unique(['tipo', 'ref_tabela', 'ref_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caixa_movimentos');
    }
};
