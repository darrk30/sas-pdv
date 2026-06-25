<x-filament-panels::page>

    <link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}">

    {{-- ══ HEADER ══ --}}
    <div class="pdv-header">
        <div class="pdv-header__left">
            <p class="pdv-header__titulo">Punto de Venta</p>
            <p class="pdv-header__sub">Cajero: {{ auth()->user()->name }}</p>
        </div>
        <div class="pdv-header__right">
            <p class="pdv-header__venta">Venta #{{ $this->getNumeroPreview() }}</p>
            <p class="pdv-header__fecha">{{ now()->format('d/m/Y  H:i') }}</p>
        </div>
    </div>

    <div class="pdv-wrap">

        {{-- ══ ÁREA DE PRODUCTOS (izquierda) ══ --}}
        <div class="pdv-productos">

            {{-- Buscador --}}
            <div class="pdv-busqueda">
                <svg class="pdv-busqueda__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="text"
                    class="pdv-busqueda__input"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar producto o promoción..."
                />
                @if($busqueda)
                    <button class="pdv-busqueda__clear" wire:click="limpiarBusqueda">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Filtros de categoría --}}
            <div class="pdv-categorias">
                <button class="pdv-cat-btn {{ $categoriaId === null ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(null)">
                    Todos
                </button>
                @foreach($this->getCategorias() as $cat)
                    <button
                        class="pdv-cat-btn {{ $categoriaId === $cat->id ? 'pdv-cat-btn--activo' : '' }}"
                        wire:click="seleccionarCategoria({{ $cat->id }})"
                    >
                        {{ $cat->nombre }}
                    </button>
                @endforeach
            </div>

            {{-- Grid scrollable --}}
            <div class="pdv-grid">

                {{-- Promociones --}}
                @php $promociones = $this->getPromociones(); @endphp
                @if($promociones->isNotEmpty())
                    <p class="pdv-seccion-titulo pdv-seccion-titulo--promo">Promociones</p>
                    <div class="pdv-items-grid">
                        @foreach($promociones as $promo)
                            <button class="pdv-card pdv-card--promo" wire:click="agregarPromocion({{ $promo->id }})">
                                <span class="pdv-badge-promo">PROMO</span>
                                @if($promo->imagen)
                                    <img class="pdv-card__imagen" src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}" alt="{{ $promo->nombre }}"/>
                                @else
                                    <div class="pdv-card__avatar">{{ strtoupper(mb_substr($promo->nombre, 0, 1)) }}</div>
                                @endif
                                <p class="pdv-card__nombre">{{ $promo->nombre }}</p>
                                <p class="pdv-card__meta">{{ $promo->detalles_count }} productos</p>
                                <p class="pdv-card__precio">S/ {{ number_format($promo->precio, 2) }}</p>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Productos --}}
                @php $productos = $this->getProductos(); @endphp
                @if($promociones->isNotEmpty() && $productos->isNotEmpty())
                    <p class="pdv-seccion-titulo pdv-seccion-titulo--productos">Productos</p>
                @endif

                @if($productos->isNotEmpty())
                    <div class="pdv-items-grid">
                        @foreach($productos as $producto)
                            @php
                                $tieneVariantes = $producto->variantes->isNotEmpty();
                                $stockSimple    = ! $tieneVariantes && $producto->control_de_stock
                                    ? (float)($producto->inventario?->stock_real ?? 0) : null;
                                $stockVariantes = $tieneVariantes && $producto->control_de_stock
                                    ? $producto->variantes->sum(fn($v) => (float)($v->inventario?->stock_real ?? 0)) : null;
                                $stock = $stockSimple ?? $stockVariantes;
                            @endphp
                            <button class="pdv-card" wire:click="abrirModalProducto({{ $producto->id }})">
                                @if($producto->logo)
                                    <img class="pdv-card__imagen" src="{{ \Illuminate\Support\Facades\Storage::url($producto->logo) }}" alt="{{ $producto->nombre }}"/>
                                @else
                                    <div class="pdv-card__avatar">{{ strtoupper(mb_substr($producto->nombre, 0, 1)) }}</div>
                                @endif
                                <p class="pdv-card__nombre">{{ $producto->nombre }}</p>
                                @if($stock !== null)
                                    <span class="pdv-card__stock {{ $stock <= 0 ? 'pdv-card__stock--agotado' : ($stock <= 5 ? 'pdv-card__stock--bajo' : 'pdv-card__stock--ok') }}">
                                        Stock: {{ number_format($stock, 0) }}
                                    </span>
                                @endif
                                @if($tieneVariantes)
                                    <p class="pdv-card__meta">{{ $producto->variantes->count() }} variantes</p>
                                    <p class="pdv-card__precio">Desde S/ {{ number_format($producto->variantes->min('precio_final'), 2) }}</p>
                                @else
                                    <p class="pdv-card__precio">S/ {{ number_format($producto->precio_venta, 2) }}</p>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="pdv-vacio">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                        <p>No se encontraron productos</p>
                        @if($busqueda)
                            <button class="pdv-vacio__link" wire:click="limpiarBusqueda">Limpiar búsqueda</button>
                        @endif
                    </div>
                @endif

            </div>
        </div>{{-- /pdv-productos --}}


        {{-- ══ CARRITO (derecha) ══ --}}
        <div class="pdv-carrito">

            {{-- Header carrito --}}
            <div class="pdv-carrito__header">
                <div class="pdv-carrito__titulo">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    Carrito
                    @if($this->getItemCount() > 0)
                        <span class="pdv-carrito__count">{{ $this->getItemCount() }}</span>
                    @endif
                </div>
                @if(! empty($carrito))
                    <button class="pdv-carrito__vaciar" wire:click="vaciarCarrito">Vaciar</button>
                @endif
            </div>

            {{-- ── CLIENTE ── --}}
            <div class="pdv-cliente">
                <div class="pdv-cliente__row">
                    <div class="pdv-cliente__search-wrap">
                        <svg class="pdv-cliente__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <input
                            type="text"
                            class="pdv-cliente__input"
                            wire:model.live.debounce.300ms="clienteBusqueda"
                            placeholder="Buscar cliente..."
                        />
                        @if($clienteId)
                            <button class="pdv-cliente__clear" wire:click="limpiarCliente">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                    <button class="pdv-cliente__nuevo-btn" wire:click="abrirModalNuevoCliente" title="Nuevo cliente">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </button>
                </div>

                {{-- Dropdown sugerencias --}}
                @if($mostrarSugerencias)
                    @php $sugeridos = $this->getClientesSugeridos(); @endphp
                    @if($sugeridos->isNotEmpty())
                        <div class="pdv-cliente__dropdown">
                            @foreach($sugeridos as $c)
                                <button class="pdv-cliente__opcion" wire:click="seleccionarCliente({{ $c->id }})">
                                    <span class="pdv-cliente__opcion-nombre">{{ $c->nombre_completo }}</span>
                                    <span class="pdv-cliente__opcion-doc">{{ strtoupper($c->tipo_documento->value) }} {{ $c->numero_documento }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="pdv-cliente__dropdown">
                            <p class="pdv-cliente__no-result">Sin resultados</p>
                        </div>
                    @endif
                @endif

            </div>

            {{-- ── TIPO DE COMPROBANTE ── --}}
            @php $series = $this->getSeries(); @endphp
            <div class="pdv-comprobante">
                @foreach([
                    ['tipo' => 'factura', 'label' => 'Factura',  'color' => 'info'],
                    ['tipo' => 'boleta',  'label' => 'Boleta',   'color' => 'success'],
                    ['tipo' => 'ticket',  'label' => 'Ticket',   'color' => 'warning'],
                ] as $c)
                    @php
                        $serie      = $series->first(fn($s) => $s->tipo->value === $c['tipo']);
                        $activo     = $tipoComprobante === $c['tipo'];
                        $disponible = $serie !== null;
                        $invalido   = $activo && $clienteId && $c['tipo'] === 'factura' && $clienteTipoDoc !== 'ruc';
                    @endphp
                    <button
                        class="pdv-comp-btn pdv-comp-btn--{{ $c['color'] }} {{ $activo ? 'pdv-comp-btn--activo' : '' }} {{ ! $disponible ? 'pdv-comp-btn--disabled' : '' }}"
                        wire:click="seleccionarComprobante('{{ $c['tipo'] }}')"
                        @if(! $disponible) disabled @endif
                        title="{{ ! $disponible ? 'Sin serie activa' : '' }}"
                    >
                        <span class="pdv-comp-btn__label">{{ $c['label'] }}</span>
                        <span class="pdv-comp-btn__serie">{{ $serie?->serie ?? 'Sin serie' }}</span>
                        @if($invalido)
                            <span class="pdv-comp-btn__alerta">Requiere RUC</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Items del carrito --}}
            @if(empty($carrito))
                <div class="pdv-carrito__empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    <p>El carrito está vacío</p>
                    <span>Selecciona productos para comenzar</span>
                </div>
            @else
                <div class="pdv-carrito__lista">
                    @foreach($carrito as $item)
                        <div class="pdv-item" wire:key="item-{{ $item['key'] }}">
                            <div class="pdv-item__info">
                                @if($item['tipo'] === 'promocion')
                                    <span class="pdv-item__badge-promo">PROMO</span>
                                @endif
                                <p class="pdv-item__nombre">{{ $item['nombre'] }}</p>
                                <p class="pdv-item__precio-unit">S/ {{ number_format($item['precio'], 2) }} c/u</p>
                            </div>
                            <div class="pdv-item__controles">
                                <span class="pdv-item__subtotal">S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}</span>
                                <div class="pdv-qty">
                                    <button class="pdv-qty__btn pdv-qty__btn--menos" wire:click="disminuirCantidad('{{ $item['key'] }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </button>
                                    <span class="pdv-qty__num">{{ $item['cantidad'] }}</span>
                                    <button class="pdv-qty__btn pdv-qty__btn--mas" wire:click="aumentarCantidad('{{ $item['key'] }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pdv-carrito__footer">
                    <div class="pdv-carrito__totales">
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__label">{{ $this->getItemCount() }} ítems</span>
                            <span class="pdv-carrito__sublabel">Subtotal</span>
                        </div>
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__total-label">Total</span>
                            <span class="pdv-carrito__total-monto">S/ {{ number_format($this->getTotal(), 2) }}</span>
                        </div>
                    </div>
                    <button class="pdv-btn-venta">Procesar Venta</button>
                </div>
            @endif

        </div>{{-- /pdv-carrito --}}

    </div>{{-- /pdv-wrap --}}


    {{-- ══ MODAL: variantes ══ --}}
    @if($modalAbierto)
        <div class="pdv-overlay" wire:key="modal-variantes">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModal"></div>
            <div class="pdv-modal">
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">{{ $productoModalNombre }}</h3>
                        <p class="pdv-modal__subtitulo">Selecciona las opciones del producto</p>
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="pdv-modal__body">
                    @foreach($atributosModal as $atributo)
                        <div wire:key="atributo-{{ $atributo['id'] }}">
                            <p class="pdv-atributo__label">
                                {{ $atributo['nombre'] }}
                                @if(! isset($seleccionados[$atributo['id']]))
                                    <span class="pdv-atributo__requerido">(requerido)</span>
                                @endif
                            </p>
                            <div class="pdv-atributo__opciones">
                                @foreach($atributo['valores'] as $valor)
                                    @php $activo = isset($seleccionados[$atributo['id']]) && (int)$seleccionados[$atributo['id']] === (int)$valor['id']; @endphp
                                    <button
                                        class="pdv-valor-btn {{ $activo ? 'pdv-valor-btn--activo' : '' }}"
                                        wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                                        wire:key="valor-{{ $atributo['id'] }}-{{ $valor['id'] }}"
                                    >
                                        {{ $valor['nombre'] }}
                                        @if($valor['precio_adicional'] > 0)
                                            <span class="pdv-valor-btn__extra">+S/ {{ number_format($valor['precio_adicional'], 2) }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="pdv-modal__footer">
                    <div class="pdv-modal__precio-row">
                        <div>
                            <p class="pdv-modal__precio-label">Precio total</p>
                            @if($precioAdicionalTotal > 0)
                                <p class="pdv-modal__precio-detalle">S/ {{ number_format($precioBase, 2) }} + S/ {{ number_format($precioAdicionalTotal, 2) }}</p>
                            @endif
                        </div>
                        <span class="pdv-modal__precio-total">S/ {{ number_format($precioBase + $precioAdicionalTotal, 2) }}</span>
                    </div>
                    @php $todosSeleccionados = count($seleccionados) >= count($atributosModal); @endphp
                    <button class="pdv-btn-confirmar" wire:click="confirmarModal" @if(! $todosSeleccionados) disabled @endif>
                        {{ $todosSeleccionados ? 'Agregar al carrito' : 'Selecciona todas las opciones' }}
                    </button>
                </div>
            </div>
        </div>
    @endif


    {{-- ══ MODAL: nuevo cliente rápido ══ --}}
    @if($modalNuevoCliente)
        <div class="pdv-overlay" wire:key="modal-nuevo-cliente">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModalNuevoCliente"></div>
            <div class="pdv-modal" style="max-width:26rem;">
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">Nuevo Cliente</h3>
                        <p class="pdv-modal__subtitulo">Registro rápido</p>
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModalNuevoCliente">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="pdv-modal__body">

                    <div class="pdv-field">
                        <label class="pdv-field__label">Tipo de documento</label>
                        <div class="pdv-doc-tipo">
                            <button
                                class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'dni' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                                wire:click="$set('ncTipoDoc', 'dni')"
                            >DNI</button>
                            <button
                                class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'ruc' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                                wire:click="$set('ncTipoDoc', 'ruc')"
                            >RUC</button>
                        </div>
                    </div>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Número de documento <span class="pdv-field__req">*</span></label>
                        <input
                            type="text"
                            class="pdv-field__input"
                            wire:model.live="ncNumeroDoc"
                            placeholder="{{ $ncTipoDoc === 'ruc' ? '11 dígitos' : '8 dígitos' }}"
                            maxlength="{{ $ncTipoDoc === 'ruc' ? 11 : 8 }}"
                        />
                    </div>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Nombre / Razón Social <span class="pdv-field__req">*</span></label>
                        <input type="text" class="pdv-field__input" wire:model.live="ncNombre" placeholder="Nombre"/>
                    </div>

                    @if($ncTipoDoc === 'dni')
                        <div class="pdv-field">
                            <label class="pdv-field__label">Apellidos</label>
                            <input type="text" class="pdv-field__input" wire:model.live="ncApellidos" placeholder="Apellidos"/>
                        </div>
                    @endif

                </div>
                <div class="pdv-modal__footer">
                    <button class="pdv-btn-confirmar" wire:click="crearCliente">
                        Crear y seleccionar cliente
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
