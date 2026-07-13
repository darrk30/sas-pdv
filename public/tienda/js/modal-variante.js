// Alpine component: modal de variantes de producto
window.modalVariante = function() {
    return {
        abierto: false,
        producto: null,
        seleccion: {},
        imgPreview: null,
        cantidad: 1,

        abrir(data) {
            this.producto   = data;
            this.seleccion  = {};
            this.cantidad   = 1;
            this.imgPreview = data.imagen;
            this.abierto    = true;
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

        // Cuántas unidades quedan disponibles de la promo (null = sin límite)
        get stockRestantePromo() {
            if (!this.producto?.promocion_id) return null;
            const stockMax = this.producto.stockMax ?? null;
            if (stockMax === null) return null;
            const _ = Alpine.store('carrito').count; // trackea reactividad del store
            const items = Alpine.store('carrito')._leerLocal();
            const enCarrito = items
                .filter(i => (i.promocion_id ?? null) == this.producto.promocion_id)
                .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
            return Math.max(0, stockMax - enCarrito);
        },

        get disponible() {
            if (!this.producto) return false;
            if (this.producto.promocion_id) {
                const r = this.stockRestantePromo;
                return r === null || r > 0;
            }
            if (this.producto.atributos.length === 0) return true;
            return this.seleccionCompleta && this.varianteCoincidente !== null && !this.varianteSinStock;
        },

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

            // Stock check para promociones
            if (this.producto.promocion_id) {
                const stockMax = this.producto.stockMax ?? null;
                if (stockMax !== null) {
                    const items = Alpine.store('carrito')._leerLocal();
                    const enCarrito = items
                        .filter(i => (i.promocion_id ?? null) == this.producto.promocion_id)
                        .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                    const restante = stockMax - enCarrito;
                    if (this.cantidad > restante) {
                        const msg = restante <= 0
                            ? 'Sin stock disponible para esta promoción.'
                            : `Solo quedan ${restante} unidades disponibles para esta promoción.`;
                        window.dispatchEvent(new CustomEvent('toast', { detail: { mensaje: msg, tipo: 'error' } }));
                        return;
                    }
                }
            }

            // Stock check: variant stock_reserva minus what's already in cart (direct + via promos)
            const sr = v ? v.stock_reserva : null;
            if (sr !== null && sr !== undefined) {
                const items = Alpine.store('carrito')._leerLocal();
                const vid = v.id;
                const pid = this.producto.id;
                const enCarrito = items
                    .filter(i => i.producto_id == pid && i.variante_id == vid && !i.promocion_id)
                    .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                const enPromos = items
                    .filter(i => i.promocion_id && Array.isArray(i.componentes))
                    .reduce((s, i) => {
                        const promoQty = parseInt(i.cantidad) || 1;
                        const comp = i.componentes.find(c => c.variante_id == vid);
                        return s + (comp ? promoQty * (parseFloat(comp.cantidad) || 1) : 0);
                    }, 0);
                const restante = sr - enCarrito - enPromos;
                if (this.cantidad > restante) {
                    const msg = restante <= 0
                        ? 'Sin stock disponible para esta variante.'
                        : `Solo quedan ${restante} unidades disponibles.`;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { mensaje: msg, tipo: 'error' } }));
                    return;
                }
            }

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
                codigo_interno:  (v?.codigo || this.producto.codigo_interno) ?? null,
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
