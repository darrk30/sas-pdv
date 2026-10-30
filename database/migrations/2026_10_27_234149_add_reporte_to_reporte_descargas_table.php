<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_descargas', function (Blueprint $table) {
            $table->string('reporte', 30)->default('ventas')->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_descargas', function (Blueprint $table) {
            $table->dropColumn('reporte');
        });
    }
};
