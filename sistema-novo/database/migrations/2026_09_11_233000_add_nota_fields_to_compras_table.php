<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de nota fiscal / lote.
     *
     * A tabela "compras" e uma linha por material (igual ao legado). Uma
     * nota fiscal com 10 itens vira 10 linhas, e o "lote_id" e o que
     * permite reagrupar essas linhas como um unico lancamento na tela.
     * A "chave_nfe" serve para detectar nota ja importada e evitar
     * duplicar entrada de estoque.
     */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->uuid('lote_id')->nullable()->after('id');
            $table->string('numero_nota', 30)->nullable()->after('fornecedor_id');
            $table->string('chave_nfe', 44)->nullable()->after('numero_nota');

            $table->index('lote_id');
            $table->index('chave_nfe');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['lote_id']);
            $table->dropIndex(['chave_nfe']);
            $table->dropColumn(['lote_id', 'numero_nota', 'chave_nfe']);
        });
    }
};
