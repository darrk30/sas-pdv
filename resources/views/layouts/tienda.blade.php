<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/marcas.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/spinner.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/categorias.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/paginacion.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/carrito.css') }}">
</head>
<body>

    <livewire:tienda.partials.navbar />
    <livewire:tienda.partials.marcas />

    <main class="pagina">
        {{ $slot }}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    @livewireScripts

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('carrito', {
            count:        0,
            empresaId:    {{ app('tienda.empresa')->id }},
            logueado:     {{ Auth::guard('cliente')->check() ? 'true' : 'false' }},
            deseos:       {},   // { producto_id: true/false }

            init() {
                this._cargarLocal();
                if (this.logueado) {
                    this._sincronizarLogin();
                }
            },

            _clave() {
                return `carrito_${this.empresaId}`;
            },

            _leerLocal() {
                return JSON.parse(localStorage.getItem(this._clave()) || '[]');
            },

            _guardarLocal(items) {
                localStorage.setItem(this._clave(), JSON.stringify(items));
                this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
            },

            _cargarLocal() {
                const items = this._leerLocal();
                this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
            },

            agregar(item) {
                // Animación fly-to-cart — manejada en la tarjeta antes de llamar aquí
                const items = this._leerLocal();
                const promoId   = item.promocion_id ?? null;
                const productoId = item.producto_id  ?? null;
                const varianteId = item.variante_id  ?? null;
                const idx = items.findIndex(i => {
                    if (promoId !== null) return (i.promocion_id ?? null) === promoId;
                    return (i.promocion_id ?? null) === null &&
                           (i.producto_id  ?? null) === productoId &&
                           (i.variante_id  ?? null) === varianteId;
                });
                if (idx !== -1) {
                    items[idx].cantidad = (items[idx].cantidad || 1) + 1;
                } else {
                    items.push({ ...item, cantidad: 1 });
                }
                this._guardarLocal(items);

                if (this.logueado) {
                    fetch('/carrito/agregar', {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            empresa_id:      this.empresaId,
                            promocion_id:    item.promocion_id  ?? null,
                            producto_id:     item.producto_id   ?? null,
                            variante_id:     item.variante_id   ?? null,
                            precio_unitario: item.precio_unitario,
                            cantidad:        1,
                        }),
                    })
                    .then(r => r.json())
                    .then(data => { if (data.count !== undefined) this.count = data.count; });
                }
            },

            toggleDeseo(productoId, varianteId = null) {
                if (!this.logueado) {
                    window.location.href = '/login';
                    return;
                }
                fetch('/lista-deseos/toggle', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        empresa_id:  this.empresaId,
                        producto_id: productoId,
                        variante_id: varianteId,
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        this.deseos[productoId] = data.en_deseos;
                    }
                });
            },

            enDeseos(productoId) {
                return this.deseos[productoId] ?? false;
            },

            // Sincroniza localStorage → DB justo después del login
            _sincronizarLogin() {
                const items = this._leerLocal();
                if (!items.length) return;

                fetch('/carrito/sincronizar', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ empresa_id: this.empresaId, items }),
                })
                .then(r => r.json())
                .then(data => { if (data.count !== undefined) this.count = data.count; });
            },
        });
    });

    // Función global para la animación fly-to-cart
    window.flyAlCarrito = function(imgEl) {
        const destino = document.getElementById('navbar-carrito');
        if (!imgEl || !destino) return;

        const o = imgEl.getBoundingClientRect();
        const d = destino.getBoundingClientRect();

        const clon = document.createElement('img');
        clon.src = imgEl.src;
        Object.assign(clon.style, {
            position:     'fixed',
            zIndex:       '9999',
            top:          o.top  + 'px',
            left:         o.left + 'px',
            width:        o.width  + 'px',
            height:       o.height + 'px',
            objectFit:    'cover',
            borderRadius: '8px',
            pointerEvents:'none',
            transition:   'all 0.6s cubic-bezier(.2,1,.2,1)',
            opacity:      '1',
        });
        document.body.appendChild(clon);

        requestAnimationFrame(() => requestAnimationFrame(() => {
            Object.assign(clon.style, {
                top:          (d.top  + d.height / 2 - 10) + 'px',
                left:         (d.left + d.width  / 2 - 10) + 'px',
                width:        '20px',
                height:       '20px',
                borderRadius: '50%',
                opacity:      '0',
            });
        }));

        clon.addEventListener('transitionend', () => clon.remove(), { once: true });
    };
    </script>

</body>
</html>
