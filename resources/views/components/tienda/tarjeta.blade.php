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

    // "desde" si cualquier atributo (no solo color) tiene precio adicional
    $tieneExtra = $producto->atributos
        ->flatMap(fn($pa) => $pa->valores)
        ->some(fn($v) => (float)($v->pivot->precio_adicional ?? 0) > 0);

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

    // ── Mapa valor_id → imagen (desde ProductoAtributoValor.imagen) ──
    $colorImagenMap = [];
    foreach ($producto->atributos as $pa) {
        foreach ($pa->valores as $valor) {
            $img = $valor->pivot->imagen ?? null;
            if ($img && ! isset($colorImagenMap[$valor->id])) {
                $colorImagenMap[$valor->id] = Storage::url($img);
            }
        }
    }

    // ── Datos para modal de variantes ─────────────────────────────
    $variantesActivas = $producto->variantes ?? collect();
    $tieneVariantes   = $variantesActivas->isNotEmpty();

    // ── Disponibilidad / stock ─────────────────────────────────────
    $productoAgotado = false;
    $stockDisp = null;
    if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
        $stockDisp = $tieneVariantes
            ? $variantesActivas->sum(fn($v) => max(0, (float)($v->inventario?->stock_reserva ?? 0)))
            : max(0, (float)($producto->inventario?->stock_reserva ?? 0));
        $productoAgotado = $tieneVariantes
            ? $variantesActivas->every(fn($v) => (float)($v->inventario?->stock_reserva ?? 0) <= 0)
            : $stockDisp <= 0;
    }

    $atributosModal = $producto->atributos->map(fn($pa) => [
        'id'     => $pa->atributo_id,
        'nombre' => $pa->atributo?->nombre ?? '',
        'tipo'   => strtolower($pa->atributo?->tipo ?? ''),
        'valores' => $pa->valores->map(fn($v) => [
            'id'               => $v->id,
            'label'            => $v->nombre ?? $v->valor ?? '',
            'valor'            => $v->valor ?? '',
            'precio_adicional' => (float)($v->pivot->precio_adicional ?? 0),
            'imagen'           => ($v->pivot->imagen ?? null) ? Storage::url($v->pivot->imagen) : null,
        ])->values()->all(),
    ])->filter(fn($a) => $a['id'] && !empty($a['valores']))->values()->all();

    $variantesModal = $variantesActivas->map(fn($var) => [
        'id'            => $var->id,
        'codigo'        => $var->codigo ?? null,
        'imagen'        => $var->imagen ? Storage::url($var->imagen) : null,
        'valores_ids'   => $var->valores->pluck('valor_id')->sort()->values()->all(),
        'sin_stock'     => $producto->control_de_stock && ! $producto->venta_sin_stock
                           && (float)($var->inventario?->stock_reserva ?? 0) <= 0,
        'stock_reserva' => $producto->control_de_stock && ! $producto->venta_sin_stock
                           ? (float)($var->inventario?->stock_reserva ?? 0)
                           : null,
    ])->values()->all();
@endphp

