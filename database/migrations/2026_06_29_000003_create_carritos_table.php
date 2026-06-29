<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'user_id']);
        });

        Schema::create('carrito_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrito_id')->constrained('carritos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->timestamps();

            $table->index(['carrito_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrito_items');
        Schema::dropIfExists('carritos');
    }
};
