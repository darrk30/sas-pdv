<div class="{{ $esGuest ? 'ld-guest-page' : 'ld' }}" x-data="listaDeseos(@js($datosParaAlpine))">

    {{-- Estado guest: no está logueado --}}
    @if ($esGuest)
        <div class="ld__guest">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 width="56" height="56" style="color:#fca5a5">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <p class="ld__guest-txt">Para ver tu lista de deseos, inicia sesión.</p>
            <div class="ld__guest-links">
                <a href="/login" wire:navigate class="ld__guest-btn">Iniciar sesión</a>
                <a href="/registro" wire:navigate class="ld__guest-link">Crear cuenta</a>
            </div>
        </div>

    {{-- Cabecera (solo usuarios auth) --}}
    @else

    <div class="ld__header">
        <h1 class="ld__titulo">
            <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"
                 width="18" height="18" style="color:#ef4444;flex-shrink:0">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Lista de deseos
        </h1>
        @if ($items->isNotEmpty())
            <span class="ld__count">
                {{ $items->count() }} {{ $items->count() === 1 ? 'producto' : 'productos' }}
            </span>
        @endif
    </div>

    {{-- Estado vacío --}}
    @if ($items->isEmpty())
        <div class="ld__vacio">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 width="52" height="52" style="margin:0 auto 1rem;display:block;opacity:.3;color:#9ca3af">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <p class="ld__vacio-txt">Aún no tienes productos en tu lista de deseos.</p>
            <a href="/" wire:navigate class="ld__vacio-link">Ver catálogo</a>
        </div>

    {{-- Lista de ítems --}}
    @else
        <div class="ld__lista">
            @foreach ($items as $item)
                @php
                    $producto    = $item->producto;
                    $variante    = $item->variante;
                    $disponible  = $disponibilidad[$item->id] ?? false;

                    $imagen = null;
                    if ($variante?->imagen) {
                        $imagen = Storage::url($variante->imagen);
                    } elseif ($producto?->logo) {
                        $imagen = Storage::url($producto->logo);
                    } elseif ($producto?->galeriaProductos?->isNotEmpty()) {
                        $imagen = Storage::url($producto->galeriaProductos->first()->imagen_path);
                    }

                    $tieneDescuento = ($producto?->porcentaje_descuento ?? 0) > 0 && $producto?->precio_con_descuento;
                    $precioUnit = $variante
                        ? (float) $variante->precio_final
                        : ($tieneDescuento ? (float) $producto->precio_con_descuento : (float) ($producto?->precio_venta ?? 0));

                    $total = $precioUnit * $item->cantidad;

                    $varianteDesc = $variante
                        ? $variante->valores->map(function ($pav) {
                            $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                            $val  = $pav->valor?->nombre ?? '';
                            return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
                          })->filter()->join(', ')
                        : null;
                @endphp

                <div class="ld__item {{ $disponible ? '' : 'ld__item--no-disp' }}"
                     wire:key="deseo-{{ $item->id }}">

                    {{-- Checkbox de selección (solo disponibles) --}}
                    <div class="ld__check-col">
                        @if ($disponible)
                            <input type="checkbox"
                                   class="ld__check"
                                   :checked="seleccion['{{ $item->id }}'] || false"
                                   @change="marcar('{{ $item->id }}')"
                                   aria-label="Seleccionar {{ $producto?->nombre }}">
                        @endif
                    </div>

                    {{-- Imagen --}}
                    <div class="ld__img-wrap">
                        @if ($imagen)
                            <img src="{{ $imagen }}" alt="{{ $producto?->nombre }}"
                                 class="ld__img {{ $disponible ? '' : 'ld__img--no-disp' }}">
                        @else
                            <div class="ld__sin-img">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"
                                     width="24" height="24">
                                    <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="m21 15-5-5L5 21"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Nombre + variante + badge --}}
                    <div class="ld__info">
                        <span class="ld__nombre" title="{{ $producto?->nombre }}">
                            {{ $producto?->nombre }}
                        </span>
                        @if ($varianteDesc)
                            <span class="ld__variante">{{ $varianteDesc }}</span>
                        @endif
                        @if (! $disponible)
                            <span class="ld__no-disp-badge">No disponible</span>
                        @endif
                    </div>

                    {{-- Cantidad --}}
                    <div class="ld__cantidad">
                        <button
                            type="button"
                            class="ld__cant-btn"
                            wire:click="decrementar({{ $item->id }})"
                            wire:loading.attr="disabled"
                            @disabled(! $disponible)
                            title="{{ $item->cantidad <= 1 ? 'Quitar' : 'Reducir cantidad' }}"
                        >
                            @if ($item->cantidad <= 1)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.5" width="11" height="11">
                                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                                </svg>
                            @else
                                −
                            @endif
                        </button>
                        <span class="ld__cant-num">{{ $item->cantidad }}</span>
                        <button
                            type="button"
                            class="ld__cant-btn"
                            wire:click="incrementar({{ $item->id }})"
                            wire:loading.attr="disabled"
                            @disabled(! $disponible)
                            title="Aumentar cantidad"
                        >+</button>
                    </div>

                    {{-- Precio --}}
                    <div class="ld__precio-wrap">
                        @if ($disponible)
                            <span class="ld__precio">S/ {{ number_format($total, 2) }}</span>
                            @if ($item->cantidad > 1)
                                <span class="ld__precio-unit">c/u S/ {{ number_format($precioUnit, 2) }}</span>
                            @endif
                        @else
                            <span class="ld__precio" style="color:#d1d5db">—</span>
                        @endif
                    </div>

                    {{-- Eliminar --}}
                    <div class="ld__acciones">
                        <button
                            type="button"
                            class="ld__btn-eliminar"
                            wire:click="eliminarItem({{ $item->id }})"
                            wire:loading.attr="disabled"
                            title="Quitar de deseos"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" width="13" height="13">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                </div>
            @endforeach
        </div>

        {{-- ── Barra de selección inferior ────────────────────── --}}
        <div class="ld__barra" x-show="hayDisponibles">
            <label class="ld__barra-todo">
                <input type="checkbox"
                       class="ld__check ld__check--lg"
                       :checked="todosSeleccionados"
                       :indeterminate="algunoSeleccionado && !todosSeleccionados"
                       @change="toggleTodos()">
                <span class="ld__barra-todo-label"
                      x-text="algunoSeleccionado
                          ? `${cantidadSel} seleccionado${cantidadSel > 1 ? 's' : ''}`
                          : 'Seleccionar todo'">
                </span>
            </label>

            <div class="ld__barra-right">
                <span class="ld__barra-total">
                    S/ <span x-text="totalSel"></span>
                </span>
                <button class="ld__barra-btn"
                        wire:loading.attr="disabled"
                        @click="$wire.moverSeleccionadosAlCarrito(idsEnMover)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" width="14" height="14" style="flex-shrink:0">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span x-text="algunoSeleccionado
                        ? `Mover ${cantidadSel} al carrito`
                        : 'Mover todo al carrito'">
                    </span>
                </button>
            </div>
        </div>
    @endif

    @endif {{-- end @else (auth) --}}

</div>
