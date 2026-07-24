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
        Schema::create('resumenes_sunat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('tipo', 20);  // TipoResumen: diario|bajas|notas_diario|notas_bajas
            $table->date('fecha_referencia');
            $table->string('correlativo', 30);   // RC-20260410-001

            // Respuesta del POST /api/summaries/send
            $table->string('hash')->nullable();
            $table->string('ticket_sunat')->nullable();
            $table->text('sunat_error')->nullable();
            $table->string('path_xml')->nullable();

            // Respuesta del POST /api/summaries/status (consulta del ticket)
            $table->boolean('sunat_success')->nullable();
            $table->string('sunat_codigo')->nullable();
            $table->string('sunat_descripcion')->nullable();
            $table->text('sunat_notas')->nullable();    // JSON de cdrResponse.notes[]
            $table->string('path_cdr_zip')->nullable();

            $table->string('estado_sunat', 20)->default('pendiente');
            // pendiente | enviado | aceptado | observado | rechazado | error

            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo', 'fecha_referencia', 'correlativo'], 'uq_resumenes_empresa_tipo_fecha_corr');
            $table->index(['empresa_id', 'estado_sunat']);
            $table->index(['empresa_id', 'fecha_referencia']);
        });

        // FK desde ventas ahora que resumenes_sunat ya existe
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('resumen_sunat_id')
                  ->references('id')->on('resumenes_sunat')
                  ->nullOnDelete();
            $table->index('resumen_sunat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['resumen_sunat_id']);
            $table->dropIndex(['resumen_sunat_id']);
        });
        Schema::dropIfExists('resumenes_sunat');
    }
};
