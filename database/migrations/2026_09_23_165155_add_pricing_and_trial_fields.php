<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('precio_anual', 10, 2)->nullable()->after('precio')
                ->comment('Precio anual total; null = no ofrece plan anual');
            $table->unsignedTinyInteger('dias_prueba_gratuita')->default(0)->after('precio_anual')
                ->comment('Días de prueba gratuita al registrarse; 0 = sin prueba');
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->boolean('es_prueba_gratuita')->default(false)->after('estado');
            $table->string('ciclo', 20)->default('mensual')->after('es_prueba_gratuita')
                ->comment('mensual | anual | prueba');
        });

        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->string('concepto')->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['precio_anual', 'dias_prueba_gratuita']);
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn(['es_prueba_gratuita', 'ciclo']);
        });

        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->dropColumn('concepto');
        });
    }
};
