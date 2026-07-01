<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    // ── Permisos del panel Admin (scope global, sin empresa_id) ───────────────

    public static array $permisosAdmin = [
        ['module' => 'admin_empresas',   'module_label' => 'Empresas',          'name' => 'admin.empresas',      'description' => 'Gestionar empresas del SaaS'],
        ['module' => 'admin_usuarios',   'module_label' => 'Usuarios del sistema','name' => 'admin.usuarios',     'description' => 'Gestionar usuarios del sistema'],
        ['module' => 'admin_planes',     'module_label' => 'Planes',             'name' => 'admin.planes',        'description' => 'Gestionar planes de suscripción'],
        ['module' => 'admin_metodos',    'module_label' => 'Métodos de Pago',    'name' => 'admin.metodos_pago',  'description' => 'Gestionar métodos de pago globales'],
        ['module' => 'admin_config',     'module_label' => 'Configuración',      'name' => 'admin.configuracion', 'description' => 'Acceder a configuración global del sistema'],
    ];

    // ── Permisos del panel PDV (scope por empresa) ────────────────────────────

    public static array $permisosPdv = [
        // Caja / Ventas
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.punto_de_venta',    'description' => 'Acceder al Punto de Venta y cobrar'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.sesiones',           'description' => 'Abrir y cerrar sesión de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.ventas_turno',       'description' => 'Ver ventas del turno actual'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.ingresos_egresos',   'description' => 'Registrar ingresos y egresos de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.cierres',            'description' => 'Ver historial de cierres de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.clientes',           'description' => 'Crear y editar clientes'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_ventas',     'description' => 'Ver reporte de ventas'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_ganancias',  'description' => 'Ver reporte de ganancias (sensible)'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_vendedores', 'description' => 'Ver reporte por vendedor'],

        // Productos / Inventario
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.ver',         'description' => 'Ver listado de productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.crear',       'description' => 'Crear nuevos productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.editar',      'description' => 'Editar productos existentes'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.activar',     'description' => 'Activar / desactivar / archivar productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'promociones.ver',       'description' => 'Ver promociones'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'promociones.gestionar', 'description' => 'Crear y editar promociones'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'inventario.ver',        'description' => 'Ver gestión de inventario'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'inventario.kardex',     'description' => 'Ver kardex de movimientos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.ver',           'description' => 'Ver ajustes de stock'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.crear',         'description' => 'Crear ajustes en borrador'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.confirmar',     'description' => 'Confirmar ajustes (aplica stock real)'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.anular',        'description' => 'Anular ajustes confirmados'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.reporte',       'description' => 'Ver reporte de ajustes'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ordenes.ver',           'description' => 'Ver órdenes de clientes web'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ordenes.gestionar',     'description' => 'Editar y procesar órdenes'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ordenes.cancelar',      'description' => 'Cancelar órdenes (restaura stock)'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ordenes.despacho',      'description' => 'Acceder a pantalla de despachos'],

        // Compras / Proveedores
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.ver',           'description' => 'Ver listado de compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.crear',         'description' => 'Registrar nuevas compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.editar',        'description' => 'Editar compras existentes'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.anular',        'description' => 'Anular compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.ver',       'description' => 'Ver proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.gestionar', 'description' => 'Crear y editar proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.reporte',       'description' => 'Ver reporte de compras'],

        // Catálogo
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'catalogo.categorias',  'description' => 'Gestionar categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'catalogo.marcas',      'description' => 'Gestionar marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'catalogo.atributos',   'description' => 'Gestionar atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'catalogo.produccion',  'description' => 'Gestionar fichas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'catalogo.dimensiones', 'description' => 'Gestionar dimensiones y unidades'],

        // Configuración
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.cajas',          'description' => 'Gestionar cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.impresoras',      'description' => 'Gestionar impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.metodos_pago',    'description' => 'Gestionar métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.metodos_envio',   'description' => 'Gestionar métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.series',          'description' => 'Gestionar series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.usuarios',        'description' => 'Gestionar usuarios del panel'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.roles',           'description' => 'Gestionar roles y permisos'],

        // Reportes
        ['module' => 'reportes', 'module_label' => 'Reportes', 'name' => 'reportes.ventas_periodo', 'description' => 'Reporte de ventas por período'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'name' => 'reportes.productos',      'description' => 'Reporte de productos más vendidos'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'name' => 'reportes.vendedor',       'description' => 'Reporte detallado por vendedor'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'name' => 'reportes.clientes',       'description' => 'Reporte de compras por cliente'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'name' => 'reportes.cuentas_cobrar', 'description' => 'Ver cuentas por cobrar'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        // ── Crear todos los permisos (admin + pdv) ────────────────────────────

        $todosLosPermisos = array_merge(
            array_map(fn($p) => array_merge($p, ['scope' => 'admin']), self::$permisosAdmin),
            array_map(fn($p) => array_merge($p, ['scope' => 'pdv']),   self::$permisosPdv),
        );

        foreach ($todosLosPermisos as $data) {
            Permission::updateOrCreate(
                ['name' => $data['name'], 'guard_name' => 'web'],
                [
                    'description'  => $data['description'],
                    'module'       => $data['module'],
                    'module_label' => $data['module_label'],
                    'scope'        => $data['scope'],
                ]
            );
        }

        $this->command->info('✔ ' . count($todosLosPermisos) . ' permisos creados/actualizados.');

        // ── Crear rol Super Administrador (empresa_id = null) ─────────────────

        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Administrador', 'guard_name' => 'web', 'empresa_id' => null]
        );

        // Asignar todos los permisos al Super Administrador
        $registrar->forgetCachedPermissions();

        $todosPermisos = Permission::where('guard_name', 'web')->get();
        $superAdmin->syncPermissions($todosPermisos);

        $registrar->forgetCachedPermissions();

        $this->command->info("✔ Super Administrador: {$todosPermisos->count()} permisos asignados.");
    }
}
