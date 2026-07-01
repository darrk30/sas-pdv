<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,   // permisos globales + rol Super Administrador
            UserSeeder::class,         // usuario admin global + empresa inicial + usuario PDV
            RolesEmpresaSeeder::class, // roles y permisos para todas las empresas existentes
        ]);
    }
}
