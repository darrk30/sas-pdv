<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_fijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->decimal('monto', 12, 2);
            $table->string('frecuencia', 20);  // diario|semanal|quincenal|mensual
            $table->text('notas')->nullable();
            $table->string('estado', 20)->default('activo'); // activo|inactivo
            $table->timestamps();

            $table->index(['empresa_id', 'estado'], 'idx_gastos_fijos_empresa_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_fijos');
    }
};
