<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receta_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('unidad_medida_id')->nullable()->after('cantidad');
            $table->foreign('unidad_medida_id')->references('id')->on('unidades_medidas')->nullOnDelete();
            $table->dropColumn('unidad');
        });
    }

    public function down(): void
    {
        Schema::table('receta_detalles', function (Blueprint $table) {
            $table->dropForeign(['unidad_medida_id']);
            $table->dropColumn('unidad_medida_id');
            $table->string('unidad', 50)->nullable();
        });
    }
};
