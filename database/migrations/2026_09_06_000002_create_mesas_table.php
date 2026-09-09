<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('piso_id')->constrained('pisos')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('capacidad')->default(4);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->string('estado', 20)->default('activo');       // activo | inactivo  (config)
            $table->string('estado_ocupacion', 20)->default('libre'); // libre | ocupada | pagando
            $table->timestamps();

            $table->index(['empresa_id', 'piso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
