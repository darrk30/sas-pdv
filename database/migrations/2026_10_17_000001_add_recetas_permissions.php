<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            ['name' => 'recetas.ver',      'description' => 'Ver listado de recetas'],
            ['name' => 'recetas.crear',    'description' => 'Crear recetas de productos'],
            ['name' => 'recetas.editar',   'description' => 'Editar recetas existentes'],
            ['name' => 'recetas.eliminar', 'description' => 'Eliminar recetas'],
        ];

        foreach ($permisos as $permiso) {
            DB::table('permissions')->insertOrIgnore([
                'name'         => $permiso['name'],
                'guard_name'   => 'web',
                'module'       => 'recetas',
                'module_label' => 'Recetas de Productos',
                'description'  => $permiso['description'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('module', 'recetas')
            ->delete();
    }
};
