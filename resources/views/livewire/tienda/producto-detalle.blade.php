@push('styles')
<link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('tienda/css/carrusel.css') }}">
<link rel="stylesheet" href="{{ asset('tienda/css/producto-detalle.css') }}?v=3">
@endpush

<div class="pd-page"
     x-data="pdPage(@js($productoData), @js($imagenes->values()->all()), @js($colorImagenMap))">

    {{-- ── Breadcrumb ────────────────────────────────────────────── --}}
    <nav class="pd-breadcrumb" aria-label="Ruta">
        <a href="{{ route('tienda.catalogo') }}" wire:navigate>Inicio</a>
        <span class="pd-breadcrumb__sep">›</span>
        <span class="pd-breadcrumb__current">{{ $producto->nombre }}</span>
    </nav>

    {{-- ── Sección principal: galería + info ────────────────────── --}}
    <div class="pd-main">

        {{-- ── Galería de imágenes ──────────────────────────────── --}}
        <div class="pd-galeria">

            {{-- Imagen principal --}}
            <div class="pd-galeria__principal"
                 @touchstart.passive="tocarInicio($event)"
                 @touchend.passive="tocarFin($event)">

                @if ($imagenes->isNotEmpty())
                    <img x-ref="imgPrincipal"
                         :src="imgActual"
                         alt="{{ $producto->nombre }}"
                         class="pd-galeria__img pd-galeria__img--zoom"
                         @click="abrirLightbox(indice)"
                         title="Clic para ampliar">
                @else
                    <div class="pd-galeria__sin-img">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="56" height="56">
                            <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="m21 15-5-5L5 21"/>
                        </svg>
                    </div>
                @endif

                {{-- Flechas (solo si hay más de 1 imagen y no hay override) --}}
                <template x-if="imagenes.length > 1 && !imgOverride">
                    <button type="button" class="pd-galeria__arrow pd-galeria__arrow--prev"
                            @click="anterior()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                </template>
                <template x-if="imagenes.length > 1 && !imgOverride">
                    <button type="button" class="pd-galeria__arrow pd-galeria__arrow--next"
                            @click="siguiente()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </template>

                {{-- Puntos de posición --}}
                <template x-if="imagenes.length > 1 && !imgOverride">
                    <div class="pd-galeria__dots">
                        <template x-for="(_, i) in imagenes" :key="i">
                            <button type="button"
                                    class="pd-galeria__dot"
                                    :class="{ 'pd-galeria__dot--activo': indice === i }"
                                    @click="seleccionarThumb(i)">
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Etiqueta del producto --}}
                <x-tienda.etiqueta-producto :etiqueta="$producto->etiqueta" />

            </div>

            {{-- Thumbnails --}}
            <div class="pd-galeria__thumbs" x-show="imagenes.length > 1">
                <template x-for="(img, i) in imagenes" :key="i">
                    <button type="button"
                            class="pd-galeria__thumb"
                            :class="{ 'pd-galeria__thumb--activo': !imgOverride && indice === i }"
                            @click="seleccionarThumb(i)">
                        <img :src="img" alt="" class="pd-galeria__thumb-img">
                    </button>
                </template>
            </div>

        </div>

        {{-- ── Info del producto ────────────────────────────────── --}}
        <div class="pd-info">

            {{-- Código + Nombre --}}
            <div class="pd-nombre-wrap">
                @if ($producto->codigo_interno)
                    <span class="pd-codigo">{{ $producto->codigo_interno }}</span>
                @endif
                <h1 class="pd-nombre">{{ $producto->nombre }}</h1>
            </div>

            {{-- Especificaciones: talla y color (solo producto simple, no editable) --}}
            @if (!$tieneVariantes)
                @php
                    $atribsEspeciales = $producto->atributos->filter(function ($pa) {
                        $n = strtolower(trim($pa->atributo?->nombre ?? ''));
                        return in_array($n, ['talla', 'color']);
                    });
                @endphp
                @if ($atribsEspeciales->isNotEmpty())
                    <div class="pd-atribs">
                        @foreach ($atribsEspeciales as $pa)
                            @php $esColor = strtolower(trim($pa->atributo?->nombre ?? '')) === 'color'; @endphp
                            <div class="pd-atribs__grupo">
                                <span class="pd-atribs__label">{{ ucfirst(strtolower(trim($pa->atributo?->nombre ?? ''))) }}</span>
                                <div class="pd-atribs__valores">
                                    @foreach ($pa->valores as $val)
                                        @if ($esColor)
                                            <span class="pd-atribs__color"
                                                  style="background-color:{{ $val->valor }}"
                                                  title="{{ $val->nombre ?? $val->valor }}"></span>
                                        @else
                                            <span class="pd-atribs__txt">{{ $val->nombre ?? $val->valor }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Stock --}}
            @if ($producto->control_de_stock && !$producto->venta_sin_stock)
                <p class="pd-stock"
                   :class="{
                       'pd-stock--agotado': producto.agotado || stockVisual === 0,
                       'pd-stock--bajo':   !producto.agotado && stockVisual !== null && stockVisual > 0 && stockVisual <= 5
                   }"
                   x-text="(producto.agotado || stockVisual === 0)
                       ? 'Sin stock disponible'
                       : (stockVisual !== null && stockVisual <= 5
                           ? 'Últimas ' + stockVisual + ' unidades'
                           : (stockVisual !== null ? stockVisual + ' unidades disponibles' : ''))"
                ></p>
            @endif

            {{-- Precios --}}
            <div class="pd-precios">
                @if ($tieneExtra)
                    <span class="pd-precio-desde">Desde</span>
                @endif
                <div class="pd-precio-fila">
                    <span class="pd-precio">
                        S/ <span x-text="(parseFloat(precioActual) * cantidad).toFixed(2)">{{ number_format($precioFinal, 2) }}</span>
                    </span>
                    @if ($tieneDescuento && $pct > 0)
                        <span class="pd-badge-desc">-{{ $pctFormateado }}%</span>
                    @endif
                </div>
                @if ($tieneDescuento)
                    <span class="pd-precio-original">S/ {{ number_format($producto->precio_venta, 2) }}</span>
                @endif
                <span class="pd-precio-desde"
                      x-show="cantidad > 1"
                      x-text="`c/u S/ ${precioActual}`">
                </span>
            </div>

            {{-- Descripción --}}
            @if ($producto->descripcion)
                <div class="pd-descripcion">{!! $producto->descripcion !!}</div>
                <hr class="pd-sep">
            @endif

            {{-- Selector de variantes --}}
            @if ($tieneVariantes)
                <div class="pd-variantes">
                    <template x-if="producto.atributos.length > 0">
                        <div>
                            <template x-for="attr in producto.atributos" :key="attr.id">
                                <div class="pd-var__grupo">
                                    <div class="pd-var__label">
                                        <span x-text="attr.nombre"></span>
                                        <span class="pd-var__sel"
                                              x-show="seleccion[attr.id]"
                                              x-text="'— ' + (seleccion[attr.id]?.label ?? '')"></span>
                                    </div>
                                    <div class="pd-var__valores">
                                        <template x-for="val in attr.valores" :key="val.id">
                                            <div :class="attr.tipo === 'color' ? 'modal-var__color-item' : 'modal-var__item'">

                                                {{-- Color --}}
                                                <button
                                                    x-show="attr.tipo === 'color'"
                                                    type="button"
                                                    class="modal-var__valor modal-var__valor--color"
                                                    :class="{
                                                        'modal-var__valor--sel':       seleccion[attr.id]?.id === val.id,
                                                        'modal-var__valor--bloqueado': esValorBloqueado(attr.id, val)
                                                    }"
                                                    :disabled="esValorBloqueado(attr.id, val) && seleccion[attr.id]?.id !== val.id"
                                                    :style="`background-color:${val.valor}`"
                                                    :title="esValorBloqueado(attr.id, val) ? val.label + ' (sin stock)' : val.label"
                                                    @click="seleccionar(attr.id, val)"
                                                ></button>

                                                {{-- Texto --}}
                                                <button
                                                    x-show="attr.tipo !== 'color'"
                                                    type="button"
                                                    class="modal-var__valor modal-var__valor--texto"
                                                    :class="{
                                                        'modal-var__valor--sel':       seleccion[attr.id]?.id === val.id,
                                                        'modal-var__valor--bloqueado': esValorBloqueado(attr.id, val)
                                                    }"
                                                    :disabled="esValorBloqueado(attr.id, val) && seleccion[attr.id]?.id !== val.id"
                                                    :title="val.label"
                                                    @click="seleccionar(attr.id, val)"
                                                >
                                                    <span x-text="val.label"></span>
                                                    <span x-show="val.precio_adicional > 0"
                                                          class="modal-var__extra-badge"
                                                          x-text="`+S/ ${parseFloat(val.precio_adicional).toFixed(2)}`">
                                                    </span>
                                                </button>

                                                {{-- Precio extra debajo de color --}}
                                                <span
                                                    x-show="attr.tipo === 'color' && val.precio_adicional > 0"
                                                    class="modal-var__extra-badge"
                                                    x-text="`+S/ ${parseFloat(val.precio_adicional).toFixed(2)}`">
                                                </span>

                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <p class="pd-aviso"
                               x-show="producto.atributos.length > 0 && seleccionCompleta && !varianteCoincidente">
                                Esta combinación no está disponible.
                            </p>
                            <p class="pd-aviso pd-aviso--stock"
                               x-show="varianteSinStock">
                                Esta variante no tiene stock disponible.
                            </p>
                        </div>
                    </template>
                </div>
                <hr class="pd-sep">
            @endif

            {{-- Cantidad + Botones --}}
            <div class="pd-acciones">

                <div class="pd-cant-row">
                    <span class="pd-cant-label">Cantidad</span>
                    <div class="pd-cant">
                        <button type="button" class="pd-cant-btn"
                                @click="if (cantidad > 1) cantidad--"
                                :disabled="cantidad <= 1">−</button>
                        <span class="pd-cant-num" x-text="cantidad">1</span>
                        <button type="button" class="pd-cant-btn"
                                @click="cantidad++">+</button>
                    </div>
                </div>

                <button
                    type="button"
                    class="pd-btn-carrito"
                    :disabled="!disponible"
                    @click="confirmar()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="flex-shrink:0">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span x-text="
                        producto.agotado ? 'Sin stock'
                        : (producto.variantes.length > 0 && producto.atributos.length > 0 && !seleccionCompleta) ? 'Selecciona las opciones'
                        : varianteSinStock ? 'Sin stock'
                        : !disponible ? 'No disponible'
                        : 'Agregar al carrito'
                    ">Agregar al carrito</span>
                </button>

                @auth('cliente')
                <button
                    type="button"
                    class="pd-btn-deseo"
                    :disabled="!disponible"
                    @click="confirmarDeseos()"
                >
                    <svg viewBox="0 0 24 24"
                         :fill="$store.carrito.enDeseos({{ $producto->id }}) ? 'currentColor' : 'none'"
                         stroke="currentColor" stroke-width="2" width="18" height="18" style="flex-shrink:0">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    <span x-text="
                        producto.variantes.length > 0 && producto.atributos.length > 0 && !seleccionCompleta ? 'Selecciona las opciones'
                        : varianteSinStock ? 'Sin stock'
                        : !disponible ? 'No disponible'
                        : $store.carrito.enDeseos({{ $producto->id }}) ? 'En lista de deseos' : 'Agregar a deseos'
                    ">Agregar a deseos</span>
                </button>
                @endauth

            </div>

        </div>
    </div>

    {{-- ── Especificaciones ──────────────────────────────────────── --}}
    @if ($producto->atributos->isNotEmpty())
        <div class="pd-specs">
            <h2 class="pd-specs__titulo">Especificaciones</h2>
            <div class="pd-specs__card">
                @foreach ($producto->atributos as $pa)
                    @if ($pa->atributo && $pa->valores->isNotEmpty())
                        <div class="pd-specs__fila">
                            <span class="pd-specs__key">{{ $pa->atributo->nombre }}</span>
                            <span class="pd-specs__val">
                                {{ $pa->valores->map(fn($v) => $v->nombre ?? $v->valor ?? '')->filter()->join(' · ') }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Más pedidos ──────────────────────────────────────────── --}}
    <x-tienda.carrusel-productos :empresa-id="$empresaId" titulo="Más pedidos" :excluir-id="$producto->id" />

    {{-- ── Lightbox ──────────────────────────────────────────────── --}}
    <div class="lb"
     x-show="lb.abierto"
     x-trap.noscroll="lb.abierto"
     @keydown.escape.window="lb.abierto = false"
     @click.self="lb.abierto = false"
     x-transition:enter="lb--enter"
     x-transition:leave="lb--leave"
     style="display:none">

    {{-- Cerrar --}}
    <button type="button" class="lb__cerrar" @click="lb.abierto = false" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22">
            <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
    </button>

    {{-- Imagen --}}
    <div class="lb__wrap" @click.self="lb.abierto = false">
        <img :src="imagenes[lb.indice]" alt="{{ $producto->nombre }}" class="lb__img">
    </div>

    {{-- Flechas --}}
    <template x-if="imagenes.length > 1">
        <button type="button" class="lb__flecha lb__flecha--prev"
                @click="lb.indice = (lb.indice - 1 + imagenes.length) % imagenes.length"
                aria-label="Anterior">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
    </template>
    <template x-if="imagenes.length > 1">
        <button type="button" class="lb__flecha lb__flecha--next"
                @click="lb.indice = (lb.indice + 1) % imagenes.length"
                aria-label="Siguiente">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </button>
    </template>

    {{-- Contador --}}
    <template x-if="imagenes.length > 1">
        <span class="lb__contador" x-text="(lb.indice + 1) + ' / ' + imagenes.length"></span>
    </template>
</div>
