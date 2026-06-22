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
        Schema::create('ajuste_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajuste_id')->constrained('ajustes')->cascadeOnDelete();
 
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
 
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();
 
            $table->string('nombre_producto');
 
            $table->foreignId('unidad_id')->constrained('unidades_medidas')->restrictOnDelete();
 
            $table->decimal('cantidad', 12, 4);
 
            $table->decimal('costo_unitario', 12, 4)->default(0);
 
            $table->decimal('costo_total', 12, 4)->default(0);
 
            $table->timestamps();
 
            // Constraint: exactamente uno de los dos debe ser no nulo
            $table->index(['ajuste_id', 'producto_id']);
            $table->index(['ajuste_id', 'variante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajuste_detalles');
    }
};
