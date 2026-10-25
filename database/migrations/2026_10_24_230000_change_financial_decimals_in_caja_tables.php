<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // transacciones.monto — pagos de ticket pueden tener 4 decimales
        Schema::table('transacciones', function (Blueprint $table) {
            $table->decimal('monto', 12, 4)->change();
        });

        // sesion_caja_pagos — importes calculados desde transacciones
        Schema::table('sesion_caja_pagos', function (Blueprint $table) {
            $table->decimal('importe_sistema', 12, 4)->change();
            $table->decimal('diferencia', 12, 4)->change();
        });

        // sesion_cajas — totales del sistema (total_sistema y diferencia_total son nullable)
        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->decimal('total_sistema', 12, 4)->nullable()->change();
            $table->decimal('diferencia_total', 12, 4)->nullable()->change();
            $table->decimal('total_creditos', 12, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->change();
        });

        Schema::table('sesion_caja_pagos', function (Blueprint $table) {
            $table->decimal('importe_sistema', 12, 2)->change();
            $table->decimal('diferencia', 12, 2)->change();
        });

        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->decimal('total_sistema', 12, 2)->nullable()->change();
            $table->decimal('diferencia_total', 12, 2)->nullable()->change();
            $table->decimal('total_creditos', 12, 2)->default(0)->change();
        });
    }
};
