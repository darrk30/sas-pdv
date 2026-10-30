<div>
@if ($categorias->isNotEmpty())

<nav class="cats-strip" aria-label="Categorías">

    {{-- Flecha izquierda (solo PC) --}}
    <button type="button" class="cats-strip__btn" aria-label="Anterior"
            @click="$refs.inner.scrollBy({ left: -200, behavior: 'smooth' })">&#8249;</button>

    <div class="cats-strip__inner" x-ref="inner">

        {{-- "Todas" pill --}}
        <button type="button" wire:click="seleccionar(0)"
            class="cats-strip__pill {{ $categoriaId === 0 ? 'cats-strip__pill--activo' : '' }}">
            <span class="cats-strip__nombre">Todas</span>
        </button>

        @foreach ($categorias as $cat)
        <button type="button" wire:click="seleccionar({{ $cat->id }})"
            class="cats-strip__pill {{ $categoriaId === $cat->id ? 'cats-strip__pill--activo' : '' }}">

            @if ($cat->imagen_url)
                <img
                    src="{{ Storage::url($cat->imagen_url) }}"
                    alt="{{ $cat->nombre }}"
                    class="cats-strip__img"
                    loading="lazy"
                >
            @endif

            <span class="cats-strip__nombre">{{ $cat->nombre }}</span>

            <span class="cats-strip__count">{{ $cat->productos_count }}</span>
        </button>
        @endforeach

    </div>

    {{-- Flecha derecha (solo PC) --}}
    <button type="button" class="cats-strip__btn" aria-label="Siguiente"
            @click="$refs.inner.scrollBy({ left: 200, behavior: 'smooth' })">&#8250;</button>

</nav>

@endif
</div>
