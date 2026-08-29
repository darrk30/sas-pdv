<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente')->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
