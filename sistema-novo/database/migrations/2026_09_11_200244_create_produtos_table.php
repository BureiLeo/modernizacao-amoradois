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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->nullable()
                ->constrained('categorias')->nullOnDelete();
            $table->string('nome');
            $table->string('sku', 60)->nullable();
            $table->decimal('preco_venda', 12, 2)->default(0);
            // Custo de referencia (cache), recalculado pela BOM pela
            // aplicacao - nao e a fonte da verdade do custo de uma venda
            // ja realizada (esse fica congelado em venda_itens).
            $table->decimal('custo_referencia', 12, 6)->nullable();
            // Nullable: produto tipicamente nao tem estoque fisico
            // proprio (consome materia-prima via BOM), mas fica preparado
            // para produtos com estoque direto no futuro.
            $table->decimal('estoque_minimo', 12, 3)->nullable();
            $table->string('imagem')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nome');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
