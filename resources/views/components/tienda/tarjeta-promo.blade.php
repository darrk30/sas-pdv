@props(['promo'])

@php
    $imagen    = $promo->imagen ? Storage::url($promo->imagen) : null;
    $precio    = number_format((float) $promo->precio, 2);
    $fechaFin  = $promo->fecha_fin?->format('d/m/Y');
    $tieneCode = ! is_null($promo->codigo_promo);
@endphp

<div class="tarjeta tarjeta-promo"
     x-data="{ hovering: false }"
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
                    @php $logo = $detalle->producto?->logo ? Storage::url($detalle->producto->logo) : null; @endphp
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $detalle->producto->nombre }}" class="tarjeta-promo__mosaico-img">
                    @else
                        <div class="tarjeta-promo__mosaico-vacio"></div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Ribbon COMBO --}}
        <div class="tarjeta__ribbon-wrap">
            <span class="tarjeta__ribbon tarjeta-promo__ribbon">COMBO</span>
        </div>

        @if ($fechaFin)
            <span class="tarjeta-promo__vence">Hasta {{ $fechaFin }}</span>
        @endif

        {{-- Botón agregar (hover) --}}
        <div class="tarjeta__acciones" x-show="hovering">
            <button
                type="button"
                class="tarjeta__btn-carrito"
                title="Agregar al carrito"
                @click.prevent.stop="
                    flyAlCarrito($el.closest('.tarjeta').querySelector('img'));
                    $store.carrito.agregar({
                        promocion_id:    {{ $promo->id }},
                        producto_id:     null,
                        variante_id:     null,
                        nombre:          @js($promo->nombre),
                        imagen:          @js($imagen),
                        precio_unitario: {{ (float) $promo->precio }},
                    })
                "
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

        {{-- Productos incluidos --}}
        <ul class="tarjeta-promo__items">
            @foreach ($promo->detalles as $detalle)
                <li class="tarjeta-promo__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         width="11" height="11" class="tarjeta-promo__check">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                    <span>
                        {{ $detalle->producto?->nombre ?? $detalle->variante?->producto?->nombre ?? '—' }}
                        @if ((float)$detalle->cantidad != 1)
                            × {{ rtrim(rtrim(number_format((float)$detalle->cantidad, 3), '0'), '.') }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>

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

        {{-- Botón móvil: siempre visible debajo del precio --}}
        <button
            type="button"
            class="tarjeta__btn-carrito tarjeta__btn-carrito--movil"
            title="Agregar al carrito"
            @click.prevent.stop="
                flyAlCarrito($el.closest('.tarjeta').querySelector('img'));
                $store.carrito.agregar({
                    promocion_id:    {{ $promo->id }},
                    producto_id:     null,
                    variante_id:     null,
                    nombre:          @js($promo->nombre),
                    imagen:          @js($imagen),
                    precio_unitario: {{ (float) $promo->precio }},
                })
            "
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span>Agregar</span>
        </button>

    </div>
</div>
