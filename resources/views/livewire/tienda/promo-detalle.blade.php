<div class="pd-page"
     x-data="{
         modal: @js($modalPromo),
         abrir() {
             window.dispatchEvent(new CustomEvent('abrir-modal-variante', { detail: this.modal }));
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
            @elseif ($agotado)
                <p class="pd-stock pd-stock--agotado">Sin stock disponible</p>
            @elseif ($stockMax !== null && $stockMax <= 5)
                <p class="pd-stock pd-stock--bajo">Últimas {{ $stockMax }} unidades</p>
            @else
                <p class="pd-stock">Disponible</p>
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

            {{-- Botón --}}
            <div class="pd-acciones" style="margin-top:1.25rem">
                <button
                    type="button"
                    class="pd-btn-carrito"
                    :disabled="{{ $agotado || !$vigente ? 'true' : 'false' }}"
                    @click="abrir()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="flex-shrink:0">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    {{ $agotado || !$vigente ? 'Sin stock' : 'Agregar al carrito' }}
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
