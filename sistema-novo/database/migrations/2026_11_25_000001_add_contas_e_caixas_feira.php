<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixas_feira', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->boolean('aberto')->nullable()->unique();
            $table->decimal('troco_inicial', 12, 2);
            $table->decimal('dinheiro_contado', 12, 2)->nullable();
            $table->foreignId('aberto_por')->constrained('users');
            $table->foreignId('fechado_por')->nullable()->constrained('users');
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('caixa_feira_id')->nullable()->constrained('caixas_feira');
        });

        foreach (['produtos', 'venda_itens', 'compras', 'caixa_movimentos'] as $nome) {
            Schema::table($nome, function (Blueprint $table) {
                $table->string('conta', 20)->default('canecas');
            });
        }
        Schema::create('transferencias_feira', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('caixa_feira_id')->constrained('caixas_feira');
            $table->foreignId('user_id')->constrained('users');
            $table->string('destino', 20);
            $table->decimal('valor', 12, 2);
            $table->decimal('taxa', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_feira');
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caixa_feira_id');
        });
        Schema::dropIfExists('caixas_feira');

        foreach (['produtos', 'venda_itens', 'compras', 'caixa_movimentos'] as $nome) {
            Schema::table($nome, fn (Blueprint $table) => $table->dropColumn('conta'));
        }
    }
};
