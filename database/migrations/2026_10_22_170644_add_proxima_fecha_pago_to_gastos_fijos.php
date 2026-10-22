<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_fijos', function (Blueprint $table) {
            $table->date('proxima_fecha_pago')->nullable()->after('estado');
            $table->unsignedTinyInteger('dias_aviso')->default(3)->after('proxima_fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('gastos_fijos', function (Blueprint $table) {
            $table->dropColumn(['proxima_fecha_pago', 'dias_aviso']);
        });
    }
};
