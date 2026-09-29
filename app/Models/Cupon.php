<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cupon extends Model
{
    use BelongsToEmpresa;

    protected $table = 'cupones';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'descripcion',
        'tipo_descuento',
        'valor',
        'monto_minimo',
        'cantidad_minima',
        'stock',
        'usos',
        'usos_por_cliente',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'valor'            => 'decimal:2',
        'monto_minimo'     => 'decimal:2',
        'cantidad_minima'  => 'integer',
        'stock'            => 'integer',
        'usos'             => 'integer',
        'usos_por_cliente' => 'integer',
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
        'activo'           => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usosPorClientes(): HasMany
    {
        return $this->hasMany(CuponUso::class);
    }

    public function contarUsosCliente(int $clienteId): int
    {
        return $this->usosPorClientes()->where('cliente_id', $clienteId)->count();
    }

    public function estaVigente(): bool
    {
        $hoy = now()->toDateString();

        if ($this->fecha_inicio && $hoy < $this->fecha_inicio->toDateString()) return false;
        if ($this->fecha_fin   && $hoy > $this->fecha_fin->toDateString())   return false;
        if ($this->stock !== null && $this->usos >= $this->stock)            return false;

        return $this->activo;
    }

    /**
     * @param float $subtotal   Monto total del carrito
     * @param int   $cantItems  Total de unidades en el carrito
     */
    public function calcularDescuento(float $subtotal, int $cantItems = 0): float
    {
        if ($this->monto_minimo    && $subtotal  < (float) $this->monto_minimo)  return 0;
        if ($this->cantidad_minima && $cantItems < (int)   $this->cantidad_minima) return 0;

        return $this->tipo_descuento === 'porcentaje'
            ? round($subtotal * ($this->valor / 100), 2)
            : min((float) $this->valor, $subtotal);
    }

    public function cumpleCondiciones(float $subtotal, int $cantItems = 0): bool
    {
        return $this->calcularDescuento($subtotal, $cantItems) > 0;
    }
}
