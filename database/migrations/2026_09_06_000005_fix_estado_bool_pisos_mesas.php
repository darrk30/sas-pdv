<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pisos.estado: 'activo' string → boolean 1
        Schema::table('pisos', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->change();
        });

        // mesas.estado: 'activo' string → boolean 1
        Schema::table('mesas', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pisos', function (Blueprint $table) {
            $table->string('estado', 20)->default('activo')->change();
        });
        Schema::table('mesas', function (Blueprint $table) {
            $table->string('estado', 20)->default('activo')->change();
        });
    }
};
