<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClienteGeneralSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Cliente::where('empresa_id', $empresa->id)
            ->where('numero_documento', '99999999')
            ->exists()) {
            return;
        }

        Cliente::create([
            'empresa_id'       => $empresa->id,
            'user_id'          => User::value('id') ?? 1,
            'tipo_documento'   => 'dni',
            'numero_documento' => '99999999',
            'nombre'           => 'PUBLICO EN GENERAL',
            'apellidos'        => null,
            'direccion'        => null,
            'correo'           => null,
            'telefono'         => null,
        ]);
    }
}
