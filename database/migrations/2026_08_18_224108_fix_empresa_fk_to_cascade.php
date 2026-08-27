<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tablas cuyos datos pertenecen exclusivamente a una empresa.
    // Al borrar la empresa, sus datos deben borrarse en cascada.
    private array $tablas = [
        ['tabla' => 'ajustes',        'fk' => 'ajustes_empresa_id_foreign'],
        ['tabla' => 'compras',        'fk' => 'compras_empresa_id_foreign'],
        ['tabla' => 'compra_detalles','fk' => 'compra_detalles_empresa_id_foreign'],
        ['tabla' => 'compra_pagos',   'fk' => 'compra_pagos_empresa_id_foreign'],
        ['tabla' => 'metodos_pago',   'fk' => 'metodos_pago_empresa_id_foreign'],
        ['tabla' => 'proveedores',    'fk' => 'proveedores_empresa_id_foreign'],
        ['tabla' => 'variantes',      'fk' => 'variantes_empresa_id_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->tablas as ['tabla' => $tabla, 'fk' => $fk]) {
            Schema::table($tabla, function (Blueprint $table) use ($fk) {
                try { $table->dropForeign($fk); } catch (\Throwable) {}
                try {
                    $table->foreign('empresa_id')
                        ->references('id')->on('empresas')
                        ->onDelete('cascade');
                } catch (\Throwable) {}
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as ['tabla' => $tabla, 'fk' => $fk]) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['empresa_id']);
                $table->foreign('empresa_id')
                    ->references('id')->on('empresas')
                    ->onDelete('restrict');
            });
        }
    }
};
