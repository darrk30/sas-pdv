<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('codigo', 50);
            $table->string('descripcion', 255)->nullable();

            // Tipo de descuento
            $table->enum('tipo_descuento', ['porcentaje', 'monto_fijo'])->default('porcentaje');
            $table->decimal('valor', 10, 2);

            // Restricciones de uso
            $table->decimal('monto_minimo', 10, 2)->nullable();
            $table->unsignedInteger('cantidad_minima')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->unsignedInteger('usos_por_cliente')->default(1);

            // Vigencia
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
