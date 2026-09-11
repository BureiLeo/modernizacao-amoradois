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
        Schema::create('produto_bom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')
                ->constrained('produtos')->cascadeOnDelete();
            // Restrict: nao deixa apagar um material que ainda esta
            // presente em alguma receita/BOM (mesmo padrao do legado).
            $table->foreignId('material_id')
                ->constrained('materiais')->restrictOnDelete();
            $table->decimal('quantidade', 12, 4);
            $table->timestamps();

            $table->unique(['produto_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produto_bom');
    }
};
