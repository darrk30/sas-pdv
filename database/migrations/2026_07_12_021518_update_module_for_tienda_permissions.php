<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permisos = [
        'ordenes.ver',
        'ordenes.gestionar',
        'ordenes.cancelar',
        'ordenes.despacho',
        'promociones.ver',
        'promociones.gestionar',
    ];

    public function up(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permisos)
            ->update([
                'module'       => 'tienda',
                'module_label' => 'Pedidos Web / Tienda',
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permisos)
            ->update([
                'module'       => 'productos',
                'module_label' => 'Productos / Inventario',
            ]);
    }
};
