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
        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')
                ->constrained('vendas')->cascadeOnDelete();
            // Restrict: um produto ja vendido nao pode ser apagado
            // fisicamente (so inativado/soft-deleted) para nao corromper
            // o historico de vendas.
            $table->foreignId('produto_id')
                ->constrained('produtos')->restrictOnDelete();
            $table->foreignId('produto_variacao_id')->nullable()
                ->constrained('produto_variacoes')->nullOnDelete();
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 12, 2);
            // Custo/lucro CONGELADOS no momento da venda - alterar o
            // custo do produto depois nao pode alterar o historico.
            $table->decimal('custo_unitario_calculado', 12, 6)->default(0);
            $table->decimal('lucro_unitario', 12, 6)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venda_itens');
    }
};
