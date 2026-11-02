<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_padre_id')->nullable()->after('id');
            $table->foreign('empresa_padre_id')->references('id')->on('empresas')->nullOnDelete();
        });

        // max_sucursales eliminado — se usa maximo_locales del plan
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['empresa_padre_id']);
            $table->dropColumn('empresa_padre_id');
        });

        // nada que revertir en plans
    }
};
