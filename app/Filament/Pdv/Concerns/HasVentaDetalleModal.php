<?php

namespace App\Filament\Pdv\Concerns;

use App\Enums\CondicionPago;
use App\Models\Venta;
use Carbon\Carbon;
use Filament\Facades\Filament;

trait HasVentaDetalleModal
{
    public bool   $modalDetalle = false;
    public ?array $detalleVenta = null;
    public array  $detalleItems = [];
    public array  $detallePagos = [];

    public function abrirModalDetalle(int $ventaId): void
    {
        $venta = Venta::with([
            'serie',
            'detalles.variante:id,codigo,imagen',
            'detalles.producto:id,codigo_interno,logo',
            'pagos.metodoPago',
            'vendedor',
        ])
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($ventaId);

        $this->detalleVenta = [
            'comprobante'      => $venta->serie->serie . '-' . $venta->correlativo,
            'fecha'            => Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i'),
            'vendedor'         => $venta->vendedor?->name ?? '—',
            'cliente'          => $venta->cliente_nombre ?: 'Cliente general',
            'cliente_doc'      => $venta->cliente_num_doc
                                    ? "{$venta->cliente_tipo_doc} {$venta->cliente_num_doc}"
                                    : null,
            'tipo_pago'        => $venta->tipo_pago instanceof \BackedEnum
                                    ? $venta->tipo_pago->value
                                    : (string) $venta->tipo_pago,
            'estado_pago'      => $venta->estado_pago,
            'fecha_vencimiento'=> $venta->fecha_vencimiento?->format('d/m/Y'),
            'igv'              => (float) $venta->igv,
            'total'            => (float) $venta->total,
            'monto_pagado'     => (float) $venta->monto_pagado,
            'saldo_pendiente'  => (float) $venta->saldo_pendiente,
        ];

        $this->detalleItems = $venta->detalles->map(function ($d) {
            if ($d->variante_id && $d->variante?->imagen) {
                $imagen = asset('storage/' . $d->variante->imagen);
            } elseif ($d->producto_id && $d->producto?->logo) {
                $imagen = asset('storage/' . $d->producto->logo);
            } else {
                $imagen = null;
            }

            return [
                'codigo'          => $d->variante_id ? ($d->variante?->codigo ?? null) : ($d->producto?->codigo_interno ?? null),
                'imagen'          => $imagen,
                'descripcion'     => $d->descripcion,
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'descuento'       => (float) $d->descuento,
                'total'           => (float) $d->total,
            ];
        })->toArray();

        // Solo pagos de contado (Efectivo, Yape, etc.) — los de crédito
        // ya se muestran en el badge tipo_pago y en saldo_pendiente
        $this->detallePagos = $venta->pagos
            ->filter(fn ($p) => $p->metodoPago?->condicion_pago !== CondicionPago::Credito)
            ->map(fn ($p) => [
                'metodo' => $p->metodoPago?->nombre ?? '—',
                'monto'  => (float) $p->monto,
            ])->values()->toArray();

        $this->modalDetalle = true;
    }

    public function cerrarModalDetalle(): void
    {
        $this->modalDetalle = false;
        $this->detalleVenta = null;
        $this->detalleItems = [];
        $this->detallePagos = [];
    }
}
