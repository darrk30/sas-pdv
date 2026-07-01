<nav class="navbar">
<div class="navbar__contenido">

    {{-- ── Logo / Nombre ──────────────────────────────────────── --}}
    <a href="/" wire:navigate class="navbar__marca">
        @if ($empresaLogo)
            <img src="{{ $empresaLogo }}" alt="{{ $empresaNombre }}" class="navbar__logo">
        @else
            {{ $empresaNombre }}
        @endif
    </a>

    {{-- ── Buscador centrado ───────────────────────────────────── --}}
    <div class="navbar__busqueda" x-data="{
        q: new URLSearchParams(window.location.search).get('q') ?? '',
        _t: null,
        lanzar() {
            const ev = new CustomEvent('tienda-buscar', { detail: { q: this.q.trim() }, cancelable: true });
            window.dispatchEvent(ev);
            if (!ev.defaultPrevented) {
                Livewire.navigate(this.q.trim() ? '/?q=' + encodeURIComponent(this.q.trim()) : '/');
            }
        }
    }">
        <div class="navbar__busqueda-campo">
            <svg class="navbar__busqueda-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input
                x-model="q"
                @input="clearTimeout(_t); _t = setTimeout(() => lanzar(), q.trim() === '' ? 0 : 400)"
                @keydown.enter.prevent="clearTimeout(_t); lanzar()"
                type="search"
                class="navbar__busqueda-input"
                placeholder="Buscar en {{ $empresaNombre }}"
            >
        </div>
    </div>

    {{-- ── Acciones derecha ────────────────────────────────────── --}}
    <div class="navbar__acciones">

        {{-- Usuario — dropdown por hover --}}
        <div
            class="navbar__usuario-menu"
            x-data="{ abierto: false, _t: null }"
            @mouseenter="clearTimeout(_t); abierto = true"
            @mouseleave="_t = setTimeout(() => { abierto = false }, 180)"
        >
            <button class="navbar__saludo" type="button" aria-haspopup="true" :aria-expanded="abierto">
                <span class="navbar__saludo-hola">Hola,</span>
                <span class="navbar__saludo-nombre">
                    {{ $cliente ? $cliente->nombre : 'Iniciar Sesión' }}
                    <svg class="navbar__chevron" :class="{ 'navbar__chevron--arriba': abierto }"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </span>
            </button>

            <div class="navbar__dropdown" x-show="abierto" x-cloak>
                <div class="navbar__dropdown-inner">
                    @if ($cliente)
                        <a href="/mis-ordenes"  wire:navigate class="navbar__dropdown-item">Mis órdenes</a>
                        <a href="/lista-deseos" wire:navigate class="navbar__dropdown-item">Lista de deseos</a>
                        <hr class="navbar__dropdown-sep">
                        <button wire:click="logout" class="navbar__dropdown-item navbar__dropdown-item--danger">
                            Cerrar sesión
                        </button>
                    @else
                        <a href="/login"    wire:navigate class="navbar__dropdown-item">Iniciar sesión</a>
                        <a href="/registro" wire:navigate class="navbar__dropdown-item navbar__dropdown-item--primario">
                            Crear cuenta
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Corazón / Lista de deseos --}}
        <a
            href="{{ $cliente ? '/lista-deseos' : '/login' }}"
            wire:navigate
            class="navbar__icono"
            title="Lista de deseos"
            x-data
            :style="$store.carrito.deseoCount > 0 ? 'color:#ef4444' : ''"
        >
            <svg viewBox="0 0 24 24"
                 :fill="$store.carrito.deseoCount > 0 ? 'currentColor' : 'none'"
                 stroke="currentColor" stroke-width="2" class="navbar__icono-svg">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <span class="navbar__badge navbar__badge--deseo"
                  x-show="$store.carrito.deseoCount > 0"
                  x-text="$store.carrito.deseoCount"></span>
        </a>

        {{-- Carrito --}}
        <a href="/carrito" wire:navigate id="navbar-carrito" class="navbar__icono navbar__icono--carrito" title="Mi carrito" x-data>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="navbar__icono-svg">
                <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span class="navbar__badge" x-show="$store.carrito.count > 0" x-text="$store.carrito.count"></span>
        </a>

    </div>

</div>
</nav>
