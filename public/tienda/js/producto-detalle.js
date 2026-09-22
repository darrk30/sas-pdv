// Alpine component: página de detalle de producto
const _pdPage = function(productoData, imagenesData, colorImagenMap) {
    return {
        producto:      productoData,
        imagenes:      imagenesData,
        colorImagenMap: colorImagenMap,
        indice:        0,
        imgOverride:   null,
        seleccion:     {},
        cantidad:      1,
        touchX:        0,
        touchY:        0,
        touchDx:       0,
        arrastrandoImg: false,
        _dirGesto:     null,

        get imgActual() {
            return this.imgOverride ?? this.imagenes[this.indice] ?? null;
        },

        seleccionarThumb(i) { this.indice = i; this.imgOverride = null; },
        siguiente() { if (this.indice < this.imagenes.length - 1) this.indice++; },
        anterior()  { if (this.indice > 0) this.indice--; },

        tocarInicio(e) {
            if (this.imgOverride || this.imagenes.length <= 1) return;
            this.touchX = e.touches[0].clientX;
            this.touchY = e.touches[0].clientY;
            this.touchDx = 0;
            this.arrastrandoImg = false;
            this._dirGesto = null;
        },
        tocarMover(e) {
            if (this._dirGesto === 'v') return;
            const dx = e.touches[0].clientX - this.touchX;
            const dy = e.touches[0].clientY - this.touchY;
            if (this._dirGesto === null) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                this._dirGesto = Math.abs(dy) > Math.abs(dx) ? 'v' : 'h';
                if (this._dirGesto === 'h') this.arrastrandoImg = true;
                else return;
            }
            if (dx > 0 && this.indice === 0) { this.touchDx = 0; return; }
            if (dx < 0 && this.indice === this.imagenes.length - 1) { this.touchDx = 0; return; }
            this.touchDx = dx;
        },
        tocarFin(e) {
            if (this._dirGesto !== 'h') {
                this._dirGesto = null; this.touchDx = 0; this.arrastrandoImg = false; return;
            }
            const dx = e.changedTouches[0].clientX - this.touchX;
            this.arrastrandoImg = false;
            this.touchDx = 0;
            this._dirGesto = null;
            if (Math.abs(dx) > 40) { if (dx < 0) this.siguiente(); else this.anterior(); }
        },

        seleccionar(attrId, val) {
            if (this.seleccion[attrId]?.id === val.id) {
                const sel = { ...this.seleccion }; delete sel[attrId]; this.seleccion = sel;
            } else {
                this.seleccion = { ...this.seleccion, [attrId]: val };
            }
            const v = this.varianteCoincidente;
            if (v?.imagen)  { this.imgOverride = v.imagen; return; }
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

        get seleccionCompleta()  { return Object.keys(this.seleccion).length === this.producto.atributos.length; },

        get stockVisual() {
            if (!this.producto.control_stock || this.producto.venta_sin_stock) return null;
            const _ = Alpine.store('carrito').count; // reactive dep
            const items = Alpine.store('carrito')._leerLocal();
            if (this.producto.variantes.length > 0) {
                const v = this.varianteCoincidente;
                if (!v) {
                    // Sin variante seleccionada: total de todas las variantes menos lo del carrito
                    const totalStock = this.producto.variantes.reduce((s, vt) => s + (vt.stock_reserva ?? 0), 0);
                    const enCarrito = items
                        .filter(i => i.producto_id == this.producto.id && !i.promocion_id)
                        .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                    return Math.max(0, totalStock - enCarrito);
                }
                if (v.stock_reserva === null || v.stock_reserva === undefined) return null;
                const enCarrito = items
                    .filter(i => i.producto_id == this.producto.id && i.variante_id == v.id && !i.promocion_id)
                    .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                return Math.max(0, v.stock_reserva - enCarrito);
            } else {
                const sr = this.producto.stock_reserva;
                if (sr === null || sr === undefined) return null;
                const enCarrito = items
                    .filter(i => i.producto_id == this.producto.id && !i.variante_id && !i.promocion_id)
                    .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                return Math.max(0, sr - enCarrito);
            }
        },

        get varianteSinStock() {
            const v = this.varianteCoincidente;
            return this.seleccionCompleta && v !== null && v.sin_stock === true;
        },

        get disponible() {
            if (this.producto.agotado) return false;
            if (this.producto.atributos.length === 0 || this.producto.variantes.length === 0) {
                if (this.producto.control_stock && !this.producto.venta_sin_stock) {
                    const sv = this.stockVisual;
                    return sv === null || sv > 0;
                }
                return true;
            }
            if (!this.seleccionCompleta || this.varianteCoincidente === null || this.varianteSinStock) return false;
            if (this.producto.control_stock && !this.producto.venta_sin_stock) {
                const sv = this.stockVisual;
                return sv === null || sv > 0;
            }
            return true;
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

            // Stock check: compare requested cantidad vs remaining stock
            const sr = v ? v.stock_reserva : this.producto.stock_reserva;
            if (sr !== null && sr !== undefined) {
                const items = Alpine.store('carrito')._leerLocal();
                const varId = v?.id ?? null;
                const enCarrito = items
                    .filter(i => i.producto_id == this.producto.id
                        && (varId ? i.variante_id == varId : !i.variante_id)
                        && !i.promocion_id)
                    .reduce((s, i) => s + (parseInt(i.cantidad) || 1), 0);
                const restante = sr - enCarrito;
                if (this.cantidad > restante) {
                    const msg = restante <= 0
                        ? 'Sin stock disponible.'
                        : `Solo quedan ${restante} unidades disponibles.`;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { mensaje: msg, tipo: 'error' } }));
                    return;
                }
            }

            const ref  = this.$refs?.imgPrincipal;
            const r    = ref?.getBoundingClientRect();
            const flyEl = (r && r.width > 0)
                ? ref
                : this.$el.querySelector('.pd-galeria__tira img');
            if (flyEl) flyAlCarrito(flyEl);
            let varNombre = Object.values(this.seleccion).map(val => val.label).filter(Boolean).join(' / ') || null;
            if (!varNombre && this.producto.variantes.length === 0 && this.producto.atributos.length > 0) {
                const especiales = this.producto.atributos.filter(a =>
                    ['talla', 'color'].includes(a.nombre.toLowerCase().trim())
                );
                if (especiales.length > 0) {
                    varNombre = especiales.map(a =>
                        a.nombre.charAt(0).toUpperCase() + a.nombre.slice(1).toLowerCase()
                        + ': ' + a.valores.map(v => v.label).join(', ')
                    ).join(' · ') || null;
                }
            }
            Alpine.store('carrito').agregar({
                promocion_id:    this.producto.promocion_id ?? null,
                producto_id:     this.producto.id ?? null,
                variante_id:     v?.id ?? null,
                variante_nombre: varNombre,
                codigo_interno:  (v?.codigo || this.producto.codigo_interno) ?? null,
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
        abrirLightbox(i) { this.lb.indice = i; this.lb.abierto = true; },
    };
};

// Registro via alpine:init: disponible en el registry para x-data="pdPage(...)"
document.addEventListener('alpine:init', () => Alpine.data('pdPage', _pdPage));
// Fallback global para evaluación directa de expresiones JS en x-data
window.pdPage = _pdPage;
