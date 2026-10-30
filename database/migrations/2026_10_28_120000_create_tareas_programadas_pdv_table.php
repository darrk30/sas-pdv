<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_programadas_pdv', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('tipo', 50)->default('envio_boletas_rc');
            $table->string('hora', 5); // HH:MM formato 24h
            $table->json('dias_semana')->nullable(); // null = todos, [0-6] = días específicos (0=Dom, 1=Lun, …)
            $table->tinyInteger('fecha_offset')->default(-7); // -1 = solo ayer, -7 = última semana (recomendado)
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_ejecucion')->nullable();
            $table->text('ultimo_resultado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_programadas_pdv');
    }
};
