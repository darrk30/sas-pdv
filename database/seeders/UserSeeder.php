<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Empresa;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. KEVIN (ADMINISTRADOR GLOBAL DEL SAAS)
        // ==========================================
        
        // Limpiamos el Team ID para asignar un rol global
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $adminGlobal = User::firstOrCreate(
            ['email' => 'kevin@gmail.com'],
            [
                'name'     => 'Kevin Rivera',
                'password' => Hash::make('123123123'),
            ]
        );

        $rolAdminGlobal = Role::where('name', 'Super Administrador')->first();
        if ($rolAdminGlobal && !$adminGlobal->hasRole($rolAdminGlobal)) {
            $adminGlobal->assignRole($rolAdminGlobal);
        }

        // ==========================================
        // 2. CREACIÓN DE LA EMPRESA
        // ==========================================
        
        $empresa = Empresa::firstOrCreate(
            ['ruc' => '20123456789'], // Buscamos por RUC para evitar duplicados
            [
                'name'                 => 'Mi bodega S.A.C.',
                'slug'                 => 'bodega',
                'direccion'            => 'Av. Javier Prado 123',
                'telefono'             => '987654321',
                'email'                => 'contacto@empresa.com',
                'departamento'         => 'Lima',
                'distrito'             => 'San Isidro',
                'provincia'            => 'Lima',
                'ubigeo'               => '150131',
                'estado'               => 'activo',
                'carta_activa_cliente' => 'activo',
                'carta_activa_admin'   => 'activo',
                'cod_local'            => '0000',
                'country_code'         => 'PE',
            ]
        );

        // ==========================================
        // 3. JUAN (CAJERO DEL PDV)
        // ==========================================
        
        if ($empresa) {
            $juanPdv = User::firstOrCreate(
                ['email' => 'juan@gmail.com'],
                [
                    'name'     => 'Juan Administrador',
                    'password' => Hash::make('123123123'),
                ]
            );

            // Vinculamos a Juan con la empresa recién creada mediante la tabla pivote
            $juanPdv->empresas()->syncWithoutDetaching([$empresa->id]);

            // Configuramos Spatie para asignar el rol DENTRO de esta empresa
            app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

            // Asignamos el rol de Administrador
            $rolAdministrador = Role::where('name', 'Administrador')->first();
            if ($rolAdministrador && !$juanPdv->hasRole($rolAdministrador)) {
                $juanPdv->assignRole($rolAdministrador);
            }
        }
    }
}