<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas_programadas', function (Blueprint $table) {
            $table->text('ultimo_resultado')->nullable()->after('ultima_ejecucion');
        });
    }

    public function down(): void
    {
        Schema::table('tareas_programadas', function (Blueprint $table) {
            $table->dropColumn('ultimo_resultado');
        });
    }
};
