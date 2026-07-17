<?php

namespace App\Models;

use App\Observers\EmpresaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([EmpresaObserver::class])]
class Empresa extends Model
{
    protected $fillable = [
        'name',
        'ruc',
        'logo',
        'icono',
        'slug',
        'direccion',
        'telefono',
        'email',
        'departamento',
        'distrito',
        'provincia',
        'ubigeo',
        'estado',
        'carta_activa_cliente',
        'carta_activa_admin',
        'cod_local',
        'country_code',
        'modulos_activos',
    ];

    protected $casts = [
        'modulos_activos' => 'array',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function getFilamentName(): string
    {
        return (string) ($this->nombre ?? 'Unnamed Tenant');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'empresa_user', 'empresa_id', 'user_id')
                    ->withPivot('id', 'estado'); // 👈 Solo agregamos 'estado' aquí, separado por coma
    }

    public function pagos()
    {
        return $this->hasManyThrough(
            PagosCliente::class, // Modelo destino
            Suscripcion::class,  // Modelo intermedio
            'empresa_id', // Foreign key en tabla Suscripciones
            'suscripcion_id', // Foreign key en tabla PagosCliente
            'id', // Local key en Empresa
            'id' // Local key en Suscripciones
        );
    }

    public function suscripcion()
    {
        return $this->hasOne(Suscripcion::class);
    }

    public function planActual(): ?Plan
    {
        $this->loadMissing('suscripcion.plan');
        return $this->suscripcion?->plan;
    }

    public function tieneVariantes(): bool
    {
        return (bool) ($this->planActual()?->tiene_variantes ?? false);
    }

    // ── Módulos ───────────────────────────────────────────────────────────────

    public static function defaultModulos(): array
    {
        return [
            // Punto de Venta
            'caja'                => true,
            'punto_de_venta'      => true,
            'sesion_cajas'        => true,
            'ventas_turno'        => true,
            'ingresos_egresos'    => true,
            'cierres_caja'        => true,
            // Inventario
            'inventario'          => true,
            'gestion_productos'   => true,
            'gestion_inventario'  => true,
            'kardex'              => true,
            'ajustes_stock'       => true,
            // Pedidos Web
            'pedidos_web'         => true,
            'ordenes_web'         => true,
            'clientes'            => true,
            'promociones'         => true,
            'despacho'            => true,
            // Compras
            'compras'             => true,
            'gestion_compras'     => true,
            'proveedores'         => true,
            // Catálogo
            'catalogo'            => true,
            'categorias'          => true,
            'marcas'              => true,
            'atributos'           => true,
            'produccion'          => true,
            'dimensiones'         => true,
            // Reportes
            'reportes'            => true,
            'ventas_periodo'      => true,
            'reporte_ventas'      => true,
            'reporte_ganancias'   => true,
            'reporte_productos'   => true,
            'reporte_compras'     => true,
            'reporte_vendedores'  => true,
            'reporte_ajustes'     => true,
            'reporte_clientes'    => true,
            'cuentas_por_cobrar'  => true,
            // Configuración
            'configuracion'       => true,
            'cajas_registradoras' => true,
            'metodos_pago'        => true,
            'metodos_envio'       => true,
            'series'              => true,
            'impresoras'          => true,
            'usuarios_roles'      => true,
        ];
    }

    private static array $moduloPadres = [
        // Punto de Venta
        'punto_de_venta'      => 'caja',
        'sesion_cajas'        => 'caja',
        'ventas_turno'        => 'caja',
        'ingresos_egresos'    => 'caja',
        'cierres_caja'        => 'caja',
        // Inventario
        'gestion_productos'   => 'inventario',
        'gestion_inventario'  => 'inventario',
        'kardex'              => 'inventario',
        'ajustes_stock'       => 'inventario',
        // Pedidos Web
        'ordenes_web'         => 'pedidos_web',
        'clientes'            => 'pedidos_web',
        'promociones'         => 'pedidos_web',
        'despacho'            => 'pedidos_web',
        // Compras
        'gestion_compras'     => 'compras',
        'proveedores'         => 'compras',
        // Catálogo
        'categorias'          => 'catalogo',
        'marcas'              => 'catalogo',
        'atributos'           => 'catalogo',
        'produccion'          => 'catalogo',
        'dimensiones'         => 'catalogo',
        // Reportes
        'ventas_periodo'      => 'reportes',
        'reporte_ventas'      => 'reportes',
        'reporte_ganancias'   => 'reportes',
        'reporte_productos'   => 'reportes',
        'reporte_compras'     => 'reportes',
        'reporte_vendedores'  => 'reportes',
        'reporte_ajustes'     => 'reportes',
        'reporte_clientes'    => 'reportes',
        'cuentas_por_cobrar'  => 'reportes',
        // Configuración
        'cajas_registradoras' => 'configuracion',
        'metodos_pago'        => 'configuracion',
        'metodos_envio'       => 'configuracion',
        'series'              => 'configuracion',
        'impresoras'          => 'configuracion',
        'usuarios_roles'      => 'configuracion',
    ];

    public function tieneModulo(string $modulo): bool
    {
        $activos = $this->modulos_activos ?? [];
        // El toggle padre es solo un helper "seleccionar todo" en la UI;
        // el estado real de acceso lo determina únicamente el sub-módulo.
        return (bool) ($activos[$modulo] ?? true);
    }
}
