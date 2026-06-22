<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dimension;
use App\Models\UnidadesMedida;
use App\Models\Empresa;

class DimensionSeeder extends Seeder
{
    public function run(): void {}


    public function runForEmpresa(Empresa $empresa): void
    {
        $empresaId = $empresa->id;

        // Evitar duplicados
        if (Dimension::where('empresa_id', $empresaId)->exists()) {
            return;
        }

        $definiciones = [
            'Masas' => [
                ['simbolo' => 'g', 'nombre' => 'Gramo', 'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'kg', 'nombre' => 'Kilogramo', 'factor' => 1000.0, 'ref' => 'g'],
            ],
            'Volúmenes' => [
                ['simbolo' => 'ml', 'nombre' => 'Mililitro', 'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'l', 'nombre' => 'Litro', 'factor' => 1000.0, 'ref' => 'ml'],
            ],
            'Cantidades' => [
                ['simbolo' => 'u', 'nombre' => 'Unidad', 'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'dz', 'nombre' => 'Docena', 'factor' => 12.0, 'ref' => 'u'],
            ],
        ];

        foreach ($definiciones as $nombreDimension => $items) {
            $dimension = Dimension::create([
                'nombre' => $nombreDimension,
                'empresa_id' => $empresaId,
                'estado' => true,
            ]);

            $creadas = [];

            // Primera pasada: Crear todas
            foreach ($items as $item) {
                $creadas[$item['simbolo']] = UnidadesMedida::create([
                    'empresa_id' => $empresaId,
                    'dimension_id' => $dimension->id,
                    'nombre' => $item['nombre'],
                    'simbolo' => $item['simbolo'],
                    'factor_conversion' => $item['factor'],
                    'es_base' => $item['es_base'] ?? false,
                    'estado' => true,
                ]);
            }

            // Segunda pasada: Asignar unidad_base_id (referencia)
            foreach ($items as $item) {
                if (isset($item['ref'])) {
                    $padre = $creadas[$item['ref']];
                    $hijo = $creadas[$item['simbolo']];
                    $hijo->update(['unidad_base_id' => $padre->id]);
                }
            }
        }
    }
}