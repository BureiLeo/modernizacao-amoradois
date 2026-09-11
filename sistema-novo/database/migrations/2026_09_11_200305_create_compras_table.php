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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            // Restrict: protege o historico de custos - nao deixa apagar
            // um material que ja teve compra registrada.
            $table->foreignId('material_id')
                ->constrained('materiais')->restrictOnDelete();
            $table->foreignId('fornecedor_id')->nullable()
                ->constrained('fornecedores')->nullOnDelete();
            $table->unsignedInteger('quantidade_embalagem')->default(1);
            // Quantidade na unidade base (equivalente ao qtd_base legado).
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor', 12, 2);
            $table->decimal('custo_unitario', 12, 6);
            $table->decimal('frete', 12, 2)->nullable();
            // Novo campo (nao existia no legado) para melhorar a
            // auditoria - o legado so aplicava o desconto no calculo,
            // sem registrar o valor isolado.
            $table->decimal('desconto', 12, 2)->nullable();
            $table->string('forma_pagamento', 30)->nullable();
            $table->boolean('pago')->default(false);
            $table->string('anexo')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
