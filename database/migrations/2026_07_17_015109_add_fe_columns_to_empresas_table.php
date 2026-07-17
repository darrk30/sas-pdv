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
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('fe_envio_directo_boleta')->default(false)->after('modulos_activos');
            $table->boolean('fe_envio_directo_factura')->default(false)->after('fe_envio_directo_boleta');
            $table->boolean('impresion_comprobante_directo')->default(false)->after('fe_envio_directo_factura');
            $table->decimal('igv_porcentaje', 5, 2)->default(18.00)->after('impresion_comprobante_directo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'fe_envio_directo_boleta',
                'fe_envio_directo_factura',
                'impresion_comprobante_directo',
                'igv_porcentaje',
            ]);
        });
    }
};
