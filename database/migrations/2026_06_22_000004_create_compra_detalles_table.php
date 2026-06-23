<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();

            // producto_id o variante_id (uno de los dos)
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();

            $table->string('nombre_producto');
            $table->foreignId('unidad_id')->constrained('unidades_medidas')->restrictOnDelete();
            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 12, 4)->default(0);
            $table->decimal('costo_total', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['compra_id', 'producto_id']);
            $table->index(['compra_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
