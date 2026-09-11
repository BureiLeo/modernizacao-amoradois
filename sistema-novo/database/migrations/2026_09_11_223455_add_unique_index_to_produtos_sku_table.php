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
        // Etapa 6/7: o schema original (Etapa 3) nao tinha unique em
        // produtos.sku. Adicionado agora porque o CRUD de produtos
        // passa a validar SKU duplicado (secao 29) - sem essa
        // constraint no banco, duas requisicoes concorrentes poderiam
        // criar SKUs repetidos apesar da validacao da aplicacao.
        // Confirmado antes de aplicar: os 13 produtos migrados nao tem
        // nenhum SKU duplicado (6 com sku=NULL, permitido em indice
        // unique no MySQL/MariaDB - varios NULLs sao aceitos).
        Schema::table('produtos', function (Blueprint $table) {
            $table->unique('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropUnique(['sku']);
        });
    }
};
