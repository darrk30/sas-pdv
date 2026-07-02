<?php

namespace Database\Seeders;

use App\Models\Dimension;
use App\Models\Empresa;
use App\Models\UnidadesMedida;
use Illuminate\Database\Seeder;

class DimensionSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        $empresaId = $empresa->id;

        if (Dimension::where('empresa_id', $empresaId)->exists()) {
            return;
        }

        // Símbolos SUNAT — Catálogo N° 03 de Unidades de Medida
        $definiciones = [

            'Masa' => [
                ['simbolo' => 'GRM', 'nombre' => 'Gramo',             'factor' => 1.0,       'es_base' => true],
                ['simbolo' => 'KGM', 'nombre' => 'Kilogramo',         'factor' => 1000.0,    'ref' => 'GRM'],
                ['simbolo' => 'TNE', 'nombre' => 'Tonelada métrica',  'factor' => 1000000.0, 'ref' => 'GRM'],
                ['simbolo' => 'ONZ', 'nombre' => 'Onza',              'factor' => 28.3495,   'ref' => 'GRM'],
                ['simbolo' => 'LBR', 'nombre' => 'Libra',             'factor' => 453.592,   'ref' => 'GRM'],
            ],

            'Volumen' => [
                ['simbolo' => 'MLT', 'nombre' => 'Mililitro', 'factor' => 1.0,     'es_base' => true],
                ['simbolo' => 'LTR', 'nombre' => 'Litro',     'factor' => 1000.0,  'ref' => 'MLT'],
                ['simbolo' => 'GLL', 'nombre' => 'Galón',     'factor' => 3785.41, 'ref' => 'MLT'],
            ],

            'Longitud' => [
                ['simbolo' => 'CMT', 'nombre' => 'Centímetro',    'factor' => 1.0,     'es_base' => true],
                ['simbolo' => 'MTR', 'nombre' => 'Metro',          'factor' => 100.0,   'ref' => 'CMT'],
                ['simbolo' => 'MTK', 'nombre' => 'Metro cuadrado', 'factor' => 10000.0, 'ref' => 'CMT'],
                ['simbolo' => 'KMT', 'nombre' => 'Kilómetro',      'factor' => 100000.0,'ref' => 'CMT'],
            ],

            'Cantidad' => [
                ['simbolo' => 'NIU', 'nombre' => 'Unidad',  'factor' => 1.0,  'es_base' => true],
                ['simbolo' => 'DZN', 'nombre' => 'Docena',  'factor' => 12.0, 'ref' => 'NIU'],
                ['simbolo' => 'PR',  'nombre' => 'Par',     'factor' => 2.0,  'ref' => 'NIU'],
                ['simbolo' => 'CEN', 'nombre' => 'Ciento',  'factor' => 100.0,'ref' => 'NIU'],
                ['simbolo' => 'MIL', 'nombre' => 'Millar',  'factor' => 1000.0,'ref' => 'NIU'],
            ],

            'Empaque' => [
                ['simbolo' => 'BX',  'nombre' => 'Caja',         'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'PK',  'nombre' => 'Paquete',      'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'BG',  'nombre' => 'Bolsa / Saco', 'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'BT',  'nombre' => 'Botella',      'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'CAN', 'nombre' => 'Lata / Tarro', 'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'SET', 'nombre' => 'Set / Juego',  'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'TUB', 'nombre' => 'Tubo',         'factor' => 1.0, 'es_base' => true],
                ['simbolo' => 'SAC', 'nombre' => 'Saco',         'factor' => 1.0, 'es_base' => true],
            ],

            'Servicios' => [
                ['simbolo' => 'HUR', 'nombre' => 'Hora',                'factor' => 1.0,  'es_base' => true],
                ['simbolo' => 'DAY', 'nombre' => 'Día',                 'factor' => 24.0, 'ref' => 'HUR'],
                ['simbolo' => 'MON', 'nombre' => 'Mes',                 'factor' => 720.0,'ref' => 'HUR'],
                ['simbolo' => 'ZZ',  'nombre' => 'Unidad de servicio',  'factor' => 1.0,  'es_base' => true],
            ],
        ];

        foreach ($definiciones as $nombreDimension => $items) {
            $dimension = Dimension::create([
                'nombre'     => $nombreDimension,
                'empresa_id' => $empresaId,
                'estado'     => true,
            ]);

            $creadas = [];

            foreach ($items as $item) {
                $creadas[$item['simbolo']] = UnidadesMedida::create([
                    'empresa_id'         => $empresaId,
                    'dimension_id'       => $dimension->id,
                    'nombre'             => $item['nombre'],
                    'simbolo'            => $item['simbolo'],
                    'factor_conversion'  => $item['factor'],
                    'es_base'            => $item['es_base'] ?? false,
                    'estado'             => true,
                ]);
            }

            foreach ($items as $item) {
                if (isset($item['ref'], $creadas[$item['ref']])) {
                    $creadas[$item['simbolo']]->update([
                        'unidad_base_id' => $creadas[$item['ref']]->id,
                    ]);
                }
            }
        }
    }
}
