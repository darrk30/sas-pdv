<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_documento', 10);
            $table->string('numero_documento', 20);
            $table->string('nombre', 255);
            $table->string('apellidos', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('correo', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
