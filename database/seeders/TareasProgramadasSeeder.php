<?php

namespace Database\Seeders;

use App\Models\TareaProgramada;
use Illuminate\Database\Seeder;

class TareasProgramadasSeeder extends Seeder
{
    public function run(): void
    {
        $tareas = [
            [
                'nombre'      => 'Resetear usos de promociones',
                'descripcion' => 'Resetea los usos_actuales de todas las promociones a 0',
                'comando'     => 'promociones:reset-usos',
                'hora'        => '00:00',
                'activo'      => true,
            ],
            [
                'nombre'      => 'Vencer suscripciones expiradas',
                'descripcion' => 'Marca como inactivas las suscripciones vencidas y sus empresas asociadas',
                'comando'     => 'suscripciones:vencer',
                'hora'        => '00:05',
                'activo'      => true,
            ],
        ];

        foreach ($tareas as $tarea) {
            TareaProgramada::updateOrCreate(
                ['comando' => $tarea['comando']],
                $tarea,
            );
        }

        $this->command->info('✔ ' . count($tareas) . ' tareas programadas registradas.');
    }
}
