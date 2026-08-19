<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            // El unique global en codigo solo permite UNA caja con 'CAJA-001' en todo el sistema.
            // En multi-tenant cada empresa debe poder tener su propio 'CAJA-001'.
            // Se mantiene el unique compuesto (empresa_id, codigo) que sí es correcto.
            $table->dropUnique(['codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }
};
