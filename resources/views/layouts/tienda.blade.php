<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cliente-logueado" content="{{ Auth::guard('cliente')->check() ? '1' : '0' }}">
    <meta name="empresa-id" content="{{ app('tienda.empresa')->id }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('tienda/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/marcas.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/spinner.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/categorias.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('tienda/css/paginacion.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('tienda/css/carrito.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('tienda/css/checkout.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/modal-variante.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('tienda/css/lista-deseos.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/mis-ordenes.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/producto-detalle.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('tienda/css/toast.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/carrusel.css') }}">
</head>

<body>

    <livewire:tienda.partials.navbar />
    <livewire:tienda.partials.marcas />

    <main class="pagina">
        {{ $slot }}
    </main>

    <x-tienda.modal-variante />
    <x-tienda.toast />

    {{-- Componente invisible: escucha eventos del browser y opera carrito/deseos contra DB --}}
    <livewire:tienda.partials.carrito-store />

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    @livewireScripts

    <script>
        // ── Modal de variantes ────────────────────────────────────────────
        window.modalVariante = function() {
            return {
                abierto: false,
                producto: null,
                seleccion: {},
                imgPreview: null,
                cantidad: 1,

                abrir(data) {
                    this.producto = data;
                    this.seleccion = {};
                    this.cantidad  = 1;
                    this.imgPreview = data.imagen;
                    this.abierto = true;
                    document.body.style.overflow = 'hidden';
                },

                cerrar() {
                    this.abierto = false;
                    document.body.style.overflow = '';
                },

                seleccionar(atributoId, valor) {
                    if (this.seleccion[atributoId]?.id === valor.id) {
                        const sel = { ...this.seleccion };
                        delete sel[atributoId];
                        this.seleccion = sel;
                    } else {
                        this.seleccion = { ...this.seleccion, [atributoId]: valor };
                    }
                    const v = this.varianteCoincidente;
                    if (v?.imagen) { this.imgPreview = v.imagen; return; }
                    for (const sv of Object.values(this.seleccion)) {
                        if (sv.imagen) { this.imgPreview = sv.imagen; return; }
                    }
                    this.imgPreview = this.producto?.imagen ?? null;
                },

                get varianteCoincidente() {
                    if (!this.producto || !this.seleccionCompleta) return null;
                    const selIds = Object.values(this.seleccion).map(v => v.id).sort((a, b) => a - b);
                    return this.producto.variantes.find(v => {
                        const vIds = [...(v.valores_ids ?? [])].sort((a, b) => a - b);
                        return JSON.stringify(vIds) === JSON.stringify(selIds);
                    }) ?? null;
                },

                get seleccionCompleta() {
                    if (!this.producto) return false;
                    return Object.keys(this.seleccion).length === this.producto.atributos.length;
                },

                get varianteSinStock() {
                    const v = this.varianteCoincidente;
                    return this.seleccionCompleta && v !== null && v.sin_stock === true;
                },

                get disponible() {
                    if (!this.producto) return false;
                    // Producto simple o promo (sin atributos): siempre disponible
                    if (this.producto.atributos.length === 0) return true;
                    return this.seleccionCompleta && this.varianteCoincidente !== null && !this.varianteSinStock;
                },

                // Devuelve true si este valor NO tiene ninguna combinación disponible
                // considerando las selecciones actuales de los otros atributos.
                esValorBloqueado(attrId, val) {
                    if (!this.producto) return false;
                    return !this.producto.variantes.some(v => {
                        if (!v.valores_ids.includes(val.id)) return false;
                        if (v.sin_stock) return false;
                        for (const [selAttrId, selVal] of Object.entries(this.seleccion)) {
                            if (parseInt(selAttrId) === attrId) continue;
                            if (!v.valores_ids.includes(selVal.id)) return false;
                        }
                        return true;
                    });
                },

                get precioActual() {
                    if (!this.producto) return '0.00';
                    const extra = Object.values(this.seleccion)
                        .reduce((s, v) => s + (parseFloat(v.precio_adicional) || 0), 0);
                    return (this.producto.precioBase + extra).toFixed(2);
                },

                confirmar() {
                    if (!this.disponible) return;
                    const v = this.varianteCoincidente;
                    const imgEl = this.$refs.imgPreview;
                    if (imgEl && imgEl.src) flyAlCarrito(imgEl);
                    const varianteNombre = Object.values(this.seleccion)
                        .map(val => val.label).filter(Boolean).join(' / ') || null;
                    Alpine.store('carrito').agregar({
                        promocion_id:    this.producto.promocion_id ?? null,
                        producto_id:     this.producto.id ?? null,
                        variante_id:     v?.id ?? null,
                        variante_nombre: varianteNombre,
                        nombre:          this.producto.nombre,
                        imagen:          this.imgPreview ?? this.producto.imagen,
                        precio_unitario: parseFloat(this.precioActual),
                        cantidad:        this.cantidad,
                    });
                    this.cerrar();
                },

                confirmarDeseos() {
                    if (!this.disponible) return;
                    const v = this.varianteCoincidente;
                    Alpine.store('carrito').agregarDeseo(this.producto.id, v?.id ?? null, this.cantidad);
                    this.cerrar();
                },
            };
        };

        // ── Alpine store: carrito ─────────────────────────────────────────
        document.addEventListener('alpine:init', () => {
            Alpine.store('carrito', {
                count: 0,
                deseoCount: 0,
                empresaId: parseInt(document.querySelector('meta[name="empresa-id"]')?.content ?? 0),
                deseos: {},

                _estaLogueado() {
                    return document.querySelector('meta[name="cliente-logueado"]')?.content === '1';
                },

                init() {
                    this._cargarLocal();

                    // Si hay sesión activa, sincroniza localStorage → DB via Livewire
                    if (this._estaLogueado()) {
                        const items = this._leerLocal();
                        if (items.length) {
                            window.dispatchEvent(new CustomEvent('browser:carrito-sincronizar', { detail: { items } }));
                        }
                    }

                    // Recibe el conteo real desde el componente Livewire CarritoStore
                    window.addEventListener('carrito-count-actualizado', (e) => {
                        this.count = e.detail.count;
                    });

                    // Tras sincronizar localStorage → DB, limpiar el local (ya no necesario)
                    window.addEventListener('carrito-limpiar-local', () => {
                        localStorage.removeItem(this._clave());
                        this.count = 0;
                    });

                    // Sincroniza guestItems de Livewire de vuelta a localStorage
                    window.addEventListener('carrito-guest-actualizado', (e) => {
                        this._guardarLocal(e.detail.items ?? []);
                    });

                    // Estado inicial de deseos (cargado desde DB en mount)
                    window.addEventListener('deseos-cargados', (e) => {
                        this.deseos = { ...this.deseos, ...e.detail.deseos };
                        // deseoCount viene por evento deseo-count-actualizado
                    });

                    // Recibe resultado de agregar/quitar wishlist desde Livewire
                    window.addEventListener('lista-deseos-actualizada', (e) => {
                        this.deseos[e.detail.productoId] = e.detail.enDeseos;
                        // deseoCount viene por evento deseo-count-actualizado
                    });

                    // Conteo real de entradas en lista de deseos (no productos únicos)
                    window.addEventListener('deseo-count-actualizado', (e) => {
                        this.deseoCount = e.detail.count;
                    });
                },

                _clave() { return `carrito_${this.empresaId}`; },
                _leerLocal() { return JSON.parse(localStorage.getItem(this._clave()) || '[]'); },

                _guardarLocal(items) {
                    localStorage.setItem(this._clave(), JSON.stringify(items));
                    this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                },

                _cargarLocal() {
                    const items = this._leerLocal();
                    this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                },

                agregar(item) {
                    // 1. Actualiza localStorage siempre (feedback inmediato)
                    const items = this._leerLocal();
                    const promoId = item.promocion_id ?? null;
                    const productoId = item.producto_id ?? null;
                    const varianteId = item.variante_id ?? null;

                    const idx = items.findIndex(i => {
                        if (promoId !== null) return (i.promocion_id ?? null) === promoId;
                        return (i.promocion_id ?? null) === null &&
                            (i.producto_id ?? null) === productoId &&
                            (i.variante_id ?? null) === varianteId;
                    });

                    const qty = parseInt(item.cantidad) || 1;
                    if (idx !== -1) {
                        items[idx].cantidad = (items[idx].cantidad || 1) + qty;
                    } else {
                        items.push({ ...item, cantidad: qty });
                    }
                    this._guardarLocal(items);

                    // 2. Si hay sesión, delega a Livewire CarritoStore para guardar en DB
                    if (this._estaLogueado()) {
                        window.dispatchEvent(new CustomEvent('browser:carrito-agregar', { detail: { item } }));
                    }

                    // 3. Notificación
                    const nombre = item.nombre ?? 'Producto';
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { mensaje: `"${nombre}" agregado al carrito`, tipo: 'success' }
                    }));
                },

                agregarDeseo(productoId, varianteId = null, cantidad = 1) {
                    if (!this._estaLogueado()) {
                        window.location.href = '/login';
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('browser:lista-deseos-agregar', {
                        detail: { productoId, varianteId, cantidad }
                    }));
                },

                enDeseos(productoId) { return this.deseos[productoId] ?? false; },
            });
        });

        // Re-sincronizar localStorage → DB tras navegación SPA (wire:navigate)
        document.addEventListener('livewire:navigated', () => {
            const store = Alpine.store('carrito');
            if (store && store._estaLogueado()) {
                const items = store._leerLocal();
                if (items.length) {
                    window.dispatchEvent(new CustomEvent('browser:carrito-sincronizar', { detail: { items } }));
                }
            }
        });

        // ── Lista de deseos: Alpine component factory ────────────────────
        window.listaDeseos = function (datosIniciales) {
            return {
                datos: datosIniciales || {},  // { "itemId": totalFloat }
                seleccion: {},

                init() {
                    // Actualiza datos tras cada re-render de Livewire
                    window.addEventListener('lista-deseos-datos', (e) => {
                        this.datos = e.detail.datos || {};
                    });
                    // Limpia selección tras mover ítems al carrito
                    window.addEventListener('lista-deseos-reset-seleccion', () => {
                        this.seleccion = {};
                    });
                },

                get disponibles() {
                    return Object.keys(this.datos).map(id => ({ id, total: this.datos[id] }));
                },
                get hayDisponibles()     { return this.disponibles.length > 0; },
                get cantidadSel()        { return this.disponibles.filter(d => this.seleccion[d.id]).length; },
                get algunoSeleccionado() { return this.disponibles.some(d => this.seleccion[d.id]); },
                get todosSeleccionados() {
                    const d = this.disponibles;
                    return d.length > 0 && d.every(d => this.seleccion[d.id]);
                },
                get totalSel() {
                    const disp = this.disponibles;
                    const sel  = disp.filter(d => this.seleccion[d.id]);
                    return (sel.length > 0 ? sel : disp)
                        .reduce((s, d) => s + d.total, 0)
                        .toFixed(2);
                },
                get idsEnMover() {
                    const disp = this.disponibles;
                    const sel  = disp.filter(d => this.seleccion[d.id]).map(d => parseInt(d.id));
                    return sel.length > 0 ? sel : disp.map(d => parseInt(d.id));
                },

                marcar(id) {
                    this.seleccion = { ...this.seleccion, [id]: !this.seleccion[id] };
                },
                toggleTodos() {
                    const todos  = this.todosSeleccionados;
                    const newSel = { ...this.seleccion };
                    this.disponibles.forEach(d => { newSel[d.id] = !todos; });
                    this.seleccion = newSel;
                },
            };
        };

        // ── Animación fly-to-cart ─────────────────────────────────────────
        // ── Página de detalle de producto ─────────────────────────────
        window.pdPage = function(productoData, imagenesData, colorImagenMap) {
            return {
                producto:      productoData,
                imagenes:      imagenesData,
                colorImagenMap: colorImagenMap,
                indice:        0,
                imgOverride:   null,
                seleccion:     {},
                cantidad:      1,
                touchX:        0,

                get imgActual() {
                    return this.imgOverride ?? this.imagenes[this.indice] ?? null;
                },

                seleccionarThumb(i) {
                    this.indice      = i;
                    this.imgOverride = null;
                },

                siguiente() {
                    if (this.imagenes.length > 1)
                        this.indice = (this.indice + 1) % this.imagenes.length;
                },

                anterior() {
                    if (this.imagenes.length > 1)
                        this.indice = (this.indice - 1 + this.imagenes.length) % this.imagenes.length;
                },

                tocarInicio(e) { this.touchX = e.touches[0].clientX; },
                tocarFin(e) {
                    const dx = e.changedTouches[0].clientX - this.touchX;
                    if (Math.abs(dx) > 40) {
                        if (dx < 0) this.siguiente(); else this.anterior();
                    }
                },

                seleccionar(attrId, val) {
                    if (this.seleccion[attrId]?.id === val.id) {
                        const sel = { ...this.seleccion };
                        delete sel[attrId];
                        this.seleccion = sel;
                    } else {
                        this.seleccion = { ...this.seleccion, [attrId]: val };
                    }
                    const v = this.varianteCoincidente;
                    if (v?.imagen) { this.imgOverride = v.imagen; return; }
                    for (const sv of Object.values(this.seleccion)) {
                        if (sv.imagen) { this.imgOverride = sv.imagen; return; }
                    }
                    this.imgOverride = null;
                },

                get varianteCoincidente() {
                    if (!this.seleccionCompleta) return null;
                    const selIds = Object.values(this.seleccion).map(v => v.id).sort((a, b) => a - b);
                    return this.producto.variantes.find(v => {
                        const vIds = [...(v.valores_ids ?? [])].sort((a, b) => a - b);
                        return JSON.stringify(vIds) === JSON.stringify(selIds);
                    }) ?? null;
                },

                get seleccionCompleta() {
                    return Object.keys(this.seleccion).length === this.producto.atributos.length;
                },

                get varianteSinStock() {
                    const v = this.varianteCoincidente;
                    return this.seleccionCompleta && v !== null && v.sin_stock === true;
                },

                get disponible() {
                    if (this.producto.agotado) return false;
                    if (this.producto.atributos.length === 0 || this.producto.variantes.length === 0) return true;
                    return this.seleccionCompleta && this.varianteCoincidente !== null && !this.varianteSinStock;
                },

                esValorBloqueado(attrId, val) {
                    return !this.producto.variantes.some(v => {
                        if (!v.valores_ids.includes(val.id)) return false;
                        if (v.sin_stock) return false;
                        for (const [selAttrId, selVal] of Object.entries(this.seleccion)) {
                            if (parseInt(selAttrId) === attrId) continue;
                            if (!v.valores_ids.includes(selVal.id)) return false;
                        }
                        return true;
                    });
                },

                get precioActual() {
                    const extra = Object.values(this.seleccion)
                        .reduce((s, v) => s + (parseFloat(v.precio_adicional) || 0), 0);
                    return (this.producto.precioBase + extra).toFixed(2);
                },

                confirmar() {
                    if (!this.disponible) return;
                    const v = this.varianteCoincidente;
                    const imgEl = this.$refs?.imgPrincipal;
                    if (imgEl && imgEl.src) flyAlCarrito(imgEl);
                    const varNombre = Object.values(this.seleccion)
                        .map(val => val.label).filter(Boolean).join(' / ') || null;
                    Alpine.store('carrito').agregar({
                        promocion_id:    this.producto.promocion_id ?? null,
                        producto_id:     this.producto.id ?? null,
                        variante_id:     v?.id ?? null,
                        variante_nombre: varNombre,
                        nombre:          this.producto.nombre,
                        imagen:          this.imgActual ?? this.producto.imagen,
                        precio_unitario: parseFloat(this.precioActual),
                        cantidad:        this.cantidad,
                    });
                },

                confirmarDeseos() {
                    if (!this.disponible) return;
                    const v = this.varianteCoincidente;
                    Alpine.store('carrito').agregarDeseo(this.producto.id, v?.id ?? null, this.cantidad);
                },

                lb: { abierto: false, indice: 0 },

                abrirLightbox(i) {
                    this.lb.indice = i;
                    this.lb.abierto = true;
                },
            };
        };

        window.flyAlCarrito = function(imgEl) {
            const destino = document.getElementById('navbar-carrito');
            if (!imgEl || !destino) return;

            const o = imgEl.getBoundingClientRect();
            const d = destino.getBoundingClientRect();

            const clon = document.createElement('img');
            clon.src = imgEl.src;
            Object.assign(clon.style, {
                position: 'fixed', zIndex: '9999',
                top: o.top + 'px', left: o.left + 'px',
                width: o.width + 'px', height: o.height + 'px',
                objectFit: 'cover', borderRadius: '8px',
                pointerEvents: 'none',
                transition: 'all 0.6s cubic-bezier(.2,1,.2,1)',
                opacity: '1',
            });
            document.body.appendChild(clon);

            requestAnimationFrame(() => requestAnimationFrame(() => {
                Object.assign(clon.style, {
                    top: (d.top + d.height / 2 - 10) + 'px',
                    left: (d.left + d.width / 2 - 10) + 'px',
                    width: '20px', height: '20px',
                    borderRadius: '50%', opacity: '0',
                });
            }));

            clon.addEventListener('transitionend', () => clon.remove(), { once: true });
        };
    </script>

</body>

</html>
