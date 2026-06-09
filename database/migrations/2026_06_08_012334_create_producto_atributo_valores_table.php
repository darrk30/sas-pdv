<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('producto_atributo_valors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_atributo_id')->constrained('producto_atributos')->cascadeOnDelete();
            $table->foreignId('valor_id')->constrained('valors')->cascadeOnDelete();
            $table->decimal('precio_adicional', 15, 2)->default(0);
            $table->string('estado')->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_atributo_valors');
    }
};
