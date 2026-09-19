<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class TareaProgramada extends Model
{
    protected $table = 'tareas_programadas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'comando',
        'hora',
        'activo',
        'ultima_ejecucion',
        'ultimo_resultado',
    ];

    protected function casts(): array
    {
        return [
            'activo'           => 'boolean',
            'ultima_ejecucion' => 'datetime',
        ];
    }

    public function ejecutar(): void
    {
        try {
            $exitCode = Artisan::call($this->comando);
            $salida   = trim(Artisan::output());
            $resultado = $exitCode === 0
                ? ($salida ?: 'Ejecutado correctamente sin salida.')
                : "Error (código {$exitCode}): " . ($salida ?: 'sin detalle.');
        } catch (\Throwable $e) {
            $resultado = 'Excepción: ' . $e->getMessage();
        }

        $this->update([
            'ultima_ejecucion' => now(),
            'ultimo_resultado' => $resultado,
        ]);
    }
}
