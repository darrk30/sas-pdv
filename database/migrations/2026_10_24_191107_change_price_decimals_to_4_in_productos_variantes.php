<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_venta', 15, 4)->default(0)->change();
            $table->decimal('precio_costo', 15, 4)->default(0)->change();
            $table->decimal('precio_con_descuento', 15, 4)->default(0)->change();
        });

        Schema::table('variantes', function (Blueprint $table) {
            $table->decimal('precio_final', 12, 4)->default(0)->change();
        });

        Schema::table('lista_precio_producto', function (Blueprint $table) {
            $table->decimal('precio', 15, 4)->default(0)->change();
        });

        Schema::table('producto_atributo_valors', function (Blueprint $table) {
            $table->decimal('precio_adicional', 15, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_venta', 15, 2)->default(0)->change();
            $table->decimal('precio_costo', 15, 2)->default(0)->change();
            $table->decimal('precio_con_descuento', 15, 2)->default(0)->change();
        });

        Schema::table('variantes', function (Blueprint $table) {
            $table->decimal('precio_final', 12, 2)->default(0)->change();
        });

        Schema::table('lista_precio_producto', function (Blueprint $table) {
            $table->decimal('precio', 15, 2)->default(0)->change();
        });

        Schema::table('producto_atributo_valors', function (Blueprint $table) {
            $table->decimal('precio_adicional', 15, 2)->default(0)->change();
        });
    }
};
