<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'activo'           => 'boolean',
            'ultima_ejecucion' => 'datetime',
        ];
    }
}
