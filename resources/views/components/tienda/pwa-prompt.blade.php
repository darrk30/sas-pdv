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
        _deferredPrompt: null,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches
                            || window.navigator.standalone;
            if (standalone) return;
            if (localStorage.getItem('pwa_dismissed')) return;

            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

            if (!this.ios) {
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    this._deferredPrompt = e;
                    setTimeout(() => this.visible = true, 3500);
                }, { once: true });
            } else {
                setTimeout(() => this.visible = true, 3500);
            }
        },
        instalar() {
            if (this._deferredPrompt) {
                this._deferredPrompt.prompt();
                this._deferredPrompt.userChoice.then(() => {
                    this._deferredPrompt = null;
                    this.cerrar();
                });
            }
        },
        cerrar() {
            this.visible = false;
            localStorage.setItem('pwa_dismissed', '1');
        }
    }"
    x-show="visible"
    x-transition:enter="pwa-enter"
    x-transition:enter-start="pwa-enter-start"
    x-transition:enter-end="pwa-enter-end"
    x-transition:leave="pwa-leave"
    x-transition:leave-start="pwa-leave-start"
    x-transition:leave-end="pwa-leave-end"
    x-cloak
    class="pwa-prompt"
    @keydown.escape.window="cerrar()"
>
    {{-- Overlay difuso --}}
    <div class="pwa-overlay" @click="cerrar()"></div>

    {{-- Bottom sheet --}}
    <div class="pwa-sheet" role="dialog" aria-modal="true">

        {{-- X --}}
        <button class="pwa-close" @click="cerrar()" aria-label="Cerrar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" width="18" height="18">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        {{-- Contenido --}}
        <div class="pwa-body">
            <img src="{{ $iconoUrl }}" alt="{{ $appName }}" class="pwa-icon">
            <div class="pwa-text">
                <span class="pwa-tag">Disponible como app</span>
                <strong class="pwa-name">{{ $appName }}</strong>
                <p x-show="!ios" class="pwa-desc">Instálala y accede rápido desde tu pantalla de inicio.</p>
                <p x-show="ios" class="pwa-desc">
                    Toca
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" width="14" height="14"
                         style="display:inline;vertical-align:middle">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                    y luego <strong>"Agregar a inicio"</strong>.
                </p>
            </div>
        </div>

        <button x-show="!ios" class="pwa-btn" @click="instalar()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" width="16" height="16">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Instalar app
        </button>

    </div>
</div>

<style>
/* Solo en móvil */
@media (min-width: 769px) { .pwa-prompt { display: none !important; } }

.pwa-prompt {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: flex-end;
}

.pwa-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.35);
    backdrop-filter: blur(2px);
}

.pwa-sheet {
    position: relative;
    width: 100%;
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 1.25rem 1.25rem 2rem;
    box-shadow: 0 -4px 32px rgba(0,0,0,.15);
}

@media (prefers-color-scheme: dark) {
    .pwa-sheet { background: #1e293b; }
    .pwa-name  { color: #f1f5f9; }
    .pwa-desc  { color: #94a3b8; }
    .pwa-tag   { background: #0f172a; color: #94a3b8; }
}

/* Handle bar */
.pwa-sheet::before {
    content: '';
    display: block;
    width: 40px; height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin: 0 auto 1.25rem;
}

.pwa-close {
    position: absolute;
    top: 1rem; right: 1rem;
    background: #f1f5f9;
    border: none;
    border-radius: 50%;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #64748b;
}

.pwa-body {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.pwa-icon {
    width: 60px; height: 60px;
    border-radius: 14px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}

.pwa-text { min-width: 0; }

.pwa-tag {
    display: inline-block;
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #64748b;
    background: #f1f5f9;
    padding: .15rem .45rem;
    border-radius: 4px;
    margin-bottom: .25rem;
}

.pwa-name {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pwa-desc {
    font-size: .8125rem;
    color: #64748b;
    margin: .2rem 0 0;
    line-height: 1.4;
}

.pwa-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    background: #1e293b;
    color: #f8fafc;
    border: none;
    border-radius: 12px;
    padding: .85rem;
    font-size: .9375rem;
    font-weight: 600;
    cursor: pointer;
}

/* Transiciones Alpine */
.pwa-enter         { transition: opacity .25s, transform .3s cubic-bezier(.32,1.01,.49,1); }
.pwa-enter-start   { opacity: 0; transform: translateY(100%); }
.pwa-enter-end     { opacity: 1; transform: translateY(0); }
.pwa-leave         { transition: opacity .2s, transform .2s ease-in; }
.pwa-leave-start   { opacity: 1; transform: translateY(0); }
.pwa-leave-end     { opacity: 0; transform: translateY(100%); }
</style>
