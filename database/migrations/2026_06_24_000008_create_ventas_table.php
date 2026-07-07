<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            // ── Relaciones principales ──────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sesion_caja_id')->nullable()->constrained('sesion_cajas')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            // ── Canal de origen ─────────────────────────────────────────
            $table->string('tipo', 10)->default('pdv'); // pdv | web

            // ── Snapshot del cliente (persiste si se elimina el registro) ─
            $table->string('cliente_nombre');
            $table->string('cliente_tipo_doc', 20);
            $table->string('cliente_num_doc', 20);

            // ── Comprobante ─────────────────────────────────────────────
            $table->foreignId('serie_id')->constrained('series')->cascadeOnDelete();
            $table->string('correlativo', 8);
            $table->dateTime('fecha_emision');
            $table->unique(['empresa_id', 'serie_id', 'correlativo']);

            // ── Pago ────────────────────────────────────────────────────
            $table->string('tipo_pago', 20)->default('contado');
            $table->date('fecha_vencimiento')->nullable();

            // ── Importes ────────────────────────────────────────────────
            $table->decimal('op_gravadas', 12, 2)->default(0);
            $table->decimal('op_exoneradas', 12, 2)->default(0);
            $table->decimal('op_inafectas', 12, 2)->default(0);
            $table->decimal('descuento_total', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->decimal('monto_pagado', 12, 2)->default(0);
            $table->decimal('saldo_pendiente', 12, 2)->default(0);
            $table->string('estado_pago', 20)->default('pagado');

            // ── Comprobante electrónico (SUNAT — para implementar luego) ─
            $table->string('total_letras')->nullable();
            $table->text('qr_data')->nullable();
            $table->string('hash')->nullable();
            $table->string('path_xml')->nullable();
            $table->string('path_pdf')->nullable();
            $table->string('path_cdr_zip')->nullable();
            $table->boolean('sunat_success')->nullable();
            $table->string('sunat_codigo')->nullable();
            $table->string('sunat_descripcion')->nullable();
            $table->text('sunat_mensaje')->nullable();

            // ── Estado ──────────────────────────────────────────────────
            $table->string('estado', 20)->default('borrador');
            $table->string('estado_despacho', 30)->nullable()->default(null);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'estado'], 'idx_ventas_empresa_estado');
            $table->index(['empresa_id', 'fecha_emision'], 'idx_ventas_empresa_fecha');
            $table->index(['empresa_id', 'tipo'], 'idx_ventas_empresa_tipo');
            $table->index(['empresa_id', 'sesion_caja_id'], 'idx_ventas_empresa_sesion');
            $table->index(['empresa_id', 'cliente_id'], 'idx_ventas_empresa_cliente');
            $table->index(['empresa_id', 'vendedor_id'], 'idx_ventas_empresa_vendedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
