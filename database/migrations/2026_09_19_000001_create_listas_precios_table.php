<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listas_precios');
    }
};
