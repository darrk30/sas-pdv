<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'estado'
    ];

    protected function casts(): array
    {
        return [
            'precio_pagado' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoGeneral::class,
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
