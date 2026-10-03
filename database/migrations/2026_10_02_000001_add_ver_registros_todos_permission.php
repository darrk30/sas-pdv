<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'ver.registros.todos', 'guard_name' => 'web'],
            [
                'name'         => 'ver.registros.todos',
                'guard_name'   => 'web',
                'scope'        => 'pdv',
                'module'       => 'general',
                'module_label' => 'General',
                'description'  => 'Ver registros de todos los usuarios',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Asignar a todos los roles Administrador de empresa existentes
        $permiso = \App\Models\Permission::where('name', 'ver.registros.todos')->first();
        if ($permiso) {
            \App\Models\Role::where('name', 'Administrador')
                ->whereNotNull('empresa_id')
                ->each(fn ($role) => $role->givePermissionTo($permiso));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'ver.registros.todos')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
