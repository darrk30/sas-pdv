<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Permisos comodín reemplazados por granulares (ver/crear/editar/eliminar). */
    private array $old = [
        'config.cajas',
        'config.impresoras',
        'config.metodos_pago',
        'config.metodos_envio',
        'config.series',
        'config.usuarios',
        'config.roles',
        'caja.clientes',
        'caja.sesiones',
        'caja.ingresos_egresos',
        'catalogo.categorias',
        'catalogo.marcas',
        'catalogo.atributos',
        'catalogo.produccion',
        'catalogo.dimensiones',
        'proveedores.gestionar',
        'promociones.gestionar',
    ];

    public function up(): void
    {
        // Al eliminar los permisos, la FK cascade borra automáticamente
        // las asignaciones en role_has_permissions y model_has_permissions.
        // Después re-ejecuta el seeder para añadir los nuevos permisos granulares
        // y asignarlos al rol SuperAdmin:
        //   php artisan db:seed --class=SuperAdminSeeder
        DB::table('permissions')->whereIn('name', $this->old)->delete();
    }

    public function down(): void
    {
        // Restaura los permisos comodín (sin re-asignar roles — eso es manual).
        $now = now();
        $rows = array_map(fn($name) => [
            'name'       => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->old);

        DB::table('permissions')->insertOrIgnore($rows);
    }
};
