<div>
@if ($categorias->isNotEmpty())
<nav class="cat-sidebar">
    <h3 class="cat-sidebar__titulo">Categorías</h3>

    <ul class="cat-sidebar__lista">

        {{-- Todas --}}
        <li>
            <button
                type="button"
                wire:click="seleccionar(0)"
                class="cat-sidebar__item {{ $categoriaId === 0 ? 'cat-sidebar__item--activo' : '' }}"
            >
                <span class="cat-sidebar__nombre">Todas</span>
            </button>
        </li>

        @foreach ($categorias as $cat)
        <li>
            <button
                type="button"
                wire:click="seleccionar({{ $cat->id }})"
                class="cat-sidebar__item {{ $categoriaId === $cat->id ? 'cat-sidebar__item--activo' : '' }}"
            >
                <span class="cat-sidebar__nombre">{{ $cat->nombre }}</span>
                <span class="cat-sidebar__count">{{ $cat->productos_count }}</span>
            </button>
        </li>
        @endforeach

    </ul>
</nav>
@endif
</div>
