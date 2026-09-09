<?php

namespace App\Models;

use App\Enums\EstadoMesa;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'piso_id',
        'nombre',
        'capacidad',
        'orden',
        'estado',
        'estado_ocupacion',
    ];

    protected $casts = [
        'estado'           => 'boolean',
        'estado_ocupacion' => EstadoMesa::class,
    ];

    public function piso(): BelongsTo
    {
        return $this->belongsTo(Piso::class);
    }

    public function ordenActiva(): HasOne
    {
        return $this->hasOne(Orden::class)
            ->where('tipo_origen', 'restaurante')
            ->whereNotIn('estado', ['pago_confirmado', 'cancelada'])
            ->latest();
    }

    public function estaLibre(): bool
    {
        return $this->estado_ocupacion === EstadoMesa::Libre;
    }

    public function marcarOcupada(): void
    {
        $this->update(['estado_ocupacion' => EstadoMesa::Ocupada]);
    }

    public function marcarLibre(): void
    {
        $this->update(['estado_ocupacion' => EstadoMesa::Libre]);
    }

    public function marcarPagando(): void
    {
        $this->update(['estado_ocupacion' => EstadoMesa::Pagando]);
    }
}
