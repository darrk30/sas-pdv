<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('impresora_id')->nullable()->constrained('impresoras')->nullOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->string('estado', 20)->default('activo'); // activo | inactivo
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pisos');
    }
};
