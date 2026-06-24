<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // quien registra
            $table->dateTime('fecha_hora');
            $table->string('tipo', 20);                   // ingreso | egreso
            $table->string('categoria', 30)->nullable();  // solo egreso: remuneracion|compra|servicio|otro_gasto
            $table->string('entregado_a', 255)->nullable(); // nombre libre (ingreso o egreso no-remuneracion)
            $table->foreignId('user_receptor_id')         // solo egreso+remuneracion
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('monto', 12, 2);
            $table->text('motivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_egresos');
    }
};
