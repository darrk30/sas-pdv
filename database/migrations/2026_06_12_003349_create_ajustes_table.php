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
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('codigo')->nullable();
            $table->enum('tipo', ['entrada', 'salida']);
            $table->string('motivo');
            $table->decimal('valor_total', 12, 4)->default(0);
            $table->enum('estado', ['borrador', 'confirmado', 'anulado'])->default('borrador');
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo'], 'ajustes_empresa_codigo_unique');
            $table->index(['empresa_id', 'tipo']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
