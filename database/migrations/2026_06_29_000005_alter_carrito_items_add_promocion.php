<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrito_items', function (Blueprint $table) {
            // Hacer producto_id nullable para soportar ítems de promoción
            $table->dropForeign(['producto_id']);
            $table->unsignedBigInteger('producto_id')->nullable()->change();
            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();

            // Nueva columna para promociones
            $table->foreignId('promocion_id')
                ->nullable()
                ->after('carrito_id')
                ->constrained('promociones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carrito_items', function (Blueprint $table) {
            $table->dropForeign(['promocion_id']);
            $table->dropColumn('promocion_id');

            $table->dropForeign(['producto_id']);
            $table->unsignedBigInteger('producto_id')->nullable(false)->change();
            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
        });
    }
};
