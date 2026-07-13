@push('styles')
<link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('tienda/css/carrusel.css') }}">
<link rel="stylesheet" href="{{ asset('tienda/css/producto-detalle.css') }}?v=3">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
@endpush

<div class="pd-page"
     x-data="{
         cantidad: 1,
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
         agregar() {
             if (this.sinStock) return;
             const agregar = this.stockRestante !== null
                 ? Math.min(this.cantidad, this.stockRestante)
                 : this.cantidad;
             Alpine.store('carrito').agregar({
                 promocion_id:    {{ $promo->id }},
                 producto_id:     null,
                 variante_id:     null,
                 nombre:          @js($promo->nombre),
                 imagen:          @js($imagen),
                 precio_unitario: {{ (float) $promo->precio }},
                 cantidad:        agregar,
                 codigo_interno:  null,
                 componentes:     @js($componentes),
             });
             this.cantidad = 1;
         }
     }">

    {{-- ── Breadcrumb ────────────────────────────────────────────── --}}
    <nav class="pd-breadcrumb" aria-label="Ruta">
        <a href="{{ route('tienda.catalogo') }}" wire:navigate>Inicio</a>
        <span class="pd-breadcrumb__sep">›</span>
        <span class="pd-breadcrumb__current">{{ $promo->nombre }}</span>
    </nav>

    {{-- ── Sección principal ─────────────────────────────────────── --}}
    <div class="pd-main">

        {{-- ── Imagen / mosaico ────────────────────────────────── --}}
        <div class="pd-galeria">
            <div class="pd-galeria__principal">

                @if ($imagen)
                    <img src="{{ $imagen }}" alt="{{ $promo->nombre }}" class="pd-galeria__img">
                @elseif ($imagenes->isNotEmpty())
                    <div class="promo-det__mosaico">
                        @foreach ($imagenes->take(4) as $img)
                            <img src="{{ $img }}" alt="" class="promo-det__mosaico-img">
                        @endforeach
                        @for ($i = $imagenes->count(); $i < 4; $i++)
                            <div class="promo-det__mosaico-vacio"></div>
                        @endfor
                    </div>
                @else
                    <div class="pd-galeria__sin-img">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="56" height="56">
                            <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                            <path d="M12 8v4m0 4h.01"/>
                        </svg>
                    </div>
                @endif

                {{-- Badge COMBO --}}
                <span class="prod-etiqueta prod-etiqueta--combo">Combo</span>

                @if ($fechaFin)
                    <span class="promo-det__vence">Hasta {{ $fechaFin }}</span>
                @endif

            </div>
        </div>

        {{-- ── Info ────────────────────────────────────────────── --}}
        <div class="pd-info">

            <h1 class="pd-nombre">{{ $promo->nombre }}</h1>

            {{-- Stock / disponibilidad --}}
            @if (! $vigente)
                <p class="pd-stock pd-stock--agotado">Promoción no disponible</p>
            @else
                <p class="pd-stock"
                   :class="{
                       'pd-stock--agotado': sinStock,
                       'pd-stock--bajo':    !sinStock && stockRestante !== null && stockRestante <= 5
                   }"
                   x-text="sinStock
                       ? 'Sin stock disponible'
                       : (stockRestante === null
                           ? 'Disponible'
                           : (stockRestante <= 5
                               ? `Últimas ${stockRestante} unidades`
                               : `${stockRestante} disponibles`))">
                    {{-- Texto inicial servidor --}}
                    @if ($agotado) Sin stock disponible
                    @elseif ($stockMax !== null && $stockMax <= 5) Últimas {{ $stockMax }} unidades
                    @elseif ($stockMax !== null) {{ $stockMax }} disponibles
                    @else Disponible
                    @endif
                </p>
            @endif

            {{-- Precio --}}
            <div class="pd-precios">
                <div class="pd-precio-fila">
                    <span class="pd-precio">S/ {{ number_format((float)$promo->precio, 2) }}</span>
                </div>
                @if ($tieneCode)
                    <p class="promo-det__code-aviso">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Requiere código de promoción
                    </p>
                @endif
            </div>

            @if ($promo->descripcion)
                <div class="pd-descripcion">{{ $promo->descripcion }}</div>
                <hr class="pd-sep">
            @endif

            {{-- Productos del combo --}}
            <div class="promo-det__contenido">
                <h2 class="promo-det__titulo">Incluye</h2>
                <ul class="promo-det__lista">
                    @foreach ($detalles as $det)
                        <li class="promo-det__item {{ $det['sin_stock'] ? 'promo-det__item--agotado' : '' }}">
                            @if ($det['logo'])
                                <img src="{{ $det['logo'] }}" alt="" class="promo-det__logo">
                            @else
                                <div class="promo-det__logo-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                        <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="m21 15-5-5L5 21"/>
                                    </svg>
                                </div>
                            @endif
                            <div class="promo-det__item-info">
                                <span class="promo-det__item-nombre">{{ $det['nombre'] }}</span>
                                @if ($det['cantidad'] != 1)
                                    <span class="promo-det__item-cant">× {{ rtrim(rtrim(number_format($det['cantidad'], 3), '0'), '.') }}</span>
                                @endif
                                @if ($det['sin_stock'])
                                    <span class="promo-det__sin-stock">Sin stock</span>
                                @endif
                            </div>
                            @if (! $det['sin_stock'])
                                <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" width="16" height="16" class="promo-det__check">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" width="16" height="16" class="promo-det__check">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Selector de cantidad + Botón --}}
            <div class="pd-acciones" style="margin-top:1.25rem">
                @if ($vigente)
                    {{-- Selector de cantidad --}}
                    <div class="pd-cant-row" style="margin-bottom:0.75rem">
                        <span class="pd-cant-label">Cantidad</span>
                        <div class="pd-cant">
                            <button type="button" class="pd-cant-btn"
                                    @click="if (cantidad > 1) cantidad--"
                                    :disabled="cantidad <= 1">−</button>
                            <span class="pd-cant-num" x-text="cantidad">1</span>
                            <button type="button" class="pd-cant-btn"
                                    @click="if (stockRestante === null || cantidad < stockRestante) cantidad++"
                                    :disabled="stockRestante !== null && cantidad >= stockRestante">+</button>
                        </div>
                    </div>
                @endif

                <button
                    type="button"
                    class="pd-btn-carrito"
                    :disabled="sinStock || {{ !$vigente ? 'true' : 'false' }}"
                    @click="agregar()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="flex-shrink:0">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span x-text="sinStock ? 'Sin stock' : 'Agregar al carrito'">
                        {{ !$vigente ? 'No disponible' : 'Agregar al carrito' }}
                    </span>
                </button>
            </div>

            {{-- Validez --}}
            @if ($promo->fecha_inicio || $promo->fecha_fin)
                <p class="promo-det__validez">
                    Válido
                    @if ($promo->fecha_inicio) desde {{ $promo->fecha_inicio->format('d/m/Y') }} @endif
                    @if ($promo->fecha_fin) hasta {{ $fechaFin }} @endif
                </p>
            @endif

        </div>
    </div>

    {{-- ── Más pedidos ──────────────────────────────────────────── --}}
    <x-tienda.carrusel-productos :empresa-id="$empresaId" titulo="Más pedidos" />

</div>
