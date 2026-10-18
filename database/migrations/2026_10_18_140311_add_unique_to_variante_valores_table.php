<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variante_valores', function (Blueprint $table) {
            $table->unique(['variante_id', 'producto_atributo_valors_id'], 'uq_variante_pav');
        });
    }

    public function down(): void
    {
        Schema::table('variante_valores', function (Blueprint $table) {
            $table->dropUnique('uq_variante_pav');
        });
    }
};
