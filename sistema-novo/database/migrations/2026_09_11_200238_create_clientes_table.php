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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // Codigo sequencial "amigavel" usado no atendimento/venda
            // (equivalente ao codigo do sistema legado).
            $table->unsignedInteger('codigo')->unique();
            $table->string('nome');
            // Sem UNIQUE em telefone/email: o banco legado tem telefone
            // legitimamente duplicado entre dois cadastros (Etapa 2).
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 20)->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->string('razao_social')->nullable();
            $table->text('endereco')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
