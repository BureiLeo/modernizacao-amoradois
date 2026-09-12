<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('frete', 12, 2)->default(0)->after('desconto');
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->string('descricao', 180)->nullable()->after('produto_variacao_id');
        });

        // Itens avulsos ("Outros") nao possuem produto vinculado.
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->unsignedBigInteger('produto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('frete');
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn('descricao');
        });
    }
};
