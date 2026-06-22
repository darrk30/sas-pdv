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
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->decimal('stock_real', 12, 2)->default(0);
            $table->decimal('stock_reserva', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->string('estado_almacen', 45)->nullable(); // este sera activo - inactivo
            $table->string('estado_inventario', 45)->nullable(); // este sera agotado, por agotarse, con stock
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->onDelete('cascade');
            $table->timestamps();
            $table->index(['empresa_id', 'producto_id', 'variante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
