<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // quien registra
            $table->foreignId('user_empleado_id')                                 // solo si categoria = remuneracion
                ->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('categoria', 30);      // alquiler|servicio|remuneracion|suministros|transporte|gasto_personal|otro
            $table->text('descripcion');
            $table->string('serie', 10)->default('G');
            $table->unsignedInteger('correlativo');
            $table->string('archivo_adjunto')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente|aprobado|anulado

            $table->timestamps();

            $table->unique(['empresa_id', 'serie', 'correlativo'], 'uq_gastos_correlativo');
            $table->index(['empresa_id', 'fecha'], 'idx_gastos_empresa_fecha');
            $table->index(['empresa_id', 'categoria'], 'idx_gastos_empresa_categoria');
            $table->index(['empresa_id', 'estado'], 'idx_gastos_empresa_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
