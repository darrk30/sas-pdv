<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionCajaPago extends Model
{
    protected $table = 'sesion_caja_pagos';

    protected $fillable = [
        'sesion_caja_id',
        'metodo_pago_id',
        'importe_sistema',
        'importe_cajero',
        'diferencia',
    ];

    protected $casts = [
        'importe_sistema' => 'decimal:4',
        'importe_cajero'  => 'decimal:4',
        'diferencia'      => 'decimal:4',
    ];

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }
}
