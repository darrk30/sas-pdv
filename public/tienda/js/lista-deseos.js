// Alpine component: lista de deseos
const _listaDeseos = function(datosIniciales) {
    return {
        datos: datosIniciales || {},
        seleccion: {},

        init() {
            window.addEventListener('lista-deseos-datos', (e) => { this.datos = e.detail.datos || {}; });
            window.addEventListener('lista-deseos-reset-seleccion', () => { this.seleccion = {}; });
        },

        get disponibles()        { return Object.keys(this.datos).map(id => ({ id, total: this.datos[id] })); },
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

        marcar(id)    { this.seleccion = { ...this.seleccion, [id]: !this.seleccion[id] }; },
        toggleTodos() {
            const todos  = this.todosSeleccionados;
            const newSel = { ...this.seleccion };
            this.disponibles.forEach(d => { newSel[d.id] = !todos; });
            this.seleccion = newSel;
        },
    };
};

document.addEventListener('alpine:init', () => Alpine.data('listaDeseos', _listaDeseos));
window.listaDeseos = _listaDeseos;
