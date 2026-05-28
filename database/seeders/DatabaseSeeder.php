<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear los Roles base del sistema
        $roleAdmin = Role::create(['name' => 'Administrador Global']);
        $roleCajero = Role::create(['name' => 'Administrador Local']);

        // 2. Crear tu Usuario Principal (Super Admin)
        $user = User::create([
            'name' => 'Kevin Rivera',
            'email' => 'kevin@gmail.com',
            'password' => Hash::make('123123123'),
        ]);

        // Asignarle el rol de Administrador
        $user->assignRole($roleAdmin);

        // 3. Crear la Primera Empresa (El Tenant Matriz)
        // Usamos los campos correctos basados en tu nueva migración
        $empresa = Empresa::create([
            'name' => 'Mi bodega S.A.C.',
            'ruc'    => '20123456789',
            'slug'   => 'mi-bodega',
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
        ]);

        // 5. ¡Conexión a través de Tablas Pivote! 
        // Le damos acceso al usuario a la empresa (para que entre al panel pdv)
        $user->empresas()->attach($empresa->id);
    }
}
