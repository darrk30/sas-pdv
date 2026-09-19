<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_precio_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('precio', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['lista_precio_id', 'producto_id'], 'uniq_lista_producto');
            $table->index(['producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_precio_producto');
    }
};
