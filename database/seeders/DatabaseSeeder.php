<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
// Asegúrate de NO tener 'use WithoutModelEvents;' aquí

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ejecutar seeders de Roles (sin permisos por ahora)
        $this->call([
            RolesAdminSeeder::class,
            UserSeeder::class,
        ]);
    }
}