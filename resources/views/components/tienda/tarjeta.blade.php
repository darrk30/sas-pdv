@props(['producto'])

@php
    // ── Colores ──────────────────────────────────────────────────
    $colores = $producto->atributos
        ->filter(fn($pa) => strtolower($pa->atributo?->tipo ?? '') === 'color')
        ->flatMap(fn($pa) => $pa->valores)
        ->filter(fn($v) => !empty($v->valor))
        ->unique('id')
        ->values();

    $tieneExtraColor = $colores->some(fn($v) => (float)($v->pivot->precio_adicional ?? 0) > 0);

    // ── Descuento ─────────────────────────────────────────────────
    $tieneDescuento = ($producto->porcentaje_descuento ?? 0) > 0 && $producto->precio_con_descuento;
    $precioFinal    = $tieneDescuento ? (float)$producto->precio_con_descuento : (float)$producto->precio_venta;
    $pct            = (float)($producto->porcentaje_descuento ?? 0);
    $pctFormateado  = rtrim(rtrim(number_format($pct, 2), '0'), '.');

    // ── Imágenes swiper: logo + galería ───────────────────────────
    $imagenes = collect();
    if ($producto->logo) {
        $imagenes->push(Storage::url($producto->logo));
    }
    $galeria = $producto->galeriaProductos ?? collect();
    foreach ($galeria as $g) {
        if ($g->imagen_path) {
            $imagenes->push(Storage::url($g->imagen_path));
        }
    }

    // Índice en $imagenes donde empieza la galería (para el hover).
    // Solo útil si hay logo Y al menos una imagen de galería.
    $indiceGaleria = ($producto->logo && $galeria->isNotEmpty()) ? 1 : null;

    // ── Mapa color_id → imagen de variante ────────────────────────
    $colorImagenMap = [];
    foreach ($producto->variantes ?? [] as $variante) {
        if (! $variante->imagen) continue;
        foreach ($variante->valores as $pav) {
            $vid = $pav->valor_id ?? null;
            if ($vid && ! isset($colorImagenMap[$vid])) {
                $colorImagenMap[$vid] = Storage::url($variante->imagen);
            }
        }
    }
@endphp

