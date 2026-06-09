<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'nombre',
        'estado',
        'impresora_id', // Permitimos la asignación de la impresora
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Relación: Un área de producción puede tener una impresora asignada.
     */
    public function impresora()
    {
        return $this->belongsTo(Impresora::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
