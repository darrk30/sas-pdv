<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renombrar 'local' → 'delivery' en datos existentes
        DB::table('metodos_envio')->where('tipo', 'local')->update(['tipo' => 'delivery']);

        Schema::table('metodos_envio', function (Blueprint $table) {
            // Cambiar default al nuevo nombre
            $table->string('tipo', 20)->default('delivery')->change();

            // Dirección de recojo (solo para tipo 'retiro')
            if (! Schema::hasColumn('metodos_envio', 'direccion_retiro')) {
                $table->string('direccion_retiro')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        DB::table('metodos_envio')->where('tipo', 'delivery')->update(['tipo' => 'local']);

        Schema::table('metodos_envio', function (Blueprint $table) {
            $table->string('tipo', 20)->default('local')->change();
            $table->dropColumn('direccion_retiro');
        });
    }
};
