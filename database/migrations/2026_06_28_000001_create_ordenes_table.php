<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();

            // ── Relaciones ──────────────────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            // ── Número de orden (secuencial por empresa) ────────────────
            $table->unsignedInteger('numero');
            $table->unique(['empresa_id', 'numero']);

            // ── Snapshot del cliente ────────────────────────────────────
            $table->string('cliente_nombre')->default('');
            $table->string('cliente_tipo_doc', 20)->default('');
            $table->string('cliente_num_doc', 20)->default('');
            $table->string('cliente_telefono', 30)->nullable();
            $table->string('cliente_direccion')->nullable();

            // ── Fechas ──────────────────────────────────────────────────
            $table->dateTime('fecha_orden');

            // ── Entrega ─────────────────────────────────────────────────
            $table->string('tipo_entrega', 20)->default('envio'); // envio | retiro
            $table->foreignId('metodo_envio_id')->nullable()->constrained('metodos_envio')->nullOnDelete();
            $table->string('direccion_agencia')->nullable();
            $table->decimal('costo_envio', 10, 2)->default(0); // snapshot del costo al crear

            // ── Importes ────────────────────────────────────────────────
            $table->decimal('descuento_total', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0); // subtotal + costo_envio

            // ── Estado ──────────────────────────────────────────────────
            $table->string('estado', 20)->default('borrador');

            // ── Pago ────────────────────────────────────────────────────
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();

            // ── Conversión a venta ──────────────────────────────────────
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();

            // ── Notas ───────────────────────────────────────────────────
            $table->text('notas')->nullable();
            $table->text('notas_internas')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
