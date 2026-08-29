<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('suscripcion_proxima_a_vencer')->default(false)->after('estado');
        });

        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente')->after('monto')->nullable(false);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('suscripcion_proxima_a_vencer');
        });
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
