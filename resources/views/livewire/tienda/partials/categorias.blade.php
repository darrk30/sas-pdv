<div>
@if ($categorias->isNotEmpty())

    {{-- ── Desktop / Tablet: sidebar vertical ──────────────────── --}}
    <nav class="cat-sidebar cat-sidebar--desktop">
        <h3 class="cat-sidebar__titulo">Categorías</h3>
        <ul class="cat-sidebar__lista">
            <li>
                <button type="button" wire:click="seleccionar(0)"
                    class="cat-sidebar__item {{ $categoriaId === 0 ? 'cat-sidebar__item--activo' : '' }}">
                    <span class="cat-sidebar__nombre">Todas</span>
                </button>
            </li>
            @foreach ($categorias as $cat)
            <li>
                <button type="button" wire:click="seleccionar({{ $cat->id }})"
                    class="cat-sidebar__item {{ $categoriaId === $cat->id ? 'cat-sidebar__item--activo' : '' }}">
                    <span class="cat-sidebar__nombre">{{ $cat->nombre }}</span>
                    <span class="cat-sidebar__count">{{ $cat->productos_count }}</span>
                </button>
            </li>
            @endforeach
        </ul>
    </nav>

    {{-- ── Móvil: botón + dropdown ──────────────────────────────── --}}
    <div class="cat-movil" x-data="{ abierto: false }" @keydown.escape.window="abierto = false">

        {{-- Backdrop transparente: atrapa el clic sin propagarlo a las tarjetas --}}
        <div
            x-show="abierto"
            x-cloak
            class="cat-movil__backdrop"
            @click.stop.prevent="abierto = false"
            @touchstart.stop.prevent="abierto = false"
        ></div>

        <button type="button" class="cat-movil__btn" @click.stop="abierto = !abierto" :aria-expanded="abierto">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="cat-movil__icono" aria-hidden="true">
                <line x1="4" y1="6"  x2="20" y2="6"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
                <line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            <span>{{ $categoriaId === 0 ? 'Categorías' : ($categorias->firstWhere('id', $categoriaId)?->nombre ?? 'Categorías') }}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="cat-movil__chevron" :class="{ 'cat-movil__chevron--arriba': abierto }" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        <div
            class="cat-movil__dropdown"
            x-show="abierto"
            x-cloak
            @click.stop
            @wheel.stop
            @touchmove.stop
        >
            <ul class="cat-movil__lista">
                <li>
                    <button type="button"
                        wire:click="seleccionar(0)"
                        @click.stop="abierto = false"
                        class="cat-movil__item {{ $categoriaId === 0 ? 'cat-movil__item--activo' : '' }}">
                        Todas
                    </button>
                </li>
                @foreach ($categorias as $cat)
                <li>
                    <button type="button"
                        wire:click="seleccionar({{ $cat->id }})"
                        @click.stop="abierto = false"
                        class="cat-movil__item {{ $categoriaId === $cat->id ? 'cat-movil__item--activo' : '' }}">
                        {{ $cat->nombre }}
                        <span class="cat-movil__count">{{ $cat->productos_count }}</span>
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
    </div>

@endif
</div>
