<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->decimal('monto', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->change();
        });
    }
};
