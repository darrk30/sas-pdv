@push('styles')
<link rel="stylesheet" href="{{ asset('tienda/css/checkout.css') }}">
@endpush

<div
    x-data
    x-init="@if($esGuest)(function(){
        const items = JSON.parse(localStorage.getItem('carrito_{{ $empresaId }}')||'[]');
        $wire.recibirItemsGuest(items);
    })();@endif"
>

{{-- ── Auth: carrito vacío ───────────────────────────────────── --}}
@if (!$esGuest && $items->isEmpty())
    <div class="carrito-pagina">
        <div class="carrito-vacio">
            <div class="carrito-vacio__ilustracion">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"
                     width="80" height="80" style="color:#d1d5db;display:block;margin:0 auto">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </div>
            <h2 class="carrito-vacio__titulo">Tu carrito está vacío</h2>
            <p class="carrito-vacio__texto">Agrega productos desde el catálogo para comenzar.</p>
            <div class="carrito-vacio__acciones">
                <a href="/" wire:navigate class="carrito-vacio__btn carrito-vacio__btn--primario">Ver catálogo</a>
            </div>
        </div>
    </div>

{{-- ── Guest: carrito vacío (solo después de inicializar) ──────── --}}
@elseif ($esGuest && $items->isEmpty() && $guestIniciado)
    <div class="carrito-pagina">
        <div class="carrito-vacio">
            <div class="carrito-vacio__ilustracion">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"
                     width="80" height="80" style="color:#d1d5db;display:block;margin:0 auto">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </div>
            <h2 class="carrito-vacio__titulo">Tu carrito está vacío</h2>
            <p class="carrito-vacio__texto">Agrega productos desde el catálogo para comenzar.</p>
            <div class="carrito-vacio__acciones">
                <a href="/" wire:navigate class="carrito-vacio__btn carrito-vacio__btn--primario">Ver catálogo</a>
            </div>
            <div class="cr-guest-auth">
                <a href="/login" wire:navigate class="cr-guest-auth__link">Iniciar sesión</a>
                <span class="cr-guest-auth__sep">·</span>
                <a href="/registro" wire:navigate class="cr-guest-auth__link">Crear cuenta</a>
            </div>
        </div>
    </div>

