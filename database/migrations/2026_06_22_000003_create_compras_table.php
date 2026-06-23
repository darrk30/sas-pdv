<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            // Código interno del sistema (auto-generado)
            $table->string('codigo')->nullable();

            // Datos del comprobante externo
            $table->string('tipo_comprobante')->default('sin_comprobante');
            $table->string('serie', 10)->nullable();
            $table->string('correlativo', 20)->nullable();

            $table->date('fecha_compra');
            $table->string('estado_despacho')->default('pendiente');
            $table->string('estado_pago')->default('pendiente');
            $table->text('observaciones')->nullable();

            // Totales
            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('costo_envio', 12, 4)->default(0);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('igv', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);

            // Archivo adjunto (factura escaneada, etc.)
            $table->string('archivo_compra')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'compras_empresa_codigo_unique');
            $table->index(['empresa_id', 'estado_despacho']);
            $table->index(['empresa_id', 'estado_pago']);
            $table->index(['empresa_id', 'fecha_compra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
