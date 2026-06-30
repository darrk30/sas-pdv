<div
    x-data="{
        toasts: [],

        mostrar(mensaje, tipo = 'success', duracion = 3500) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, mensaje, tipo, saliendo: false });
            setTimeout(() => this.quitar(id), duracion);
        },

        quitar(id) {
            const t = this.toasts.find(t => t.id === id);
            if (!t) return;
            t.saliendo = true;
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 200);
        }
    }"
    x-init="
        window.addEventListener('toast', e => mostrar(e.detail.mensaje, e.detail.tipo ?? 'success', e.detail.duracion ?? 3500));
    "
    class="toast-wrap"
>
    <template x-for="t in toasts" :key="t.id">
        <div class="toast" :class="['toast--' + t.tipo, t.saliendo ? 'saliendo' : '']">

            {{-- Ícono según tipo --}}
            <svg class="toast__icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <template x-if="t.tipo === 'success'">
                    <path d="M20 6 9 17l-5-5"/>
                </template>
                <template x-if="t.tipo === 'error'">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </template>
                <template x-if="t.tipo === 'info'">
                    <path d="M12 8v4m0 4h.01M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/>
                </template>
                <template x-if="t.tipo === 'warning'">
                    <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                </template>
            </svg>

            <span class="toast__msg" x-text="t.mensaje"></span>

            <button type="button" class="toast__cerrar" @click="quitar(t.id)" title="Cerrar">✕</button>
        </div>
    </template>
</div>
