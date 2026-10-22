<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignId('gasto_fijo_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('gastos_fijos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\GastoFijo::class);
            $table->dropColumn('gasto_fijo_id');
        });
    }
};
