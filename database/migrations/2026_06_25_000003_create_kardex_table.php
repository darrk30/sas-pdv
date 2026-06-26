<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();

            // ── Empresa y usuario ──────────────────────────────────────────
            $table->foreignId('empresa_id')
                ->nullable()
                ->constrained('empresas')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que generó el movimiento');

            // ── Origen polimórfico (Compra, Ajuste, Venta, etc.) ──────────
            // Permanece aunque se elimine el registro origen (auditoría)
            $table->nullableMorphs('movible');

            // ── Producto / Variante ────────────────────────────────────────
            // Nullable: si se elimina el producto el kardex no se pierde
            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();

            $table->foreignId('variante_id')
                ->nullable()
                ->constrained('variantes')
                ->nullOnDelete();

            // Snapshot de nombre en el momento del movimiento
            $table->string('producto_nombre');
            $table->string('variante_nombre')->nullable();

            // ── Tipo de movimiento ─────────────────────────────────────────
            $table->enum('tipo', ['entrada', 'salida'])
                ->comment('entrada = suma stock, salida = resta stock');

            // ── Referencia/Concepto ────────────────────────────────────────
            $table->string('concepto')
                ->comment('Ej: COMPRA-00001, VEN-B001-00000001, AJU-00001');

            $table->text('notas')->nullable();

            // ── Cantidad y Unidad ──────────────────────────────────────────
            $table->decimal('cantidad', 12, 4)
                ->comment('Cantidad en la unidad del movimiento');

            $table->string('unidad')->default('unidad')
                ->comment('Unidad usada en el movimiento: unidad, caja, docena, kg...');

            $table->decimal('factor_conversion', 10, 4)->default(1)
                ->comment('Cuántas unidades base equivale 1 unidad del movimiento');

            $table->decimal('cantidad_base', 12, 4)
                ->comment('cantidad × factor_conversion → en unidad base del producto');

            // ── Costos (Compra y Ajuste principalmente) ────────────────────
            $table->decimal('costo_unitario', 12, 4)->nullable()
                ->comment('Costo por unidad base');

            $table->decimal('costo_total', 12, 4)->nullable()
                ->comment('costo_unitario × cantidad_base');

            // ── Precio de venta (Venta principalmente) ────────────────────
            $table->decimal('precio_unitario', 12, 4)->nullable()
                ->comment('Precio de venta por unidad base');

            $table->decimal('precio_total', 12, 4)->nullable()
                ->comment('precio_unitario × cantidad_base');

            // ── Saldo del inventario ───────────────────────────────────────
            $table->decimal('stock_antes', 12, 4)->nullable()
                ->comment('Stock real antes de aplicar el movimiento');

            $table->decimal('stock_despues', 12, 4)->nullable()
                ->comment('Stock real después de aplicar el movimiento');

            // ── Fecha efectiva ─────────────────────────────────────────────
            $table->timestamp('fecha')
                ->comment('Fecha y hora en que ocurrió el movimiento');

            $table->timestamps();

            // ── Índices ────────────────────────────────────────────────────
            // Consulta kardex por producto/variante en el tiempo (reporte)
            $table->index(['empresa_id', 'producto_id', 'fecha']);
            $table->index(['empresa_id', 'variante_id', 'fecha']);
            // Consulta todos los movimientos de una empresa por fecha
            $table->index(['empresa_id', 'fecha']);
            // nullableMorphs('movible') ya crea el índice compuesto movible_type+movible_id
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
