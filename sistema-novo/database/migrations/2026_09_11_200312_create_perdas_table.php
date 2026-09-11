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
        Schema::create('perdas', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data');
            // 'produto' (via BOM) ou 'material' (direta) - App\Enums\PerdaTipo.
            $table->string('tipo', 20);
            $table->foreignId('produto_id')->nullable()
                ->constrained('produtos')->nullOnDelete();
            $table->foreignId('material_id')->nullable()
                ->constrained('materiais')->nullOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->string('motivo', 100)->nullable();
            $table->text('observacoes')->nullable();
            $table->decimal('custo_total', 12, 2)->default(0);
            // Novo (nao existia no legado): quem registrou a perda,
            // para auditoria - nao remove nenhum campo existente.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('data');
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perdas');
    }
};
