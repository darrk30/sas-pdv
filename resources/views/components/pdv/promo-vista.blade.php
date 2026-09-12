@props(['detalles' => [], 'clase' => ''])

@if(count($detalles) > 0)
<div
    class="promo-vista {{ $clase }}"
    x-data="{
        open: false,
        pstyle: '',
        toggle() {
            if (this.open) { this.open = false; return; }
            const trigger = this.$refs.trigger;
            if (!trigger) return;
            const b  = trigger.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            if (vw < 480) {
                this.pstyle = 'position:fixed;bottom:0;left:0;right:0;width:100%;border-radius:.75rem .75rem 0 0;z-index:500';
            } else {
                const w     = Math.min(220, vw - 16);
                const right = Math.max(8, vw - b.right);
                this.pstyle = b.top > 140
                    ? 'position:fixed;bottom:' + (vh - b.top + 4) + 'px;right:' + right + 'px;width:' + w + 'px;z-index:500'
                    : 'position:fixed;top:'    + (b.bottom + 4)   + 'px;right:' + right + 'px;width:' + w + 'px;z-index:500';
            }
            this.open = true;
        }
    }"
    @click.window="open && !$el.contains($event.target) && (open = false)"
>
    {{-- div en lugar de button: evita anidar <button> dentro de <button> (HTML inválido) --}}
    <div
        class="promo-vista__btn"
        :class="open ? 'promo-vista__btn--on' : ''"
        x-ref="trigger"
        @click.stop="toggle()"
        @keydown.enter.stop="toggle()"
        @keydown.space.prevent.stop="toggle()"
        role="button"
        tabindex="0"
        title="Ver productos de la promo"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
    </div>

    {{-- Backdrop: solo visible en móvil (CSS), tapa la pantalla y cierra al tocar fuera --}}
    <div
        x-show="open"
        class="promo-vista__backdrop"
        @click.stop="open = false"
        style="display:none"
    ></div>

    <div
        x-show="open"
        :style="pstyle"
        class="promo-vista__panel"
        @click.stop
        style="display:none"
    >
        <p class="promo-vista__panel-titulo">Incluye:</p>
        <ul class="promo-vista__lista">
            @foreach($detalles as $d)
            <li class="promo-vista__item">
                <span class="promo-vista__qty">×{{ $d['cantidad'] == floor((float)$d['cantidad']) ? (int)$d['cantidad'] : number_format((float)$d['cantidad'], 2) }}</span>
                <span class="promo-vista__nombre">{{ $d['nombre'] }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
