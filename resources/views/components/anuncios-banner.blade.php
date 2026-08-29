@php
    use App\Models\Anuncio;
    $anuncios = Anuncio::vigentes()->orderBy('created_at', 'desc')->get();
@endphp

@if($anuncios->isNotEmpty())
<style>
.sas-banner-wrap {
    position: relative;
    overflow: hidden;
    width: 100%;
}
.sas-anuncio {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    font-size: 0.855rem;
    line-height: 1.45;
    border-left: 4px solid;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
}
.sas-anuncio-info        { background:#eff6ff; border-color:#3b82f6; color:#1e3a8a; }
.sas-anuncio-advertencia { background:#fffbeb; border-color:#f59e0b; color:#78350f; }
.sas-anuncio-peligro     { background:#fef2f2; border-color:#ef4444; color:#7f1d1d; }
.sas-anuncio-exito       { background:#f0fdf4; border-color:#22c55e; color:#14532d; }

.sas-anuncio-icono  { font-size: 1rem; flex-shrink: 0; }
.sas-anuncio-body   { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sas-anuncio-titulo { font-weight: 700; }
.sas-anuncio-msg    { opacity: 0.9; }
.sas-anuncio-fecha  { opacity: 0.6; font-size: 0.78rem; margin-left: 6px; }
.sas-anuncio-nav    { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.sas-anuncio-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: currentColor; opacity: 0.25; cursor: pointer;
    transition: opacity .2s;
    border: none; padding: 0; flex-shrink: 0;
}
.sas-anuncio-dot.active { opacity: 0.8; }
.sas-anuncio-close {
    background: none; border: none; cursor: pointer;
    font-size: 1.15rem; line-height: 1; padding: 0 2px;
    flex-shrink: 0; opacity: 0.4; color: inherit;
    transition: opacity .15s;
}
.sas-anuncio-close:hover { opacity: 1; }

/* slide derecha: entra */
.slide-enter-active { transition: transform .35s ease, opacity .35s ease; }
.slide-enter-from   { transform: translateX(60px); opacity: 0; }
.slide-enter-to     { transform: translateX(0); opacity: 1; }
/* slide derecha: sale */
.slide-leave-active { transition: transform .35s ease, opacity .35s ease; position: absolute; width: 100%; }
.slide-leave-from   { transform: translateX(0); opacity: 1; }
.slide-leave-to     { transform: translateX(-60px); opacity: 0; }

@media (prefers-color-scheme: dark) {
    .sas-anuncio-info        { background:#0d1b2e; border-color:#3b82f6; color:#93c5fd; }
    .sas-anuncio-advertencia { background:#1c1504; border-color:#f59e0b; color:#fde68a; }
    .sas-anuncio-peligro     { background:#1c0808; border-color:#ef4444; color:#fca5a5; }
    .sas-anuncio-exito       { background:#071a0e; border-color:#22c55e; color:#86efac; }
}
[data-theme="dark"] .sas-anuncio-info        { background:#0d1b2e; border-color:#3b82f6; color:#93c5fd; }
[data-theme="dark"] .sas-anuncio-advertencia { background:#1c1504; border-color:#f59e0b; color:#fde68a; }
[data-theme="dark"] .sas-anuncio-peligro     { background:#1c0808; border-color:#ef4444; color:#fca5a5; }
[data-theme="dark"] .sas-anuncio-exito       { background:#071a0e; border-color:#22c55e; color:#86efac; }
[data-theme="light"] .sas-anuncio-info        { background:#eff6ff; border-color:#3b82f6; color:#1e3a8a; }
[data-theme="light"] .sas-anuncio-advertencia { background:#fffbeb; border-color:#f59e0b; color:#78350f; }
[data-theme="light"] .sas-anuncio-peligro     { background:#fef2f2; border-color:#ef4444; color:#7f1d1d; }
[data-theme="light"] .sas-anuncio-exito       { background:#f0fdf4; border-color:#22c55e; color:#14532d; }
</style>

<div
    x-data="{
        dismissed: JSON.parse(localStorage.getItem('sas_anuncios_dismissed') || '[]'),
        all: {{ $anuncios->toJson() }},
        current: 0,
        timer: null,
        get visible() {
            return this.all.filter(a => !this.dismissed.includes(a.id));
        },
        get anuncio() {
            return this.visible[this.current] ?? null;
        },
        next() {
            if (this.visible.length <= 1) return;
            this.current = (this.current + 1) % this.visible.length;
        },
        goTo(i) {
            this.current = i;
            this.resetTimer();
        },
        dismiss(id) {
            this.dismissed.push(id);
            localStorage.setItem('sas_anuncios_dismissed', JSON.stringify(this.dismissed));
            if (this.current >= this.visible.length) this.current = 0;
            this.resetTimer();
        },
        startTimer() {
            if (this.visible.length <= 1) return;
            this.timer = setInterval(() => this.next(), 5000);
        },
        resetTimer() {
            clearInterval(this.timer);
            this.startTimer();
        },
        init() {
            this.startTimer();
            this.$watch('visible', () => {
                if (this.current >= this.visible.length) this.current = 0;
            });
        }
    }"
    x-show="visible.length > 0"
    class="sas-banner-wrap"
>
    <template x-for="(anuncio, index) in visible" :key="anuncio.id">
        <div
            x-show="current === index"
            x-transition:enter="slide-enter-active"
            x-transition:enter-start="slide-enter-from"
            x-transition:enter-end="slide-enter-to"
            x-transition:leave="slide-leave-active"
            x-transition:leave-start="slide-leave-from"
            x-transition:leave-end="slide-leave-to"
            :class="'sas-anuncio sas-anuncio-' + anuncio.tipo"
        >
            <span class="sas-anuncio-icono" x-text="{'info':'ℹ️','advertencia':'⚠️','peligro':'🚨','exito':'✅'}[anuncio.tipo] ?? 'ℹ️'"></span>

            <div class="sas-anuncio-body">
                <span class="sas-anuncio-titulo" x-text="anuncio.titulo"></span>
                <span class="sas-anuncio-msg" x-text="' — ' + anuncio.mensaje"></span>
                <span
                    x-show="anuncio.fecha_fin"
                    class="sas-anuncio-fecha"
                    x-text="anuncio.fecha_fin ? 'Hasta ' + new Date(anuncio.fecha_fin).toLocaleString('es-PE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : ''"
                ></span>
            </div>

            <div class="sas-anuncio-nav" x-show="visible.length > 1">
                <template x-for="(_, i) in visible" :key="i">
                    <button
                        :class="'sas-anuncio-dot' + (current === i ? ' active' : '')"
                        @click="goTo(i)"
                        :title="'Anuncio ' + (i+1)"
                    ></button>
                </template>
            </div>

            <button class="sas-anuncio-close" @click="dismiss(anuncio.id)" title="Cerrar">×</button>
        </div>
    </template>
</div>
@endif
