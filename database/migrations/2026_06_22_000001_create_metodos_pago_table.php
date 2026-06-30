<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('imagen')->nullable();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('visible_en')->default('ambos');
            $table->boolean('requiere_referencia')->default(false);
            $table->string('condicion_pago')->default('contado');
            $table->string('estado')->default('activo');
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
