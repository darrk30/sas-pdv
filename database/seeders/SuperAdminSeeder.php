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
        // ── Caja / Ventas — accesos funcionales ───────────────────────────────
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.punto_de_venta',    'description' => 'Acceder al Punto de Venta y cobrar'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.ventas_turno',       'description' => 'Ver ventas del turno actual'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.cierres',            'description' => 'Ver historial de cierres de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_ventas',     'description' => 'Ver reporte de ventas'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_ganancias',  'description' => 'Ver reporte de ganancias (sensible)'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'caja.reporte_vendedores', 'description' => 'Ver reporte por vendedor'],
        // Sesiones de caja
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'sesiones.ver',            'description' => 'Ver historial de sesiones de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'sesiones.eliminar',       'description' => 'Eliminar sesiones de caja'],
        // Clientes
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'clientes.ver',            'description' => 'Ver lista de clientes'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'clientes.crear',          'description' => 'Crear nuevos clientes'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'clientes.editar',         'description' => 'Editar datos de clientes'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'clientes.eliminar',       'description' => 'Eliminar clientes'],
        // Ingresos y egresos
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'ingresos_egresos.ver',    'description' => 'Ver ingresos y egresos de caja'],
        ['module' => 'caja', 'module_label' => 'Caja / Ventas', 'name' => 'ingresos_egresos.crear',  'description' => 'Registrar ingresos y egresos de caja'],

        // ── Productos / Inventario ─────────────────────────────────────────────
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.ver',      'description' => 'Ver listado de productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.crear',    'description' => 'Crear nuevos productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.editar',   'description' => 'Editar productos existentes'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.eliminar', 'description' => 'Eliminar productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'productos.activar',  'description' => 'Activar / desactivar / archivar productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'inventario.ver',     'description' => 'Ver gestión de inventario'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'inventario.kardex',  'description' => 'Ver kardex de movimientos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.ver',        'description' => 'Ver ajustes de stock'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.crear',      'description' => 'Crear ajustes en borrador'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.confirmar',  'description' => 'Confirmar ajustes (aplica stock real)'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.anular',     'description' => 'Anular ajustes confirmados'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'name' => 'ajustes.reporte',    'description' => 'Ver reporte de ajustes'],

        // ── Pedidos Web / Tienda ───────────────────────────────────────────────
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'ordenes.ver',           'description' => 'Ver órdenes de clientes web'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'ordenes.gestionar',     'description' => 'Editar y procesar órdenes'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'ordenes.cancelar',      'description' => 'Cancelar órdenes (restaura stock)'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'ordenes.despacho',      'description' => 'Acceder a pantalla de despachos'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'promociones.ver',       'description' => 'Ver promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'promociones.crear',     'description' => 'Crear promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'promociones.editar',    'description' => 'Editar promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'name' => 'promociones.eliminar',  'description' => 'Eliminar promociones de la tienda'],

        // ── Compras / Proveedores ──────────────────────────────────────────────
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.ver',           'description' => 'Ver listado de compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.crear',         'description' => 'Registrar nuevas compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.editar',        'description' => 'Editar compras existentes'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.anular',        'description' => 'Anular compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'compras.reporte',       'description' => 'Ver reporte de compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.ver',       'description' => 'Ver proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.crear',     'description' => 'Crear nuevos proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.editar',    'description' => 'Editar proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'name' => 'proveedores.eliminar',  'description' => 'Eliminar proveedores'],

        // ── Catálogo ───────────────────────────────────────────────────────────
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'categorias.ver',       'description' => 'Ver categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'categorias.crear',     'description' => 'Crear categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'categorias.editar',    'description' => 'Editar categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'categorias.eliminar',  'description' => 'Eliminar categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'marcas.ver',           'description' => 'Ver marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'marcas.crear',         'description' => 'Crear marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'marcas.editar',        'description' => 'Editar marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'marcas.eliminar',      'description' => 'Eliminar marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'atributos.ver',        'description' => 'Ver atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'atributos.crear',      'description' => 'Crear atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'atributos.editar',     'description' => 'Editar atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'atributos.eliminar',   'description' => 'Eliminar atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'produccion.ver',       'description' => 'Ver áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'produccion.crear',     'description' => 'Crear áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'produccion.editar',    'description' => 'Editar áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'produccion.eliminar',  'description' => 'Eliminar áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'dimensiones.ver',      'description' => 'Ver dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'dimensiones.crear',    'description' => 'Crear dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'dimensiones.editar',   'description' => 'Editar dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'name' => 'dimensiones.eliminar', 'description' => 'Eliminar dimensiones y unidades'],

        // ── Configuración ──────────────────────────────────────────────────────
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'cajas.ver',              'description' => 'Ver cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'cajas.crear',            'description' => 'Crear cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'cajas.editar',           'description' => 'Editar cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'cajas.eliminar',         'description' => 'Eliminar cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'impresoras.ver',         'description' => 'Ver impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'impresoras.crear',       'description' => 'Crear impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'impresoras.editar',      'description' => 'Editar impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'impresoras.eliminar',    'description' => 'Eliminar impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_pago.ver',       'description' => 'Ver métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_pago.crear',     'description' => 'Crear métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_pago.editar',    'description' => 'Editar métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_pago.eliminar',  'description' => 'Eliminar métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_envio.ver',      'description' => 'Ver métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_envio.crear',    'description' => 'Crear métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_envio.editar',   'description' => 'Editar métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'metodos_envio.eliminar', 'description' => 'Eliminar métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'series.ver',             'description' => 'Ver series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'series.crear',           'description' => 'Crear series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'series.editar',          'description' => 'Editar series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'series.eliminar',        'description' => 'Eliminar series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'usuarios.ver',           'description' => 'Ver lista de usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'usuarios.crear',         'description' => 'Crear nuevos usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'usuarios.editar',        'description' => 'Editar usuarios existentes'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'usuarios.eliminar',      'description' => 'Eliminar usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'roles.ver',              'description' => 'Ver lista de roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'roles.crear',            'description' => 'Crear roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'roles.editar',           'description' => 'Editar roles y sus permisos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'roles.eliminar',         'description' => 'Eliminar roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'name' => 'config.suscripcion',     'description' => 'Ver suscripción y registrar comprobantes de pago'],

        // ── Reportes ───────────────────────────────────────────────────────────
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
