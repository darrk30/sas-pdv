<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // pdv_delivery tiene 12 chars; el límite anterior era 10
            $table->string('tipo', 20)->default('pdv')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo', 10)->default('pdv')->change();
        });
    }
};
