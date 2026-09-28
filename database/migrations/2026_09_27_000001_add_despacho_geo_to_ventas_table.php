<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'despacho_departamento')) {
                $table->string('despacho_departamento', 100)->nullable()->after('despacho_direccion');
            }
            if (! Schema::hasColumn('ventas', 'despacho_provincia')) {
                $table->string('despacho_provincia', 100)->nullable()->after('despacho_departamento');
            }
            if (! Schema::hasColumn('ventas', 'despacho_distrito')) {
                $table->string('despacho_distrito', 100)->nullable()->after('despacho_provincia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['despacho_departamento', 'despacho_provincia', 'despacho_distrito']);
        });
    }
};
