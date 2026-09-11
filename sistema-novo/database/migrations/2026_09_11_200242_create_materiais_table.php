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
        Schema::create('materiais', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('unidade_base', 16);
            $table->unsignedInteger('fator_embalagem')->default(1);
            // Corrigido para decimal (Etapa 2: bug de precisao com estoque
            // int perdendo casas decimais em ajustes fracionados).
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->decimal('custo_medio', 12, 6)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materiais');
    }
};
