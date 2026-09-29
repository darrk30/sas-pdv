<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('costo_envio', 10, 2)->nullable()->default(null)->after('total');
            // true = el envío se consideró en el total y aparece como ítem en los detalles
            // false/null = el envío es solo un concepto informativo, no está en el total
            $table->boolean('envio_facturado')->nullable()->default(null)->after('costo_envio');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['costo_envio', 'envio_facturado']);
        });
    }
};
