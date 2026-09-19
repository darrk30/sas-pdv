<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            ['name' => 'listas_precios.ver',      'description' => 'Ver listas de precios',             'module' => 'listas_precios', 'module_label' => 'Listas de Precios'],
            ['name' => 'listas_precios.crear',    'description' => 'Crear listas de precios',           'module' => 'listas_precios', 'module_label' => 'Listas de Precios'],
            ['name' => 'listas_precios.editar',   'description' => 'Editar listas de precios',          'module' => 'listas_precios', 'module_label' => 'Listas de Precios'],
            ['name' => 'listas_precios.eliminar', 'description' => 'Eliminar listas de precios',        'module' => 'listas_precios', 'module_label' => 'Listas de Precios'],
        ];

        foreach ($permisos as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p['name'], 'guard_name' => 'web'],
                array_merge($p, ['guard_name' => 'web', 'scope' => 'pdv', 'updated_at' => now(), 'created_at' => now()])
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Asignar todos los permisos nuevos al rol Super Administrador
        $superAdmin = \App\Models\Role::where('name', 'Super Administrador')->whereNull('empresa_id')->first();
        if ($superAdmin) {
            $nuevosPermisos = \App\Models\Permission::whereIn('name', array_column($permisos, 'name'))->get();
            $superAdmin->givePermissionTo($nuevosPermisos);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'listas_precios.ver', 'listas_precios.crear', 'listas_precios.editar', 'listas_precios.eliminar',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
