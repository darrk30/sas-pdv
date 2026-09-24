<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesEmpresaSeeder extends Seeder
{
    public function runForEmpresa(Empresa $empresa): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($empresa->id);
        $registrar->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name'       => 'Administrador',
            'guard_name' => 'web',
            'empresa_id' => $empresa->id,
        ]);

        $perms = Permission::where('guard_name', 'web')->where('scope', 'pdv')->get();
        $role->syncPermissions($perms);

        $registrar->forgetCachedPermissions();
    }

    // Permite ejecutarlo también desde DatabaseSeeder para la empresa inicial
    public function run(): void
    {
        Empresa::all()->each(fn(Empresa $e) => $this->runForEmpresa($e));
    }
}