<div class="tarjeta"
     x-data="{
         imagenes:      @js($imagenes->values()->all()),
         indiceGaleria: @js($indiceGaleria),
         colorImagenes: @js($colorImagenMap),
         indice:   0,
         colorSel: null,
         imgColor: null,
         hovering: false,
         get imgActual() {
             return this.imgColor ?? this.imagenes[this.indice] ?? null;
         },
         entrar() {
             this.hovering = true;
             if (!this.imgColor && this.indiceGaleria !== null) {
                 this.indice = this.indiceGaleria;
             }
         },
         salir() {
             this.hovering = false;
             if (!this.imgColor) this.indice = 0;
         },
         clickColor(id) {
             if (this.colorSel === id) {
                 this.colorSel = null;
                 this.imgColor = null;
             } else {
                 this.colorSel = id;
                 this.imgColor = this.colorImagenes[id] ?? null;
             }
         },
         siguiente() {
             if (this.imagenes.length > 1)
                 this.indice = (this.indice + 1) % this.imagenes.length;
         },
         anterior() {
             if (this.imagenes.length > 1)
                 this.indice = (this.indice - 1 + this.imagenes.length) % this.imagenes.length;
         },
         touchX: 0,
         tocarInicio(e) { this.touchX = e.touches[0].clientX; },
         tocarFin(e) {
             if (this.imgColor) return;
             const dx = e.changedTouches[0].clientX - this.touchX;
             if (Math.abs(dx) > 40) { if (dx < 0) this.siguiente(); else this.anterior(); }
         }
     }"
     @mouseenter="entrar()"
     @mouseleave="salir()">

    {{-- ── Imagen ──────────────────────────────────────────────── --}}
    <div class="tarjeta__imagen"
         @touchstart.passive="tocarInicio($event)"
         @touchend.passive="tocarFin($event)">

        @if ($imagenes->isNotEmpty())
            <img :src="imgActual" alt="{{ $producto->nombre }}" class="tarjeta__img" loading="lazy">
        @else
            <div class="tarjeta__sin-imagen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="tarjeta__sin-imagen-svg">
                    <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <path d="m21 15-5-5L5 21"/>
                </svg>
            </div>
        @endif

        {{-- Flechas swiper --}}
        <div class="tarjeta__nav" x-show="hovering && imagenes.length > 1 && !imgColor">
            <button type="button" class="tarjeta__nav-btn" @click.prevent.stop="anterior()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            <button type="button" class="tarjeta__nav-btn" @click.prevent.stop="siguiente()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </button>
        </div>

        {{-- Puntos --}}
        <div class="tarjeta__dots" x-show="hovering && imagenes.length > 1 && !imgColor">
            <template x-for="(_, i) in imagenes" :key="i">
                <button
                    type="button"
                    class="tarjeta__dot"
                    :class="{ 'tarjeta__dot--activo': i === indice }"
                    @click.prevent.stop="indice = i"
                ></button>
            </template>
        </div>

        {{-- Ribbon de etiqueta (esquina superior izquierda, inclinado) --}}
        @if ($producto->etiqueta)
            <div class="tarjeta__ribbon-wrap">
                <span class="tarjeta__ribbon tarjeta__ribbon--{{ $producto->etiqueta->value }}">
                    {{ $producto->etiqueta->getLabel() }}
                </span>
            </div>
        @endif

        {{-- Badge de descuento (esquina superior derecha) --}}
        @if ($tieneDescuento && $pct > 0)
            <span class="tarjeta__badge-oferta">-{{ $pctFormateado }}%</span>
        @endif

        {{-- ── Botones de acción ──────────────────────────────── --}}
        <div class="tarjeta__acciones" x-show="hovering">

            <button
                type="button"
                class="tarjeta__btn-carrito"
                title="Agregar al carrito"
                @click.prevent.stop="
                    flyAlCarrito($el.closest('.tarjeta').querySelector('.tarjeta__img'));
                    $store.carrito.agregar({
                        producto_id:     {{ $producto->id }},
                        variante_id:     null,
                        nombre:          @js($producto->nombre),
                        imagen:          @js($imagenes->first()),
                        precio_unitario: {{ $precioFinal }},
                    })
                "
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span>Agregar</span>
            </button>

            @auth('cliente')
            <button
                type="button"
                class="tarjeta__btn-deseo"
                title="Agregar a deseos"
                :class="{ 'tarjeta__btn-deseo--activo': $store.carrito.enDeseos({{ $producto->id }}) }"
                @click.prevent.stop="$store.carrito.toggleDeseo({{ $producto->id }})"
            >
                <svg viewBox="0 0 24 24"
                     :fill="$store.carrito.enDeseos({{ $producto->id }}) ? 'currentColor' : 'none'"
                     stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>
            @endauth

        </div>
    </div>

    {{-- ── Info ────────────────────────────────────────────────── --}}
    <div class="tarjeta__cuerpo">

        <h3 class="tarjeta__nombre">{{ $producto->nombre }}</h3>

        @if ($colores->isNotEmpty())
            <div class="tarjeta__colores">
                @foreach ($colores->take(7) as $color)
                    <span
                        class="tarjeta__color"
                        :class="{ 'tarjeta__color--sel': colorSel === {{ $color->id }} }"
                        style="background-color: {{ $color->valor }}"
                        title="{{ $color->nombre }}"
                        @click.prevent.stop="clickColor({{ $color->id }})"
                    ></span>
                @endforeach
                @if ($colores->count() > 7)
                    <span class="tarjeta__color-mas">+{{ $colores->count() - 7 }}</span>
                @endif
            </div>
        @endif

        <div class="tarjeta__precio-wrap">
            <span class="tarjeta__precio">
                @if ($tieneExtraColor)desde @endif
                S/ {{ number_format($precioFinal, 2) }}
            </span>
            @if ($tieneDescuento)
                <span class="tarjeta__precio-original">S/ {{ number_format($producto->precio_venta, 2) }}</span>
            @endif
        </div>

        {{-- Botón móvil: siempre visible debajo del precio --}}
        <button
            type="button"
            class="tarjeta__btn-carrito tarjeta__btn-carrito--movil"
            title="Agregar al carrito"
            @click.prevent.stop="
                flyAlCarrito($el.closest('.tarjeta').querySelector('.tarjeta__img'));
                $store.carrito.agregar({
                    producto_id:     {{ $producto->id }},
                    variante_id:     null,
                    nombre:          @js($producto->nombre),
                    imagen:          @js($imagenes->first()),
                    precio_unitario: {{ $precioFinal }},
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
