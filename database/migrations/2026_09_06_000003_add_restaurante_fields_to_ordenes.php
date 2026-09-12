<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            // Origen de la orden: web (ecommerce) | restaurante (mesa)
            $table->string('tipo_origen', 20)->default('web')->after('empresa_id');

            // Mesa vinculada (solo para tipo_origen = 'restaurante')
            $table->foreignId('mesa_id')
                ->nullable()
                ->after('tipo_origen')
                ->constrained('mesas')
                ->nullOnDelete();

            $table->index(['empresa_id', 'tipo_origen'], 'idx_ordenes_empresa_tipo');
            $table->index('mesa_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['mesa_id']);
            $table->dropIndex('idx_ordenes_empresa_tipo');
            $table->dropIndex(['mesa_id']);
            $table->dropColumn(['tipo_origen', 'mesa_id']);
        });
    }
};
