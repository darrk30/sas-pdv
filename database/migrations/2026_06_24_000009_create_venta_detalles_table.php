<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();

            // ── Tipo de ítem y referencias ───────────────────────────────
            $table->string('tipo_item', 20);                                          // producto|variante|promocion
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->foreignId('promocion_id')->nullable()->constrained('promociones')->nullOnDelete();

            // ── Descripción snapshot ─────────────────────────────────────
            $table->string('descripcion');                                             // "Polo (Talla M / Azul)"

            // ── Cantidades y precios ─────────────────────────────────────
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);                                // PVP con IGV
            $table->decimal('valor_unitario', 12, 4);                                 // sin IGV
            $table->decimal('costo_unitario', 12, 4)->default(0);                     // costo de compra
            $table->decimal('descuento', 12, 2)->default(0);

            // ── Totales calculados ───────────────────────────────────────
            $table->decimal('subtotal', 12, 2);                                       // cantidad × valor_unitario
            $table->decimal('valor_total', 12, 2);                                    // subtotal − descuento (base imponible)
            $table->decimal('igv', 12, 2)->default(0);                                // valor_total × 0.18
            $table->decimal('total', 12, 2);                                          // valor_total + igv
            $table->decimal('costo_total', 12, 2)->default(0);                        // cantidad × costo_unitario

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
