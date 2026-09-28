<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes', 'orden_departamento')) {
                $table->string('orden_departamento', 100)->nullable()->after('direccion_agencia');
            }
            if (! Schema::hasColumn('ordenes', 'orden_provincia')) {
                $table->string('orden_provincia', 100)->nullable()->after('orden_departamento');
            }
            if (! Schema::hasColumn('ordenes', 'orden_distrito')) {
                $table->string('orden_distrito', 100)->nullable()->after('orden_provincia');
            }
            if (! Schema::hasColumn('ordenes', 'tracking_code')) {
                $table->string('tracking_code')->nullable()->after('orden_distrito');
            }
            if (! Schema::hasColumn('ordenes', 'codigo_retiro')) {
                $table->string('codigo_retiro')->nullable()->after('tracking_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropColumn(['orden_departamento', 'orden_provincia', 'orden_distrito', 'tracking_code', 'codigo_retiro']);
        });
    }
};
