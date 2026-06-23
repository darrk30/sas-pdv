<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->decimal('monto', 12, 4);
            $table->string('referencia')->nullable();
            $table->timestamps();

            $table->index(['compra_id']);
            $table->index(['empresa_id', 'metodo_pago_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_pagos');
    }
};
