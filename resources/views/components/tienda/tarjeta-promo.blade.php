@props(['promo'])

@php
    $imagen    = $promo->imagen ? Storage::url($promo->imagen) : null;
    $precio    = number_format((float) $promo->precio, 2);
    $fechaFin  = $promo->fecha_fin?->format('d/m/Y');
    $tieneCode = ! is_null($promo->codigo_promo);
    $agotado   = ($promo->stockPredictivo() ?? 1) <= 0;
@endphp

<div class="tarjeta tarjeta-promo"
     style="cursor:pointer"
     x-data="{
         hovering: false,
         modalPromo: @js([
             'id'           => null,
             'promocion_id' => $promo->id,
             'nombre'       => $promo->nombre,
             'imagen'       => $imagen,
             'precioBase'   => (float) $promo->precio,
             'atributos'    => [],
             'variantes'    => [],
         ]),
         abrirModal() {
             window.dispatchEvent(new CustomEvent('abrir-modal-variante', { detail: this.modalPromo }));
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
                class="tarjeta__btn-carrito {{ $agotado ? 'tarjeta__btn-carrito--agotado' : '' }}"
                :disabled="{{ $agotado ? 'true' : 'false' }}"
                title="{{ $agotado ? 'Sin stock' : 'Agregar al carrito' }}"
                @click.prevent.stop="{{ $agotado ? '' : 'abrirModal()' }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span>Agregar</span>
            </button>
        </div>

    </div>

    {{-- ── Cuerpo ───────────────────────────────────────────────── --}}
    <div class="tarjeta__cuerpo">

        <h3 class="tarjeta__nombre">{{ $promo->nombre }}</h3>

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

        {{-- Stock --}}
        @if ($agotado)
            <p class="tarjeta__stock tarjeta__stock--agotado">Sin stock</p>
        @endif

        {{-- Botón móvil: siempre visible debajo del precio --}}
        <button
            type="button"
            class="tarjeta__btn-carrito tarjeta__btn-carrito--movil {{ $agotado ? 'tarjeta__btn-carrito--agotado' : '' }}"
            :disabled="{{ $agotado ? 'true' : 'false' }}"
            title="{{ $agotado ? 'Sin stock' : 'Agregar al carrito' }}"
            @click.prevent.stop="{{ $agotado ? '' : 'abrirModal()' }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span>Agregar</span>
        </button>

    </div>
</div>
