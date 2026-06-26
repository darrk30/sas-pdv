<?php

namespace App\Models;

use App\Observers\AjusteObserver;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(AjusteObserver::class)]
class Ajuste extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'user_id',
        'codigo',
        'tipo',
        'motivo',
        'valor_total',
        'estado',
    ];

    protected $casts = [
        'valor_total' => 'float',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AjusteDetalle::class);
    }

    public function kardex(): MorphMany
    {
        return $this->morphMany(Kardex::class, 'movible');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function esEntrada(): bool
    {
        return $this->tipo === 'entrada';
    }

    public function esSalida(): bool
    {
        return $this->tipo === 'salida';
    }

    public function estaConfirmado(): bool
    {
        return $this->estado === 'confirmado';
    }

    /**
     * Recalcula y persiste el valor_total sumando todos los detalles.
     */
    public function recalcularTotal(): void
    {
        $this->update([
            'valor_total' => $this->detalles()->sum('costo_total'),
        ]);
    }
}
