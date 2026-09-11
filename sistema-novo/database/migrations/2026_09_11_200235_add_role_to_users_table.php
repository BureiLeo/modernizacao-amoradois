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
        Schema::table('users', function (Blueprint $table) {
            // Papel do usuario no sistema (App\Enums\UserRole). Guardado
            // como string (nao ENUM do banco) para poder evoluir sem
            // precisar de migration. Default 'vendedor' = menor privilegio.
            $table->string('role', 20)->default('vendedor')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
