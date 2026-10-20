@push('styles')
<link rel="stylesheet" href="{{ asset('tienda/css/catalogo.css') }}">
<link rel="stylesheet" href="{{ asset('tienda/css/categorias.css') }}">
<link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}?v=9">
<link rel="stylesheet" href="{{ asset('tienda/css/paginacion.css') }}?v=3">
@endpush

<div class="catalogo__layout"
     @tienda-buscar.window.prevent="$wire.recibirBusqueda($event.detail.q)">

    {{-- ── Strip de categorías (solo si existen) ──────────────── --}}
    @if ($tieneCategorias)
        <livewire:tienda.partials.categorias />
    @endif

    {{-- ── Contenido principal ────────────────────────────────── --}}
    <div class="catalogo__contenido">

        {{-- Spinner mientras carga --}}
        <div wire:loading class="spinner">
            <div class="spinner__inner">
                <div class="spinner__anillo"></div>
                <span class="spinner__texto">Buscando...</span>
            </div>
        </div>

        {{-- Grid de productos + promociones --}}
        <div wire:loading.remove>
            @if ($productos->isEmpty() && $promociones->isEmpty())
                <p class="catalogo__estado">No se encontraron productos.</p>
            @else
                <div class="catalogo__grid">
                    {{-- Promociones vigentes primero (solo pág. 1) --}}
                    @foreach ($promociones as $promo)
                        <x-tienda.tarjeta-promo :promo="$promo" wire:key="promo-{{ $promo->id }}" />
                    @endforeach

                    {{-- Productos normales --}}
                    @foreach ($productos as $producto)
                        <x-tienda.tarjeta :producto="$producto" wire:key="producto-{{ $producto->id }}" />
                    @endforeach
                </div>
            @endif

            {{ $productos->links('livewire.tienda.paginacion') }}
        </div>

    </div>
</div>
