<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;
use App\Models\Produccion;

class ConfiguracionInicialSeeder extends Seeder
{
    public function run(): void
    {
        // Solo para ejecución aislada, no se usará desde Observer o DatabaseSeeder
    }

    public function runForEmpresa(Empresa $empresa): void
    {
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
    }
}