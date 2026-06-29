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
         }
     }"
     @mouseenter="entrar()"
     @mouseleave="salir()">

    {{-- ── Imagen ──────────────────────────────────────────────── --}}
    <div class="tarjeta__imagen">

        <template x-if="imgActual">
            <img :src="imgActual" alt="{{ $producto->nombre }}" class="tarjeta__img" loading="lazy">
        </template>
        <template x-if="!imgActual">
            <div class="tarjeta__sin-imagen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="tarjeta__sin-imagen-svg">
                    <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <path d="m21 15-5-5L5 21"/>
                </svg>
            </div>
        </template>

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

    </div>
</div>