<div {{ $attributes->merge(['class' => 'tarjeta']) }}
     style="cursor:pointer"
     @click="if (!$event.target.closest('button') && !$event.target.closest('.tarjeta__color')) Livewire.navigate('/producto/{{ $producto->id }}')"
     x-data="{
         imagenes:      @js($imagenes->values()->all()),
         indiceGaleria: @js($indiceGaleria),
         colorImagenes: @js($colorImagenMap),
         indice:   0,
         colorSel: null,
         imgColor: null,
         hovering: false,
         tieneVariantes: @js($tieneVariantes),
         agotado: @js($productoAgotado),
         stockInicial: @js($stockDisp),
         get stockRestante() {
             if (this.stockInicial === null) return null;
             const _ = Alpine.store('carrito').count;
             const items = Alpine.store('carrito')._leerLocal();
             const pid = this.modalProducto.id;
             const esVariante = this.tieneVariantes;
             const enCarrito = items
                 .filter(i => i.producto_id == pid && !i.promocion_id)
                 .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
             const enPromos = items
                 .filter(i => i.promocion_id && Array.isArray(i.componentes))
                 .reduce((s, i) => {
                     const promoQty = parseInt(i.cantidad) || 1;
                     const consumed = i.componentes
                         .filter(c => c.producto_id == pid && (esVariante || !c.variante_id))
                         .reduce((cs, c) => cs + (parseFloat(c.cantidad) || 1), 0);
                     return s + promoQty * consumed;
                 }, 0);
             return Math.max(0, this.stockInicial - enCarrito - enPromos);
         },
         modalProducto: @js([
             'id'             => $producto->id,
             'nombre'         => $producto->nombre,
             'imagen'         => $imagenes->first(),
             'precioBase'     => $precioFinal,
             'codigo_interno' => $producto->codigo_interno,
             'atributos'      => $atributosModal,
             'variantes'      => $variantesModal,
             'stock_reserva'  => !$tieneVariantes && $producto->control_de_stock && !$producto->venta_sin_stock
                                 ? (float)($producto->inventario?->stock_reserva ?? 0)
                                 : null,
         ]),
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
         },
         agregarOModal(el) {
             if (this.tieneVariantes) {
                 window.dispatchEvent(new CustomEvent('abrir-modal-variante', { detail: this.modalProducto }));
             } else {
                 const sr = this.modalProducto.stock_reserva;
                 if (sr !== null && sr !== undefined) {
                     const items = Alpine.store('carrito')._leerLocal();
                     const pid = this.modalProducto.id;
                     const enCarrito = items
                         .filter(i => i.producto_id == pid && !i.variante_id && !i.promocion_id)
                         .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                     const enPromos = items
                         .filter(i => i.promocion_id && Array.isArray(i.componentes))
                         .reduce((s, i) => {
                             const promoQty = parseInt(i.cantidad) || 1;
                             const comp = i.componentes.find(c => c.producto_id == pid && !c.variante_id);
                             return s + (comp ? promoQty * (parseFloat(comp.cantidad) || 1) : 0);
                         }, 0);
                     if (enCarrito + enPromos >= sr) {
                         window.dispatchEvent(new CustomEvent('toast', {
                             detail: { mensaje: 'No hay más unidades disponibles.', tipo: 'error' }
                         }));
                         return;
                     }
                 }
                 flyAlCarrito(el);
                 Alpine.store('carrito').agregar({
                     promocion_id:    null,
                     producto_id:     this.modalProducto.id,
                     variante_id:     null,
                     variante_nombre: null,
                     nombre:          this.modalProducto.nombre,
                     imagen:          this.modalProducto.imagen,
                     precio_unitario: this.modalProducto.precioBase,
                     cantidad:        1,
                     codigo_interno:  this.modalProducto.codigo_interno ?? null,
                 });
             }
         },
         agregarODeseo(el) {
             window.dispatchEvent(new CustomEvent('abrir-modal-variante', { detail: this.modalProducto }));
         }
     }"
     @mouseenter="entrar()"
     @mouseleave="salir()">

    {{-- ── Imagen ──────────────────────────────────────────────── --}}
    <div class="tarjeta__imagen"
         @touchstart.passive="tocarInicio($event)"
         @touchend.passive="tocarFin($event)">

        @if ($imagenes->isNotEmpty())
            <img :src="imgActual" alt="{{ $producto->nombre }}" class="tarjeta__img" loading="lazy"
                 :class="{ 'tarjeta__img--agotado': agotado || stockRestante === 0 }">
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

        {{-- Etiqueta del producto --}}
        <x-tienda.etiqueta-producto :etiqueta="$producto->etiqueta" />

        {{-- ── Botones de acción ──────────────────────────────── --}}
        <div class="tarjeta__acciones" x-show="hovering">

            <button
                type="button"
                class="tarjeta__btn-carrito"
                :class="{ 'tarjeta__btn-carrito--agotado': agotado || stockRestante === 0 }"
                :disabled="agotado || stockRestante === 0"
                :title="(agotado || stockRestante === 0) ? 'Sin stock' : (tieneVariantes ? 'Seleccionar opciones' : 'Agregar al carrito')"
                @click.prevent.stop="if (!agotado && stockRestante !== 0) agregarOModal($el)"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span x-text="(agotado || stockRestante === 0) ? 'Sin stock' : (tieneVariantes ? 'Ver opciones' : 'Agregar')"></span>
            </button>

            @auth('cliente')
            <button
                type="button"
                class="tarjeta__btn-deseo"
                :disabled="agotado"
                title="Agregar a lista de deseos"
                :class="{ 'tarjeta__btn-deseo--activo': !agotado && $store.carrito.enDeseos({{ $producto->id }}) }"
                @click.prevent.stop="if (!agotado) agregarODeseo($el)"
            >
                <svg viewBox="0 0 24 24"
                     :fill="!agotado && $store.carrito.enDeseos({{ $producto->id }}) ? 'currentColor' : 'none'"
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

        {{-- Stock disponible --}}
        @if ($producto->control_de_stock && !$producto->venta_sin_stock)
            <p class="tarjeta__stock"
               :class="{
                   'tarjeta__stock--agotado': agotado || stockRestante === 0,
                   'tarjeta__stock--bajo':   !agotado && stockRestante !== null && stockRestante > 0 && stockRestante <= 5
               }"
               x-text="(agotado || stockRestante === 0)
                   ? 'Sin stock'
                   : (stockRestante <= 5
                       ? 'Últimas ' + stockRestante + ' unidades'
                       : stockRestante + ' disponibles')"
            ></p>
        @endif

        {{-- Precios --}}
        <div class="tarjeta__precio-wrap">
            <div class="tarjeta__precio-fila">
                <span class="tarjeta__precio">
                    @if ($tieneExtra)desde @endif
                    S/ {{ number_format($precioFinal, 2) }}
                </span>
                @if ($tieneDescuento && $pct > 0)
                    <span class="tarjeta__badge-oferta">-{{ $pctFormateado }}%</span>
                @endif
            </div>
            @if ($tieneDescuento)
                <span class="tarjeta__precio-original">S/ {{ number_format($producto->precio_venta, 2) }}</span>
            @endif
        </div>

        {{-- Botón móvil: siempre visible debajo del precio --}}
        <button
            type="button"
            class="tarjeta__btn-carrito tarjeta__btn-carrito--movil"
            :class="{ 'tarjeta__btn-carrito--agotado': agotado || stockRestante === 0 }"
            :disabled="agotado || stockRestante === 0"
            :title="(agotado || stockRestante === 0) ? 'Sin stock' : (tieneVariantes ? 'Seleccionar opciones' : 'Agregar al carrito')"
            @click.prevent.stop="if (!agotado && stockRestante !== 0) agregarOModal($el)"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span x-text="(agotado || stockRestante === 0) ? 'Sin stock' : (tieneVariantes ? 'Ver opciones' : 'Agregar')"></span>
        </button>

    </div>
</div>
