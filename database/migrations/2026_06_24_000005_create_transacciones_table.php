<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sesion_caja_id')->constrained('sesion_cajas')->cascadeOnDelete();
            $table->morphs('transaccionable'); // transaccionable_type + transaccionable_id
            $table->string('tipo', 20);          // ingreso | egreso
            $table->string('concepto', 255);
            $table->decimal('monto', 12, 2);
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->constrained('metodos_pago')
                ->nullOnDelete();
            $table->string('estado', 20)->default('aprobado');
            $table->dateTime('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
