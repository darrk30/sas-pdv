<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->decimal('costo_envio', 15, 4)->default(0)->change();
            $table->decimal('descuento_total', 15, 4)->default(0)->change();
            $table->decimal('descuento_cupon', 15, 4)->default(0)->change();
            $table->decimal('igv', 15, 4)->default(0)->change();
            $table->decimal('subtotal', 15, 4)->default(0)->change();
            $table->decimal('total', 15, 4)->default(0)->change();
        });

        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->decimal('precio_unitario', 15, 4)->change();
            $table->decimal('subtotal', 15, 4)->change();
            $table->decimal('total', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->decimal('costo_envio', 10, 2)->default(0)->change();
            $table->decimal('descuento_total', 12, 2)->default(0)->change();
            $table->decimal('descuento_cupon', 12, 2)->default(0)->change();
            $table->decimal('igv', 12, 2)->default(0)->change();
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->default(0)->change();
        });

        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->decimal('precio_unitario', 12, 4)->change();
            $table->decimal('subtotal', 12, 4)->change();
            $table->decimal('total', 12, 4)->change();
        });
    }
};
