<div>
@if ($categorias->isNotEmpty())

<nav class="cats-strip" aria-label="Categorías">
    <div class="cats-strip__inner"
         x-data
         @wheel.prevent="$el.scrollLeft += $event.deltaY">

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
</nav>

@endif
</div>
