<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->boolean('activo_en_pdv')->default(true)->after('estado');
            $table->boolean('activo_en_catalogo')->default(true)->after('activo_en_pdv');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn(['activo_en_pdv', 'activo_en_catalogo']);
        });
    }
};
