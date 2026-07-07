<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesion_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->text('notas_cierre')->nullable();
            $table->decimal('monto_apertura', 12, 2)->default(0);
            $table->decimal('total_sistema', 12, 2)->nullable();
            $table->decimal('total_cajero', 12, 2)->nullable();
            $table->decimal('diferencia_total', 12, 2)->nullable();
            $table->decimal('total_creditos', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'estado'], 'idx_sesion_cajas_empresa_estado');
            $table->index(['empresa_id', 'caja_id', 'estado'], 'idx_sesion_cajas_empresa_caja_estado');
            $table->index(['empresa_id', 'fecha_apertura'], 'idx_sesion_cajas_empresa_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_cajas');
    }
};
