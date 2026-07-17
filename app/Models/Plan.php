<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'ciclo_facturacion',
        'maximo_usuarios',
        'maximo_locales',
        'tiene_variantes',
        'tiene_catalogo_web',
        'facturacion_electronica',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'precio'            => 'decimal:2',
            'tiene_variantes'          => 'boolean',
            'tiene_catalogo_web'       => 'boolean',
            'facturacion_electronica'  => 'boolean',
            'estado'            => EstadoGeneral::class,
        ];
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }
}
