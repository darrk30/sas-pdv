<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeria_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('imagen_path');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeria_productos');
    }
};
