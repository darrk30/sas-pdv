<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_egresos', function (Blueprint $table) {
            $table->foreignId('sesion_caja_id')
                ->nullable()
                ->after('user_id')
                ->constrained('sesion_cajas')
                ->nullOnDelete();

            $table->string('estado', 20)
                ->default('aprobado')
                ->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_egresos', function (Blueprint $table) {
            $table->dropForeign(['sesion_caja_id']);
            $table->dropColumn(['sesion_caja_id', 'estado']);
        });
    }
};
