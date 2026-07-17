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
        Schema::table('ventas', function (Blueprint $table) {
            // Estado del ciclo de vida con SUNAT
            $table->string('estado_sunat', 20)->default('no_aplica')->after('sunat_mensaje');

            // Observaciones del CDR (notas aunque sea aceptado)
            $table->text('sunat_notas')->nullable()->after('estado_sunat');

            // FK al resumen diario — constraint se agrega en create_resumenes_sunat_table
            $table->unsignedBigInteger('resumen_sunat_id')->nullable()->after('sunat_notas');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['estado_sunat', 'sunat_notas', 'resumen_sunat_id']);
        });
    }
};
