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
        Schema::create('unidades_medidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('dimensions')->cascadeOnDelete();

            // 🌟 Agregada la "s" al final de unidades_medidas
$table->foreignId('unidad_base_id')->nullable()->constrained('unidades_medidas')->nullOnDelete();
            $table->string('nombre');
            $table->string('simbolo');
            $table->decimal('factor_conversion', 15, 6)->default(1.000000);
            $table->boolean('es_base')->default(false);
            $table->boolean('estado')->default(true);

            $table->timestamps();

            $table->unique(['empresa_id', 'simbolo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_medidas');
    }
};
