<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Una receta por producto simple o por variante específica
            $table->unique(['empresa_id', 'producto_id', 'variante_id']);
        });

        Schema::create('receta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insumo_producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('insumo_variante_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->decimal('cantidad', 10, 4);
            $table->string('unidad', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receta_detalles');
        Schema::dropIfExists('recetas');
    }
};
