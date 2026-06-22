<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turno extends Model
{
    protected $fillable = [
        'empresa_id', 
        'nombre', 
        'hora_inicio', 
        'hora_fin', 
        'estado'
    ];

    // Relación con la empresa
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
