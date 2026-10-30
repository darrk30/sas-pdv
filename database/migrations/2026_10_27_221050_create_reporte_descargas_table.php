<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_descargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10);                        // excel | pdf
            $table->string('estado', 20)->default('pendiente'); // pendiente | procesando | listo | error
            $table->json('filtros');                            // snapshot de todos los filtros aplicados
            $table->string('path')->nullable();                 // ruta en storage
            $table->string('nombre_archivo')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_descargas');
    }
};
