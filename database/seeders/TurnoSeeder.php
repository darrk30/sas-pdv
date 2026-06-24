<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Turno;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Turno::where('empresa_id', $empresa->id)->exists()) {
            return;
        }

        $turnos = [
            ['nombre' => 'MAÑANA', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00'],
            ['nombre' => 'TARDE',  'hora_inicio' => '14:00:00', 'hora_fin' => '22:00:00'],
            ['nombre' => 'NOCHE',  'hora_inicio' => '22:00:00', 'hora_fin' => '06:00:00'],
        ];

        foreach ($turnos as $turno) {
            Turno::create([
                'empresa_id'  => $empresa->id,
                'nombre'      => $turno['nombre'],
                'hora_inicio' => $turno['hora_inicio'],
                'hora_fin'    => $turno['hora_fin'],
                'estado'      => true,
            ]);
        }
    }
}
