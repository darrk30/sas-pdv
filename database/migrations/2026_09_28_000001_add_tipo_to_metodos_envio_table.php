<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodos_envio', function (Blueprint $table) {
            if (! Schema::hasColumn('metodos_envio', 'tipo')) {
                $table->string('tipo', 20)->default('local')->after('con_direccion');
                // local     = entrega en dirección del cliente (misma ciudad)
                // provincial = envío a agencia de transporte
            }
        });
    }

    public function down(): void
    {
        Schema::table('metodos_envio', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
