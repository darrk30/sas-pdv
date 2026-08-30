<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // api_token_impresion en empresas — identifica el canal WebSocket único por empresa
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('api_token_impresion')->nullable()->after('impresion_comprobante_directo');
        });

        // impresora_id en cajas — a qué impresora física envía esa caja
        Schema::table('cajas', function (Blueprint $table) {
            $table->foreignId('impresora_id')
                ->nullable()
                ->after('codigo')
                ->constrained('impresoras')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropForeign(['impresora_id']);
            $table->dropColumn('impresora_id');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('api_token_impresion');
        });
    }
};
