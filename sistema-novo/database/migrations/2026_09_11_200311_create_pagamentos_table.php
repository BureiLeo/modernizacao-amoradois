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
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            // Restrict: pagamento e historico financeiro - nao some
            // junto se a venda for removida por engano.
            $table->foreignId('venda_id')
                ->constrained('vendas')->restrictOnDelete();
            $table->string('forma', 30);
            $table->decimal('valor', 12, 2);
            $table->string('status', 20)->default('pago');
            $table->dateTime('pago_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('venda_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
