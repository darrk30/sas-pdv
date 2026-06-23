<?php

namespace App\Models;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Observers\CompraObserver;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CompraObserver::class)]
class Compra extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'user_id',
        'proveedor_id',
        'codigo',
        'serie',
        'correlativo',
        'tipo_comprobante',
        'fecha_compra',
        'estado',
        'estado_despacho',
        'estado_pago',
        'observaciones',
        'costo_envio',
        'descuento',
        'subtotal',
        'igv',
        'total',
        'archivo_compra',
    ];

    protected $casts = [
        'tipo_comprobante' => TipoComprobante::class,
        'estado_despacho'  => EstadoDespacho::class,
        'estado_pago'      => EstadoPago::class,
        'fecha_compra'     => 'date',
        'costo_envio'      => 'float',
        'descuento'        => 'float',
        'subtotal'         => 'float',
        'igv'              => 'float',
        'total'            => 'float',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(CompraPago::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'confirmado';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulado';
    }

    public function estaRecibida(): bool
    {
        return $this->estado_despacho === EstadoDespacho::Recibido;
    }

    public function estaPagada(): bool
    {
        return $this->estado_pago === EstadoPago::Pagado;
    }

    public function listaParaConfirmar(): bool
    {
        return $this->esBorrador()
            && $this->estaRecibida()
            && $this->estaPagada();
    }

    public function recalcularTotales(): void
    {
        $subtotal    = $this->detalles()->sum('costo_total');
        $costoEnvio  = (float) $this->costo_envio;
        $descuento   = (float) $this->descuento;
        $base        = $subtotal + $costoEnvio - $descuento;
        $igv         = round($base * 0.18, 4);

        $this->update([
            'subtotal' => round($subtotal, 4),
            'igv'      => $igv,
            'total'    => round($base + $igv, 4),
        ]);
    }
}
