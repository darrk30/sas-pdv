<div>
@if ($marcas->isNotEmpty() && request()->routeIs('tienda.catalogo'))
<section class="marcas">
    <div class="marcas__inner">
        <div
            class="marcas__swiper swiper"
            x-data
            x-init="
                new Swiper($el, {
                    slidesPerView: 'auto',
                    spaceBetween: 4,
                    freeMode: true,
                    grabCursor: true,
                    mousewheel: { forceToAxis: true },
                })
            "
        >
            <div class="swiper-wrapper">
                @foreach ($marcas as $marca)
                <div class="swiper-slide">
                    <button
                        type="button"
                        wire:click="seleccionar({{ $marca->id }})"
                        class="marcas__slide {{ $marcaId === $marca->id ? 'marcas__slide--activo' : '' }}"
                        title="{{ $marca->nombre }}"
                    >
                        <div class="marcas__circulo {{ $marcaId === $marca->id ? 'marcas__circulo--activo' : '' }}">
                            @if ($marca->logo)
                                <img
                                    src="{{ Storage::url($marca->logo) }}"
                                    alt="{{ $marca->nombre }}"
                                    class="marcas__img"
                                    loading="lazy"
                                >
                            @else
                                <span class="marcas__inicial">
                                    {{ mb_substr($marca->nombre, 0, 1) }}
                                </span>
                            @endif
                        </div>
                        <span class="marcas__nombre">{{ $marca->nombre }}</span>
                    </button>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
</div>
