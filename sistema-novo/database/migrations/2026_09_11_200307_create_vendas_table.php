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
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data');
            // Nullable: 96 das 197 vendas do legado nao tem cliente
            // vinculado (Etapa 2) - precisa continuar sendo permitido.
            $table->foreignId('cliente_id')->nullable()
                ->constrained('clientes')->nullOnDelete();
            // Vendedor que registrou a venda.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            // Persistidos mesmo podendo ser recalculados pelos itens,
            // para auditoria/performance de relatorio (pedido explicito
            // da Etapa 3).
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('custo_total', 12, 2)->default(0);
            $table->decimal('lucro_total', 12, 2)->default(0);
            // Forma de pagamento "legada" (venda com 1 pagamento so).
            // Pagamentos multiplos ficam na tabela pagamentos.
            $table->string('forma_pagamento', 30)->nullable();
            $table->boolean('pago')->default(false);
            $table->string('canal', 40)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status', 20)->default('concluida');
            $table->timestamps();

            $table->index('data');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendas');
    }
};
