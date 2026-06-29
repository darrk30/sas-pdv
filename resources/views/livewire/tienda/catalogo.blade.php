<div class="{{ $tieneCategorias ? 'catalogo__layout' : '' }}">

    {{-- ── Sidebar de categorías (solo si existen) ────────────── --}}
    @if ($tieneCategorias)
    <aside>
        <livewire:tienda.partials.categorias />
    </aside>
    @endif

    {{-- ── Contenido principal ────────────────────────────────── --}}
    <div class="catalogo__contenido">

        {{-- Chips de filtros activos --}}
        @if ($marcaActiva || $categoriaActiva)
        <div class="catalogo__filtros">
            @if ($categoriaActiva)
            <span class="catalogo__chip">
                {{ $categoriaActiva }}
                <button wire:click="limpiarCategoria" class="catalogo__chip-x" title="Quitar filtro" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </span>
            @endif
            @if ($marcaActiva)
            <span class="catalogo__chip">
                {{ $marcaActiva }}
                <button wire:click="limpiarMarca" class="catalogo__chip-x" title="Quitar filtro" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </span>
            @endif
        </div>
        @endif

        {{-- Spinner mientras carga --}}
        <div wire:loading class="spinner">
            <div class="spinner__anillo"></div>
        </div>

        {{-- Grid de productos --}}
        <div wire:loading.remove>
            @if ($productos->isEmpty())
                <p class="catalogo__estado">No se encontraron productos.</p>
            @else
                <div class="catalogo__grid">
                    @foreach ($productos as $producto)
                        <x-tienda.tarjeta :producto="$producto" />
                    @endforeach
                </div>
            @endif

            {{ $productos->links('livewire.tienda.paginacion') }}
        </div>

    </div>
</div>
