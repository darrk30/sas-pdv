<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('tiene_variantes')->default(false)->after('maximo_locales');
            $table->boolean('tiene_catalogo_web')->default(false)->after('tiene_variantes');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['tiene_variantes', 'tiene_catalogo_web']);
        });
    }
};
