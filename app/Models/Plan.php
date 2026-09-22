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
        'subtitulo',
        'descripcion',
        'precio',
        'ciclo_facturacion',
        'maximo_usuarios',
        'maximo_locales',
        'tiene_variantes',
        'tiene_catalogo_web',
        'facturacion_electronica',
        'tiene_impresion_directa',
        'tiene_lista_precios',
        'tiene_cuentas',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'precio'                  => 'decimal:2',
            'tiene_variantes'         => 'boolean',
            'tiene_catalogo_web'      => 'boolean',
            'facturacion_electronica' => 'boolean',
            'tiene_impresion_directa' => 'boolean',
            'tiene_lista_precios'     => 'boolean',
            'tiene_cuentas'           => 'boolean',
            'estado'                  => EstadoGeneral::class,
        ];
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }
}
