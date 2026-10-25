<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('total',       12, 4)->change();
            $table->decimal('subtotal',    12, 4)->change();
            $table->decimal('valor_total', 12, 4)->change();
            $table->decimal('costo_total', 12, 4)->change();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('total',           12, 4)->change();
            $table->decimal('op_inafectas',    12, 4)->change();
            $table->decimal('monto_pagado',    12, 4)->change();
            $table->decimal('saldo_pendiente', 12, 4)->change();
            $table->decimal('descuento_total', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('total',       12, 2)->change();
            $table->decimal('subtotal',    12, 2)->change();
            $table->decimal('valor_total', 12, 2)->change();
            $table->decimal('costo_total', 12, 2)->change();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('total',           12, 2)->change();
            $table->decimal('op_inafectas',    12, 2)->change();
            $table->decimal('monto_pagado',    12, 2)->change();
            $table->decimal('saldo_pendiente', 12, 2)->change();
            $table->decimal('descuento_total', 12, 2)->change();
        });
    }
};
