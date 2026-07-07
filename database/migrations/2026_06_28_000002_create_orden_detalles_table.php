<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();

            // ── Tipo de ítem y referencias ───────────────────────────────
            $table->string('tipo_item', 20);
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->foreignId('promocion_id')->nullable()->constrained('promociones')->nullOnDelete();

            // ── Descripción snapshot ─────────────────────────────────────
            $table->string('descripcion');

            // ── Cantidades y precios ─────────────────────────────────────
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);   // precio con IGV
            $table->decimal('valor_unitario', 12, 4);    // precio sin IGV
            $table->decimal('costo_unitario', 12, 4)->default(0); // costo snapshot
            $table->decimal('descuento', 12, 2)->default(0);

            // ── Totales calculados ───────────────────────────────────────
            $table->decimal('subtotal', 12, 2);          // cantidad × valor_unitario
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2);             // subtotal + igv − descuento
            $table->decimal('costo_total', 12, 2)->default(0); // cantidad × costo_unitario

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_detalles');
    }
};
