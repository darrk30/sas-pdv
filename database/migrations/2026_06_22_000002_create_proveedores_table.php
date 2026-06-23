<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre');
            $table->string('tipo_documento')->default('ruc');
            $table->string('numero_documento', 11);
            $table->string('correo')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->string('departamento')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->unique(['empresa_id', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
