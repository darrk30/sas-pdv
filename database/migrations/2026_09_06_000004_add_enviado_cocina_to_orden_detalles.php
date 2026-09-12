<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            // Marca si este ítem ya fue enviado a cocina (evita reimprimir al agregar más ítems)
            $table->boolean('enviado_cocina')->default(false)->after('total');
            // Notas del ítem (ej: "sin cebolla", "término medio")
            $table->string('notas_item')->nullable()->after('enviado_cocina');
        });
    }

    public function down(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->dropColumn(['enviado_cocina', 'notas_item']);
        });
    }
};