{{-- ── Auth: carrito con ítems ──────────────────────────────── --}}
@elseif ($items->isNotEmpty() && !$mostrarFormOrden && !$esGuest)
    <div class="cr">
        <div class="cr-layout">

            {{-- Columna izquierda: ítems --}}
            <div class="cr-items-wrap">

                <div class="cr-header">
                    <div class="cr-header-left">
                        <h1 class="cr-titulo">Mi carrito</h1>
                        <span class="cr-count">
                            {{ $items->count() }} {{ $items->count() === 1 ? 'producto' : 'productos' }}
                        </span>
                    </div>
                    <button type="button" class="cr-btn-vaciar"
                            wire:click="vaciarCarrito"
                            wire:confirm="¿Vaciar todo el carrito?"
                            wire:loading.attr="disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" width="14" height="14">
                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                        </svg>
                        Vaciar
                    </button>
                </div>

                <div class="cr-lista">
                    @foreach ($items as $item)
                        @php
                            $disponible = $disponibilidad[$item->id] ?? true;
                            $esPromo = (bool) $item->promocion_id;
                            $nombre  = $esPromo
                                ? ($item->promocion?->nombre ?? 'Promoción')
                                : ($item->producto?->nombre  ?? 'Producto');

                            $imagen = null;
                            if ($esPromo && $item->promocion?->imagen) {
                                $imagen = Storage::url($item->promocion->imagen);
                            } elseif (! $esPromo) {
                                if ($item->variante?->imagen) {
                                    $imagen = Storage::url($item->variante->imagen);
                                } elseif ($item->producto?->logo) {
                                    $imagen = Storage::url($item->producto->logo);
                                } elseif ($item->producto?->galeriaProductos?->isNotEmpty()) {
                                    $imagen = Storage::url($item->producto->galeriaProductos->first()->imagen_path);
                                }
                            }

                            if ($item->variante) {
                                    $varianteDesc = $item->variante->valores->map(function ($pav) {
                                        $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                                        $val  = $pav->valor?->nombre ?? '';
                                        return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
                                    })->filter()->join(', ') ?: null;
                                } elseif (!$esPromo && $item->producto?->atributos) {
                                    $varianteDesc = $item->producto->atributos
                                        ->filter(fn($pa) => in_array(strtolower(trim($pa->atributo?->nombre ?? '')), ['talla', 'color']))
                                        ->map(fn($pa) => ucfirst(strtolower($pa->atributo->nombre)) . ': ' .
                                            $pa->valores->map(fn($v) => $v->nombre ?? $v->valor ?? '')->filter()->join(', '))
                                        ->filter()->join(' · ') ?: null;
                                } else {
                                    $varianteDesc = null;
                                }

                            $totalLinea = $item->precio_unitario * $item->cantidad;
                        @endphp

                        <div class="cr-item {{ $disponible ? '' : 'cr-item--no-disp' }}"
                             wire:key="item-{{ $item->id }}">

                            <div class="cr-img-wrap">
                                @if ($imagen)
                                    <img src="{{ $imagen }}" alt="{{ $nombre }}"
                                         class="cr-img {{ $disponible ? '' : 'cr-img--no-disp' }}">
                                @else
                                    <div class="cr-sin-img">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1" width="22" height="22">
                                            <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <div class="cr-info">
                                @php $codigoItem = $item->variante?->codigo ?: $item->producto?->codigo_interno; @endphp
                                @if ($codigoItem)
                                    <span class="cr-codigo">{{ $codigoItem }}</span>
                                @endif
                                <span class="cr-nombre" title="{{ $nombre }}">{{ $nombre }}</span>
                                @if ($varianteDesc)
                                    <span class="cr-variante">{{ $varianteDesc }}</span>
                                @endif
                                @if ($esPromo)
                                    <span class="cr-badge-promo">Promo</span>
                                @endif
                                @if (! $disponible)
                                    <span class="cr-no-disp-badge">No disponible</span>
                                @endif
                                <span class="cr-precio-unit-mob">
                                    S/ {{ number_format($item->precio_unitario, 2) }} c/u
                                </span>
                            </div>

                            <div class="cr-cantidad">
                                <button type="button" class="cr-cant-btn"
                                        wire:click="decrementar({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        @disabled(! $disponible)>
                                    @if ($item->cantidad <= 1)
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2.5" width="11" height="11">
                                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                                        </svg>
                                    @else
                                        −
                                    @endif
                                </button>
                                <span class="cr-cant-num">{{ $item->cantidad }}</span>
                                <button type="button" class="cr-cant-btn"
                                        wire:click="incrementar({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        @disabled(! $disponible)>+</button>
                            </div>

                            <div class="cr-precio-wrap">
                                @if ($disponible)
                                    <span class="cr-precio">S/ {{ number_format($totalLinea, 2) }}</span>
                                    @if ($item->cantidad > 1)
                                        <span class="cr-precio-unit">c/u S/ {{ number_format($item->precio_unitario, 2) }}</span>
                                    @endif
                                @else
                                    <span class="cr-precio" style="color:#d1d5db">—</span>
                                @endif
                            </div>

                            <button type="button" class="cr-btn-eliminar"
                                    wire:click="eliminarItem({{ $item->id }})"
                                    wire:loading.attr="disabled">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.5" width="13" height="13">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="cr-seguir">
                    <a href="/" wire:navigate class="cr-seguir-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" width="14" height="14">
                            <path d="M19 12H5M12 5l-7 7 7 7"/>
                        </svg>
                        Seguir comprando
                    </a>
                </div>
            </div>

            {{-- Resumen (derecha) --}}
            <aside class="cr-resumen">
                <h2 class="cr-resumen-titulo">Resumen del pedido</h2>
                <div class="cr-resumen-linea">
                    <span class="cr-resumen-label">
                        Subtotal
                        <small class="cr-resumen-hint">({{ $items->count() }} {{ $items->count() === 1 ? 'producto' : 'productos' }})</small>
                    </span>
                    <span class="cr-resumen-valor">S/ {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="cr-cupon">
                    <label class="cr-cupon-label">¿Tienes un código de descuento?</label>
                    <div class="cr-cupon-row">
                        <input type="text" class="cr-cupon-input" placeholder="Ingresa tu código" maxlength="30">
                        <button type="button" class="cr-cupon-btn" disabled>Aplicar</button>
                    </div>
                </div>
                <div class="cr-resumen-divider"></div>
                <div class="cr-resumen-total">
                    <span class="cr-resumen-total-label">Total</span>
                    <span class="cr-resumen-total-valor">S/ {{ number_format($subtotal, 2) }}</span>
                </div>
                @php $hayDisponibles = collect($disponibilidad)->contains(true); @endphp
                <button type="button"
                        class="cr-btn-orden {{ $hayDisponibles ? 'cr-btn-orden--activo' : '' }}"
                        wire:click="{{ $hayDisponibles ? 'abrirFormOrden' : '' }}"
                        @disabled(! $hayDisponibles)>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" width="16" height="16">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Completar orden
                </button>
                <p class="cr-resumen-aviso">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" width="12" height="12" style="flex-shrink:0;color:#9ca3af">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Pago 100% seguro
                </p>
            </aside>

        </div>
    </div>

{{-- ── Guest: carrito con ítems ─────────────────────────────── --}}
@elseif ($items->isNotEmpty() && !$mostrarFormOrden && $esGuest)
    <div class="cr">
        <div class="cr-layout">

            <div class="cr-items-wrap">

                <div class="cr-header">
                    <div class="cr-header-left">
                        <h1 class="cr-titulo">Mi carrito</h1>
                        <span class="cr-count">
                            {{ $items->count() }} {{ $items->count() === 1 ? 'producto' : 'productos' }}
                        </span>
                    </div>
                    <button type="button" class="cr-btn-vaciar"
                            wire:click="vaciarCarritoGuest"
                            wire:confirm="¿Vaciar todo el carrito?"
                            wire:loading.attr="disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" width="14" height="14">
                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                        </svg>
                        Vaciar
                    </button>
                </div>

                <div class="cr-lista">
                    @foreach ($items as $item)
                        @php
                            $totalLinea = $item->precio_unitario * $item->cantidad;
                        @endphp
                        <div class="cr-item" wire:key="guest-item-{{ $item->id }}">

                            <div class="cr-img-wrap">
                                @if ($item->imagen)
                                    <img src="{{ $item->imagen }}" alt="{{ $item->nombre }}" class="cr-img">
                                @else
                                    <div class="cr-sin-img">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1" width="22" height="22">
                                            <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <div class="cr-info">
                                @if (!empty($item->codigo_interno))
                                    <span class="cr-codigo">{{ $item->codigo_interno }}</span>
                                @endif
                                <span class="cr-nombre">{{ $item->nombre }}</span>
                                @if (!empty($item->variante_nombre))
                                    <span class="cr-variante">{{ $item->variante_nombre }}</span>
                                @endif
                                @if ($item->promocion_id)
                                    <span class="cr-badge-promo">Promo</span>
                                @endif
                                <span class="cr-precio-unit-mob">
                                    S/ {{ number_format($item->precio_unitario, 2) }} c/u
                                </span>
                            </div>

                            <div class="cr-cantidad">
                                <button type="button" class="cr-cant-btn"
                                        wire:click="decrementarGuest({{ $item->id }})"
                                        wire:loading.attr="disabled">
                                    @if ($item->cantidad <= 1)
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2.5" width="11" height="11">
                                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                                        </svg>
                                    @else
                                        −
                                    @endif
                                </button>
                                <span class="cr-cant-num">{{ $item->cantidad }}</span>
                                <button type="button" class="cr-cant-btn"
                                        wire:click="incrementarGuest({{ $item->id }})"
                                        wire:loading.attr="disabled">+</button>
                            </div>

                            <div class="cr-precio-wrap">
                                <span class="cr-precio">S/ {{ number_format($totalLinea, 2) }}</span>
                                @if ($item->cantidad > 1)
                                    <span class="cr-precio-unit">c/u S/ {{ number_format($item->precio_unitario, 2) }}</span>
                                @endif
                            </div>

                            <button type="button" class="cr-btn-eliminar"
                                    wire:click="eliminarItemGuest({{ $item->id }})"
                                    wire:loading.attr="disabled">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.5" width="13" height="13">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="cr-seguir">
                    <a href="/" wire:navigate class="cr-seguir-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" width="14" height="14">
                            <path d="M19 12H5M12 5l-7 7 7 7"/>
                        </svg>
                        Seguir comprando
                    </a>
                </div>
            </div>

            <aside class="cr-resumen">
                <h2 class="cr-resumen-titulo">Resumen del pedido</h2>
                <div class="cr-resumen-linea">
                    <span class="cr-resumen-label">
                        Subtotal
                        <small class="cr-resumen-hint">({{ $items->count() }} {{ $items->count() === 1 ? 'producto' : 'productos' }})</small>
                    </span>
                    <span class="cr-resumen-valor">S/ {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="cr-resumen-divider"></div>
                <div class="cr-resumen-total">
                    <span class="cr-resumen-total-label">Total</span>
                    <span class="cr-resumen-total-valor">S/ {{ number_format($subtotal, 2) }}</span>
                </div>
                <button type="button"
                        class="cr-btn-orden cr-btn-orden--activo"
                        wire:click="abrirFormOrden">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" width="16" height="16">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Completar orden
                </button>
                <div class="cr-guest-auth" style="margin-top:0.75rem;text-align:center">
                    <a href="/login" wire:navigate class="cr-guest-auth__link">Iniciar sesión</a>
                    <span class="cr-guest-auth__sep">·</span>
                    <a href="/registro" wire:navigate class="cr-guest-auth__link">Crear cuenta</a>
                </div>
                <p class="cr-resumen-aviso">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" width="12" height="12" style="flex-shrink:0;color:#9ca3af">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Pago 100% seguro
                </p>
            </aside>

        </div>
    </div>

{{-- ── Formulario de orden ──────────────────────────────────── --}}
@elseif ($mostrarFormOrden)
    @include('livewire.tienda.partials.checkout-form', [
        'subtotal'          => $subtotal,
        'costoEnvio'        => $costoEnvio,
        'total'             => $total,
        'metodosEnvio'      => $metodosEnvio,
        'metodosPago'       => $metodosPago,
        'disponibilidad'    => $disponibilidad,
        'items'             => $items,
        'requiereDireccion' => $requiereDireccion,
        'esGuest'           => $esGuest,
    ])
@endif

{{-- ── Modal orden completada ───────────────────────────────── --}}
@if ($mostrarModalExito)
    <div class="ord-modal-overlay" wire:click.self="cerrarModal">
        <div class="ord-modal">

            <div class="ord-modal__icono">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" width="36" height="36">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h2 class="ord-modal__titulo">¡Pedido registrado!</h2>
            <p class="ord-modal__codigo">{{ $ordenCodigo }}</p>

            <p class="ord-modal__mensaje">
                Tu pedido fue registrado correctamente.<br>
                Envíanos el comprobante de pago por WhatsApp para confirmar y procesar tu envío.
            </p>

            <div class="ord-modal__total">
                Total a pagar: <strong>S/ {{ number_format($ordenTotal, 2) }}</strong>
            </div>

            <a href="{{ $whatsappUrl }}"
               target="_blank"
               rel="noopener noreferrer"
               wire:click="cerrarModal"
               class="ord-modal__btn-wsp">
                <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                Enviar comprobante por WhatsApp
            </a>

            @if ($esOrdenGuest)
                <a href="/registro" wire:navigate class="ord-modal__btn-cuenta">
                    Crear cuenta para ver mis órdenes
                </a>
            @endif

        </div>
    </div>
@endif

</div>
