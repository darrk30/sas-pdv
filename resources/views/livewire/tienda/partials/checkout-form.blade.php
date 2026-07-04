<div class="chk">

    {{-- Encabezado --}}
    <div class="chk__header">
        <button type="button" class="chk__volver" wire:click="volverAlCarrito">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" width="15" height="15">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Volver al carrito
        </button>
        <h1 class="chk__titulo">Finalizar pedido</h1>
    </div>

    {{-- Alerta si no hay métodos de pago --}}
    @if ($metodosPago->isEmpty())
        <div class="chk__alerta chk__alerta--error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 width="16" height="16" style="flex-shrink:0">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01"/>
            </svg>
            No hay métodos de pago configurados. No es posible procesar la orden.
        </div>
    @endif

    <div class="chk__layout">

        {{-- ── Columna izquierda ──────────────────────────── --}}
        <div class="chk__form">

            {{-- 1. Datos personales --}}
            <div class="chk__seccion">
                <h2 class="chk__seccion-titulo">
                    <span class="chk__seccion-num">1</span>
                    Datos personales
                </h2>
                <div class="chk__seccion-cuerpo">

                    <div class="chk__campo-grid">
                        <div class="chk__field">
                            <label class="chk__label">Nombre(s) <span class="chk__req">*</span></label>
                            <input type="text"
                                   class="chk__input @error('chkNombre') chk__input--error @enderror"
                                   wire:model="chkNombre" placeholder="Tu nombre">
                            @error('chkNombre')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="chk__field">
                            <label class="chk__label">Apellidos <span class="chk__req">*</span></label>
                            <input type="text"
                                   class="chk__input @error('chkApellidos') chk__input--error @enderror"
                                   wire:model="chkApellidos" placeholder="Tus apellidos">
                            @error('chkApellidos')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="chk__campo-grid">
                        <div class="chk__field">
                            <label class="chk__label">Tipo doc.</label>
                            <input type="text" class="chk__input chk__input--locked"
                                   value="DNI" disabled>
                            <input type="hidden" wire:model="chkTipoDoc">
                        </div>
                        <div class="chk__field">
                            <label class="chk__label">N° DNI <span class="chk__req">*</span></label>
                            <input type="text"
                                   class="chk__input @error('chkNumDoc') chk__input--error @enderror"
                                   wire:model="chkNumDoc" placeholder="12345678" maxlength="8">
                            @error('chkNumDoc')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="chk__campo-grid">
                        <div class="chk__field">
                            <label class="chk__label">Teléfono <span class="chk__req">*</span></label>
                            <input type="tel"
                                   class="chk__input @error('chkTelefono') chk__input--error @enderror"
                                   wire:model="chkTelefono" placeholder="987654321" maxlength="9">
                            @error('chkTelefono')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="chk__field">
                            <label class="chk__label">Correo</label>
                            <input type="email"
                                   class="chk__input @error('chkEmail') chk__input--error @enderror"
                                   wire:model="chkEmail" placeholder="correo@ejemplo.com">
                            @error('chkEmail')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- 2. Método de entrega --}}
            <div class="chk__seccion">
                <h2 class="chk__seccion-titulo">
                    <span class="chk__seccion-num">2</span>
                    Método de entrega
                </h2>
                <div class="chk__seccion-cuerpo">

                    @forelse ($metodosEnvio as $metodo)
                        <button type="button"
                                class="chk__envio {{ $chkMetodoEnvioId === $metodo->id ? 'chk__envio--sel' : '' }}"
                                wire:click="seleccionarEnvio({{ $metodo->id }})">
                            <span class="chk__metodo-check {{ $chkMetodoEnvioId === $metodo->id ? 'chk__metodo-check--sel' : '' }}"></span>
                            <span class="chk__envio-info">
                                <span class="chk__envio-nombre">{{ $metodo->nombre }}</span>
                                @if ($metodo->descripcion)
                                    <span class="chk__envio-desc">{!! $metodo->descripcion !!}</span>
                                @endif
                            </span>
                            @if ((float)$metodo->costo > 0)
                                <span class="chk__envio-costo">S/ {{ number_format($metodo->costo, 2) }}</span>
                            @endif
                        </button>
                    @empty
                        <p class="chk__sin-opciones">No hay métodos de envío disponibles.</p>
                    @endforelse

                    @error('chkMetodoEnvioId')
                        <span class="chk__error chk__error--block">{{ $message }}</span>
                    @enderror

                    {{-- Dirección de entrega (solo si el método lo requiere) --}}
                    @if ($requiereDireccion)
                        <div class="chk__field chk__field--full chk__dir-envio">
                            <label class="chk__label">Dirección de la agencia <span class="chk__req">*</span></label>
                            <input type="text"
                                   class="chk__input @error('chkDireccion') chk__input--error @enderror"
                                   wire:model="chkDireccion"
                                   placeholder="Av. Ejemplo 123">
                            @error('chkDireccion')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>

                        <div class="chk__campo-grid">
                            <div class="chk__field">
                                <label class="chk__label">Departamento <span class="chk__req">*</span></label>
                                <input type="text"
                                       class="chk__input @error('chkDepartamento') chk__input--error @enderror"
                                       wire:model="chkDepartamento" placeholder="Ej. Lima">
                                @error('chkDepartamento')<span class="chk__error">{{ $message }}</span>@enderror
                            </div>
                            <div class="chk__field">
                                <label class="chk__label">Provincia <span class="chk__req">*</span></label>
                                <input type="text"
                                       class="chk__input @error('chkProvincia') chk__input--error @enderror"
                                       wire:model="chkProvincia" placeholder="Ej. Lima">
                                @error('chkProvincia')<span class="chk__error">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="chk__field chk__field--full">
                            <label class="chk__label">Distrito <span class="chk__req">*</span></label>
                            <input type="text"
                                   class="chk__input @error('chkDistrito') chk__input--error @enderror"
                                   wire:model="chkDistrito" placeholder="Ej. Miraflores">
                            @error('chkDistrito')<span class="chk__error">{{ $message }}</span>@enderror
                        </div>
                    @endif

                </div>
            </div>

            {{-- 3. Método de pago --}}
            <div class="chk__seccion">
                <h2 class="chk__seccion-titulo">
                    <span class="chk__seccion-num">3</span>
                    Método de pago
                </h2>
                <div class="chk__seccion-cuerpo">
                    @if ($metodosPago->isEmpty())
                        <p class="chk__sin-opciones chk__sin-opciones--error">
                            No hay métodos de pago disponibles para esta tienda.
                        </p>
                    @else
                        @foreach ($metodosPago as $mp)
                            <button type="button"
                                    class="chk__envio {{ $chkMetodoPagoId === $mp->id ? 'chk__envio--sel' : '' }}"
                                    wire:click="$set('chkMetodoPagoId', {{ $mp->id }})">
                                <span class="chk__metodo-check {{ $chkMetodoPagoId === $mp->id ? 'chk__metodo-check--sel' : '' }}"></span>
                                @if ($mp->imagen)
                                    <img src="{{ Storage::url($mp->imagen) }}"
                                         alt="{{ $mp->nombre }}" class="chk__pago-img">
                                @endif
                                <span class="chk__envio-info">
                                    <span class="chk__envio-nombre">{{ $mp->nombre }}</span>
                                    @if ($mp->descripcion)
                                        <span class="chk__envio-desc">{!! $mp->descripcion !!}</span>
                                    @endif
                                </span>
                                @if ($mp->requiere_referencia)
                                    <span class="chk__pago-ref-badge">Requiere ref.</span>
                                @endif
                            </button>
                        @endforeach

                        @error('chkMetodoPagoId')
                            <span class="chk__error chk__error--block">{{ $message }}</span>
                        @enderror
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Resumen (derecha) ──────────────────────── --}}
        <aside class="chk__resumen">
            <h2 class="chk__resumen-titulo">Resumen del pedido</h2>

            <div class="chk__resumen-items">
                @foreach ($items as $item)
                    @if ($disponibilidad[$item->id] ?? false)
                        @php
                            $esPromo     = (bool) $item->promocion_id;
                            $esItemGuest = ($esGuest ?? false) || $item->producto === null;
                            if ($esItemGuest) {
                                $nombre = $item->nombre ?? 'Producto';
                                $imagen = $item->imagen ?? null;
                            } else {
                                $nombre = $esPromo
                                    ? ($item->promocion?->nombre ?? 'Promoción')
                                    : ($item->producto?->nombre  ?? 'Producto');
                                $imagen = null;
                                if ($esPromo && $item->promocion?->imagen) {
                                    $imagen = Storage::url($item->promocion->imagen);
                                } elseif (!$esPromo) {
                                    if ($item->variante?->imagen)   $imagen = Storage::url($item->variante->imagen);
                                    elseif ($item->producto?->logo) $imagen = Storage::url($item->producto->logo);
                                }
                            }
                        @endphp
                        <div class="chk__resumen-item">
                            <div class="chk__resumen-img">
                                @if ($imagen)
                                    <img src="{{ $imagen }}" alt="{{ $nombre }}" class="chk__resumen-img-el">
                                @else
                                    <div class="chk__resumen-img-ph">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"
                                             width="14" height="14">
                                            <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                    </div>
                                @endif
                                <span class="chk__resumen-qty">{{ $item->cantidad }}</span>
                            </div>
                            <span class="chk__resumen-nombre">
                                {{ $nombre }}
                                @if ($esItemGuest && !empty($item->variante_nombre))
                                    <small class="chk__resumen-variante">{{ $item->variante_nombre }}</small>
                                @elseif (!$esItemGuest && !$esPromo && $item->variante)
                                    @php
                                        $vd = $item->variante->valores->map(function ($pav) {
                                            $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                                            $val  = $pav->valor?->nombre ?? '';
                                            return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
                                        })->filter()->join(', ');
                                    @endphp
                                    @if ($vd)
                                        <small class="chk__resumen-variante">{{ $vd }}</small>
                                    @endif
                                @elseif (!$esItemGuest && !$esPromo && !$item->variante && $item->producto?->atributos)
                                    @php
                                        $vd = $item->producto->atributos
                                            ->filter(fn($pa) => in_array(strtolower(trim($pa->atributo?->nombre ?? '')), ['talla', 'color']))
                                            ->map(fn($pa) => ucfirst(strtolower($pa->atributo->nombre)) . ': ' .
                                                $pa->valores->map(fn($v) => $v->nombre ?? $v->valor ?? '')->filter()->join(', '))
                                            ->filter()->join(' · ');
                                    @endphp
                                    @if ($vd)
                                        <small class="chk__resumen-variante">{{ $vd }}</small>
                                    @endif
                                @endif
                            </span>
                            <span class="chk__resumen-precio">
                                S/ {{ number_format($item->precio_unitario * $item->cantidad, 2) }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="chk__resumen-sep"></div>

            <div class="chk__resumen-linea">
                <span class="chk__resumen-label">Subtotal</span>
                <span class="chk__resumen-valor">S/ {{ number_format($subtotal, 2) }}</span>
            </div>
            @if ($costoEnvio > 0)
                <div class="chk__resumen-linea">
                    <span class="chk__resumen-label">Envío</span>
                    <span class="chk__resumen-valor">S/ {{ number_format($costoEnvio, 2) }}</span>
                </div>
            @endif

            <div class="chk__resumen-sep"></div>

            <div class="chk__resumen-total">
                <span class="chk__resumen-total-label">Total</span>
                <span class="chk__resumen-total-valor">S/ {{ number_format($total, 2) }}</span>
            </div>

            <button type="button"
                    class="chk__btn-confirmar"
                    wire:click="confirmarOrden"
                    wire:loading.attr="disabled"
                    wire:loading.class="chk__btn-confirmar--loading"
                    @disabled($metodosPago->isEmpty())>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" width="16" height="16" style="flex-shrink:0">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span wire:loading.remove wire:target="confirmarOrden">Confirmar orden</span>
                <span wire:loading wire:target="confirmarOrden">Procesando...</span>
            </button>

            <p class="chk__resumen-aviso">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" width="12" height="12" style="flex-shrink:0">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Pago 100% seguro
            </p>
        </aside>

    </div>
</div>
