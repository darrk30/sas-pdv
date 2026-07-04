@php
    $empresa  = app('tienda.empresa');
    $appName  = (string) ($empresa->name ?? config('app.name'));
    $iconoUrl = $empresa->icono
        ? \Illuminate\Support\Facades\Storage::url($empresa->icono)
        : '/tienda/icons/icon-192.png';
@endphp

<div
    x-data="{
        visible: false,
        ios: false,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches
                             || window.navigator.standalone;
            if (standalone) return;
            if (localStorage.getItem('pwa_dismissed')) return;

            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

            if (this.ios) {
                setTimeout(() => this.visible = true, 3000);
                return;
            }

            // El evento pudo haber llegado antes de que Alpine iniciara
            if (window._pwaDeferredPrompt) {
                setTimeout(() => this.visible = true, 3000);
            } else {
                window.addEventListener('pwa-ready', () => {
                    setTimeout(() => this.visible = true, 3000);
                }, { once: true });
            }
        },
        instalar() {
            const prompt = window._pwaDeferredPrompt;
            if (!prompt) return;
            prompt.prompt();
            prompt.userChoice.then(() => {
                window._pwaDeferredPrompt = null;
                this.cerrar();
            });
        },
        cerrar() {
            this.visible = false;
            localStorage.setItem('pwa_dismissed', '1');
        }
    }"
    x-show="visible"
    x-transition:enter="pwa-t"
    x-transition:enter-start="pwa-t--hidden"
    x-transition:enter-end="pwa-t--shown"
    x-transition:leave="pwa-t"
    x-transition:leave-start="pwa-t--shown"
    x-transition:leave-end="pwa-t--hidden"
    x-cloak
    class="pwa-chip"
>
    <img src="{{ $iconoUrl }}" alt="{{ $appName }}" class="pwa-chip__icon">

    <div class="pwa-chip__text">
        <strong class="pwa-chip__name">{{ $appName }}</strong>
        <span class="pwa-chip__sub" x-show="!ios">Instala la app gratis</span>
        <span class="pwa-chip__sub" x-show="ios">
            Compartir
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" width="12" height="12"
                 style="display:inline;vertical-align:-1px">
                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                <polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/>
            </svg>
            → Agregar a inicio
        </span>
    </div>

    <button x-show="!ios" class="pwa-chip__btn" @click="instalar()">Instalar</button>

    <button class="pwa-chip__close" @click="cerrar()" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" width="14" height="14">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>

<style>
@media (min-width: 769px) { .pwa-chip { display: none !important; } }

.pwa-chip {
    position: fixed;
    bottom: calc(1rem + env(safe-area-inset-bottom));
    left: 1rem;
    right: 1rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #fff;
    border-radius: 16px;
    padding: .75rem .75rem .75rem 1rem;
    box-shadow: 0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.08);
    border: 1px solid rgba(0,0,0,.06);
}

@media (prefers-color-scheme: dark) {
    .pwa-chip {
        background: #1e293b;
        border-color: rgba(255,255,255,.08);
        box-shadow: 0 8px 32px rgba(0,0,0,.45);
    }
    .pwa-chip__name { color: #f1f5f9; }
    .pwa-chip__sub  { color: #94a3b8; }
    .pwa-chip__close { color: #64748b; }
    .pwa-chip__close:hover { background: #0f172a; }
}

.pwa-chip__icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
}

.pwa-chip__text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: .1rem;
}

.pwa-chip__name {
    font-size: .875rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}

.pwa-chip__sub {
    font-size: .75rem;
    color: #64748b;
    line-height: 1.3;
}

.pwa-chip__btn {
    flex-shrink: 0;
    background: #1e293b;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: .45rem .9rem;
    font-size: .8125rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

@media (prefers-color-scheme: dark) {
    .pwa-chip__btn { background: #f1f5f9; color: #0f172a; }
}

.pwa-chip__close {
    flex-shrink: 0;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: .35rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pwa-chip__close:hover { background: #f1f5f9; }

/* Transiciones */
.pwa-t { transition: opacity .25s, transform .3s cubic-bezier(.34,1.56,.64,1); }
.pwa-t--hidden { opacity: 0; transform: translateY(20px) scale(.96); }
.pwa-t--shown  { opacity: 1; transform: translateY(0) scale(1); }
</style>
