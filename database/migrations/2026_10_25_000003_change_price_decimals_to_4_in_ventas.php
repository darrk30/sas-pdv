<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('op_gravadas', 15, 4)->default(0)->change();
            $table->decimal('op_exoneradas', 15, 4)->default(0)->change();
            $table->decimal('op_inafectas', 15, 4)->default(0)->change();
            $table->decimal('descuento_total', 15, 4)->default(0)->change();
            $table->decimal('igv', 15, 4)->default(0)->change();
            $table->decimal('total', 15, 4)->default(0)->change();
            $table->decimal('costo_total', 15, 4)->default(0)->change();
            $table->decimal('monto_pagado', 15, 4)->default(0)->change();
            $table->decimal('saldo_pendiente', 15, 4)->default(0)->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('descuento', 15, 4)->default(0)->change();
            $table->decimal('subtotal', 15, 4)->change();
            $table->decimal('valor_total', 15, 4)->change();
            $table->decimal('igv', 15, 4)->default(0)->change();
            $table->decimal('total', 15, 4)->change();
            $table->decimal('costo_total', 15, 4)->default(0)->change();
        });

        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->decimal('monto', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('op_gravadas', 12, 2)->default(0)->change();
            $table->decimal('op_exoneradas', 12, 2)->default(0)->change();
            $table->decimal('op_inafectas', 12, 2)->default(0)->change();
            $table->decimal('descuento_total', 12, 2)->default(0)->change();
            $table->decimal('igv', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->default(0)->change();
            $table->decimal('costo_total', 12, 2)->default(0)->change();
            $table->decimal('monto_pagado', 12, 2)->default(0)->change();
            $table->decimal('saldo_pendiente', 12, 2)->default(0)->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('descuento', 12, 2)->default(0)->change();
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('valor_total', 12, 2)->change();
            $table->decimal('igv', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->change();
            $table->decimal('costo_total', 12, 2)->default(0)->change();
        });

        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->change();
        });
    }
};
