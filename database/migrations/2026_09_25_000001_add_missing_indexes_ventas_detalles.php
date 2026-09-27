<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ventas: 3 índices usados en CuentasPorCobrar, DespachoPage y badges ──
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['empresa_id', 'estado_pago'],       'idx_ventas_empresa_estado_pago');
            $table->index(['empresa_id', 'fecha_vencimiento'], 'idx_ventas_empresa_fecha_venc');
            $table->index(['empresa_id', 'estado_despacho'],   'idx_ventas_empresa_estado_despacho');
        });

        // ── venta_detalles: 2 índices para reportes por producto/variante ─────────
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->index('producto_id', 'idx_venta_detalles_producto');
            $table->index('variante_id', 'idx_venta_detalles_variante');
        });

        // ── orden_detalles: 2 índices para reportes por producto/variante ─────────
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->index('producto_id', 'idx_orden_detalles_producto');
            $table->index('variante_id', 'idx_orden_detalles_variante');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_empresa_estado_pago');
            $table->dropIndex('idx_ventas_empresa_fecha_venc');
            $table->dropIndex('idx_ventas_empresa_estado_despacho');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_venta_detalles_producto');
            $table->dropIndex('idx_venta_detalles_variante');
        });

        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_orden_detalles_producto');
            $table->dropIndex('idx_orden_detalles_variante');
        });
    }
};
