<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SesionCaja;

class VentaPago extends Model
{
    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'sesion_caja_id',
        'metodo_pago_id',
        'tipo',
        'monto',
        'referencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }
}
