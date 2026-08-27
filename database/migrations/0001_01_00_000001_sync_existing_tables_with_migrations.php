<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración de sincronización: detecta tablas/columnas que ya existen en la BD
 * y las marca como completadas en la tabla migrations, evitando errores "table already exists"
 * al desplegar en servidores con una instalación previa.
 */
return new class extends Migration
{
    /** migration_name => tabla que crea */
    private array $createTable = [
        '0001_01_00_022334_create_empresas_table'              => 'empresas',
        '0001_01_01_000000_create_users_table'                 => 'users',
        '0001_01_01_000001_create_cache_table'                 => 'cache',
        '0001_01_01_000002_create_jobs_table'                  => 'jobs',
        '2026_05_28_021357_create_permission_tables'           => 'permissions',
        '2026_05_28_031241_create_empresa_user_table'          => 'empresa_user',
        '2026_06_03_052521_create_plans_table'                 => 'plans',
        '2026_06_03_052609_create_suscripciones_table'         => 'suscripciones',
        '2026_06_03_052658_create_pagos_clientes_table'        => 'pagos_clientes',
        '2026_06_06_014309_create_categorias_table'            => 'categorias',
        '2026_06_06_014603_create_atributos_table'             => 'atributos',
        '2026_06_06_014709_create_valors_table'                => 'valors',
        '2026_06_06_235503_create_dimensions_table'            => 'dimensions',
        '2026_06_06_235616_create_unidades_medidas_table'      => 'unidades_medidas',
        '2026_06_07_010959_create_impresoras_table'            => 'impresoras',
        '2026_06_07_011638_create_produccions_table'           => 'produccions',
        '2026_06_07_013604_create_marcas_table'                => 'marcas',
        '2026_06_07_213617_create_productos_table'             => 'productos',
        '2026_06_08_012221_create_producto_atributos_table'    => 'producto_atributos',
        '2026_06_08_012334_create_producto_atributo_valores_table' => 'producto_atributo_valores',
        '2026_06_08_134130_create_exclusions_table'            => 'exclusions',
        '2026_06_09_001206_create_variantes_table'             => 'variantes',
        '2026_06_09_012619_create_variante_valores_table'      => 'variante_valores',
        '2026_06_10_003147_create_inventarios_table'           => 'inventarios',
        '2026_06_11_003603_create_cajas_table'                 => 'cajas',
        '2026_06_11_004758_create_turnos_table'                => 'turnos',
        '2026_06_11_005003_create_caja_usuario_table'          => 'caja_usuario',
        '2026_06_12_003349_create_ajustes_table'               => 'ajustes',
        '2026_06_12_003635_create_ajuste_detalles_table'       => 'ajuste_detalles',
        '2026_06_22_000001_create_metodos_pago_table'          => 'metodos_pago',
        '2026_06_22_000002_create_proveedores_table'           => 'proveedores',
        '2026_06_22_000003_create_compras_table'               => 'compras',
        '2026_06_22_000004_create_compra_detalles_table'       => 'compra_detalles',
        '2026_06_22_000005_create_compra_pagos_table'          => 'compra_pagos',
        '2026_06_23_000001_create_series_table'                => 'series',
        '2026_06_23_000002_create_clientes_table'              => 'clientes',
        '2026_06_24_000001_create_sesion_cajas_table'          => 'sesion_cajas',
        '2026_06_24_000002_create_sesion_caja_pagos_table'     => 'sesion_caja_pagos',
        '2026_06_24_000003_create_ingresos_egresos_table'      => 'ingresos_egresos',
        '2026_06_24_000005_create_transacciones_table'         => 'transacciones',
        '2026_06_24_000006_create_promociones_table'           => 'promociones',
        '2026_06_24_000007_create_promocion_detalles_table'    => 'promocion_detalles',
        '2026_06_24_000008_create_ventas_table'                => 'ventas',
        '2026_06_24_000009_create_venta_detalles_table'        => 'venta_detalles',
        '2026_06_24_000010_create_venta_pagos_table'           => 'venta_pagos',
        '2026_06_25_000003_create_kardex_table'                => 'kardex',
        '2026_06_28_000000_create_metodos_envio_table'         => 'metodos_envio',
        '2026_06_28_000001_create_ordenes_table'               => 'ordenes',
        '2026_06_28_000002_create_orden_detalles_table'        => 'orden_detalles',
        '2026_06_29_000002_create_galeria_productos_table'     => 'galeria_productos',
        '2026_06_29_000003_create_carritos_table'              => 'carritos',
        '2026_06_29_000004_create_lista_deseos_table'          => 'lista_deseos',
        '2026_07_06_100000_create_notifications_table'         => 'notifications',
        '2026_07_11_191721_create_push_subscriptions_table'    => 'push_subscriptions',
        '2026_07_17_015110_create_resumenes_sunat_table'       => 'resumenes_sunat',
        '2026_07_17_015110_create_empresa_facturacion_table'   => 'empresa_facturacion',
        '2026_07_17_015111_create_notas_table'                 => 'notas',
        '2026_08_27_000001_create_gastos_table'                => 'gastos',
    ];

    /** migration_name => [tabla, columna_indicadora] */
    private array $addColumn = [
        '2026_07_07_000000_add_modulos_activos_to_empresas_table'       => ['empresas',      'modulos_activos'],
        '2026_07_11_235649_add_features_to_plans_table'                  => ['plans',         'tiene_variantes'],
        '2026_07_17_010041_add_facturacion_electronica_to_planes_table'  => ['plans',         'facturacion_electronica'],
        '2026_07_17_015109_add_fe_columns_to_empresas_table'             => ['empresas',      'fe_envio_directo_boleta'],
        '2026_07_17_015110_add_sunat_columns_to_ventas_table'            => ['ventas',        'estado_sunat'],
        '2026_07_17_021812_add_sunat_fields_to_venta_detalles_table'     => ['venta_detalles','tip_afe_igv'],
        '2026_07_23_000001_add_despacho_direccion_to_ventas_table'       => ['ventas',        'despacho_direccion'],
        '2026_08_02_225137_add_bot_contexto_to_empresas_table'           => ['empresas',      'bot_contexto'],
        '2026_08_27_000002_add_vendible_to_productos_table'              => ['productos',     'vendible'],
    ];

    public function up(): void
    {
        $batch = 1;

        foreach ($this->createTable as $migration => $table) {
            if (Schema::hasTable($table) && ! DB::table('migrations')->where('migration', $migration)->exists()) {
                DB::table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
            }
        }

        foreach ($this->addColumn as $migration => [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                && ! DB::table('migrations')->where('migration', $migration)->exists()) {
                DB::table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
            }
        }
    }

    public function down(): void
    {
        // No hay nada que revertir: solo eliminamos las entradas que esta migración
        // pudo haber insertado artificialmente.
        $allMigrations = array_merge(
            array_keys($this->createTable),
            array_keys($this->addColumn),
        );

        DB::table('migrations')->whereIn('migration', $allMigrations)->where('batch', 1)->delete();
    }
};
