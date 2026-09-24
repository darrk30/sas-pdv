<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('modulos_activos')->nullable()->after('dias_prueba_gratuita')
                ->comment('Plantilla de módulos para inicializar empresas nuevas con este plan');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('modulos_activos');
        });
    }
};
