@props(['promo'])

@php
    $imagen    = $promo->imagen ? Storage::url($promo->imagen) : null;
    $precio    = number_format((float) $promo->precio, 2);
    $fechaFin  = $promo->fecha_fin?->format('d/m/Y');
    $tieneCode = ! is_null($promo->codigo_promo);
    $stockMax  = $promo->stockPredictivo();
    $agotado   = ($stockMax ?? 1) <= 0;

    // Productos internos con control_de_stock para cruzar stock en tarjetas de catálogo
    $componentes = $promo->detalles->map(function ($d) {
        $productoReal = $d->variante?->producto ?? $d->producto;
        if (! $productoReal?->control_de_stock) return null;
        return [
            'producto_id' => $productoReal->id,
            'variante_id' => $d->variante_id,
            'cantidad'    => (float) $d->cantidad,
        ];
    })->filter()->values()->all();
@endphp

<div class="tarjeta tarjeta-promo"
     style="cursor:pointer"
     x-data="{
         hovering: false,
         stockInicial: @js($stockMax),
         componentes: @js($componentes),
         get stockRestante() {
             if (this.stockInicial === null) return null;
             const _ = Alpine.store('carrito').count;
             const items = Alpine.store('carrito')._leerLocal();
             const promoId = {{ $promo->id }};
             const enPromo = items
                 .filter(i => (i.promocion_id ?? null) === promoId)
                 .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
             if (!this.componentes.length) {
                 return Math.max(0, this.stockInicial - enPromo);
             }
             const posibles = this.componentes.map(c => {
                 const cant = parseFloat(c.cantidad) || 1;
                 const individual = items
                     .filter(i => !i.promocion_id
                         && i.producto_id == c.producto_id
                         && (i.variante_id ?? null) == (c.variante_id ?? null))
                     .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                 const otrasPromos = items
                     .filter(i => i.promocion_id && i.promocion_id != promoId && Array.isArray(i.componentes))
                     .reduce((s, i) => {
                         const q = parseInt(i.cantidad) || 1;
                         const co = i.componentes.find(co =>
                             co.producto_id == c.producto_id &&
                             (co.variante_id ?? null) == (c.variante_id ?? null));
                         return s + (co ? q * (parseFloat(co.cantidad) || 1) : 0);
                     }, 0);
                 const restante = this.stockInicial * cant - enPromo * cant - individual - otrasPromos;
                 return Math.max(0, Math.floor(restante / cant));
             });
             return Math.min(...posibles);
         },
         get sinStock() { return this.stockRestante !== null && this.stockRestante <= 0; },
         agregar(el) {
             if (this.sinStock) return;
             const imgEl = el.closest('.tarjeta')?.querySelector('img');
             if (imgEl) flyAlCarrito(imgEl);
             Alpine.store('carrito').agregar({
                 promocion_id:    {{ $promo->id }},
                 producto_id:     null,
                 variante_id:     null,
                 nombre:          @js($promo->nombre),
                 imagen:          @js($imagen),
                 precio_unitario: {{ (float) $promo->precio }},
                 cantidad:        1,
                 codigo_interno:  null,
                 componentes:     @js($componentes),
             });
         }
     }"
     @click="if (!$event.target.closest('button')) Livewire.navigate('/promo/{{ $promo->id }}')"
     @mouseenter="hovering = true"
     @mouseleave="hovering = false">

    {{-- ── Imagen / cabecera ───────────────────────────────────── --}}
    <div class="tarjeta__imagen tarjeta-promo__imagen">

        @if ($imagen)
            <img src="{{ $imagen }}" alt="{{ $promo->nombre }}" class="tarjeta__img" loading="lazy">
        @else
            {{-- Fallback: mosaico de productos incluidos --}}
            <div class="tarjeta-promo__mosaico">
                @foreach ($promo->detalles->take(4) as $detalle)
                    @php $logo = ($detalle->variante?->producto ?? $detalle->producto)?->logo; @endphp
                    @if ($logo)
                        <img src="{{ Storage::url($logo) }}" alt="" class="tarjeta-promo__mosaico-img">
                    @else
                        <div class="tarjeta-promo__mosaico-vacio"></div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Badge COMBO --}}
        <span class="prod-etiqueta prod-etiqueta--combo">Combo</span>

        @if ($fechaFin)
            <span class="tarjeta-promo__vence">Hasta {{ $fechaFin }}</span>
        @endif

        {{-- Botón agregar (hover) --}}
        <div class="tarjeta__acciones" x-show="hovering">
            <button
                type="button"
                class="tarjeta__btn-carrito"
                :class="{ 'tarjeta__btn-carrito--agotado': sinStock }"
                :disabled="sinStock"
                :title="sinStock ? 'Sin stock' : 'Agregar al carrito'"
                @click.prevent.stop="agregar($el)"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span x-text="sinStock ? 'Sin stock' : 'Agregar'">Agregar</span>
            </button>
        </div>

    </div>

    {{-- ── Cuerpo ───────────────────────────────────────────────── --}}
    <div class="tarjeta__cuerpo">

        <h3 class="tarjeta__nombre">{{ $promo->nombre }}</h3>

        {{-- Stock reactivo (solo si hay límite, igual que productos simples) --}}
        <p class="tarjeta__stock"
           x-show="stockRestante !== null"
           x-cloak
           :class="{
               'tarjeta__stock--agotado': sinStock,
               'tarjeta__stock--bajo':    !sinStock && stockRestante <= 5
           }"
           x-text="sinStock
               ? 'Sin stock'
               : (stockRestante <= 5
                   ? `Últimas ${stockRestante} unidades`
                   : `${stockRestante} disponibles`)"></p>

        {{-- Precio + código promo --}}
        <div class="tarjeta__precio-wrap">
            <span class="tarjeta__precio">S/ {{ $precio }}</span>
            @if ($tieneCode)
                <span class="tarjeta-promo__code" title="Código requerido">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Código requerido
                </span>
            @endif
        </div>

        {{-- Botón móvil --}}
        <button
            type="button"
            class="tarjeta__btn-carrito tarjeta__btn-carrito--movil"
            :class="{ 'tarjeta__btn-carrito--agotado': sinStock }"
            :disabled="sinStock"
            :title="sinStock ? 'Sin stock' : 'Agregar al carrito'"
            @click.prevent.stop="agregar($el)"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span x-text="sinStock ? 'Sin stock' : 'Agregar'">Agregar</span>
        </button>

    </div>
</div>
