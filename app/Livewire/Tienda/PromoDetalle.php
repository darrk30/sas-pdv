<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoPromocion;
use App\Models\Promocion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::tienda')]
class PromoDetalle extends Component
{
    public int $empresaId = 0;
    public Promocion $promo;

    public function mount(int $id): void
    {
        $this->empresaId = app('tienda.empresa')->id;

        $this->promo = Promocion::where('empresa_id', $this->empresaId)
            ->where('id', $id)
            ->where('estado', EstadoPromocion::Activo)
            ->with([
                'detalles.producto.galeriaProductos',
                'detalles.producto.inventario',
                'detalles.variante.producto.inventario',
                'detalles.variante.inventario',
            ])
            ->firstOrFail();
    }

    public function render()
    {
        $promo     = $this->promo;
        $imagen    = $promo->imagen ? Storage::url($promo->imagen) : null;
        $tieneCode = ! is_null($promo->codigo_promo);
        $fechaFin  = $promo->fecha_fin?->format('d/m/Y');
        $diaSemana = (string) Carbon::now()->dayOfWeekIso;

        $vigente = $promo->estaVigente() &&
            (empty($promo->dias_semana) || in_array($diaSemana, array_map('strval', $promo->dias_semana)));

        $stockMax = $promo->stockPredictivo();
        $agotado  = $vigente && $stockMax !== null && $stockMax <= 0;

        // Datos de productos del combo para la vista y el modal
        $detalles = $promo->detalles->map(function ($d) {
            if ($d->variante_id) {
                $prod     = $d->variante?->producto;
                $nombre   = ($prod?->nombre ?? '—') . ' — ' . ($d->variante?->valores->map(fn($pav) => $pav->valor?->nombre ?? '')->filter()->implode(' / ') ?: '');
                $logo     = $prod?->logo ? Storage::url($prod->logo) : null;
                $stock    = (float) ($d->variante?->inventario?->stock_reserva ?? 0);
                $ctrlStock = (bool) ($prod?->control_de_stock ?? false);
                $sinStock  = $ctrlStock && !($prod?->venta_sin_stock ?? false) && $stock <= 0;
            } else {
                $prod     = $d->producto;
                $nombre   = $prod?->nombre ?? '—';
                $logo     = $prod?->logo ? Storage::url($prod->logo) : null;
                $stock    = (float) ($prod?->inventario?->stock_reserva ?? 0);
                $ctrlStock = (bool) ($prod?->control_de_stock ?? false);
                $sinStock  = $ctrlStock && !($prod?->venta_sin_stock ?? false) && $stock <= 0;
            }

            return [
                'nombre'    => $nombre,
                'cantidad'  => (float) $d->cantidad,
                'logo'      => $logo,
                'sin_stock' => $sinStock,
            ];
        });

        // Datos para el modal (mismo formato que tarjeta-promo)
        $modalPromo = [
            'id'           => null,
            'promocion_id' => $promo->id,
            'nombre'       => $promo->nombre,
            'imagen'       => $imagen,
            'precioBase'   => (float) $promo->precio,
            'atributos'    => [],
            'variantes'    => [],
        ];

        // Imágenes: propia + logos de los productos del combo
        $imagenes = collect();
        if ($imagen) {
            $imagenes->push($imagen);
        } else {
            foreach ($promo->detalles->take(4) as $d) {
                $logo = ($d->variante?->producto ?? $d->producto)?->logo;
                if ($logo) $imagenes->push(Storage::url($logo));
            }
        }

        return view('livewire.tienda.promo-detalle', [
            'promo'      => $promo,
            'imagen'     => $imagen,
            'imagenes'   => $imagenes,
            'tieneCode'  => $tieneCode,
            'fechaFin'   => $fechaFin,
            'vigente'    => $vigente,
            'agotado'    => $agotado,
            'stockMax'   => $stockMax,
            'detalles'   => $detalles,
            'modalPromo' => $modalPromo,
        ])->title($promo->nombre);
    }
}
