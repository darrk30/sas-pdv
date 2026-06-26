<?php

namespace Database\Seeders;

use App\Enums\TipoComprobante;
use App\Models\Empresa;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Database\Seeder;

class SeriesSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Serie::where('empresa_id', $empresa->id)->exists()) {
            return;
        }

        $userId = User::value('id') ?? 1;

        $series = [
            ['tipo' => TipoComprobante::Boleta,  'serie' => 'B001'],
            ['tipo' => TipoComprobante::Factura,  'serie' => 'F001'],
            ['tipo' => TipoComprobante::Ticket,   'serie' => 'TK01'],
        ];

        foreach ($series as $data) {
            Serie::create([
                'empresa_id' => $empresa->id,
                'user_id'    => $userId,
                'tipo'       => $data['tipo'],
                'serie'      => $data['serie'],
                'numero'     => 0,
                'estado'     => true,
            ]);
        }
    }
}
