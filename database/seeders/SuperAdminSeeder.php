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
        // ── Empresas ──────────────────────────────────────────────────────────
        ['module' => 'admin_empresas', 'module_label' => 'Empresas',           'name' => 'admin.empresas.ver',              'description' => 'Ver empresas del SaaS'],
        ['module' => 'admin_empresas', 'module_label' => 'Empresas',           'name' => 'admin.empresas.crear',            'description' => 'Crear empresas en el SaaS'],
        ['module' => 'admin_empresas', 'module_label' => 'Empresas',           'name' => 'admin.empresas.editar',           'description' => 'Editar datos de empresas'],
        ['module' => 'admin_empresas', 'module_label' => 'Empresas',           'name' => 'admin.empresas.eliminar',         'description' => 'Eliminar empresas del SaaS'],

        // ── Usuarios del sistema ───────────────────────────────────────────────
        ['module' => 'admin_usuarios', 'module_label' => 'Usuarios del sistema', 'name' => 'admin.usuarios.ver',            'description' => 'Ver usuarios del sistema'],
        ['module' => 'admin_usuarios', 'module_label' => 'Usuarios del sistema', 'name' => 'admin.usuarios.crear',          'description' => 'Crear usuarios del sistema'],
        ['module' => 'admin_usuarios', 'module_label' => 'Usuarios del sistema', 'name' => 'admin.usuarios.editar',         'description' => 'Editar usuarios del sistema'],
        ['module' => 'admin_usuarios', 'module_label' => 'Usuarios del sistema', 'name' => 'admin.usuarios.eliminar',       'description' => 'Eliminar usuarios del sistema'],

        // ── Planes ────────────────────────────────────────────────────────────
        ['module' => 'admin_planes',   'module_label' => 'Planes',             'name' => 'admin.planes.ver',                'description' => 'Ver planes de suscripción'],
        ['module' => 'admin_planes',   'module_label' => 'Planes',             'name' => 'admin.planes.crear',              'description' => 'Crear planes de suscripción'],
        ['module' => 'admin_planes',   'module_label' => 'Planes',             'name' => 'admin.planes.editar',             'description' => 'Editar planes de suscripción'],
        ['module' => 'admin_planes',   'module_label' => 'Planes',             'name' => 'admin.planes.eliminar',           'description' => 'Eliminar planes de suscripción'],

        // ── Métodos de Pago ───────────────────────────────────────────────────
        ['module' => 'admin_metodos',  'module_label' => 'Métodos de Pago',    'name' => 'admin.metodos_pago.ver',          'description' => 'Ver métodos de pago globales'],
        ['module' => 'admin_metodos',  'module_label' => 'Métodos de Pago',    'name' => 'admin.metodos_pago.crear',        'description' => 'Crear métodos de pago globales'],
        ['module' => 'admin_metodos',  'module_label' => 'Métodos de Pago',    'name' => 'admin.metodos_pago.editar',       'description' => 'Editar métodos de pago globales'],
        ['module' => 'admin_metodos',  'module_label' => 'Métodos de Pago',    'name' => 'admin.metodos_pago.eliminar',     'description' => 'Eliminar métodos de pago globales'],

        // ── Anuncios ──────────────────────────────────────────────────────────
        ['module' => 'admin_anuncios', 'module_label' => 'Anuncios',           'name' => 'admin.anuncios.ver',              'description' => 'Ver anuncios del sistema'],
        ['module' => 'admin_anuncios', 'module_label' => 'Anuncios',           'name' => 'admin.anuncios.crear',            'description' => 'Crear anuncios del sistema'],
        ['module' => 'admin_anuncios', 'module_label' => 'Anuncios',           'name' => 'admin.anuncios.editar',           'description' => 'Editar anuncios del sistema'],
        ['module' => 'admin_anuncios', 'module_label' => 'Anuncios',           'name' => 'admin.anuncios.eliminar',         'description' => 'Eliminar anuncios del sistema'],

        // ── Tareas Programadas ────────────────────────────────────────────────
        ['module' => 'admin_tareas',   'module_label' => 'Tareas Programadas', 'name' => 'admin.tareas.ver',                'description' => 'Ver tareas programadas del sistema'],
        ['module' => 'admin_tareas',   'module_label' => 'Tareas Programadas', 'name' => 'admin.tareas.crear',              'description' => 'Crear tareas programadas'],
        ['module' => 'admin_tareas',   'module_label' => 'Tareas Programadas', 'name' => 'admin.tareas.editar',             'description' => 'Editar tareas programadas'],
        ['module' => 'admin_tareas',   'module_label' => 'Tareas Programadas', 'name' => 'admin.tareas.eliminar',           'description' => 'Eliminar tareas programadas'],
    ];

    // ── Permisos del panel PDV (scope por empresa) ────────────────────────────

    public static array $permisosPdv = [
        // ── Punto de Venta ────────────────────────────────────────────────────
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => null,               'name' => 'caja.punto_de_venta',    'description' => 'Acceso al punto de venta'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => null,               'name' => 'caja.ventas_turno',       'description' => 'Ver ventas del turno'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => null,               'name' => 'caja.cierres',            'description' => 'Reporte de cierre de cajas'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Sesiones de Caja', 'name' => 'sesiones.ver',            'description' => 'Aperturar y Cerrar Caja'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Sesiones de Caja', 'name' => 'sesiones.eliminar',       'description' => 'Eliminar sesiones de caja'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Clientes',         'name' => 'clientes.ver',            'description' => 'Ver lista de clientes'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Clientes',         'name' => 'clientes.crear',          'description' => 'Crear nuevos clientes'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Clientes',         'name' => 'clientes.editar',         'description' => 'Editar datos de clientes'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Clientes',         'name' => 'clientes.eliminar',       'description' => 'Eliminar clientes'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Movimientos',      'name' => 'ingresos_egresos.ver',    'description' => 'Ver ingresos y egresos de caja'],
        ['module' => 'caja', 'module_label' => 'Punto de Venta', 'group' => 'Movimientos',      'name' => 'ingresos_egresos.crear',  'description' => 'Registrar ingresos y egresos de caja'],

        // ── Facturación Electrónica ────────────────────────────────────────────
        ['module' => 'fe', 'module_label' => 'Facturación Electrónica', 'group' => null, 'name' => 'caja.resumen_boletas', 'description' => 'Ver y gestionar resumenes SUNAT (RC/RA)'],
        ['module' => 'fe', 'module_label' => 'Facturación Electrónica', 'group' => null, 'name' => 'caja.notas',           'description' => 'Ver y gestionar notas de crédito / débito'],

        // ── Productos / Inventario ─────────────────────────────────────────────
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Productos',       'name' => 'productos.ver',      'description' => 'Ver listado de productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Productos',       'name' => 'productos.crear',    'description' => 'Crear nuevos productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Productos',       'name' => 'productos.editar',   'description' => 'Editar productos existentes'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Productos',       'name' => 'productos.eliminar', 'description' => 'Eliminar productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Productos',       'name' => 'productos.activar',  'description' => 'Activar / desactivar / archivar productos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Inventario',      'name' => 'inventario.ver',     'description' => 'Ver gestión de inventario'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Inventario',      'name' => 'inventario.kardex',  'description' => 'Ver kardex de movimientos'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Ajustes de Stock','name' => 'ajustes.ver',        'description' => 'Ver ajustes de stock'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Ajustes de Stock','name' => 'ajustes.crear',      'description' => 'Crear ajustes en borrador'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Ajustes de Stock','name' => 'ajustes.confirmar',  'description' => 'Confirmar ajustes (aplica stock real)'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Ajustes de Stock','name' => 'ajustes.anular',     'description' => 'Anular ajustes confirmados'],
        ['module' => 'productos', 'module_label' => 'Productos / Inventario', 'group' => 'Ajustes de Stock','name' => 'ajustes.reporte',    'description' => 'Ver reporte de ajustes'],

        // ── Recetas ───────────────────────────────────────────────────────────
        ['module' => 'recetas', 'module_label' => 'Recetas de Productos', 'group' => null, 'name' => 'recetas.ver',      'description' => 'Ver listado de recetas'],
        ['module' => 'recetas', 'module_label' => 'Recetas de Productos', 'group' => null, 'name' => 'recetas.crear',    'description' => 'Crear recetas de productos'],
        ['module' => 'recetas', 'module_label' => 'Recetas de Productos', 'group' => null, 'name' => 'recetas.editar',   'description' => 'Editar recetas existentes'],
        ['module' => 'recetas', 'module_label' => 'Recetas de Productos', 'group' => null, 'name' => 'recetas.eliminar', 'description' => 'Eliminar recetas'],

        // ── Pedidos Web / Tienda ───────────────────────────────────────────────
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Órdenes',     'name' => 'ordenes.ver',           'description' => 'Ver órdenes de clientes web'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Órdenes',     'name' => 'ordenes.gestionar',     'description' => 'Editar y procesar órdenes'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Órdenes',     'name' => 'ordenes.cancelar',      'description' => 'Cancelar órdenes (restaura stock)'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Órdenes',     'name' => 'ordenes.despacho',      'description' => 'Acceder a pantalla de despachos'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Promociones', 'name' => 'promociones.ver',       'description' => 'Ver promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Promociones', 'name' => 'promociones.crear',     'description' => 'Crear promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Promociones', 'name' => 'promociones.editar',    'description' => 'Editar promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Promociones', 'name' => 'promociones.eliminar',  'description' => 'Eliminar promociones de la tienda'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Cupones',     'name' => 'cupones.ver',           'description' => 'Ver y listar cupones de descuento'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Cupones',     'name' => 'cupones.crear',         'description' => 'Crear cupones de descuento'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Cupones',     'name' => 'cupones.editar',        'description' => 'Editar cupones de descuento'],
        ['module' => 'tienda', 'module_label' => 'Pedidos Web / Tienda', 'group' => 'Cupones',     'name' => 'cupones.eliminar',      'description' => 'Eliminar cupones de descuento'],

        // ── Compras / Proveedores ──────────────────────────────────────────────
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Compras',     'name' => 'compras.ver',           'description' => 'Ver listado de compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Compras',     'name' => 'compras.crear',         'description' => 'Registrar nuevas compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Compras',     'name' => 'compras.editar',        'description' => 'Editar compras existentes'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Compras',     'name' => 'compras.anular',        'description' => 'Anular compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Compras',     'name' => 'compras.reporte',       'description' => 'Ver reporte de compras'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Proveedores', 'name' => 'proveedores.ver',       'description' => 'Ver proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Proveedores', 'name' => 'proveedores.crear',     'description' => 'Crear nuevos proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Proveedores', 'name' => 'proveedores.editar',    'description' => 'Editar proveedores'],
        ['module' => 'compras', 'module_label' => 'Compras / Proveedores', 'group' => 'Proveedores', 'name' => 'proveedores.eliminar',  'description' => 'Eliminar proveedores'],

        // ── Gastos ────────────────────────────────────────────────────────────
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Variables', 'name' => 'gastos.ver',          'description' => 'Ver listado de gastos'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Variables', 'name' => 'gastos.crear',        'description' => 'Registrar y editar gastos'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Variables', 'name' => 'gastos.anular',       'description' => 'Anular gastos registrados'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Fijos',     'name' => 'gastos_fijos.ver',    'description' => 'Ver listado de gastos fijos'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Fijos',     'name' => 'gastos_fijos.crear',  'description' => 'Crear nuevos gastos fijos'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Fijos',     'name' => 'gastos_fijos.editar', 'description' => 'Editar gastos fijos existentes'],
        ['module' => 'gastos', 'module_label' => 'Gastos', 'group' => 'Gastos Fijos',     'name' => 'gastos_fijos.eliminar','description' => 'Eliminar gastos fijos'],

        // ── Resumen / Dashboard ───────────────────────────────────────────────
        ['module' => 'resumen', 'module_label' => 'Resumen', 'group' => null, 'name' => 'resumen.camino_ganancias', 'description' => 'Ver sección Camino a tus ganancias en el Resumen'],

        // ── Catálogo ───────────────────────────────────────────────────────────
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Categorías',  'name' => 'categorias.ver',       'description' => 'Ver categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Categorías',  'name' => 'categorias.crear',     'description' => 'Crear categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Categorías',  'name' => 'categorias.editar',    'description' => 'Editar categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Categorías',  'name' => 'categorias.eliminar',  'description' => 'Eliminar categorías'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Marcas',      'name' => 'marcas.ver',           'description' => 'Ver marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Marcas',      'name' => 'marcas.crear',         'description' => 'Crear marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Marcas',      'name' => 'marcas.editar',        'description' => 'Editar marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Marcas',      'name' => 'marcas.eliminar',      'description' => 'Eliminar marcas'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Atributos',   'name' => 'atributos.ver',        'description' => 'Ver atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Atributos',   'name' => 'atributos.crear',      'description' => 'Crear atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Atributos',   'name' => 'atributos.editar',     'description' => 'Editar atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Atributos',   'name' => 'atributos.eliminar',   'description' => 'Eliminar atributos y valores'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Producción',  'name' => 'produccion.ver',       'description' => 'Ver áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Producción',  'name' => 'produccion.crear',     'description' => 'Crear áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Producción',  'name' => 'produccion.editar',    'description' => 'Editar áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Producción',  'name' => 'produccion.eliminar',  'description' => 'Eliminar áreas de producción'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Dimensiones', 'name' => 'dimensiones.ver',      'description' => 'Ver dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Dimensiones', 'name' => 'dimensiones.crear',    'description' => 'Crear dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Dimensiones', 'name' => 'dimensiones.editar',   'description' => 'Editar dimensiones y unidades'],
        ['module' => 'catalogo', 'module_label' => 'Catálogo', 'group' => 'Dimensiones', 'name' => 'dimensiones.eliminar', 'description' => 'Eliminar dimensiones y unidades'],

        // ── Configuración ──────────────────────────────────────────────────────
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Cajas Registradoras', 'name' => 'cajas.ver',              'description' => 'Ver cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Cajas Registradoras', 'name' => 'cajas.crear',            'description' => 'Crear cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Cajas Registradoras', 'name' => 'cajas.editar',           'description' => 'Editar cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Cajas Registradoras', 'name' => 'cajas.eliminar',         'description' => 'Eliminar cajas registradoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Impresoras',          'name' => 'impresoras.ver',         'description' => 'Ver impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Impresoras',          'name' => 'impresoras.crear',       'description' => 'Crear impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Impresoras',          'name' => 'impresoras.editar',      'description' => 'Editar impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Impresoras',          'name' => 'impresoras.eliminar',    'description' => 'Eliminar impresoras'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Pago',     'name' => 'metodos_pago.ver',       'description' => 'Ver métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Pago',     'name' => 'metodos_pago.crear',     'description' => 'Crear métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Pago',     'name' => 'metodos_pago.editar',    'description' => 'Editar métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Pago',     'name' => 'metodos_pago.eliminar',  'description' => 'Eliminar métodos de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Envío',    'name' => 'metodos_envio.ver',      'description' => 'Ver métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Envío',    'name' => 'metodos_envio.crear',    'description' => 'Crear métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Envío',    'name' => 'metodos_envio.editar',   'description' => 'Editar métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Métodos de Envío',    'name' => 'metodos_envio.eliminar', 'description' => 'Eliminar métodos de envío'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Series',              'name' => 'series.ver',             'description' => 'Ver series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Series',              'name' => 'series.crear',           'description' => 'Crear series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Series',              'name' => 'series.editar',          'description' => 'Editar series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Series',              'name' => 'series.eliminar',        'description' => 'Eliminar series de documentos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'usuarios.ver',           'description' => 'Ver lista de usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'usuarios.crear',         'description' => 'Crear nuevos usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'usuarios.editar',        'description' => 'Editar usuarios existentes'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'usuarios.eliminar',      'description' => 'Eliminar usuarios'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'roles.ver',              'description' => 'Ver lista de roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'roles.crear',            'description' => 'Crear roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'roles.editar',           'description' => 'Editar roles y sus permisos'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Usuarios y Roles',    'name' => 'roles.eliminar',         'description' => 'Eliminar roles'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Empresa',             'name' => 'config.suscripcion',     'description' => 'Ver suscripción y registrar comprobantes de pago'],
        ['module' => 'config', 'module_label' => 'Configuración', 'group' => 'Empresa',             'name' => 'empresa.configurar',     'description' => 'Configurar datos de Mi Empresa (RUC, certificado, facturación)'],

        // ── Pisos y Mesas ──────────────────────────────────────────────────────
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Pisos', 'name' => 'pisos.ver',     'description' => 'Ver pisos y mesas'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Pisos', 'name' => 'pisos.crear',   'description' => 'Crear pisos'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Pisos', 'name' => 'pisos.editar',  'description' => 'Editar pisos'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Pisos', 'name' => 'pisos.eliminar','description' => 'Eliminar pisos'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Mesas', 'name' => 'mesas.crear',   'description' => 'Crear mesas dentro de un piso'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Mesas', 'name' => 'mesas.editar',  'description' => 'Editar mesas (nombre, capacidad, estado)'],
        ['module' => 'pisos_mesas', 'module_label' => 'Pisos y Mesas', 'group' => 'Mesas', 'name' => 'mesas.eliminar','description' => 'Eliminar mesas'],

        // ── Restaurante ────────────────────────────────────────────────────────
        ['module' => 'restaurante', 'module_label' => 'Restaurante', 'group' => null, 'name' => 'restaurante.ver',             'description' => 'Ver restaurante: mapa de mesas y pedidos activos'],
        ['module' => 'restaurante', 'module_label' => 'Restaurante', 'group' => null, 'name' => 'restaurante.pedido.crear',    'description' => 'Crear pedidos de mesa'],
        ['module' => 'restaurante', 'module_label' => 'Restaurante', 'group' => null, 'name' => 'restaurante.pedido.editar',   'description' => 'Editar pedidos de mesa en curso'],
        ['module' => 'restaurante', 'module_label' => 'Restaurante', 'group' => null, 'name' => 'restaurante.pedido.cobrar',   'description' => 'Cobrar pedidos de mesa (genera venta)'],
        ['module' => 'restaurante', 'module_label' => 'Restaurante', 'group' => null, 'name' => 'restaurante.pedido.eliminar', 'description' => 'Eliminar / anular pedidos de mesa'],

        // ── Reportes ───────────────────────────────────────────────────────────
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes de Caja', 'name' => 'caja.reporte_ventas',     'description' => 'Ver reporte de ventas'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes de Caja', 'name' => 'caja.reporte_ganancias',  'description' => 'Ver reporte de ganancias'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes de Caja', 'name' => 'caja.reporte_vendedores', 'description' => 'Ver reporte por vendedor'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.ventas_periodo', 'description' => 'Reporte de ventas por período'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.productos',      'description' => 'Reporte de productos más vendidos'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.vendedor',       'description' => 'Reporte detallado por vendedor'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.clientes',       'description' => 'Reporte de compras por cliente'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.cuentas_cobrar', 'description' => 'Ver cuentas por cobrar'],
        ['module' => 'reportes', 'module_label' => 'Reportes', 'group' => 'Reportes',         'name' => 'reportes.balance',        'description' => 'Ver reporte de balance (ingresos, egresos y ganancias)'],

        // ── Listas de Precios ──────────────────────────────────────────────────
        ['module' => 'listas_precios', 'module_label' => 'Listas de Precios', 'group' => null, 'name' => 'listas_precios.ver',      'description' => 'Ver listas de precios'],
        ['module' => 'listas_precios', 'module_label' => 'Listas de Precios', 'group' => null, 'name' => 'listas_precios.crear',    'description' => 'Crear listas de precios'],
        ['module' => 'listas_precios', 'module_label' => 'Listas de Precios', 'group' => null, 'name' => 'listas_precios.editar',   'description' => 'Editar listas de precios'],
        ['module' => 'listas_precios', 'module_label' => 'Listas de Precios', 'group' => null, 'name' => 'listas_precios.eliminar', 'description' => 'Eliminar listas de precios'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        // ── Limpiar permisos obsoletos renombrados ────────────────────────────
        Permission::whereIn('name', [
            'mesas.ver', 'comandas.ver', 'comandas.gestionar', 'comandas.cobrar',
            // Permisos admin monolíticos reemplazados por granulares
            'admin.empresas', 'admin.usuarios', 'admin.planes', 'admin.metodos_pago', 'admin.configuracion',
            // Permisos admin.configuracion.* fusionados → separados en anuncios y tareas
            'admin.configuracion.ver', 'admin.configuracion.crear', 'admin.configuracion.editar', 'admin.configuracion.eliminar',
        ])->where('guard_name', 'web')->delete();
        $registrar->forgetCachedPermissions();

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
                    'group'        => $data['group'] ?? null,
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
