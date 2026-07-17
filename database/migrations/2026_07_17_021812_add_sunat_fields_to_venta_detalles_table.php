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
        Schema::table('venta_detalles', function (Blueprint $table) {
            // Código de afectación IGV: 10=gravado, 20=exonerado, 30=inafecto
            $table->string('tip_afe_igv', 2)->default('10')->after('igv');
            // Unidad de medida SUNAT: NIU=unidad, ZZ=servicio, KGM=kilogramo
            $table->string('unidad', 10)->default('NIU')->after('tip_afe_igv');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn(['tip_afe_igv', 'unidad']);
        });
    }
};
