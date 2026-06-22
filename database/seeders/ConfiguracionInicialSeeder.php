<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;
use App\Models\Produccion;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConfiguracionInicialSeeder extends Seeder
{
    public function run(): void
    {
        // Solo para ejecución aislada, no se usará desde Observer o DatabaseSeeder
    }

    public function runForEmpresa(Empresa $empresa): void
    {
        // ==========================================
        // 1. ASIGNACIÓN DE ROLES PARA ESTA EMPRESA
        // ==========================================
        // Aseguramos que los roles se busquen/creen dentro del contexto de esta empresa
        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

        $rolesPdv = [
            'Administrador',
            'Cajero',
            'Vendedor',
            'Almacenero'
        ];

        foreach ($rolesPdv as $rolName) {
            Role::firstOrCreate([
                'name'       => $rolName,
                'guard_name' => 'web',
                'empresa_id' => $empresa->id 
            ]);
        }

        // NOTA: Aún no asignamos permisos (syncPermissions) porque no se han definido.

        // ==========================================
        // 2. PUNTOS DE PRODUCCIÓN (Cocina y Almacén)
        // ==========================================
        $puntosProduccion = ['Caja', 'Almacén'];

        foreach ($puntosProduccion as $punto) {
            Produccion::firstOrCreate([
                'nombre'     => $punto,
                'empresa_id' => $empresa->id,
            ], [
                'estado'       => true,
                'impresora_id' => null,
            ]);
        }
        
        // Limpiamos el contexto al terminar por seguridad
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}