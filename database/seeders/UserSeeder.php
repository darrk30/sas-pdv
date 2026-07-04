<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Kevin — Super Administrador global del SaaS ────────────────────
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $kevin = User::firstOrCreate(
            ['email' => 'kevin@gmail.com'],
            [
                'name'     => 'Kevin Rivera',
                'password' => Hash::make('123123123'),
            ]
        );

        $rolSuper = Role::where('name', 'Super Administrador')->first();
        if ($rolSuper && ! $kevin->hasRole($rolSuper)) {
            $kevin->assignRole($rolSuper);
        }

        // ── 2. Empresa Kittybell ──────────────────────────────────────────────
        $empresa = Empresa::firstOrCreate(
            ['slug' => 'bodega'],
            [
                'name'                 => 'Kittybell',
                'ruc'                  => '20000000001',
                'slug'                 => 'bodega',
                'direccion'            => 'Chiclayo',
                'telefono'             => '948798072',
                'email'                => 'belen@kittybell.com',
                'departamento'         => 'Lambayeque',
                'provincia'            => 'Chiclayo',
                'distrito'             => 'Chiclayo',
                'ubigeo'               => '140101',
                'estado'               => 'activo',
                'carta_activa_cliente' => 'activo',
                'carta_activa_admin'   => 'activo',
                'cod_local'            => '0000',
                'country_code'         => 'PE',
            ]
        );

        // ── 3. Belén Cano — Administradora de Kittybell ───────────────────────
        $belen = User::firstOrCreate(
            ['email' => 'belen@kittybell.com'],
            [
                'name'     => 'Belen Cano',
                'password' => Hash::make('Belen14@'),
            ]
        );

        $belen->empresas()->syncWithoutDetaching([$empresa->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

        $rolAdmin = Role::where('name', 'Administrador')->first();
        if ($rolAdmin) {
            $yaAsignado = \DB::table('model_has_roles')
                ->where('empresa_id', $empresa->id)
                ->where('role_id',    $rolAdmin->id)
                ->where('model_id',   $belen->id)
                ->where('model_type', get_class($belen))
                ->exists();

            if (! $yaAsignado) {
                $belen->assignRole($rolAdmin);
            }
        }

        // ── 4. Kevin también vinculado a Kittybell ────────────────────────────
        $kevin->empresas()->syncWithoutDetaching([$empresa->id]);
    }
}
