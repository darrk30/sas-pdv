<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CajaPrincipalSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Caja::where('empresa_id', $empresa->id)->where('codigo', 'CAJA-001')->exists()) {
            return;
        }

        Caja::create([
            'empresa_id' => $empresa->id,
            'nombre'     => 'Caja Principal',
            'codigo'     => 'CAJA-001',
            'estado'     => true,
        ]);
        // La vinculación usuario-caja ocurre automáticamente en EmpresaUser (pivot model)
        // cuando el usuario es adjuntado a la empresa vía empresa_user.
    }
}
