<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProveedorGeneralSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Proveedor::where('empresa_id', $empresa->id)
            ->where('numero_documento', '00000000001')
            ->exists()) {
            return;
        }

        Proveedor::create([
            'empresa_id'       => $empresa->id,
            'user_id'          => User::value('id') ?? 1,
            'nombre'           => 'PROVEEDOR GENERAL',
            'tipo_documento'   => 'ruc',
            'numero_documento' => '00000000001',
            'correo'           => null,
            'telefono'         => null,
            'direccion'        => null,
            'departamento'     => null,
            'estado'           => 'activo',
        ]);
    }
}
