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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Restrict: protege a rastreabilidade - nao deixa apagar um
            // material que ja tem movimentacao de estoque registrada.
            $table->foreignId('material_id')
                ->constrained('materiais')->restrictOnDelete();
            $table->foreignId('produto_id')->nullable()
                ->constrained('produtos')->nullOnDelete();
            $table->foreignId('produto_variacao_id')->nullable()
                ->constrained('produto_variacoes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            // compra | venda | perda | ajuste | devolucao | estorno
            // (App\Enums\StockMovementType).
            $table->string('type', 20);
            // Quantidade com sinal: positiva = entrada, negativa = saida.
            $table->decimal('quantity', 12, 3);
            $table->decimal('quantity_before', 12, 3)->nullable();
            $table->decimal('quantity_after', 12, 3)->nullable();
            // De onde veio a movimentacao (ex.: source_type=venda,
            // source_id=123) - mesma logica do ref_tabela/ref_id legado,
            // para nunca perder a rastreabilidade da origem.
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
