<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use App\Observers\SuscripcionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([SuscripcionObserver::class])]
class Suscripcion extends Model
{
    use HasFactory;
    protected $table = 'suscripciones';

    protected $fillable = [
        'cliente_id',
        'plan_id',
        'precio_pagado',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'es_prueba_gratuita',
        'ciclo',
    ];

    protected function casts(): array
    {
        return [
            'precio_pagado'      => 'decimal:2',
            'fecha_inicio'       => 'date',
            'fecha_fin'          => 'date',
            'estado'             => EstadoGeneral::class,
            'es_prueba_gratuita' => 'boolean',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pagos()
    {
        return $this->hasMany(PagosCliente::class);
    }
}
