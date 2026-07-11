// ── Alpine store: carrito ─────────────────────────────────────────────────────
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

            if (this._estaLogueado()) {
                const items = this._leerLocal();
                if (items.length) {
                    window.dispatchEvent(new CustomEvent('browser:carrito-sincronizar', { detail: { items } }));
                }
            }

            window.addEventListener('carrito-count-actualizado', (e) => { this.count = e.detail.count; });
            window.addEventListener('carrito-limpiar-local', () => { localStorage.removeItem(this._clave()); this.count = 0; });
            window.addEventListener('carrito-actualizar-local', (e) => { this._guardarLocal(e.detail.items ?? []); });
            window.addEventListener('carrito-guest-actualizado', (e) => { this._guardarLocal(e.detail.items ?? []); });
            window.addEventListener('deseos-cargados', (e) => { this.deseos = { ...this.deseos, ...e.detail.deseos }; });
            window.addEventListener('lista-deseos-actualizada', (e) => { this.deseos[e.detail.productoId] = e.detail.enDeseos; });
            window.addEventListener('deseo-count-actualizado', (e) => { this.deseoCount = e.detail.count; });
        },

        _clave()      { return `carrito_${this.empresaId}`; },
        _leerLocal()  { return JSON.parse(localStorage.getItem(this._clave()) || '[]'); },

        _guardarLocal(items) {
            localStorage.setItem(this._clave(), JSON.stringify(items));
            this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
        },

        _cargarLocal() {
            const items = this._leerLocal();
            this.count = items.reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
        },

        agregar(item) {
            const items     = this._leerLocal();
            const promoId   = item.promocion_id ?? null;
            const productoId= item.producto_id ?? null;
            const varianteId= item.variante_id ?? null;

            const idx = items.findIndex(i => {
                if (promoId !== null) return (i.promocion_id ?? null) === promoId;
                return (i.promocion_id ?? null) === null &&
                    (i.producto_id ?? null) === productoId &&
                    (i.variante_id ?? null) === varianteId;
            });

            const qty = parseInt(item.cantidad) || 1;
            if (idx !== -1) { items[idx].cantidad = (items[idx].cantidad || 1) + qty; }
            else            { items.push({ ...item, cantidad: qty }); }
            this._guardarLocal(items);

            if (this._estaLogueado()) {
                window.dispatchEvent(new CustomEvent('browser:carrito-agregar', { detail: { item } }));
            }

            window.dispatchEvent(new CustomEvent('toast', {
                detail: { mensaje: `"${item.nombre ?? 'Producto'}" agregado al carrito`, tipo: 'success' }
            }));
        },

        agregarDeseo(productoId, varianteId = null, cantidad = 1) {
            if (!this._estaLogueado()) { window.location.href = '/login'; return; }
            window.dispatchEvent(new CustomEvent('browser:lista-deseos-agregar', { detail: { productoId, varianteId, cantidad } }));
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
            // Hay ítems en localStorage → sincronizar con DB
            window.dispatchEvent(new CustomEvent('browser:carrito-sincronizar', { detail: { items } }));
        } else {
            // localStorage vacío pero cliente logueado (ej: recién hizo login) → recargar desde DB
            window.dispatchEvent(new CustomEvent('browser:carrito-recargar'));
        }
    }
});

// ── Animación fly-to-cart ─────────────────────────────────────────────────────
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
            top:  (d.top  + d.height / 2 - 10) + 'px',
            left: (d.left + d.width  / 2 - 10) + 'px',
            width: '20px', height: '20px',
            borderRadius: '50%', opacity: '0',
        });
    }));

    clon.addEventListener('transitionend', () => clon.remove(), { once: true });
};
