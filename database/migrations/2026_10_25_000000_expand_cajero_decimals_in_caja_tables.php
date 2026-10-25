<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesion_caja_pagos', function (Blueprint $table) {
            $table->decimal('importe_cajero', 12, 4)->default(0)->change();
        });

        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->decimal('total_cajero', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sesion_caja_pagos', function (Blueprint $table) {
            $table->decimal('importe_cajero', 12, 2)->default(0)->change();
        });

        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->decimal('total_cajero', 12, 2)->nullable()->change();
        });
    }
};
