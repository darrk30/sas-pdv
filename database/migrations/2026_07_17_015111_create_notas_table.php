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
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('users');
            $table->string('tipo', 10);  // TipoNota: credito|debito

            // Comprobante
            $table->foreignId('serie_id')->constrained('series');
            $table->string('correlativo', 8);
            $table->dateTime('fecha_emision');

            // Motivo (catálogo SUNAT)
            $table->string('motivo_codigo', 4);           // 01=anulación, 07=descuento...
            $table->string('motivo_descripcion');

            // Importes
            $table->decimal('total', 12, 2)->default(0);
            $table->string('total_letras')->nullable();
            $table->text('qr_data')->nullable();

            // Respuesta SUNAT (mismo patrón que ventas individuales)
            $table->string('hash')->nullable();
            $table->string('path_xml')->nullable();
            $table->string('path_pdf')->nullable();
            $table->string('path_cdr_zip')->nullable();
            $table->boolean('sunat_success')->nullable();
            $table->string('sunat_codigo')->nullable();
            $table->string('sunat_descripcion')->nullable();
            $table->text('sunat_mensaje')->nullable();
            $table->text('sunat_notas')->nullable();       // cdrResponse.notes[] JSON

            $table->string('estado_sunat', 20)->default('por_enviar');
            // por_enviar | aceptado | observado | rechazado

            $table->string('estado', 20)->default('emitida');  // emitida | anulada
            $table->foreignId('resumen_sunat_id')->nullable()->constrained('resumenes_sunat')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'serie_id', 'correlativo']);
            $table->index(['empresa_id', 'venta_id']);
            $table->index(['empresa_id', 'estado_sunat']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
