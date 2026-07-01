<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesEmpresaSeeder extends Seeder
{
    // Permisos por rol (nombres de SuperAdminSeeder::$permisosPdv)
    private static array $asignaciones = [

        'Cajero' => [
            'caja.punto_de_venta',
            'caja.sesiones',
            'caja.ventas_turno',
            'caja.ingresos_egresos',
            'caja.cierres',
            'caja.clientes',
            'caja.reporte_ventas',
        ],

        'Vendedor' => [
            'caja.punto_de_venta',
            'caja.ventas_turno',
            'caja.clientes',
            'productos.ver',
            'promociones.ver',
        ],

        'Almacenero' => [
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.activar',
            'promociones.ver',
            'promociones.gestionar',
            'inventario.ver',
            'inventario.kardex',
            'ajustes.ver',
            'ajustes.crear',
            'ajustes.confirmar',
            'ajustes.anular',
            'ajustes.reporte',
            'ordenes.ver',
            'ordenes.gestionar',
            'ordenes.cancelar',
            'ordenes.despacho',
            'compras.ver',
            'proveedores.ver',
        ],

        // Administrador recibe todos los permisos PDV (null = todos)
        'Administrador' => null,
    ];

    public function runForEmpresa(Empresa $empresa): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($empresa->id);
        $registrar->forgetCachedPermissions();

        foreach (self::$asignaciones as $rolNombre => $permisoNames) {
            $role = Role::firstOrCreate([
                'name'       => $rolNombre,
                'guard_name' => 'web',
                'empresa_id' => $empresa->id,
            ]);

            $query = Permission::where('guard_name', 'web')->where('scope', 'pdv');

            $perms = $permisoNames === null
                ? $query->get()
                : $query->whereIn('name', $permisoNames)->get();

            $role->syncPermissions($perms);
        }

        $registrar->forgetCachedPermissions();
    }

    // Permite ejecutarlo también desde DatabaseSeeder para la empresa inicial
    public function run(): void
    {
        Empresa::all()->each(fn(Empresa $e) => $this->runForEmpresa($e));
    }
}
